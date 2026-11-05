<?php
/**
 * Archive a patient record — hides it from the default Patient Records list.
 *
 * POST JSON or form: patient_id
 *
 * - Records / IT: Draft or Rejected → Archived (is_archived = 1)
 * - IT: any other non-archived status → Archived
 * - IT: Printed → Archived (via workflow archive_record)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/Permissions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$user = currentUser();
$user['role'] = Permissions::normalizeRole($user['role'] ?? '');
$patientId = (int) ($input['patient_id'] ?? 0);

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
    exit;
}

$conn = null;

try {
    $conn = getDBConnection();
    WorkflowStatus::ensurePatientsStatusEnum($conn);

    $stmt = $conn->prepare('SELECT id, status, is_archived FROM patients WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        throw new RuntimeException('Patient record not found.');
    }

    if ((int) ($patient['is_archived'] ?? 0) === 1) {
        throw new RuntimeException('This record is already archived.');
    }

    $status = WorkflowStatus::normalize($patient['status'] ?? '');

    if ($user['role'] === ROLE_IT) {
        if ($status === WORKFLOW_PRINTED) {
            $conn->begin_transaction();
            $result = Workflow::execute($conn, 'archive_record', $patientId, $user);
            $conn->commit();
            $conn->close();

            echo json_encode([
                'success' => true,
                'message' => $result['message'] ?? 'Record archived successfully.',
                'patient_id' => $patientId,
                'status' => WORKFLOW_ARCHIVED,
            ]);
            exit;
        }

        if ($status !== WORKFLOW_ARCHIVED) {
            $conn->begin_transaction();

            $toStatus = WORKFLOW_ARCHIVED;
            $uid = (int) ($user['id'] ?? 0);
            $fromStatus = $patient['status'];

            $stmt = $conn->prepare('UPDATE patients SET status = ?, is_archived = 1, rejection_reason = NULL WHERE id = ?');
            $stmt->bind_param('si', $toStatus, $patientId);
            $stmt->execute();
            if ($stmt->errno) {
                throw new RuntimeException('Unable to archive record: ' . $stmt->error);
            }
            $stmt->close();

            logWorkflow($conn, $patientId, $uid, 'Record archived', $fromStatus, $toStatus, null);

            $conn->commit();
            $conn->close();

            echo json_encode([
                'success' => true,
                'message' => 'Record archived. It is hidden from the default patient list.',
                'patient_id' => $patientId,
                'status' => WORKFLOW_ARCHIVED,
            ]);
            exit;
        }
    }

    $canArchiveDraft = in_array($user['role'], [ROLE_RECORDS, ROLE_IT], true)
        && in_array($status, [WORKFLOW_DRAFT, WORKFLOW_REJECTED], true);

    if (!$canArchiveDraft) {
        throw new RuntimeException('Your role cannot archive this record in its current status.');
    }

    $conn->begin_transaction();

    $toStatus = WORKFLOW_ARCHIVED;
    $uid = (int) ($user['id'] ?? 0);
    $fromStatus = $patient['status'];

    $stmt = $conn->prepare('UPDATE patients SET status = ?, is_archived = 1, rejection_reason = NULL WHERE id = ?');
    $stmt->bind_param('si', $toStatus, $patientId);
    $stmt->execute();
    if ($stmt->errno) {
        throw new RuntimeException('Unable to archive record: ' . $stmt->error);
    }
    $stmt->close();

    logWorkflow($conn, $patientId, $uid, 'Record archived', $fromStatus, $toStatus, null);

    $conn->commit();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Record archived. It is hidden from the default patient list.',
        'patient_id' => $patientId,
        'status' => WORKFLOW_ARCHIVED,
    ]);
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
        try {
            $conn->close();
        } catch (Throwable $ignored) {
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Unable to archive record.',
    ]);
}
