<?php
/**
 * Receptionist desk queue API — queue management only (no form editing).
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/ReceptionQueue.php';
requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');

if ($role !== ROLE_RECEPTIONIST) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Receptionist access only.']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? 'list'));
$conn = getDBConnection();

try {
    ReceptionQueue::ensureSchema($conn);

    if ($action === 'list' || $action === '' || $action === 'stats') {
        $filters = [
            'date'       => trim((string) ($_GET['date'] ?? $_POST['date'] ?? ReceptionQueue::today())),
            'status'     => trim((string) ($_GET['status'] ?? $_POST['status'] ?? '')),
            'barangay'   => trim((string) ($_GET['barangay'] ?? $_POST['barangay'] ?? '')),
            'patient_id' => trim((string) ($_GET['patient_id'] ?? $_POST['patient_id'] ?? '')),
            'name'       => trim((string) ($_GET['name'] ?? $_POST['name'] ?? '')),
        ];
        $stats = ReceptionQueue::dashboardStats($conn, $filters['date']);
        if ($action === 'stats') {
            $conn->close();
            echo json_encode(['success' => true, 'date' => $filters['date'], 'stats' => $stats]);
            exit;
        }
        $items = ReceptionQueue::listQueue($conn, $filters);
        $conn->close();
        echo json_encode([
            'success' => true,
            'date'    => $filters['date'],
            'items'   => $items,
            'stats'   => $stats,
            'counts'  => $stats,
        ]);
        exit;
    }

    $queueId = (int) ($_POST['queue_id'] ?? 0);

    if ($action === 'call_next') {
        $conn->begin_transaction();
        try {
            $result = ReceptionQueue::callNext($conn, $user);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        $conn->close();
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'No waiting patients in the queue.']);
            exit;
        }
        $qn = $result['entry']['queue_number'] ?? '—';
        echo json_encode([
            'success'         => true,
            'message'         => 'Now serving ' . $qn,
            'entry'           => $result['entry'],
            'highlight_id'    => (int) ($result['entry']['id'] ?? 0),
            'forwarded'       => $result['forward'] !== null,
        ]);
        exit;
    }

    if ($queueId <= 0) {
        throw new InvalidArgumentException('Invalid queue entry.');
    }

    if ($action === 'call' || $action === 'serve') {
        $conn->begin_transaction();
        try {
            $result = ReceptionQueue::markServing($conn, $queueId, $user, true);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        $conn->close();
        $qn = $result['entry']['queue_number'] ?? '—';
        echo json_encode([
            'success'      => true,
            'message'      => 'Now serving ' . $qn . ($result['forward'] ? ' — sent to Doctor queue.' : ''),
            'entry'        => $result['entry'],
            'highlight_id' => (int) ($result['entry']['id'] ?? 0),
        ]);
        exit;
    }

    if ($action === 'skip') {
        $entry = ReceptionQueue::markSkipped($conn, $queueId);
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Patient marked as Skipped.', 'entry' => $entry]);
        exit;
    }

    if ($action === 'complete' || $action === 'done_at_reception') {
        $entry = ReceptionQueue::markCompleted($conn, $queueId);
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Patient marked as Completed.', 'entry' => $entry]);
        exit;
    }

    if ($action === 'cancel') {
        $entry = ReceptionQueue::markCancelled($conn, $queueId);
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Patient marked as Cancelled.', 'entry' => $entry]);
        exit;
    }

    throw new InvalidArgumentException('Unknown action.');
} catch (Throwable $e) {
    if ($conn) {
        @$conn->close();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
