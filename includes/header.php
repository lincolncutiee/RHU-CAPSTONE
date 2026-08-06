<?php
/**
 * Shared page header — top navigation layout (HIS/EMR).
 */
require_once __DIR__ . '/bootstrap.php';
requireLogin();

$user = currentUser();
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/main.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/patient-search.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
