<?php
/**
 * Process due patient follow-up reminder messages.
 * Schedule daily, e.g. Windows Task Scheduler at 7:00 AM:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\RHUWEB\scripts\send-follow-up-reminders.php
 */
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/FollowUpReminder.php';

$conn = getDBConnection();
FollowUpReminder::ensureSchema($conn);
$stats = FollowUpReminder::processDueMessages($conn, false);
$conn->close();

$line = sprintf(
    "[%s] follow-up reminders — processed:%d sent:%d failed:%d skipped:%d\n",
    date('c'),
    $stats['processed'],
    $stats['sent'],
    $stats['failed'],
    $stats['skipped']
);
echo $line;
