<?php
/**
 * Restrict Reports & Heat Map to Doctor + IT (remove from Records, Pharmacy, Receptionist).
 * CLI: php scripts/apply-analytics-doctor-it-only.php
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/core/RolePermissions.php';

$conn = getDBConnection();
RolePermissions::ensureSchema($conn);

$keys = ['reports.view', 'heatmap.view'];
$denyRoles = ['records_office', 'pharmacy', 'receptionist', 'system_administrator'];
$allowRoles = ['doctor', 'it_department'];

$stmt = $conn->prepare(
    'INSERT INTO role_permissions (role_slug, permission_key, `allowed`) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`)'
);
if (!$stmt) {
    fwrite(STDERR, 'prepare failed: ' . $conn->error . PHP_EOL);
    exit(1);
}

foreach ($denyRoles as $role) {
    foreach ($keys as $key) {
        $allowed = 0;
        $stmt->bind_param('ssi', $role, $key, $allowed);
        $stmt->execute();
    }
}
foreach ($allowRoles as $role) {
    foreach ($keys as $key) {
        $allowed = 1;
        $stmt->bind_param('ssi', $role, $key, $allowed);
        $stmt->execute();
    }
}
$stmt->close();
$conn->close();

echo "OK: reports.view and heatmap.view — allowed for doctor, it_department only.\n";
echo "Log out and log in again as records/pharmacy to refresh the menu.\n";
