<?php

require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Permissions.php';

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $conn = getDBConnection();
        require_once __DIR__ . '/AdminInstall.php';
        require_once __DIR__ . '/AdminUsers.php';
        AdminInstall::ensureSchema($conn);

        $adminSelect = AdminInstall::hasCanAccessAdminColumn($conn)
            ? 'u.can_access_admin'
            : '0 AS can_access_admin';

        $sql = "SELECT u.id, u.username, u.password_hash, u.full_name, u.is_active, {$adminSelect},
                    r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('[RHU Auth] prepare failed: ' . $conn->error);
            $conn->close();
            return false;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            require_once __DIR__ . '/AccessLog.php';
            $reason = !$user ? 'unknown_username' : (!$user['is_active'] ? 'inactive' : 'wrong_password');
            AccessLog::write(
                $conn,
                'failed',
                $user ? (int) $user['id'] : null,
                $username,
                $user['role_slug'] ?? null,
                $user['role_name'] ?? null,
                $reason
            );
            $conn->close();
            return false;
        }

        $privileges = AdminUsers::loadPrivilegesForSession($conn, (int) $user['id']);
        require_once __DIR__ . '/RolePermissions.php';
        $roleSlug = Permissions::normalizeRole($user['role_slug'] ?? '');
        $rolePerms = RolePermissions::loadForSession($conn, $roleSlug);
        $conn->close();

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('username', $user['username']);
        Session::set('full_name', $user['full_name']);
        Session::set('role', Permissions::normalizeRole($user['role_slug'] ?? ''));
        Session::set('role_name', $user['role_name']);
        Session::set('can_access_admin', !empty($user['can_access_admin']));
        Session::set('field_privileges', $privileges);
        Session::set('role_permissions', $rolePerms);
        Session::set('rhu_session_hydrated', true);

        require_once __DIR__ . '/AccessLog.php';
        $logConn = getDBConnection();
        AccessLog::write(
            $logConn,
            'login',
            (int) $user['id'],
            (string) $user['username'],
            $roleSlug,
            (string) ($user['role_name'] ?? ''),
            null
        );
        $logConn->close();

        return true;
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id'               => Session::get('user_id'),
            'username'         => Session::get('username'),
            'full_name'        => Session::get('full_name'),
            'role'             => Session::get('role'),
            'role_name'        => Session::get('role_name'),
            'can_access_admin' => (bool) Session::get('can_access_admin'),
        ];
    }

    public static function role(): ?string
    {
        return Session::get('role');
    }

    public static function logout(): void
    {
        if (self::check()) {
            require_once __DIR__ . '/AccessLog.php';
            $conn = getDBConnection();
            AccessLog::write(
                $conn,
                'logout',
                (int) Session::get('user_id'),
                (string) Session::get('username'),
                (string) Session::get('role'),
                (string) Session::get('role_name'),
                null
            );
            $conn->close();
        }
        Session::destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}
