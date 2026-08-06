<?php
/**
 * Toggle patient physical paper-folder recorded flag.
 * POST: patient_id, physical_recorded (0|1)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/PatientSearch.php';
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

$patientId = (int) ($input['patient_id'] ?? 0);
$recordedRaw = $input['physical_recorded'] ?? null;
$recorded = ((int) $recordedRaw) === 1 ? 1 : 0;

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
    exit;
}

try {
    $conn = getDBConnection();
    PatientSearch::ensurePhysicalRecordedColumn($conn);

    $stmt = $conn->prepare(
        'UPDATE patients SET physical_recorded = ? WHERE id = ? AND is_archived = 0'
    );
    $stmt->bind_param('ii', $recorded, $patientId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 0) {
        throw new Exception('Unable to update physical folder status.');
    }

    // affected_rows can be 0 when value is unchanged — still success if patient exists
    $check = $conn->prepare('SELECT id, physical_recorded FROM patients WHERE id = ? AND is_archived = 0 LIMIT 1');
    $check->bind_param('i', $patientId);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();
    $conn->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'patient_id' => $patientId,
        'physical_recorded' => (int) $row['physical_recorded'] === 1,
        'message' => ((int) $row['physical_recorded'] === 1)
            ? 'Physical folder marked as recorded.'
            : 'Physical folder marked as not recorded.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save physical folder status.']);
}
