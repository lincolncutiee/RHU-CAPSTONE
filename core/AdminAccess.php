<?php

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AdminInstall.php';
require_once __DIR__ . '/Permissions.php';

class AdminAccess
{
    public static function canAccess(?array $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return false;
        }
        if (!Permissions::can('admin.access')) {
            return false;
        }
        if (Permissions::normalizeRole($user['role'] ?? '') === ROLE_SYSTEM_ADMIN) {
            return true;
        }
        return !empty($user['can_access_admin']);
    }

    public static function requireAccess(): void
    {
        Auth::requireLogin();
        if (!self::canAccess()) {
            http_response_code(403);
            die('Access denied. Administration is limited to authorized admin accounts.');
        }
    }

    public static function requirePermission(string $key): void
    {
        self::requireAccess();
        Permissions::requireCan($key);
    }

    /**
     * Re-verify the acting admin's password before sensitive changes.
     */
    public static function verifyActorPassword(string $password): bool
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        if ($userId <= 0 || $password === '') {
            return false;
        }

        $conn = getDBConnection();
        AdminInstall::ensureSchema($conn);
        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        return $row && password_verify($password, (string) $row['password_hash']);
    }

    public static function clientIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
