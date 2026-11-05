<?php

require_once __DIR__ . '/Permissions.php';
require_once __DIR__ . '/AdminInstall.php';
require_once __DIR__ . '/AdminAudit.php';

class AdminUsers
{
    /** @return array<string, string> slug => label */
    public static function assignableRoles(): array
    {
        return require __DIR__ . '/../config/admin.php';
    }

    public static function assignableRolesList(): array
    {
        $cfg = self::assignableRoles();
        return $cfg['assignable_roles'] ?? [];
    }

    public static function assignableFieldGroups(): array
    {
        $cfg = self::assignableRoles();
        return $cfg['assignable_field_groups'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public static function listUsers(mysqli $conn, ?string $search = null): array
    {
        AdminInstall::ensureSchema($conn);
        $search = trim((string) $search);
        $assignable = array_keys(self::assignableRolesList());

        if ($search === '') {
            $placeholders = implode(',', array_fill(0, count($assignable), '?'));
            $types = str_repeat('s', count($assignable));
            $sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.can_access_admin,
                           u.created_at, u.updated_at, r.slug AS role_slug, r.name AS role_name
                    FROM users u
                    JOIN roles r ON r.id = u.role_id
                    WHERE r.slug IN ($placeholders)
                    ORDER BY u.full_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$assignable);
        } else {
            $like = '%' . $search . '%';
            $placeholders = implode(',', array_fill(0, count($assignable), '?'));
            $types = 'sss' . str_repeat('s', count($assignable));
            $sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.can_access_admin,
                           u.created_at, u.updated_at, r.slug AS role_slug, r.name AS role_name
                    FROM users u
                    JOIN roles r ON r.id = u.role_id
                    WHERE r.slug IN ($placeholders)
                      AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
                    ORDER BY u.full_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...array_merge($assignable, [$like, $like, $like]));
        }

        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public static function getUser(mysqli $conn, int $userId): ?array
    {
        AdminInstall::ensureSchema($conn);
        $stmt = $conn->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $row['privileges'] = self::getPrivileges($conn, $userId);
        return $row;
    }

    /** @return array<string, string> group => level */
    public static function getPrivileges(mysqli $conn, int $userId): array
    {
        $groups = array_keys(self::assignableFieldGroups());
        $out = array_fill_keys($groups, 'inherit');

        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_field_privileges'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            if ($tableCheck) {
                $tableCheck->free();
            }
            return $out;
        }
        $tableCheck->free();

        $stmt = $conn->prepare(
            'SELECT field_group, access_level FROM user_field_privileges WHERE user_id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $g = Permissions::normalizeGroup($r['field_group']);
            if (isset($out[$g])) {
                $out[$g] = $r['access_level'];
            }
        }
        $stmt->close();
        return $out;
    }

    /** @param array<string, string> $privileges */
    public static function savePrivileges(mysqli $conn, int $userId, array $privileges): void
    {
        AdminInstall::ensureSchema($conn);
        $allowed = array_keys(self::assignableFieldGroups());
        $levels = ['inherit', 'none', 'view', 'edit'];

        $conn->query('DELETE FROM user_field_privileges WHERE user_id = ' . (int) $userId);
        $stmt = $conn->prepare(
            'INSERT INTO user_field_privileges (user_id, field_group, access_level) VALUES (?, ?, ?)'
        );
        foreach ($allowed as $group) {
            $level = $privileges[$group] ?? 'inherit';
            if (!in_array($level, $levels, true) || $level === 'inherit') {
                continue;
            }
            $stmt->bind_param('iss', $userId, $group, $level);
            $stmt->execute();
        }
        $stmt->close();
    }

    public static function countActiveAdmins(mysqli $conn): int
    {
        AdminInstall::ensureSchema($conn);
        $row = $conn->query(
            'SELECT COUNT(*) AS c FROM users WHERE can_access_admin = 1 AND is_active = 1'
        )->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array{username:string,full_name:string,email?:string,role_slug:string,password:string,can_access_admin?:bool,privileges?:array<string,string>} $data
     */
    public static function createUser(mysqli $conn, int $actorId, array $data): array
    {
        AdminInstall::ensureSchema($conn);
        $username = trim($data['username'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '') ?: null;
        $roleSlug = Permissions::normalizeRole($data['role_slug'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $canAdmin = !empty($data['can_access_admin']);

        $assignable = self::assignableRolesList();
        if (!isset($assignable[$roleSlug])) {
            return ['success' => false, 'message' => 'Invalid role.'];
        }
        if ($username === '' || $fullName === '' || strlen($password) < 8) {
            return ['success' => false, 'message' => 'Username, full name, and password (min 8 chars) are required.'];
        }

        $stmt = $conn->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $roleSlug);
        $stmt->execute();
        $roleRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$roleRow) {
            return ['success' => false, 'message' => 'Role not found.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $roleId = (int) $roleRow['id'];
        $adminFlag = $canAdmin ? 1 : 0;

        $stmt = $conn->prepare(
            'INSERT INTO users (role_id, username, password_hash, full_name, email, can_access_admin)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssi', $roleId, $username, $hash, $fullName, $email, $adminFlag);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['success' => false, 'message' => 'Username already exists.'];
            }
            throw $e;
        }
        $newId = (int) $stmt->insert_id;
        $stmt->close();

        if (!empty($data['privileges']) && is_array($data['privileges'])) {
            self::savePrivileges($conn, $newId, $data['privileges']);
        }

        AdminAudit::log($conn, $actorId, 'user.create', 'user', (string) $newId, [
            'username' => $username,
            'role' => $roleSlug,
            'can_access_admin' => $canAdmin,
        ]);

        return ['success' => true, 'user_id' => $newId];
    }

    public static function updateUser(mysqli $conn, int $actorId, int $userId, array $data): array
    {
        AdminInstall::ensureSchema($conn);
        $existing = self::getUser($conn, $userId);
        if (!$existing) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $assignable = self::assignableRolesList();
        $roleSlug = isset($data['role_slug'])
            ? Permissions::normalizeRole($data['role_slug'])
            : $existing['role_slug'];
        if (!isset($assignable[$roleSlug])) {
            return ['success' => false, 'message' => 'Invalid role.'];
        }

        $fullName = trim($data['full_name'] ?? $existing['full_name']);
        $email = array_key_exists('email', $data)
            ? (trim($data['email'] ?? '') ?: null)
            : $existing['email'];
        $canAdmin = array_key_exists('can_access_admin', $data)
            ? !empty($data['can_access_admin'])
            : (bool) $existing['can_access_admin'];

        if ($canAdmin === false && (int) $existing['can_access_admin'] === 1) {
            if (self::countActiveAdmins($conn) <= 1 && (int) $existing['is_active'] === 1) {
                return ['success' => false, 'message' => 'Cannot remove admin access from the last active administrator.'];
            }
        }

        $stmt = $conn->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $stmt->bind_param('s', $roleSlug);
        $stmt->execute();
        $roleRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$roleRow) {
            return ['success' => false, 'message' => 'Role not found.'];
        }

        $roleId = (int) $roleRow['id'];
        $adminFlag = $canAdmin ? 1 : 0;
        $stmt = $conn->prepare(
            'UPDATE users SET role_id = ?, full_name = ?, email = ?, can_access_admin = ? WHERE id = ?'
        );
        $stmt->bind_param('issii', $roleId, $fullName, $email, $adminFlag, $userId);
        $stmt->execute();
        $stmt->close();

        if (!empty($data['privileges']) && is_array($data['privileges'])) {
            self::savePrivileges($conn, $userId, $data['privileges']);
        }

        AdminAudit::log($conn, $actorId, 'user.update', 'user', (string) $userId, [
            'role' => $roleSlug,
            'can_access_admin' => $canAdmin,
        ]);

        return ['success' => true];
    }

    public static function resetPassword(mysqli $conn, int $actorId, int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        AdminInstall::ensureSchema($conn);
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        AdminAudit::log($conn, $actorId, 'user.reset_password', 'user', (string) $userId, []);
        return ['success' => true];
    }

    public static function setActive(mysqli $conn, int $actorId, int $userId, bool $active): array
    {
        AdminInstall::ensureSchema($conn);
        $user = self::getUser($conn, $userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if (!$active) {
            if ((int) $userId === $actorId) {
                return ['success' => false, 'message' => 'You cannot deactivate your own account.'];
            }
            if ((int) $user['can_access_admin'] === 1 && (int) $user['is_active'] === 1) {
                if (self::countActiveAdmins($conn) <= 1) {
                    return ['success' => false, 'message' => 'Cannot deactivate the last active administrator.'];
                }
            }
        }

        $flag = $active ? 1 : 0;
        $stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->bind_param('ii', $flag, $userId);
        $stmt->execute();
        $stmt->close();

        AdminAudit::log($conn, $actorId, $active ? 'user.reactivate' : 'user.deactivate', 'user', (string) $userId, []);

        return ['success' => true];
    }

    /** Load privileges for session after login. */
    public static function loadPrivilegesForSession(mysqli $conn, int $userId): array
    {
        return self::getPrivileges($conn, $userId);
    }
}
