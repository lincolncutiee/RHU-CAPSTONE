<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/AdminAccess.php';
require_once __DIR__ . '/../core/FollowUpReminder.php';
require_once __DIR__ . '/../core/AdminAudit.php';

AdminAccess::requirePermission('admin.messaging');

$actor = currentUser();
$actorId = (int) ($actor['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    $conn = getDBConnection();
    FollowUpReminder::ensureSchema($conn);

    if ($method === 'GET' && $action === 'settings') {
        echo json_encode(['success' => true, 'settings' => FollowUpReminder::settings($conn)]);
        $conn->close();
        exit;
    }

    if ($method === 'POST' && $action === 'save') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        FollowUpReminder::saveSettings($conn, $payload, $actorId);
        AdminAudit::log($conn, $actorId, 'messaging.settings_update', 'reminder_settings', '1', null);
        echo json_encode(['success' => true, 'message' => 'Messaging settings saved.']);
        $conn->close();
        exit;
    }

    if ($method === 'POST' && $action === 'run_queue') {
        $stats = FollowUpReminder::processDueMessages($conn, false);
        AdminAudit::log($conn, $actorId, 'messaging.run_queue', 'reminder_queue', null, $stats);
        echo json_encode(['success' => true, 'stats' => $stats]);
        $conn->close();
        exit;
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
