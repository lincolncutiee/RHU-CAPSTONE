<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/Permissions.php';

if (Auth::check()) {
    $user = currentUser();
    $role = Permissions::normalizeRole($user['role'] ?? '');
    if ($role === ROLE_DOCTOR) {
        $home = BASE_URL . '/workflow/queue.php';
    } elseif ($role === ROLE_SYSTEM_ADMIN) {
        $home = BASE_URL . '/admin/users.php';
    } else {
        $home = BASE_URL . '/dashboard.php';
    }
    header('Location: ' . $home);
    exit;
}
header('Location: ' . BASE_URL . '/login.php');
exit;
