<?php
/**
 * Emergency SQL export when mysqldump crashes (e.g. innodb_force_recovery).
 * Usage: php scripts/export-rhu-emergency.php [output.sql]
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$out = $argv[1] ?? (__DIR__ . '/../backups/rhu_management_emergency_' . date('Ymd_His') . '.sql');
$dir = dirname($out);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$conn = getDBConnection();
$conn->set_charset('utf8mb4');

$tables = [];
$res = $conn->query(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
     ORDER BY table_name"
);
while ($row = $res->fetch_row()) {
    $tables[] = $row[0];
}

$fp = fopen($out, 'wb');
fwrite($fp, "-- Emergency export " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$failed = [];
foreach ($tables as $table) {
    $safe = '`' . str_replace('`', '``', $table) . '`';
    $cr = $conn->query("SHOW CREATE TABLE {$safe}");
    if (!$cr) {
        $failed[] = $table . ' (structure)';
        continue;
    }
    $row = $cr->fetch_assoc();
    fwrite($fp, "DROP TABLE IF EXISTS {$safe};\n{$row['Create Table']};\n\n");

    $data = $conn->query("SELECT * FROM {$safe}");
    if (!$data) {
        $failed[] = $table . ' (data)';
        continue;
    }
    while ($r = $data->fetch_assoc()) {
        $cols = array_map(static fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($r));
        $vals = [];
        foreach ($r as $v) {
            $vals[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string((string) $v) . "'";
        }
        fwrite($fp, 'INSERT INTO ' . $safe . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
    }
    fwrite($fp, "\n");
    echo "Exported {$table}\n";
}

fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fp);
$conn->close();

echo "Wrote: {$out}\n";
if ($failed !== []) {
    fwrite(STDERR, 'Failed: ' . implode(', ', $failed) . "\n");
    exit(2);
}
exit(0);
