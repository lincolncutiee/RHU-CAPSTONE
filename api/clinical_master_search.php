<?php
/**
 * Offline autocomplete search — diagnosis_master / medication_master.
 * GET: type=diagnosis|medication&q=hyp&limit=20
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
requireLogin();

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$query = (string) ($_GET['q'] ?? '');
$limit = (int) ($_GET['limit'] ?? 20);
$checkOnly = isset($_GET['check']) && (string) $_GET['check'] === '1';

if (!in_array($type, ['diagnosis', 'medication'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type. Use diagnosis or medication.']);
    exit;
}

$conn = getDBConnection();
ClinicalMaster::ensureSchema($conn);

if ($checkOnly) {
    $count = ClinicalMaster::countActive($conn, $type);
    $conn->close();
    $empty = $count === 0;
    echo json_encode([
        'success' => true,
        'type' => $type,
        'empty_dataset' => $empty,
        'count' => $count,
        'message' => $empty
            ? ($type === 'medication'
                ? 'No medication dataset found. Please import the medication dataset.'
                : 'No diagnosis dataset found. Please import the ICD-10 diagnosis dataset.')
            : null,
        'results' => [],
    ]);
    exit;
}

$data = $type === 'medication'
    ? ClinicalMaster::searchMedication($conn, $query, $limit)
    : ClinicalMaster::searchDiagnosis($conn, $query, $limit);

$conn->close();

echo json_encode([
    'success' => true,
    'type' => $type,
    'query' => $query,
    'empty_dataset' => $data['empty_dataset'],
    'message' => $data['message'],
    'results' => $data['results'],
]);
