<?php
/**
 * CLI: test MySQL connection for this app.
 * Usage: php scripts/db-ping.php
 */
require_once __DIR__ . '/../config/db.php';

try {
    $conn = getDBConnection();
    $conn->close();
    echo "OK connected to rhu_management\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
