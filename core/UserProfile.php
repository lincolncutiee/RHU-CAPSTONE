<?php

require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/AdminInstall.php';

class UserProfile
{
    public static function get(mysqli $conn, int $userId): ?array
    {
        AdminInstall::ensureSchema($conn);
        $adminSelect = AdminInstall::hasCanAccessAdminColumn($conn)
            ? 'u.can_access_admin'
            : '0 AS can_access_admin';

        $sql = "SELECT u.id, u.username, u.full_name, u.email, u.is_active, {$adminSelect},
                    r.slug AS role_slug, r.name AS role_name, u.created_at, u.updated_at
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * @return array{success: bool, message?: string, user?: array}
     */
    public static function updateSelf(mysqli $conn, int $userId, string $fullName, ?string $email): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['success' => false, 'message' => 'Full name is required.'];
        }

        $email = $email !== null ? trim($email) : null;
        if ($email === '') {
            $email = null;
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enter a valid email address or leave it blank.'];
        }

        $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not update profile.'];
        }
        $stmt->bind_param('ssi', $fullName, $email, $userId);
        $stmt->execute();
        $stmt->close();

        Session::set('full_name', $fullName);

        $user = self::get($conn, $userId);

        return ['success' => true, 'user' => $user];
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public static function changePassword(mysqli $conn, int $userId, string $currentPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not verify account.'];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Could not save password.'];
        }
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();

        return ['success' => true];
    }
}
