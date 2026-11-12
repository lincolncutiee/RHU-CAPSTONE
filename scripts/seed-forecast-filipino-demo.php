<?php
/**
 * Dummy Filipino patients + consultations for Case Forecast dashboard demo.
 *
 *   php scripts/seed-forecast-filipino-demo.php
 *   php scripts/seed-forecast-filipino-demo.php --clean
 *
 * Patient IDs: FC-FIL-*. Safe to remove with --clean.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';
require_once __DIR__ . '/../core/ReportForecast.php';
require_once __DIR__ . '/../core/ReportFilters.php';

$cleanOnly = in_array('--clean', $argv ?? [], true);
$conn = getDBConnection();
ClinicalMaster::ensureSchema($conn);

if ($cleanOnly) {
    $removed = cleanFilipinoForecastDemo($conn);
    echo "Removed {$removed} FC-FIL demo patient(s).\n";
    $conn->close();
    exit(0);
}

cleanFilipinoForecastDemo($conn);

$encodedBy = resolveUserId($conn, 'records')
    ?? resolveUserId($conn, 'it')
    ?? resolveUserId($conn, 'admin')
    ?? 1;

$anchor = date('Y-m-d');

$lastNames = [
    'Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores',
    'Ramos', 'Gonzales', 'Villanueva', 'Castillo', 'Aquino', 'Navarro', 'Dela Cruz',
    'Pascual', 'Lopez', 'Rivera', 'Fernandez', 'Santiago',
];
$firstMale = [
    'Juan', 'Jose', 'Andres', 'Miguel', 'Carlos', 'Ramon', 'Paolo', 'Gabriel',
    'Mark', 'Joshua', 'Christian', 'Daniel', 'Angelo', 'Francis', 'Nathan',
];
$firstFemale = [
    'Maria', 'Ana', 'Lucia', 'Rosa', 'Carmen', 'Sofia', 'Angela', 'Patricia',
    'Andrea', 'Nicole', 'Katrina', 'Michelle', 'Jasmine', 'Camille', 'Isabelle',
];
$barangays = MagallanesAddress::barangays();
if ($barangays === []) {
    $barangays = ['Poblacion', 'Guiasan', 'Taod-oy', 'Buhang', 'Caloc-an'];
}

/** days-ago => cases — shapes that show well on weekly bars + OLS */
$plans = [
    'Dengue Fever' => [
        55 => 1, 50 => 1, 45 => 1, 40 => 2, 35 => 2, 30 => 2, 28 => 1, 25 => 2,
        21 => 2, 18 => 3, 14 => 3, 12 => 2, 10 => 3, 8 => 4, 6 => 4, 4 => 5, 2 => 5, 1 => 6, 0 => 4,
    ],
    'Influenza' => [
        52 => 2, 45 => 2, 38 => 3, 31 => 2, 24 => 3, 20 => 2, 16 => 2, 12 => 3,
        9 => 2, 7 => 2, 5 => 1, 3 => 2, 1 => 1, 0 => 1,
    ],
    'Diarrhea' => [
        48 => 1, 40 => 2, 33 => 1, 26 => 2, 19 => 2, 15 => 1, 11 => 2, 8 => 1,
        6 => 2, 4 => 1, 2 => 2, 0 => 1,
    ],
    'Essential Hypertension' => [
        49 => 1, 42 => 1, 35 => 1, 28 => 1, 21 => 2, 14 => 1, 10 => 1, 7 => 1, 3 => 1, 0 => 1,
    ],
    'Acute Upper Respiratory Infection' => [
        44 => 2, 36 => 1, 29 => 2, 22 => 2, 17 => 1, 13 => 2, 9 => 1, 5 => 2, 2 => 1, 0 => 2,
    ],
];

$dxIds = resolveDiagnosisIds($conn, array_keys($plans));
$seq = 0;
$inserted = 0;

foreach ($plans as $dxName => $byDay) {
    foreach ($byDay as $daysAgo => $count) {
        for ($n = 0; $n < $count; $n++) {
            $seq++;
            $sex = ($seq % 2 === 0) ? 'Female' : 'Male';
            $first = $sex === 'Female'
                ? $firstFemale[($seq - 1) % count($firstFemale)]
                : $firstMale[($seq - 1) % count($firstMale)];
            $last = $lastNames[($seq - 1) % count($lastNames)];
            $barangay = $barangays[($seq - 1) % count($barangays)];
            $birthYear = 1965 + ($seq % 40);
            $birthDate = sprintf('%04d-%02d-%02d', $birthYear, (($seq % 12) + 1), (($seq % 27) + 1));

            $ok = insertFilipinoCase($conn, [
                'seq'          => $seq,
                'last_name'    => $last,
                'first_name'   => $first,
                'sex'          => $sex,
                'birth_date'   => $birthDate,
                'barangay'     => $barangay,
                'consult_date' => date('Y-m-d', strtotime("-{$daysAgo} days", strtotime($anchor))),
                'diagnosis'    => $dxName,
                'diagnosis_id' => $dxIds[$dxName],
                'encoded_by'   => $encodedBy,
            ]);
            if ($ok) {
                $inserted++;
            }
        }
    }
}

echo "Inserted {$inserted} Filipino demo consultation(s) (FC-FIL-*).\n";
echo "Anchor date: {$anchor}\n\n";

$dash = ReportForecast::dashboard($conn, $anchor, new ReportFilters(), 7, 'weekly', 'all', 'chart');
$summary = $dash['summary'] ?? [];
echo "Case Forecast snapshot (All diseases, weekly, next 7 days):\n";
echo '  Historical total:  ' . (int) ($summary['historical_total'] ?? 0) . "\n";
echo '  Forecasted total:  ' . (int) ($summary['forecasted_total'] ?? 0) . "\n";
echo '  Trend:             ' . (string) ($summary['trend'] ?? '') . "\n";
echo '  Early warning:     ' . (string) ($summary['early_warning'] ?? '') . "\n";
echo "\nOpen: Reports → Case Forecast (Ctrl+F5)\n";
echo "Remove with: php scripts/seed-forecast-filipino-demo.php --clean\n";
$conn->close();

function cleanFilipinoForecastDemo(mysqli $conn): int
{
    $ids = [];
    $res = $conn->query("SELECT id FROM patients WHERE patient_id LIKE 'FC-FIL-%'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
    }
    $removed = count($ids);
    if ($ids === []) {
        return 0;
    }
    $in = implode(',', $ids);
    $conn->query("DELETE FROM consultation_medications WHERE consultation_id IN (SELECT id FROM treatment_records WHERE patient_id IN ({$in}))");
    $conn->query("DELETE FROM consultation_diagnoses WHERE consultation_id IN (SELECT id FROM treatment_records WHERE patient_id IN ({$in}))");
    $conn->query("DELETE FROM treatment_records WHERE patient_id IN ({$in})");
    @$conn->query("DELETE FROM workflow_logs WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM patients WHERE id IN ({$in})");

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
        $stmt = $conn->prepare(
            "SELECT id FROM diagnosis_master
             WHERE status = 'active'
               AND (diagnosis_name = ? OR diagnosis_name LIKE ?)
             ORDER BY (diagnosis_name = ?) DESC, id ASC
             LIMIT 1"
        );
        $like = '%' . $name . '%';
        $stmt->bind_param('sss', $name, $like, $name);
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
function insertFilipinoCase(mysqli $conn, array $opts): bool
{
    $seq = (int) $opts['seq'];
    $patientCode = sprintf('FC-FIL-%04d', $seq);
    $barangay = (string) $opts['barangay'];
    $address = 'Purok ' . (($seq % 5) + 1) . ', Brgy. ' . $barangay . ', Magallanes, Agusan del Norte';
    $last = (string) $opts['last_name'];
    $first = (string) $opts['first_name'];
    $sex = (string) $opts['sex'];
    $birthDate = (string) $opts['birth_date'];
    $contact = '09' . str_pad((string) (100000000 + ($seq * 37) % 899999999), 9, '0', STR_PAD_LEFT);
    $status = 'Printed';
    $encodedBy = (int) $opts['encoded_by'];

    $stmt = $conn->prepare(
        'INSERT INTO patients (
            patient_id, last_name, first_name, sex, birth_date,
            residential_address, contact_number, status, encoded_by, date_encoded
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param(
        'ssssssssi',
        $patientCode,
        $last,
        $first,
        $sex,
        $birthDate,
        $address,
        $contact,
        $status,
        $encodedBy
    );
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $patientId = (int) $conn->insert_id;
    $stmt->close();

    $dxName = (string) $opts['diagnosis'];
    $complaint = match (true) {
        stripos($dxName, 'Dengue') !== false => 'Lagnat, sakit sa lawas, pananakit sa ulo',
        stripos($dxName, 'Influenza') !== false => 'Sipon, ubo, lagnat',
        stripos($dxName, 'Diarrhea') !== false => 'Pagtatae, pananakit sa tiyan',
        stripos($dxName, 'Hypertension') !== false => 'Pag-check up sa blood pressure',
        default => 'Konsulta — ' . $dxName,
    };
    $provider = 'Dr. Maria Elena Cruz';
    $consultDate = (string) $opts['consult_date'];
    $reception = json_encode([
        'enrollment_extended' => [
            'fields' => [
                'address_barangay'     => $barangay,
                'address_municipality' => MagallanesAddress::municipality(),
                'address_province'     => MagallanesAddress::province(),
                'address_street'       => $address,
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
