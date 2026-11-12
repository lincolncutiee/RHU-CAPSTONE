<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/AdminInstall.php';

$conn = getDBConnection();
AdminInstall::ensureSchema($conn);

$users = [];
$result = $conn->query(
    'SELECT u.id, u.username, r.slug, r.name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.is_active = 1
     ORDER BY u.id'
);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$result->free();

if ($users === []) {
    fwrite(STDERR, "No active users.\n");
    exit(1);
}

$day = '2026-11-06';
$cursor = strtotime($day . ' 10:00:00');
$events = [];

$failReasons = ['wrong_password', 'wrong_password', 'unknown_username'];
for ($i = 0; $i < 100; $i++) {
    $user = $users[$i % count($users)];
    $reason = $failReasons[$i % 3];
    if ($reason === 'unknown_username') {
        $events[] = [
            'at' => $cursor,
            'event' => 'failed',
            'user_id' => null,
            'username' => 'unknown' . (($i % 17) + 1),
            'role_slug' => null,
            'role_name' => null,
            'reason' => 'unknown_username',
        ];
    } else {
        $events[] = [
            'at' => $cursor,
            'event' => 'failed',
            'user_id' => (int) $user['id'],
            'username' => $user['username'],
            'role_slug' => $user['slug'],
            'role_name' => $user['name'],
            'reason' => 'wrong_password',
        ];
    }
    $cursor += 12 + ($i % 5) * 4;
    if (($i + 1) % 3 === 0) {
        $cursor += 3 * 60;
    }
}

$successStart = strtotime($day . ' 10:02:10');
for ($i = 0; $i < 100; $i++) {
    $user = $users[($i * 3) % count($users)];
    $events[] = [
        'at' => $successStart,
        'event' => 'login',
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'role_slug' => $user['slug'],
        'role_name' => $user['name'],
        'reason' => null,
    ];
    $successStart += 55 + ($i % 7) * 8;
}

usort($events, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

$stmt = $conn->prepare(
    "INSERT INTO access_logs (event, user_id, username, role_slug, role_name, reason, ip_address, created_at)
     VALUES (?, NULLIF(?, ''), ?, ?, ?, ?, '127.0.0.1', ?)"
);
if (!$stmt) {
    fwrite(STDERR, $conn->error . PHP_EOL);
    exit(1);
}

$login = 0;
$failed = 0;
foreach ($events as $row) {
    $userKey = $row['user_id'] !== null ? (string) $row['user_id'] : '';
    $created = date('Y-m-d H:i:s', $row['at']);
    $stmt->bind_param(
        'sssssss',
        $row['event'],
        $userKey,
        $row['username'],
        $row['role_slug'],
        $row['role_name'],
        $row['reason'],
        $created
    );
    $stmt->execute();
    if ($row['event'] === 'login') {
        $login++;
    } else {
        $failed++;
    }
}
$stmt->close();

$first = date('Y-m-d H:i:s', $events[0]['at']);
$last = date('Y-m-d H:i:s', $events[count($events) - 1]['at']);
echo "Inserted login={$login} failed={$failed} from {$first} to {$last}\n";
$conn->close();
