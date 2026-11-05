<?php
/**
 * Historical records API — list, save, duplicate check, create patient from old form.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/HistoricalRecord.php';
require_once __DIR__ . '/../core/Permissions.php';
requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
    HistoricalRecord::ensureSchema($conn);

    if ($method === 'GET') {
        if (!HistoricalRecord::canView($role)) {
            throw new RuntimeException('Access denied.');
        }

        $action = trim($_GET['action'] ?? '');

        if ($action === 'duplicates') {
            if (!HistoricalRecord::canAdd($role)) {
                throw new RuntimeException('Access denied.');
            }

            $patientData = [
                'last_name'        => $_GET['last_name'] ?? '',
                'first_name'       => $_GET['first_name'] ?? '',
                'birth_date'       => $_GET['birth_date'] ?? '',
                'philhealth_no'    => $_GET['philhealth_no'] ?? '',
            ];

            echo json_encode([
                'success'    => true,
                'duplicates' => HistoricalRecord::findPossibleDuplicates($conn, $patientData),
            ]);
            $conn->close();
            exit;
        }

        $patientId = (int) ($_GET['patient_id'] ?? 0);
        if ($patientId <= 0) {
            throw new InvalidArgumentException('Invalid patient_id.');
        }

        $check = $conn->prepare('SELECT id FROM patients WHERE id = ? AND is_archived = 0 LIMIT 1');
        $check->bind_param('i', $patientId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            throw new RuntimeException('Patient not found.');
        }
        $check->close();

        echo json_encode([
            'success' => true,
            'records' => HistoricalRecord::listForPatient($conn, $patientId),
        ]);
        $conn->close();
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    if (!HistoricalRecord::canAdd($role)) {
        throw new RuntimeException('Your role cannot add historical records.');
    }

    if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please take a photo of the old form.');
    }

    $reviewed = null;
    if (!empty($_POST['reviewed_data'])) {
        $decoded = json_decode((string) $_POST['reviewed_data'], true);
        if (is_array($decoded)) {
            $reviewed = $decoded;
        }
    }

    $meta = [
        'record_type'   => $_POST['record_type'] ?? '',
        'record_date'   => $_POST['record_date'] ?? '',
        'remarks'       => $_POST['remarks'] ?? '',
        'ocr_raw_text'  => $_POST['ocr_raw_text'] ?? '',
        'reviewed_data' => $reviewed,
        'force_create'  => ($_POST['force_create'] ?? '0') === '1',
    ];

    $createPatient = ($_POST['create_patient'] ?? '0') === '1';

    if ($createPatient) {
        $patientData = json_decode((string) ($_POST['patient_data'] ?? '{}'), true);
        if (!is_array($patientData)) {
            throw new InvalidArgumentException('Invalid patient data.');
        }

        $result = HistoricalRecord::createPatientFromForm(
            $conn,
            $patientData,
            $meta,
            (string) $_FILES['photo']['tmp_name'],
            (string) ($_FILES['photo']['name'] ?? 'photo.jpg'),
            (int) ($user['id'] ?? 0)
        );

        $conn->close();

        echo json_encode([
            'success' => true,
            'message' => 'Patient created and historical form attached.',
            'result'  => $result,
        ]);
        exit;
    }

    $patientId = (int) ($_POST['patient_id'] ?? 0);
    if ($patientId <= 0) {
        throw new InvalidArgumentException('Invalid patient_id.');
    }

    $check = $conn->prepare('SELECT id FROM patients WHERE id = ? LIMIT 1');
    $check->bind_param('i', $patientId);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        throw new RuntimeException('Patient not found.');
    }
    $check->close();

    $record = HistoricalRecord::savePhotoRecord(
        $conn,
        $patientId,
        $meta,
        (string) $_FILES['photo']['tmp_name'],
        (string) ($_FILES['photo']['name'] ?? 'photo.jpg'),
        (int) ($user['id'] ?? 0)
    );

    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Historical record saved.',
        'record'  => $record,
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Request failed.',
    ]);
}
