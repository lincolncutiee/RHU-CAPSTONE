<?php
/**
 * Execute a workflow transition on a patient record.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/Consultation.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/ReceptionQueue.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/Pharmacy.php';
requireLogin();

$user = currentUser();
$user['role'] = Permissions::normalizeRole($user['role'] ?? '');
$action = trim($_POST['action'] ?? '');
$patientId = (int) ($_POST['patient_id'] ?? 0);
$consultationId = (int) ($_POST['consultation_id'] ?? 0);
$user['_rejection_reason'] = trim((string) ($_POST['rejection_reason'] ?? ''));
$user['_consultation_id'] = $consultationId;
$user['_return_to_user_id'] = (int) ($_POST['return_to_user_id'] ?? 0);

if ($patientId <= 0 || $action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Missing patient_id or action.']);
    exit;
}

$conn = null;
$committed = false;

try {
    $conn = getDBConnection();
    WorkflowStatus::ensurePatientsStatusEnum($conn);
    Pharmacy::ensureSchema($conn);
    ClinicalMaster::ensureSchema($conn);

    if (in_array($action, ['send_to_pharmacy', 'complete_consultation'], true)) {
        if ($consultationId <= 0) {
            $consultationId = ReceptionQueue::latestConsultationId($conn, $patientId);
            $user['_consultation_id'] = $consultationId;
        }
        if ($consultationId <= 0) {
            throw new RuntimeException('Missing consultation_id. Save the consultation first, then send to Pharmacy.');
        }

        $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE id = ? AND patient_id = ? LIMIT 1');
        $stmt->bind_param('ii', $consultationId, $patientId);
        $stmt->execute();
        $ownsConsult = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ownsConsult) {
            throw new RuntimeException('Consultation not found for this patient (consultation_id=' . $consultationId . ').');
        }

        if (!ClinicalMaster::hasConsultationMedications($conn, $consultationId)) {
            $stmtMed = $conn->prepare(
                'SELECT medication_treatment FROM treatment_records WHERE id = ? AND patient_id = ? LIMIT 1'
            );
            $stmtMed->bind_param('ii', $consultationId, $patientId);
            $stmtMed->execute();
            $medRow = $stmtMed->get_result()->fetch_assoc();
            $stmtMed->close();
            $legacyMedText = trim((string) ($medRow['medication_treatment'] ?? ''));
            if ($legacyMedText === '') {
                throw new RuntimeException(
                    'Missing medications. Add at least one prescription before sending to Pharmacy.'
                );
            }
        }
    }

    $conn->begin_transaction();
    $result = Workflow::execute($conn, $action, $patientId, $user);

    if ($consultationId <= 0 && !empty($result['to_status'])) {
        $consultationId = ReceptionQueue::latestConsultationId($conn, $patientId);
    }

    if ($consultationId > 0 && !empty($result['to_status'])) {
        // Keep it_data.consultation_status in sync on the same connection/transaction.
        $stmt = $conn->prepare('SELECT it_data FROM treatment_records WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('ii', $consultationId, $patientId);
        $stmt->execute();
        $tRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $it = Consultation::getItDataArray($tRow);
        $it['consultation_status'] = $result['to_status'];
        if ($user['role'] === ROLE_DOCTOR && !empty($user['full_name'])) {
            $it['doctor_name'] = $user['full_name'];
        }
        if (in_array($action, ['send_to_pharmacy', 'complete_consultation'], true)) {
            $it['pharmacy'] = array_merge(
                is_array($it['pharmacy'] ?? null) ? $it['pharmacy'] : [],
                [
                    'sent_at' => date('Y-m-d H:i:s'),
                    'sent_by' => (int) ($user['id'] ?? 0),
                    'sent_by_name' => (string) ($user['full_name'] ?? ''),
                    'status' => WORKFLOW_WAITING_FOR_PHARMACY,
                ]
            );
        }
        $json = json_encode($it, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Database error: failed to encode consultation metadata (JSON).');
        }
        $stmt = $conn->prepare('UPDATE treatment_records SET it_data = ? WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('sii', $json, $consultationId, $patientId);
        if (!$stmt->execute()) {
            throw new RuntimeException('SQL error updating consultation status: ' . $stmt->error);
        }
        $stmt->close();

        if ($action === 'mark_printed') {
            $stmt = $conn->prepare('SELECT date_of_consultation FROM treatment_records WHERE id = ?');
            $stmt->bind_param('i', $consultationId);
            $stmt->execute();
            $printRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $consultDate = !empty($printRow['date_of_consultation'])
                ? date('M d, Y', strtotime($printRow['date_of_consultation']))
                : date('M d, Y');

            Consultation::appendPrintHistory($consultationId, [
                'consultation_date' => $consultDate,
                'printed_by'        => $user['full_name'] ?? 'IT Staff',
                'printed_at'        => date('Y-m-d H:i:s'),
                'forms'             => ['itr', 'ekas', 'epress'],
            ]);
        }
    }

    $conn->commit();
    $committed = true;

    $finalStatus = $result['to_status'] ?? null;
    if (in_array($action, ['send_to_pharmacy', 'complete_consultation'], true)) {
        $stmt = $conn->prepare('SELECT status FROM patients WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $statusRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $finalStatus = WorkflowStatus::normalize($statusRow['status'] ?? ($result['to_status'] ?? ''));
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => $result,
        'consultation_id' => $consultationId > 0 ? $consultationId : null,
        'to_status' => $finalStatus,
    ]);
} catch (Throwable $e) {
    if ($conn instanceof mysqli && !$committed) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }

    http_response_code(400);
    $message = $e->getMessage();
    if ($message === '') {
        $message = 'Workflow action failed.';
    }

    // Surface SQL-ish failures clearly for the doctor UI.
    if ($e instanceof mysqli_sql_exception) {
        $message = 'Database/SQL error: ' . $message;
    }

    echo json_encode([
        'success' => false,
        'message' => $message,
        'action' => $action,
        'patient_id' => $patientId,
        'consultation_id' => $consultationId > 0 ? $consultationId : null,
    ]);
} finally {
    if ($conn instanceof mysqli) {
        try {
            $conn->close();
        } catch (Throwable $ignored) {
        }
    }
}
