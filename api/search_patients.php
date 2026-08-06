<?php
/**
 * Patient Search API — live autocomplete and full search.
 *
 * Query params:
 *   search          — partial match (name, ID, PhilHealth, family serial)
 *   list=all          — return default patient list (FIFO) when no search term
 *   sort              — registry sort (records page only; ignored for autocomplete)
 *   mode=autocomplete — lighter response, limit 20 (default for short queries)
 *   barangay, status, sex, date_encoded — optional filters
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/PatientSearch.php';
requireLogin();

$search = trim($_GET['search'] ?? '');
$mode   = trim($_GET['mode'] ?? '');
$listAll = ($_GET['list'] ?? '') === 'all';
$sort = trim($_GET['sort'] ?? 'encoded_asc');
$barangay = trim($_GET['barangay'] ?? '');
$status = trim($_GET['status'] ?? '');
$sex = trim($_GET['sex'] ?? '');
$dateEncoded = trim($_GET['date_encoded'] ?? '');
$physicalRecorded = $_GET['physical_recorded'] ?? '';
if ($physicalRecorded !== '0' && $physicalRecorded !== '1') {
    $physicalRecorded = '';
}

$allowedSorts = [
    'encoded_asc', 'encoded_desc', 'name_asc', 'name_desc',
    'consult_newest', 'consult_oldest', 'consult_most', 'consult_least',
];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'encoded_asc';
}

$hasSearch = $search !== '';
$hasFilters = $barangay !== '' || $status !== '' || $sex !== '' || $dateEncoded !== '' || $physicalRecorded !== '';

if (!$hasSearch && !$hasFilters && !$listAll) {
    echo json_encode([
        'success'  => true,
        'searched' => false,
        'patients' => [],
        'count'    => 0,
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    PatientSearch::ensurePhysicalRecordedColumn($conn);

    $filters = array_filter([
        'barangay'      => $barangay,
        'status'        => $status,
        'sex'           => $sex,
        'date_encoded'  => $dateEncoded,
    ], static fn($v) => $v !== '' && $v !== null);

    if ($physicalRecorded !== '') {
        $filters['physical_recorded'] = $physicalRecorded;
    }

    $where = PatientSearch::buildWhereClause($search, $filters);

    $isAutocomplete = $mode === 'autocomplete'
        || ($hasSearch && !$hasFilters && !$listAll && mb_strlen($search) <= 4);

    $consultSql = $isAutocomplete ? '' : PatientSearch::consultationSelectSql();

    if ($isAutocomplete) {
        $limit = 20;
        $prefix = $search . '%';
        $orderSql = ' ORDER BY
            CASE
                WHEN p.patient_id LIKE ? THEN 0
                WHEN p.last_name LIKE ? OR p.first_name LIKE ? THEN 1
                ELSE 2
            END,
            p.date_encoded ASC, p.id ASC';
        $orderParams = [$prefix, $prefix, $prefix];
        $orderTypes = 'sss';
    } elseif ($hasSearch && $sort === 'encoded_asc') {
        $limit = 100;
        $prefix = $search . '%';
        $orderSql = ' ORDER BY
            CASE
                WHEN p.patient_id LIKE ? THEN 0
                WHEN p.last_name LIKE ? OR p.first_name LIKE ? THEN 1
                ELSE 2
            END,
            p.date_encoded ASC, p.id ASC';
        $orderParams = [$prefix, $prefix, $prefix];
        $orderTypes = 'sss';
    } else {
        $limit = ($listAll && !$hasSearch) ? 200 : 100;
        $order = PatientSearch::buildOrderClause($sort);
        $orderSql = $order['sql'];
        $orderParams = $order['params'];
        $orderTypes = $order['types'];
    }

    $sql = 'SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name,
                   p.birth_date, p.sex, p.contact_number, p.residential_address,
                   p.philhealth_no, p.family_serial_number,
                   p.status, p.rejection_reason, p.date_encoded, p.physical_recorded,
                   u.full_name AS encoded_by'
        . $consultSql
        . ' FROM patients p
            LEFT JOIN users u ON u.id = p.encoded_by'
        . $where['sql']
        . $orderSql
        . ' LIMIT ' . (int) $limit;

    $params = array_merge($where['params'], $orderParams);
    $types = $where['types'] . $orderTypes;

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = PatientSearch::formatRow($row);
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success'  => true,
        'searched' => true,
        'patients' => $patients,
        'count'    => count($patients),
        'sort'     => $sort,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $msg = 'Search error.';
    $raw = $e->getMessage();
    if (stripos($raw, 'refused') !== false
        || stripos($raw, 'connect') !== false
        || stripos($raw, '2002') !== false
        || stripos($raw, '2006') !== false
    ) {
        $msg = 'Database connection failed. Please start MySQL in XAMPP and try again.';
    }
    echo json_encode(['success' => false, 'message' => $msg]);
}
