<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/FollowUpReminder.php';

$conn = getDBConnection();
FollowUpReminder::ensureSchema($conn);
$row = $conn->query('SELECT is_enabled, email_enabled, smtp_host, smtp_port, smtp_user, smtp_from_email, LENGTH(smtp_pass) AS pass_len FROM reminder_settings WHERE id = 1')->fetch_assoc();
print_r($row);
$conn->close();
