<?php
/**
 * Database connection for RHU Management System
 */

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rhu_management');

function getDBConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = mysqli_init();
    if (!$conn) {
        rhu_db_fail('Database init failed.');
    }
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    try {
        $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (mysqli_sql_exception $e) {
        rhu_db_fail(
            'Cannot reach MySQL. Open XAMPP Control Panel and start MySQL (green). '
            . 'If it stops immediately, check mysql_error.log in XAMPP.'
        );
    }

    if (!$connected || $conn->connect_error) {
        rhu_db_fail('Database connection failed. Start MySQL in XAMPP.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function rhu_db_fail(string $message): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        die(json_encode(['success' => false, 'message' => $message]));
    }
    header('Content-Type: text/html; charset=UTF-8');
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>RHU — Database</title></head><body style="font-family:system-ui;padding:2rem">'
        . '<h1>Cannot connect to database</h1><p>' . htmlspecialchars($message) . '</p>'
        . '<p>Open <strong>XAMPP Control Panel</strong> and start <strong>MySQL</strong>, then refresh.</p></body></html>');
}
