<?php
/**
 * Pharmacy workflow actions — start dispensing / mark dispensed.
 * POST: action, patient_id, consultation_id?, remarks?
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/Consultation.php';
require_once __DIR__ . '/../core/Pharmacy.php';
require_once __DIR__ . '/../core/ReceptionQueue.php';

requireLogin();

$user = currentUser();
$user['role'] = Permissions::normalizeRole($user['role'] ?? '');

if ($user['role'] !== ROLE_PHARMACY) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Pharmacy access only.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = trim((string) ($input['action'] ?? ''));
$patientId = (int) ($input['patient_id'] ?? 0);
$consultationId = (int) ($input['consultation_id'] ?? 0);
$remarks = trim((string) ($input['remarks'] ?? ''));

if ($patientId <= 0 || !in_array($action, ['start_dispensing', 'mark_dispensed'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$conn = getDBConnection();
Pharmacy::ensureSchema($conn);

try {
    $conn->begin_transaction();

    $user['_consultation_id'] = $consultationId;
    $user['_remarks'] = $remarks;
    $result = Workflow::execute($conn, $action, $patientId, $user);

    if ($consultationId <= 0) {
        $consultationId = ReceptionQueue::latestConsultationId($conn, $patientId);
    }

    if ($consultationId > 0) {
        Consultation::updateConsultationStatus($consultationId, $patientId, $result['to_status']);

        $now = date('Y-m-d H:i:s');
        $patch = [
            'status' => $result['to_status'],
            'updated_at' => $now,
        ];

        if ($action === 'start_dispensing') {
            $patch['started_at'] = $now;
            $patch['started_by'] = (int) $user['id'];
            $patch['started_by_name'] = (string) ($user['full_name'] ?? '');
            if ($remarks !== '') {
                $patch['remarks'] = $remarks;
            }
        }

        if ($action === 'mark_dispensed') {
            $patch['dispensed_at'] = $now;
            $patch['dispensed_by'] = (int) $user['id'];
            $patch['dispensed_by_name'] = (string) ($user['full_name'] ?? '');
            $patch['remarks'] = $remarks;
        }

        Pharmacy::mergePharmacyMeta($conn, $consultationId, $patientId, $patch);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'to_status' => $result['to_status'],
        'consultation_id' => $consultationId > 0 ? $consultationId : null,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
