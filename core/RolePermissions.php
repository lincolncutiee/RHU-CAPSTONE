<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/Permissions.php';

class RolePermissions
{
    private static ?array $config = null;
    private static bool $schemaReady = false;

    /** @return array<string, mixed> */
    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/role-permissions.php';
        }
        return self::$config;
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::config()['groups'] ?? [] as $group) {
            foreach ($group['permissions'] ?? [] as $key => $_label) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /** @return array<string, string> slug => label */
    public static function configurableRoles(): array
    {
        return self::config()['configurable_roles'] ?? [];
    }

    /** @return array<string, int> permission_key => 0|1 */
    public static function defaultForRole(string $roleSlug): array
    {
        $roleSlug = Permissions::normalizeRole($roleSlug);
        $defaults = self::config()['defaults'][$roleSlug] ?? [];
        $out = [];
        foreach (self::allKeys() as $key) {
            $out[$key] = !empty($defaults[$key]) ? 1 : 0;
        }
        return $out;
    }

    public static function ensureSchema(mysqli $conn): void
    {
        if (self::$schemaReady) {
            return;
        }

        $conn->query(
            'CREATE TABLE IF NOT EXISTS role_permissions (
                role_slug VARCHAR(64) NOT NULL,
                permission_key VARCHAR(80) NOT NULL,
                allowed TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_slug, permission_key),
                INDEX idx_rp_role (role_slug)
            ) ENGINE=InnoDB'
        );
        self::seedDefaultsIfEmpty($conn);
        self::$schemaReady = true;
    }

    public static function seedDefaultsIfEmpty(mysqli $conn): void
    {
        $res = $conn->query('SELECT COUNT(*) AS c FROM role_permissions');
        if (!$res) {
            throw new RuntimeException('role_permissions count failed: ' . $conn->error);
        }
        $count = (int) ($res->fetch_assoc()['c'] ?? 0);
        $res->free();
        if ($count > 0) {
            return;
        }

        $stmt = $conn->prepare(
            'INSERT INTO role_permissions (role_slug, permission_key, `allowed`) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            throw new RuntimeException('role_permissions seed prepare failed: ' . $conn->error);
        }
        foreach (self::configurableRoles() as $slug => $_label) {
            $map = self::defaultForRole($slug);
            foreach ($map as $key => $allowed) {
                $stmt->bind_param('ssi', $slug, $key, $allowed);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    /** @return array<string, int> */
    public static function effectiveForRole(mysqli $conn, string $roleSlug): array
    {
        self::ensureSchema($conn);
        $roleSlug = Permissions::normalizeRole($roleSlug);
        $effective = self::defaultForRole($roleSlug);

        $stmt = $conn->prepare(
            'SELECT permission_key, `allowed` FROM role_permissions WHERE role_slug = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('role_permissions load prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $roleSlug);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $key = (string) $row['permission_key'];
            if (array_key_exists($key, $effective)) {
                $effective[$key] = (int) $row['allowed'] ? 1 : 0;
            }
        }
        $stmt->close();

        if ($roleSlug === ROLE_SYSTEM_ADMIN) {
            foreach (self::allKeys() as $key) {
                if (str_starts_with($key, 'admin.')) {
                    $effective[$key] = 1;
                }
            }
        }

        return $effective;
    }

    /** @return array<string, array<string, int>> role => permissions */
    public static function matrixForAdmin(mysqli $conn): array
    {
        self::ensureSchema($conn);
        $out = [];
        foreach (self::configurableRoles() as $slug => $_label) {
            $out[$slug] = self::effectiveForRole($conn, $slug);
        }
        return $out;
    }

    /**
     * @param array<string, int|bool> $permissions permission_key => allowed
     */
    public static function saveForRole(mysqli $conn, string $roleSlug, array $permissions): void
    {
        self::ensureSchema($conn);
        $roleSlug = Permissions::normalizeRole($roleSlug);
        if (!isset(self::configurableRoles()[$roleSlug])) {
            throw new InvalidArgumentException('Unknown role.');
        }

        $allowedKeys = array_fill_keys(self::allKeys(), 0);
        foreach ($permissions as $key => $val) {
            if (isset($allowedKeys[$key])) {
                $allowedKeys[$key] = !empty($val) ? 1 : 0;
            }
        }

        if ($roleSlug === ROLE_SYSTEM_ADMIN) {
            foreach ($allowedKeys as $key => $_v) {
                if (str_starts_with($key, 'admin.')) {
                    $allowedKeys[$key] = 1;
                }
            }
        }

        $conn->begin_transaction();
        try {
            $del = $conn->prepare('DELETE FROM role_permissions WHERE role_slug = ?');
            $del->bind_param('s', $roleSlug);
            $del->execute();
            $del->close();

            $ins = $conn->prepare(
                'INSERT INTO role_permissions (role_slug, permission_key, `allowed`) VALUES (?, ?, ?)'
            );
            if (!$ins) {
                throw new RuntimeException('role_permissions save prepare failed: ' . $conn->error);
            }
            foreach ($allowedKeys as $key => $allowed) {
                $ins->bind_param('ssi', $roleSlug, $key, $allowed);
                $ins->execute();
            }
            $ins->close();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public static function loadForSession(mysqli $conn, string $roleSlug): array
    {
        return self::effectiveForRole($conn, $roleSlug);
    }

    /** @return array<string, array{edit?: string, view?: string}> */
    public static function fieldGroupCaps(): array
    {
        return self::config()['field_group_caps'] ?? [];
    }
}
