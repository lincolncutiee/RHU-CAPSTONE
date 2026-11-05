<?php
/**
 * Seed Reports & case-forecast demo data (consultations + linked diagnoses).
 *
 * Usage:
 *   php scripts/seed-reports-demo.php           # insert demo data
 *   php scripts/seed-reports-demo.php --clean   # remove RPT-DEMO-* only
 *
 * Demo patients use prefix RPT-DEMO- on patient_id.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/ReportForecast.php';
require_once __DIR__ . '/../core/Reports.php';
require_once __DIR__ . '/../core/ReportFilters.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';

$clean = in_array('--clean', $argv ?? [], true);

$conn = getDBConnection();
ClinicalMaster::ensureSchema($conn);

if ($clean) {
    cleanReportsDemo($conn);
    echo "Removed Reports demo patients (RPT-DEMO-*).\n";
    $conn->close();
    exit(0);
}

$encodedBy = resolveUserId($conn, 'records') ?? 1;
$anchor = date('Y-m-d');

/** Recent 14d vs prior 14d case targets (for forecast trends) */
$diseasePlan = [
    'Dengue Fever'                        => ['recent' => 9, 'prior' => 3],
    'Type 2 Diabetes Mellitus'            => ['recent' => 6, 'prior' => 3],
    'Essential Hypertension'              => ['recent' => 5, 'prior' => 5],
    'Acute Upper Respiratory Infection'    => ['recent' => 4, 'prior' => 6],
    'Diabetic polyneuropathy'             => ['recent' => 3, 'prior' => 1],
];

$dxIds = resolveDiagnosisIds($conn, array_keys($diseasePlan));
$barangays = ['Poblacion', 'Guiasan', 'Taod-oy', 'Buhang', 'Caloc-an', 'Santo Rosario'];
$seq = 0;
$inserted = 0;

foreach ($diseasePlan as $dxName => $plan) {
    $dxId = $dxIds[$dxName] ?? null;
    if ($dxId === null) {
        continue;
    }
    foreach (['recent' => [1, 14], 'prior' => [15, 28]] as $bucket => [$dayMin, $dayMax]) {
        $count = (int) ($plan[$bucket] ?? 0);
        for ($n = 0; $n < $count; $n++) {
            $daysAgo = $dayMin + ($n % max(1, $dayMax - $dayMin + 1));
            $consultDate = date('Y-m-d', strtotime("-{$daysAgo} days", strtotime($anchor)));
            $seq++;
            $inserted += insertDemoCase($conn, [
                'seq'                  => $seq,
                'consult_date'         => $consultDate,
                'diagnosis_name'       => $dxName,
                'diagnosis_id'         => $dxId,
                'encoded_by'           => $encodedBy,
                'barangay'             => $barangays[($seq - 1) % count($barangays)],
            ]) ? 1 : 0;
        }
    }
}

// Extra consults (mixed) for daily trend — last 30 days, 1–3 per day
for ($d = 0; $d < 30; $d++) {
    $perDay = ($d % 7 === 0) ? 3 : (($d % 3 === 0) ? 2 : 1);
    for ($k = 0; $k < $perDay; $k++) {
        $seq++;
        $consultDate = date('Y-m-d', strtotime("-{$d} days", strtotime($anchor)));
        $dxName = array_keys($diseasePlan)[($seq + $d) % count($diseasePlan)];
        $inserted += insertDemoCase($conn, [
            'seq'            => $seq,
            'consult_date'   => $consultDate,
            'diagnosis_name' => $dxName,
            'diagnosis_id'   => $dxIds[$dxName],
            'encoded_by'     => $encodedBy,
            'barangay'       => $barangays[$d % count($barangays)],
        ]) ? 1 : 0;
    }
}

echo "Reports demo seed complete.\n";
echo "Inserted {$inserted} consultation(s) with diagnoses (RPT-DEMO-*).\n\n";

$from = date('Y-m-d', strtotime('-29 days', strtotime($anchor)));
$to = $anchor;
$report = Reports::build($conn, $from, $to, new ReportFilters());
$forecast = $report['case_forecast']['rows'] ?? [];

echo "Sample forecast ({$from} to {$to}, top diseases):\n";
foreach (array_slice($forecast, 0, 5) as $row) {
    echo sprintf(
        "  %-42s last14=%d prior14=%d trend=%s projected~%d\n",
        (string) $row['disease'],
        (int) ($row['recent_14'] ?? 0),
        (int) ($row['previous_14'] ?? 0),
        (string) ($row['trend_label'] ?? ''),
        (int) ($row['projected_mid'] ?? 0),
    );
}

echo "\nOpen Reports, use 30 days preset + Update, then check Case forecast.\n";
echo "To remove: php scripts/seed-reports-demo.php --clean\n";

$conn->close();

// -------------------------------------------------------------------------

function cleanReportsDemo(mysqli $conn): void
{
    $ids = [];
    $res = $conn->query("SELECT id FROM patients WHERE patient_id LIKE 'RPT-DEMO-%'");
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

/** @param array<string, mixed> $opts */
function insertDemoCase(mysqli $conn, array $opts): bool
{
    $seq = (int) $opts['seq'];
    $patientCode = sprintf('RPT-DEMO-%04d', $seq);
    if (patientExists($conn, $patientCode)) {
        return false;
    }

    $barangay = (string) $opts['barangay'];
    $street = 'Purok 1, Brgy. ' . $barangay . ', Magallanes, Agusan del Norte';
    $address = 'Brgy. ' . $barangay . ', Magallanes, Agusan del Norte';

    $ageBucket = $seq % 5;
    $birthDate = match (true) {
        $ageBucket === 0 => '2012-06-01',
        $ageBucket === 1 => '1992-03-15',
        default           => '1958-11-20',
    };
    $sex = ($seq % 2 === 0) ? 'Female' : 'Male';

    $receptionistJson = json_encode([
        'enrollment_extended' => [
            'fields' => [
                'address_barangay'     => $barangay,
                'address_municipality' => MagallanesAddress::municipality(),
                'address_province'     => MagallanesAddress::province(),
                'address_street'       => $street,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $patientId = insertPatient($conn, [
        'patient_id'          => $patientCode,
        'last_name'           => 'ReportsDemo',
        'first_name'          => 'Case' . $seq,
        'sex'                 => $sex,
        'birth_date'          => $birthDate,
        'residential_address' => $address,
        'contact_number'      => '0917123' . str_pad((string) ($seq % 10000), 4, '0', STR_PAD_LEFT),
        'status'              => 'Printed',
        'encoded_by'          => (int) $opts['encoded_by'],
    ]);

    $dxName = (string) $opts['diagnosis_name'];
    $consultId = insertConsultation($conn, [
        'patient_id'           => $patientId,
        'date_of_consultation' => (string) $opts['consult_date'],
        'chief_complaints'     => 'Reports demo — ' . $dxName,
        'diagnosis'            => $dxName,
        'receptionist_data'    => $receptionistJson,
    ]);

    linkDiagnosis($conn, $consultId, (int) $opts['diagnosis_id']);
    return true;
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
    $provider = 'Dr. Demo Provider';
    $stmt = $conn->prepare(
        'INSERT INTO treatment_records (
            patient_id, mode_of_transaction, date_of_consultation,
            nature_of_visit, chief_complaints, diagnosis, healthcare_provider,
            receptionist_data, created_at
        ) VALUES (?, \'Walk-in\', ?, \'New Consultation/Case\', ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param(
        'isssss',
        $data['patient_id'],
        $data['date_of_consultation'],
        $data['chief_complaints'],
        $data['diagnosis'],
        $provider,
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
