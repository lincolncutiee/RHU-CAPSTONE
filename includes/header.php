<?php
/**
 * Shared page header — top navigation layout (HIS/EMR).
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/vendor-assets.php';
requireLogin();

$user = currentUser();
if ($user && !Session::has('rhu_session_hydrated')) {
    try {
        require_once __DIR__ . '/../core/AdminInstall.php';
        require_once __DIR__ . '/../core/AdminUsers.php';
        $hConn = getDBConnection();
        AdminInstall::ensureSchema($hConn);
        $uid = (int) $user['id'];
        if (!Session::has('can_access_admin')) {
            $stmt = $hConn->prepare('SELECT can_access_admin FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                Session::set('can_access_admin', !empty($row['can_access_admin']));
                $user['can_access_admin'] = !empty($row['can_access_admin']);
            }
        } else {
            $user['can_access_admin'] = (bool) Session::get('can_access_admin');
        }
        if (!Session::has('field_privileges')) {
            Session::set('field_privileges', AdminUsers::loadPrivilegesForSession($hConn, $uid));
        }
        if (!Session::has('role_permissions')) {
            require_once __DIR__ . '/../core/RolePermissions.php';
            Permissions::refreshRolePermissionsInSession($hConn, $user['role'] ?? null);
        }
        Session::set('rhu_session_hydrated', true);
        $hConn->close();
    } catch (Throwable $e) {
        // ignore
    }
} elseif ($user) {
    $user['can_access_admin'] = (bool) Session::get('can_access_admin', false);
}
$pageTitle = $pageTitle ?? 'RHU Management System';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | RHU Management System</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png">
    <link href="<?= htmlspecialchars(rhu_asset_url('bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(rhu_asset_url('bootstrap_icons_css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/main.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/patient-search.css" rel="stylesheet">
    <link href="<?= htmlspecialchars(rhu_asset_url('sweetalert2_css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css')) ?>" rel="stylesheet">
    <?php if (!empty($extraCSS)): ?>
        <?php foreach ((array)$extraCSS as $css): ?>
            <link href="<?= htmlspecialchars($css) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="layout-topnav">
    <div class="app-wrapper">
        <?php include __DIR__ . '/topnav.php'; ?>
        <div class="main-content">
            <?php if (!empty($pageTitle) && ($activePage ?? '') !== 'encoding' && empty($hidePageTitleBar)): ?>
            <div class="page-title-bar">
                <h1 class="page-heading"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="page-subheading"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <main class="page-content">
