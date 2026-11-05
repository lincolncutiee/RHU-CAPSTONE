<?php
/**
 * Serve historical record files (original photo / soft copy) with auth.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/HistoricalRecord.php';
require_once __DIR__ . '/../core/Permissions.php';
requireLogin();

$role = Permissions::normalizeRole(currentUser()['role'] ?? '');
if (!HistoricalRecord::canView($role)) {
    http_response_code(403);
    exit('Forbidden');
}

$recordId = (int) ($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'original';

if ($recordId <= 0) {
    http_response_code(400);
    exit('Invalid record.');
}

$conn = getDBConnection();
$record = HistoricalRecord::find($conn, $recordId);
$conn->close();

if (!$record) {
    http_response_code(404);
    exit('Not found.');
}

$relative = $type === 'soft'
    ? ($record['soft_copy_path'] ?? null)
    : ($record['original_photo_path'] ?? null);

if (!$relative) {
    http_response_code(404);
    exit('File not available.');
}

$path = HistoricalRecord::absolutePath((string) $relative);
if (!is_readable($path)) {
    http_response_code(404);
    exit('File missing.');
}

if ($type === 'soft') {
    header('Content-Type: application/json; charset=utf-8');
    readfile($path);
    exit;
}

$mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
    'png'  => 'image/png',
    'webp' => 'image/webp',
    default => 'image/jpeg',
};
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
readfile($path);
