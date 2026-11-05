<?php
/**
 * Seed Heat Map demo data — patients + consultations + diagnoses across Magallanes barangays.
 *
 * Usage:
 *   php scripts/seed-heatmap-demo.php          # insert demo data
 *   php scripts/seed-heatmap-demo.php --clean   # remove demo data only
 *   php scripts/seed-heatmap-demo.php --verify  # print heat map summary
 *
 * Demo login (existing): records / password
 * Optional demo user created: heatmap / password
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/HeatMap.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';

$clean = in_array('--clean', $argv ?? [], true);
$verify = in_array('--verify', $argv ?? [], true);

$conn = getDBConnection();
ClinicalMaster::ensureSchema($conn);

if ($clean) {
    cleanDemo($conn);
    echo "Removed Heat Map demo patients and consultations.\n";
    $conn->close();
    exit(0);
}

ensureDemoUser($conn);

/** @var array<string, int> barangay => case count */
$distribution = [
    'Poblacion'     => 15,
    'Guiasan'       => 8,
    'Taod-oy'       => 7,
    'Buhang'        => 4,
    'Caloc-an'      => 3,
    'Santo Rosario' => 2,
    'Santo Niño'    => 1,
    'Marcos'        => 0,
];

$diagnosisRotation = ['Dengue Fever', 'Essential Hypertension', 'Type 2 Diabetes Mellitus'];
$dxIds = resolveDiagnosisIds($conn, $diagnosisRotation);

$encodedBy = resolveUserId($conn, 'records') ?? resolveUserId($conn, 'heatmap') ?? 1;
$today = date('Y-m-d');
$seq = 0;
$inserted = 0;

foreach ($distribution as $barangay => $caseCount) {
    for ($i = 1; $i <= $caseCount; $i++) {
        $seq++;
        $patientCode = sprintf('HM-DEMO-%04d', $seq);
        if (patientExists($conn, $patientCode)) {
            continue;
        }

        $purokNum = (($i - 1) % 5) + 1;
        $dxName = $diagnosisRotation[($seq - 1) % count($diagnosisRotation)];
        $dxId = $dxIds[$dxName] ?? reset($dxIds);

        $consultDate = date('Y-m-d', strtotime('-' . (($seq % 20) + 1) . ' days'));
        $address = 'Brgy. ' . $barangay . ', Magallanes, Agusan del Norte';
        $street = 'Purok ' . $purokNum . ', Brgy. ' . $barangay . ', Magallanes, Agusan del Norte';

        $receptionistJson = json_encode([
            'enrollment_extended' => [
                'fields' => [
                    'address_barangay'      => $barangay,
                    'address_municipality'  => MagallanesAddress::municipality(),
                    'address_province'      => MagallanesAddress::province(),
                    'address_street'        => $street,
                ],
                'form_progress' => 100,
            ],
            'form_progress' => 100,
        ], JSON_UNESCAPED_UNICODE);

        $patientId = insertPatient($conn, [
            'patient_id'          => $patientCode,
            'last_name'           => 'Heatmap',
            'first_name'          => 'Demo' . $seq,
            'sex'                 => ($seq % 2 === 0) ? 'Female' : 'Male',
            'birth_date'          => '1990-01-15',
            'residential_address' => $address,
            'contact_number'      => '0999000' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'status'              => 'Printed',
            'encoded_by'          => $encodedBy,
        ]);

        $consultId = insertConsultation($conn, [
            'patient_id'           => $patientId,
            'date_of_consultation' => $consultDate,
            'chief_complaints'     => 'Heat map demo consultation',
            'receptionist_data'    => $receptionistJson,
        ]);

        linkDiagnosis($conn, $consultId, (int) $dxId);
        $inserted++;
    }
}

echo "Heat Map demo seed complete.\n";
echo "Inserted {$inserted} demo consultation(s) with active diagnoses.\n\n";
echo "Login to test:\n";
echo "  URL:      http://localhost/RHUWEB/login.php\n";
echo "  Username: records   Password: password\n";
echo "  Or:       heatmap    Password: password\n";
echo "  Then open Heat Map from the navigation.\n\n";

printDistribution($conn);
printHeatMapSummary($conn);

if ($verify) {
    echo "\n--verify only (no insert)\n";
}

$conn->close();

// -------------------------------------------------------------------------

function cleanDemo(mysqli $conn): void
{
    $ids = [];
    $res = $conn->query("SELECT id FROM patients WHERE patient_id LIKE 'HM-DEMO-%'");
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    if ($ids === []) {
        return;
    }
    $in = implode(',', $ids);
    $conn->query("DELETE FROM consultation_diagnoses WHERE consultation_id IN (SELECT id FROM treatment_records WHERE patient_id IN ({$in}))");
    $conn->query("DELETE FROM treatment_records WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM workflow_logs WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM patients WHERE id IN ({$in})");
}

function ensureDemoUser(mysqli $conn): void
{
    $check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $username = 'heatmap';
    $check->bind_param('s', $username);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        return;
    }
    $check->close();

    $roleId = 1;
    $r = $conn->query("SELECT id FROM roles WHERE slug = 'records_office' LIMIT 1");
    if ($r && ($row = $r->fetch_assoc())) {
        $roleId = (int) $row['id'];
    }

    $hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password
    $fullName = 'Heat Map Demo User';
    $email = 'heatmap@rhu.local';
    $stmt = $conn->prepare(
        'INSERT INTO users (role_id, username, password_hash, full_name, email, is_active) VALUES (?, ?, ?, ?, ?, 1)'
    );
    $stmt->bind_param('issss', $roleId, $username, $hash, $fullName, $email);
    $stmt->execute();
    $stmt->close();
}

function resolveUserId(mysqli $conn, string $username): ?int
{
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

/** @param list<string> $names @return array<string, int> */
function resolveDiagnosisIds(mysqli $conn, array $names): array
{
    $out = [];
    foreach ($names as $name) {
        $stmt = $conn->prepare(
            "SELECT id FROM diagnosis_master WHERE status = 'active' AND diagnosis_name = ? LIMIT 1"
        );
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $out[$name] = (int) $row['id'];
            continue;
        }
        $stmt = $conn->prepare(
            "INSERT INTO diagnosis_master (diagnosis_name, status) VALUES (?, 'active')"
        );
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $out[$name] = (int) $conn->insert_id;
        $stmt->close();
    }
    return $out;
}

function patientExists(mysqli $conn, string $patientId): bool
{
    $stmt = $conn->prepare('SELECT id FROM patients WHERE patient_id = ? LIMIT 1');
    $stmt->bind_param('s', $patientId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

/** @param array<string, mixed> $data */
function insertPatient(mysqli $conn, array $data): int
{
    $stmt = $conn->prepare(
        'INSERT INTO patients (
            patient_id, last_name, first_name, sex, birth_date,
            residential_address, contact_number, status, encoded_by, date_encoded
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param(
        'ssssssssi',
        $data['patient_id'],
        $data['last_name'],
        $data['first_name'],
        $data['sex'],
        $data['birth_date'],
        $data['residential_address'],
        $data['contact_number'],
        $data['status'],
        $data['encoded_by']
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

/** @param array<string, mixed> $data */
function insertConsultation(mysqli $conn, array $data): int
{
    $stmt = $conn->prepare(
        'INSERT INTO treatment_records (
            patient_id, mode_of_transaction, date_of_consultation,
            nature_of_visit, chief_complaints, receptionist_data, created_at
        ) VALUES (?, \'Walk-in\', ?, \'New Consultation/Case\', ?, ?, NOW())'
    );
    $stmt->bind_param(
        'isss',
        $data['patient_id'],
        $data['date_of_consultation'],
        $data['chief_complaints'],
        $data['receptionist_data']
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function linkDiagnosis(mysqli $conn, int $consultationId, int $diagnosisId): void
{
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO consultation_diagnoses (consultation_id, diagnosis_id) VALUES (?, ?)'
    );
    $stmt->bind_param('ii', $consultationId, $diagnosisId);
    $stmt->execute();
    $stmt->close();
}

function printDistribution(mysqli $conn): void
{
    echo "Demo patients by barangay (HM-DEMO-*):\n";
    $sql = "SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tr.receptionist_data, '$.enrollment_extended.fields.address_barangay')), '?') AS barangay,
                COUNT(DISTINCT p.id) AS patients
            FROM patients p
            INNER JOIN treatment_records tr ON tr.patient_id = p.id
            WHERE p.patient_id LIKE 'HM-DEMO-%'
            GROUP BY barangay
            ORDER BY barangay";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        echo '  ' . str_pad($row['barangay'], 14) . $row['patients'] . " patient(s)\n";
    }
}

function printHeatMapSummary(mysqli $conn): void
{
    $range = HeatMap::suggestDateRange($conn);
    $payload = HeatMap::build($conn, $range['from'], $range['to'], null);
    echo "\nActive cases on Heat Map ({$range['from']} to {$range['to']}):\n";
    foreach ($payload['barangays'] as $b) {
        echo sprintf("  %-14s %3d cases  (%s)\n", $b['name'], $b['total_cases'], $b['intensity']);
    }
    echo "\nTotal active cases: " . ($payload['summary']['total_active_cases'] ?? 0) . "\n";
}
