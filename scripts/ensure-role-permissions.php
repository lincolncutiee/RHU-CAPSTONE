<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/core/RolePermissions.php';

$conn = getDBConnection();
RolePermissions::ensureSchema($conn);
$res = $conn->query('SELECT COUNT(*) AS c FROM role_permissions');
$row = $res ? $res->fetch_assoc() : ['c' => 0];
echo 'role_permissions rows: ' . ($row['c'] ?? 0) . PHP_EOL;
$conn->close();
