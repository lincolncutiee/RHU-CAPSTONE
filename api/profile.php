<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/UserProfile.php';

Auth::requireLogin();

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not signed in.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $conn = getDBConnection();

    if ($method === 'GET') {
        $profile = UserProfile::get($conn, $userId);
        if (!$profile) {
            throw new RuntimeException('Profile not found.');
        }
        echo json_encode(['success' => true, 'user' => $profile]);
        $conn->close();
        exit;
    }

    if ($method !== 'POST') {
        throw new RuntimeException('Method not allowed.');
    }

    $payload = $_POST;
    if (empty($payload) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    $action = (string) ($payload['action'] ?? '');

    switch ($action) {
        case 'update_profile':
            $result = UserProfile::updateSelf(
                $conn,
                $userId,
                (string) ($payload['full_name'] ?? ''),
                array_key_exists('email', $payload) ? (string) $payload['email'] : null
            );
            echo json_encode($result);
            break;

        case 'change_password':
            $result = UserProfile::changePassword(
                $conn,
                $userId,
                (string) ($payload['current_password'] ?? ''),
                (string) ($payload['new_password'] ?? '')
            );
            echo json_encode($result);
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }

    $conn->close();
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
