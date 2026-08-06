<?php
/**
 * Legacy Pharmacy Queue URL — redirect to Dashboard (queue lives there now).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';

requireLogin();
requireRole([ROLE_PHARMACY]);

$status = trim((string) ($_GET['status'] ?? ''));
$qs = $status !== '' ? ('?status=' . rawurlencode($status)) : '';
header('Location: ' . BASE_URL . '/dashboard.php' . $qs);
exit;
