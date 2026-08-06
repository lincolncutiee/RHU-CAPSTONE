<?php
/**
 * Consultations API — list and create consultation records.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Consultation.php';
require_once __DIR__ . '/../core/PatientFolder.php';
requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $patientId = (int) ($_GET['patient_id'] ?? 0);
    if ($patientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
        exit;
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT status FROM patients WHERE id = ? AND is_archived = 0');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }

    $consultations = Consultation::listForPatient($patientId, $patient['status']);
    echo json_encode([
        'success'       => true,
        'consultations' => $consultations,
        'count'         => count($consultations),
    ]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';
    $patientId = (int) ($input['patient_id'] ?? 0);

    if ($patientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
        exit;
    }

    if ($action !== 'create') {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;
    }

    if (!PatientFolder::canCreateConsultation($role)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to create consultations.']);
        exit;
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, status FROM patients WHERE id = ? AND is_archived = 0');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }

    $consultationId = Consultation::create($patientId, (int) $user['id']);
    $consultations = Consultation::listForPatient($patientId, WORKFLOW_DRAFT);

    echo json_encode([
        'success'         => true,
        'message'         => 'New consultation created. Only patient profile information is reused; clinical fields start blank.',
        'consultation_id' => $consultationId,
        'redirect_url'    => BASE_URL . '/patient-folder.php?id=' . $patientId . '&tab=consultations',
        'consultations'   => $consultations,
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
