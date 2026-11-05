<?php
require_once __DIR__ . '/../config/db.php';
$conn = getDBConnection();
$conn->query("UPDATE reminder_settings SET smtp_host = 'smtp.gmail.com' WHERE id = 1 AND TRIM(COALESCE(smtp_host, '')) = ''");
echo "smtp_host fixed if was empty\n";
$conn->close();
