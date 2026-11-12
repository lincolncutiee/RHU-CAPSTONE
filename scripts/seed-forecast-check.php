<?php
/**
 * Dummy consultations for checking Case Forecast.
 *
 *   php scripts/seed-forecast-check.php
 *   php scripts/seed-forecast-check.php --clean
 *
 * Patients use patient_id FC-CHK-*. Diagnoses are named "Demo …"
 * so they are easy to spot on the Reports page and easy to remove.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/ReportForecast.php';
require_once __DIR__ . '/../core/ReportFilters.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';

$cleanOnly = in_array('--clean', $argv ?? [], true);
$conn = getDBConnection();
ClinicalMaster::ensureSchema($conn);

if ($cleanOnly) {
    $removed = cleanForecastCheck($conn);
    echo "Removed {$removed} forecast-check patient(s).\n";
    $conn->close();
    exit(0);
}

cleanForecastCheck($conn);

$encodedBy = resolveUserId($conn, 'records') ?? 1;
$anchor = date('Y-m-d');

/** @var array<string, array<int, int>> days-ago => cases that day */
$plans = [
    'Demo Rising Fever' => risingPlan(),
    'Demo New Cough'    => [6 => 2, 5 => 1, 4 => 1],
    'Demo Falling Flu'  => [13 => 2, 12 => 2, 11 => 2, 10 => 2, 9 => 1, 8 => 1, 7 => 1, 6 => 1, 5 => 1],
    'Demo Steady Rash'  => array_fill_keys(range(0, 20), 1),
];

$dxIds = resolveDiagnosisIds($conn, array_keys($plans));
$seq = 0;
$inserted = 0;

foreach ($plans as $name => $byDay) {
    foreach ($byDay as $daysAgo => $count) {
        for ($n = 0; $n < $count; $n++) {
            $seq++;
            $inserted += insertCheckCase($conn, [
                'seq'          => $seq,
                'consult_date' => date('Y-m-d', strtotime("-{$daysAgo} days", strtotime($anchor))),
                'diagnosis'    => $name,
                'diagnosis_id' => $dxIds[$name],
                'encoded_by'   => $encodedBy,
                'barangay'     => barangayFor($name, (int) $daysAgo),
            ]) ? 1 : 0;
        }
    }
}

$payload = ReportForecast::diseaseForecasts($conn, $anchor, new ReportFilters(), 8, 7);
echo "Inserted {$inserted} forecast-check consultation(s).\n\n";
echo "Top diseases likely to increase:\n";
if (($payload['top_increases'] ?? []) === []) {
    echo "  (none)\n";
} else {
    foreach ($payload['top_increases'] as $row) {
        if (!str_starts_with((string) $row['disease'], 'Demo ')) {
            continue;
        }
        echo sprintf(
            "  %s | recent %d | projected %s | change %s | %s | %s\n",
            $row['disease'],
            (int) $row['recent_7'],
            (string) $row['projected_7_mid'],
            (string) $row['pace_change_7'],
            $row['alert_label'],
            $row['confidence_label']
        );
    }
}

echo "\nEarly warning:\n";
foreach ($payload['early_warnings'] as $row) {
    if (!str_starts_with((string) $row['disease'], 'Demo ')) {
        continue;
    }
    $ranked = !empty($row['above_recent_pace']) ? 'also ranked' : 'warning only';
    echo sprintf(
        "  %s | recent %d | projected %s | %s | %s | %s\n",
        $row['disease'],
        (int) $row['recent_7'],
        (string) ($row['projected_7_mid'] ?? 'n/a'),
        $row['alert_label'],
        $row['confidence_label'],
        $ranked
    );
}

echo "\nBarangay monitoring:\n";
foreach ($payload['barangays'] as $bg) {
    echo sprintf("  %s | recent %d | prior %d\n", $bg['barangay'], (int) $bg['recent'], (int) $bg['previous']);
}

echo "\nOpen Reports and look for the Demo diseases.\n";
echo "Remove these records with: php scripts/seed-forecast-check.php --clean\n";
$conn->close();

/** @return array<int, int> */
function risingPlan(): array
{
    $plan = [];
    for ($daysAgo = 56; $daysAgo >= 16; $daysAgo -= 2) {
        $plan[$daysAgo] = 1;
    }
    $recent = [13 => 0, 12 => 0, 11 => 1, 10 => 1, 9 => 1, 8 => 2, 7 => 2, 6 => 3, 5 => 3, 4 => 4, 3 => 4, 2 => 5, 1 => 6, 0 => 7];
    foreach ($recent as $daysAgo => $count) {
        if ($count > 0) {
            $plan[$daysAgo] = $count;
        }
    }

    return $plan;
}

function barangayFor(string $disease, int $daysAgo): string
{
    if ($disease === 'Demo Rising Fever' && $daysAgo <= 6) {
        return 'Poblacion';
    }
    if ($disease === 'Demo Rising Fever') {
        return 'Guiasan';
    }

    return 'Taod-oy';
}

function cleanForecastCheck(mysqli $conn): int
{
    $ids = [];
    $res = $conn->query("SELECT id FROM patients WHERE patient_id LIKE 'FC-CHK-%'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
    }
    $removed = count($ids);
    if ($ids !== []) {
        $in = implode(',', $ids);
        $conn->query("DELETE FROM consultation_diagnoses WHERE consultation_id IN (SELECT id FROM treatment_records WHERE patient_id IN ({$in}))");
        $conn->query("DELETE FROM treatment_records WHERE patient_id IN ({$in})");
        $conn->query("DELETE FROM workflow_logs WHERE patient_id IN ({$in})");
        $conn->query("DELETE FROM patients WHERE id IN ({$in})");
    }

    $names = ['Demo Rising Fever', 'Demo New Cough', 'Demo Falling Flu', 'Demo Steady Rash'];
    foreach ($names as $name) {
        $stmt = $conn->prepare(
            "DELETE dm FROM diagnosis_master dm
             LEFT JOIN consultation_diagnoses cd ON cd.diagnosis_id = dm.id
             WHERE dm.diagnosis_name = ? AND cd.id IS NULL"
        );
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
        }
    }

    return $removed;
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
        $stmt = $conn->prepare("SELECT id FROM diagnosis_master WHERE status = 'active' AND diagnosis_name = ? LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $out[$name] = (int) $row['id'];
            continue;
        }
        $stmt = $conn->prepare("INSERT INTO diagnosis_master (diagnosis_name, status) VALUES (?, 'active')");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $out[$name] = (int) $conn->insert_id;
        $stmt->close();
    }

    return $out;
}

/** @param array<string, mixed> $opts */
function insertCheckCase(mysqli $conn, array $opts): bool
{
    $seq = (int) $opts['seq'];
    $patientCode = sprintf('FC-CHK-%04d', $seq);
    $barangay = (string) $opts['barangay'];
    $address = 'Brgy. ' . $barangay . ', Magallanes, Agusan del Norte';
    $sex = ($seq % 2 === 0) ? 'Female' : 'Male';
    $birthDate = ($seq % 3 === 0) ? '2014-04-02' : '1990-08-18';
    $contact = '0918000' . str_pad((string) ($seq % 10000), 4, '0', STR_PAD_LEFT);
    $last = 'ForecastCheck';
    $first = 'Case' . $seq;
    $status = 'Printed';
    $encodedBy = (int) $opts['encoded_by'];

    $stmt = $conn->prepare(
        'INSERT INTO patients (
            patient_id, last_name, first_name, sex, birth_date,
            residential_address, contact_number, status, encoded_by, date_encoded
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('ssssssssi', $patientCode, $last, $first, $sex, $birthDate, $address, $contact, $status, $encodedBy);
    $stmt->execute();
    $patientId = (int) $conn->insert_id;
    $stmt->close();

    $dxName = (string) $opts['diagnosis'];
    $complaint = 'Forecast check — ' . $dxName;
    $provider = 'Dr. Demo Provider';
    $consultDate = (string) $opts['consult_date'];
    $reception = json_encode([
        'enrollment_extended' => [
            'fields' => [
                'address_barangay'     => $barangay,
                'address_municipality' => MagallanesAddress::municipality(),
                'address_province'     => MagallanesAddress::province(),
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare(
        'INSERT INTO treatment_records (
            patient_id, mode_of_transaction, date_of_consultation,
            nature_of_visit, chief_complaints, diagnosis, healthcare_provider,
            receptionist_data, created_at
        ) VALUES (?, \'Walk-in\', ?, \'New Consultation/Case\', ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('isssss', $patientId, $consultDate, $complaint, $dxName, $provider, $reception);
    $stmt->execute();
    $consultId = (int) $conn->insert_id;
    $stmt->close();

    $dxId = (int) $opts['diagnosis_id'];
    $stmt = $conn->prepare('INSERT INTO consultation_diagnoses (consultation_id, diagnosis_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $consultId, $dxId);
    $stmt->execute();
    $stmt->close();

    return true;
}
