<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/Permissions.php';

if (Auth::check()) {
    $user = currentUser();
    $role = Permissions::normalizeRole($user['role'] ?? '');
    $home = $role === ROLE_DOCTOR
        ? BASE_URL . '/workflow/queue.php'
        : BASE_URL . '/dashboard.php';
    header('Location: ' . $home);
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
