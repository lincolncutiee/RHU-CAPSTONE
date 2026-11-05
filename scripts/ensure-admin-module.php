<?php
/**
 * Idempotent setup for Administration module (run once after deploy).
 * Usage: php scripts/ensure-admin-module.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/AdminInstall.php';
require_once __DIR__ . '/../core/FormTemplate.php';

$conn = getDBConnection();
AdminInstall::ensureSchema($conn);

$issues = [];
if (!AdminInstall::hasCanAccessAdminColumn($conn)) {
    $issues[] = 'Column users.can_access_admin is missing (ALTER may have failed — check innodb_force_recovery in my.ini).';
}
if (!FormTemplate::hasTemplateStringsTable($conn)) {
    $issues[] = 'Table form_template_strings is missing (CREATE may have failed — database may be read-only).';
} elseif (!FormTemplate::syncDefaults($conn)) {
    $issues[] = 'Template defaults could not be synced (see PHP error log).';
}

$adminCheck = $conn->prepare("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if ($adminCheck) {
    $adminCheck->execute();
    $hasAdmin = (bool) $adminCheck->get_result()->fetch_assoc();
    $adminCheck->close();
    if (!$hasAdmin) {
        $issues[] = 'Demo user "admin" was not created (INSERT may have failed).';
    }
} else {
    $issues[] = 'Could not verify admin user: ' . $conn->error;
}

$conn->close();

if ($issues) {
    fwrite(STDERR, "Administration setup finished with problems:\n");
    foreach ($issues as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    fwrite(STDERR, "\nIf MySQL is in recovery mode, set innodb_force_recovery=0 in my.ini, restart MySQL, then run this script again.\n");
    exit(1);
}

echo "Administration module schema and template defaults are ready.\n";
echo "Demo admin login: admin / password\n";
