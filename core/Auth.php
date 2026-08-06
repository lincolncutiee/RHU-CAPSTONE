<?php

require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Permissions.php';

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $conn = getDBConnection();
        $stmt = $conn->prepare(
            'SELECT u.id, u.username, u.password_hash, u.full_name, u.is_active,
                    r.slug AS role_slug, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('username', $user['username']);
        Session::set('full_name', $user['full_name']);
        Session::set('role', Permissions::normalizeRole($user['role_slug'] ?? ''));
        Session::set('role_name', $user['role_name']);

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
            'id'        => Session::get('user_id'),
            'username'  => Session::get('username'),
            'full_name' => Session::get('full_name'),
            'role'      => Session::get('role'),
            'role_name' => Session::get('role_name'),
        ];
    }

    public static function role(): ?string
    {
        return Session::get('role');
    }

    public static function logout(): void
    {
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
