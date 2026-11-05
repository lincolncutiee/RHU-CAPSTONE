<?php
/**
 * Seed Doctor Dashboard demo data — queue, consults, pharmacy, weekly trend, surveillance.
 *
 * Usage:
 *   php scripts/seed-doctor-dashboard-demo.php          # insert demo data
 *   php scripts/seed-doctor-dashboard-demo.php --clean  # remove demo data only
 *   php scripts/seed-doctor-dashboard-demo.php --verify # print dashboard summary
 *   php scripts/seed-doctor-dashboard-demo.php --refresh # today queue + follow-ups only (no weekly bulk)
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/DoctorDashboard.php';
require_once __DIR__ . '/../core/FollowUpReminder.php';
require_once __DIR__ . '/../core/HeatMap.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';
require_once __DIR__ . '/../core/ReceptionQueue.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';

$clean = in_array('--clean', $argv ?? [], true);
$verifyOnly = in_array('--verify', $argv ?? [], true);
$refreshOnly = in_array('--refresh', $argv ?? [], true);

$conn = getDBConnection();
ClinicalMaster::ensureSchema($conn);
ReceptionQueue::ensureSchema($conn);
WorkflowStatus::ensurePatientsStatusEnum($conn);

if ($clean) {
    cleanDemo($conn);
    echo "Removed Doctor Dashboard demo patients (DD-DEMO-*).\n";
    $conn->close();
    exit(0);
}

if ($verifyOnly) {
    printSummary($conn);
    $conn->close();
    exit(0);
}

FollowUpReminder::ensureSchema($conn);

$encodedBy = resolveUserId($conn, 'records') ?? 1;
$doctorUserId = resolveUserId($conn, 'doctor') ?? $encodedBy;
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$dxIds = resolveDiagnosisIds($conn, [
    'Dengue Fever',
    'Essential Hypertension',
    'Type 2 Diabetes Mellitus',
    'Acute Upper Respiratory Infection',
]);

$barangays = ['Poblacion', 'Guiasan', 'Taod-oy', 'Buhang', 'Caloc-an', 'Santo Rosario'];
$firstNames = ['Juan', 'Maria', 'Pedro', 'Ana', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Liza', 'Jose'];
$lastNames = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Bautista', 'Castillo'];

$seq = 0;
$nextPin = static function (string $prefix) use (&$seq): string {
    $seq++;
    return sprintf('DD-DEMO-%s-%03d', $prefix, $seq);
};

// --- Weekly historical consults (last 6 days) ---
if (!$refreshOnly) {
$weeklyCounts = [3, 5, 4, 6, 8, 5];
foreach ($weeklyCounts as $dayOffset => $count) {
    $consultDate = date('Y-m-d', strtotime('-' . (6 - $dayOffset) . ' days'));
    for ($i = 1; $i <= $count; $i++) {
        $pin = $nextPin('WK');
        if (patientExists($conn, $pin)) {
            continue;
        }
        $barangay = $barangays[($seq + $i) % count($barangays)];
        $patientId = insertDemoPatient($conn, [
            'patient_id'          => $pin,
            'first_name'          => $firstNames[($seq + $i) % count($firstNames)],
            'last_name'           => $lastNames[($seq + $i) % count($lastNames)],
            'sex'                 => ($i % 2 === 0) ? 'Female' : 'Male',
            'barangay'            => $barangay,
            'status'              => WORKFLOW_PRINTED,
            'encoded_by'          => $encodedBy,
        ]);
        $dxName = array_keys($dxIds)[($seq + $i) % count($dxIds)];
        insertDemoConsultation($conn, [
            'patient_id'           => $patientId,
            'date_of_consultation' => $consultDate,
            'barangay'             => $barangay,
            'consultation_types'   => ['General Consultation'],
            'diagnosis'            => 'Routine follow-up — demo',
            'healthcare_provider'  => 'Dr. Pedro Reyes',
            'medication_treatment' => 'Paracetamol 500mg',
            'diagnosis_id'         => $dxIds[$dxName],
        ]);
    }
}
}

// --- Today's completed consults (desk completed → IT validation) ---
$completedToday = [
    ['pin' => 'DD-DEMO-DONE-01', 'first' => 'Teresa', 'last' => 'Villanueva', 'barangay' => 'Poblacion', 'queue' => 'A006', 'time' => '08:15:00', 'type' => ['General Consultation']],
    ['pin' => 'DD-DEMO-DONE-02', 'first' => 'Ramon', 'last' => 'Aquino', 'barangay' => 'Guiasan', 'queue' => 'A007', 'time' => '09:02:00', 'type' => ['Prenatal']],
    ['pin' => 'DD-DEMO-DONE-03', 'first' => 'Grace', 'last' => 'Navarro', 'barangay' => 'Taod-oy', 'queue' => 'A008', 'time' => '09:45:00', 'type' => ['General Consultation']],
    ['pin' => 'DD-DEMO-DONE-04', 'first' => 'Benjamin', 'last' => 'Lim', 'barangay' => 'Buhang', 'queue' => 'A009', 'time' => '10:20:00', 'type' => ['Follow-up']],
];
foreach ($completedToday as $row) {
    $pin = $row['pin'];
    $patientId = ensureDemoPatient($conn, [
        'patient_id'     => $pin,
        'first_name'     => $row['first'],
        'last_name'      => $row['last'],
        'sex'            => 'Female',
        'barangay'       => $row['barangay'],
        'status'         => WORKFLOW_PENDING_IT_REVIEW,
        'encoded_by'     => $encodedBy,
        'updated_at'     => $now,
    ]);
    $consultId = ensureDemoConsultation($conn, $patientId, [
        'patient_id'           => $patientId,
        'date_of_consultation' => $today,
        'barangay'             => $row['barangay'],
        'consultation_types'   => $row['type'],
        'diagnosis'            => 'Completed consult — demo',
        'healthcare_provider'  => 'Dr. Pedro Reyes',
        'medication_treatment' => 'Vitamins + rest',
        'diagnosis_id'         => $dxIds['Essential Hypertension'],
    ]);
    upsertQueueEntry($conn, [
        'patient_id'       => $patientId,
        'queue_date'       => $today,
        'queue_number'     => $row['queue'],
        'desk_status'      => ReceptionQueue::STATUS_COMPLETED,
        'consultation_id'  => $consultId,
        'sent_at'          => $today . ' ' . $row['time'],
        'done_at'          => $today . ' ' . date('H:i:s', strtotime($row['time'] . ' +25 minutes')),
    ]);
}

// --- Sent to Pharmacy today ---
$sentPharmacy = [
    ['pin' => 'DD-DEMO-PHARM-01', 'first' => 'Cynthia', 'last' => 'Morales', 'barangay' => 'Poblacion', 'queue' => 'A010', 'time' => '10:55:00'],
    ['pin' => 'DD-DEMO-PHARM-02', 'first' => 'Albert', 'last' => 'Domingo', 'barangay' => 'Caloc-an', 'queue' => 'A011', 'time' => '11:10:00'],
    ['pin' => 'DD-DEMO-PHARM-03', 'first' => 'Helen', 'last' => 'Castro', 'barangay' => 'Santo Rosario', 'queue' => 'A012', 'time' => '11:28:00'],
];
foreach ($sentPharmacy as $row) {
    $pin = $row['pin'];
    $patientId = ensureDemoPatient($conn, [
        'patient_id'     => $pin,
        'first_name'     => $row['first'],
        'last_name'      => $row['last'],
        'sex'            => ($row['first'] === 'Albert') ? 'Male' : 'Female',
        'barangay'       => $row['barangay'],
        'status'         => WORKFLOW_WAITING_FOR_PHARMACY,
        'encoded_by'     => $encodedBy,
        'updated_at'     => $now,
    ]);
    $consultId = ensureDemoConsultation($conn, $patientId, [
        'patient_id'           => $patientId,
        'date_of_consultation' => $today,
        'barangay'             => $row['barangay'],
        'consultation_types'   => ['General Consultation'],
        'diagnosis'            => 'URTI — demo prescription',
        'healthcare_provider'  => 'Dr. Pedro Reyes',
        'medication_treatment' => 'Amoxicillin 500mg TID x 7 days',
        'diagnosis_id'         => $dxIds['Acute Upper Respiratory Infection'],
    ]);
    upsertQueueEntry($conn, [
        'patient_id'       => $patientId,
        'queue_date'       => $today,
        'queue_number'     => $row['queue'],
        'desk_status'      => ReceptionQueue::STATUS_COMPLETED,
        'consultation_id'  => $consultId,
        'sent_at'          => $today . ' ' . $row['time'],
        'done_at'          => $today . ' ' . date('H:i:s', strtotime($row['time'] . ' +20 minutes')),
    ]);
}

// --- Waiting for Doctor (today's queue preview) ---
$waiting = [
    ['pin' => 'DD-DEMO-WAIT-01', 'first' => 'Juan', 'last' => 'Dela Cruz', 'barangay' => 'Poblacion', 'queue' => 'A013', 'time' => '11:40:00'],
    ['pin' => 'DD-DEMO-WAIT-02', 'first' => 'Maria', 'last' => 'Santos', 'barangay' => 'Guiasan', 'queue' => 'A014', 'time' => '11:48:00'],
    ['pin' => 'DD-DEMO-WAIT-03', 'first' => 'Pedro', 'last' => 'Reyes', 'barangay' => 'Taod-oy', 'queue' => 'A015', 'time' => '11:55:00'],
    ['pin' => 'DD-DEMO-WAIT-04', 'first' => 'Ana', 'last' => 'Garcia', 'barangay' => 'Buhang', 'queue' => 'A016', 'time' => '12:02:00'],
    ['pin' => 'DD-DEMO-WAIT-05', 'first' => 'Rosa', 'last' => 'Mendoza', 'barangay' => 'Caloc-an', 'queue' => 'A017', 'time' => '12:08:00'],
];
foreach ($waiting as $row) {
    $pin = $row['pin'];
    $patientId = ensureDemoPatient($conn, [
        'patient_id'     => $pin,
        'first_name'     => $row['first'],
        'last_name'      => $row['last'],
        'sex'            => ($row['first'] === 'Pedro' || $row['first'] === 'Juan') ? 'Male' : 'Female',
        'barangay'       => $row['barangay'],
        'status'         => WORKFLOW_WAITING_FOR_DOCTOR,
        'encoded_by'     => $encodedBy,
        'updated_at'     => $now,
    ]);
    $consultId = ensureDemoConsultation($conn, $patientId, [
        'patient_id'           => $patientId,
        'date_of_consultation' => $today,
        'barangay'             => $row['barangay'],
        'consultation_types'   => ['General Consultation'],
        'chief_complaints'     => 'Fever and body weakness — awaiting doctor',
    ]);
    upsertQueueEntry($conn, [
        'patient_id'      => $patientId,
        'queue_date'      => $today,
        'queue_number'    => $row['queue'],
        'desk_status'     => ReceptionQueue::STATUS_WAITING,
        'consultation_id' => $consultId,
        'sent_at'         => $today . ' ' . $row['time'],
    ]);
}

seedDemoFollowUps($conn, $doctorUserId, $today, $encodedBy, $dxIds);

echo "Doctor Dashboard demo seed complete.\n\n";
echo "Login as doctor and open Dashboard:\n";
echo "  URL:      http://localhost/RHUWEB/login.php\n";
echo "  Username: doctor    Password: password\n\n";

printSummary($conn);
$conn->close();

// -------------------------------------------------------------------------

function cleanDemo(mysqli $conn): void
{
    $ids = [];
    $res = $conn->query("SELECT id FROM patients WHERE patient_id LIKE 'DD-DEMO-%'");
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    if ($ids === []) {
        return;
    }
    $in = implode(',', $ids);
    $conn->query("DELETE FROM patient_reminder_messages WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM patient_follow_ups WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM reception_queue WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM consultation_diagnoses WHERE consultation_id IN (SELECT id FROM treatment_records WHERE patient_id IN ({$in}))");
    $conn->query("DELETE FROM treatment_records WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM workflow_logs WHERE patient_id IN ({$in})");
    $conn->query("DELETE FROM patients WHERE id IN ({$in})");
}

function printSummary(mysqli $conn): void
{
    $payload = DoctorDashboard::load($conn);
    $stats = $payload['stats'];

    echo "Doctor Dashboard summary (today):\n";
    echo '  Today\'s Consults:   ' . $stats['todays_consults'] . "\n";
    echo '  Waiting in Queue:   ' . $stats['waiting_in_queue'] . "\n";
    echo '  Sent to Pharmacy:   ' . $stats['sent_to_pharmacy'] . "\n";
    echo '  Completed Today:    ' . $stats['completed_today'] . "\n";
    echo '  Waiting preview:    ' . count($payload['waiting']) . " patient(s)\n";
    echo '  Recent activity:    ' . count($payload['recent']) . " row(s)\n";
    echo '  Follow-ups (7d):    ' . count($payload['follow_ups']) . " row(s)\n";

    echo "\nWeekly consultations:\n";
    foreach ($payload['weekly'] as $day) {
        echo '  ' . str_pad($day['label'], 4) . ' (' . $day['short'] . '): ' . $day['count'] . "\n";
    }

    $surv = $payload['surveillance'];
    echo "\nDisease surveillance (this week):\n";
    echo '  Active cases:       ' . $surv['total_cases'] . "\n";
    echo '  Barangays affected: ' . $surv['affected_barangays'] . "\n";
    foreach ($surv['top_barangays'] as $bg) {
        echo '    - ' . $bg['name'] . ': ' . $bg['count'] . "\n";
    }

    echo "\nDemo PIN prefix: DD-DEMO-*\n";
    echo "Remove with: php scripts/seed-doctor-dashboard-demo.php --clean\n";
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
function insertDemoPatient(mysqli $conn, array $data): int
{
    $address = 'Brgy. ' . $data['barangay'] . ', Magallanes, Agusan del Norte';
    $updatedAt = $data['updated_at'] ?? date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        'INSERT INTO patients (
            patient_id, last_name, first_name, sex, birth_date,
            residential_address, contact_number, status, encoded_by, date_encoded, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
    );
    $birth = '1992-06-15';
    $contact = '0998' . substr(md5((string) $data['patient_id']), 0, 7);
    $stmt->bind_param(
        'ssssssssis',
        $data['patient_id'],
        $data['last_name'],
        $data['first_name'],
        $data['sex'],
        $birth,
        $address,
        $contact,
        $data['status'],
        $data['encoded_by'],
        $updatedAt
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

/** @param array<string, mixed> $data */
function insertDemoConsultation(mysqli $conn, array $data): int
{
    $street = 'Purok ' . ((($data['patient_id'] ?? 1) % 5) + 1) . ', Brgy. ' . $data['barangay'] . ', Magallanes, Agusan del Norte';
    $receptionistJson = json_encode([
        'enrollment_extended' => [
            'fields' => [
                'address_barangay'     => $data['barangay'],
                'address_municipality' => MagallanesAddress::municipality(),
                'address_province'     => MagallanesAddress::province(),
                'address_street'       => $street,
            ],
            'form_progress' => 100,
        ],
        'form_progress' => 100,
    ], JSON_UNESCAPED_UNICODE);

    $typesJson = json_encode($data['consultation_types'] ?? ['General Consultation'], JSON_UNESCAPED_UNICODE);
    $chief = $data['chief_complaints'] ?? 'Demo consultation';
    $diagnosis = $data['diagnosis'] ?? '';
    $provider = $data['healthcare_provider'] ?? '';
    $meds = $data['medication_treatment'] ?? '';

    $stmt = $conn->prepare(
        'INSERT INTO treatment_records (
            patient_id, mode_of_transaction, date_of_consultation,
            nature_of_visit, consultation_types, chief_complaints,
            diagnosis, medication_treatment, healthcare_provider,
            receptionist_data, created_at, updated_at
        ) VALUES (?, \'Walk-in\', ?, \'New Consultation/Case\', ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->bind_param(
        'isssssss',
        $data['patient_id'],
        $data['date_of_consultation'],
        $typesJson,
        $chief,
        $diagnosis,
        $meds,
        $provider,
        $receptionistJson
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();

    if (!empty($data['diagnosis_id'])) {
        $dxStmt = $conn->prepare(
            'INSERT IGNORE INTO consultation_diagnoses (consultation_id, diagnosis_id) VALUES (?, ?)'
        );
        $dxId = (int) $data['diagnosis_id'];
        $dxStmt->bind_param('ii', $id, $dxId);
        $dxStmt->execute();
        $dxStmt->close();
    }

    return $id;
}

/** @param array<string, mixed> $data */
/** @param array<string, mixed> $data */
function ensureDemoPatient(mysqli $conn, array $data): int
{
    if (!patientExists($conn, $data['patient_id'])) {
        return insertDemoPatient($conn, $data);
    }

    $stmt = $conn->prepare(
        'UPDATE patients SET first_name = ?, last_name = ?, sex = ?, residential_address = ?,
                status = ?, updated_at = ? WHERE patient_id = ?'
    );
    $address = 'Brgy. ' . $data['barangay'] . ', Magallanes, Agusan del Norte';
    $updatedAt = $data['updated_at'] ?? date('Y-m-d H:i:s');
    $stmt->bind_param(
        'sssssss',
        $data['first_name'],
        $data['last_name'],
        $data['sex'],
        $address,
        $data['status'],
        $updatedAt,
        $data['patient_id']
    );
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT id FROM patients WHERE patient_id = ? LIMIT 1');
    $stmt->bind_param('s', $data['patient_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['id'] ?? 0);
}

/** @param array<string, mixed> $data */
function ensureDemoConsultation(mysqli $conn, int $patientId, array $data): int
{
    $stmt = $conn->prepare(
        'SELECT id FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $data['patient_id'] = $patientId;
        return insertDemoConsultation($conn, $data);
    }

    $consultId = (int) $existing['id'];
    $typesJson = json_encode($data['consultation_types'] ?? ['General Consultation'], JSON_UNESCAPED_UNICODE);
    $chief = $data['chief_complaints'] ?? 'Demo consultation';
    $diagnosis = $data['diagnosis'] ?? '';
    $provider = $data['healthcare_provider'] ?? '';
    $meds = $data['medication_treatment'] ?? '';
    $date = $data['date_of_consultation'];

    $stmt = $conn->prepare(
        'UPDATE treatment_records SET
            date_of_consultation = ?, consultation_types = ?, chief_complaints = ?,
            diagnosis = ?, medication_treatment = ?, healthcare_provider = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->bind_param('ssssssi', $date, $typesJson, $chief, $diagnosis, $meds, $provider, $consultId);
    $stmt->execute();
    $stmt->close();

    if (!empty($data['diagnosis_id'])) {
        $dxStmt = $conn->prepare(
            'INSERT IGNORE INTO consultation_diagnoses (consultation_id, diagnosis_id) VALUES (?, ?)'
        );
        $dxId = (int) $data['diagnosis_id'];
        $dxStmt->bind_param('ii', $consultId, $dxId);
        $dxStmt->execute();
        $dxStmt->close();
    }

    return $consultId;
}

/** @param array<string, mixed> $data */
function upsertQueueEntry(mysqli $conn, array $data): void
{
    $doneAt = $data['done_at'] ?? null;
    $sentAt = $data['sent_at'] ?? date('Y-m-d H:i:s');
    $consultationId = (int) ($data['consultation_id'] ?? 0);
    $patientId = (int) $data['patient_id'];
    $queueDate = $data['queue_date'];

    $stmt = $conn->prepare(
        'SELECT id FROM reception_queue WHERE patient_id = ? AND queue_date = ? LIMIT 1'
    );
    $stmt->bind_param('is', $patientId, $queueDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int) $row['id'];
        $stmt = $conn->prepare(
            'UPDATE reception_queue SET queue_number = ?, desk_status = ?, consultation_id = ?,
                    sent_at = ?, done_at = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param(
            'ssissi',
            $data['queue_number'],
            $data['desk_status'],
            $consultationId,
            $sentAt,
            $doneAt,
            $id
        );
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO reception_queue (
            patient_id, queue_date, queue_number, desk_status,
            consultation_id, sent_at, done_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $stmt->bind_param(
        'isssiss',
        $patientId,
        $queueDate,
        $data['queue_number'],
        $data['desk_status'],
        $consultationId,
        $sentAt,
        $doneAt
    );
    $stmt->execute();
    $stmt->close();
}

/** @param array<string, int> $dxIds */
function seedDemoFollowUps(mysqli $conn, int $doctorUserId, string $today, int $encodedBy, array $dxIds): void
{
    $rows = [
        ['pin' => 'DD-DEMO-FU-01', 'first' => 'Lorna', 'last' => 'Padilla', 'barangay' => 'Poblacion', 'days' => 1],
        ['pin' => 'DD-DEMO-FU-02', 'first' => 'Victor', 'last' => 'Salazar', 'barangay' => 'Guiasan', 'days' => 2],
        ['pin' => 'DD-DEMO-FU-03', 'first' => 'Nina', 'last' => 'Ocampo', 'barangay' => 'Buhang', 'days' => 3],
        ['pin' => 'DD-DEMO-FU-04', 'first' => 'Felix', 'last' => 'Aguilar', 'barangay' => 'Taod-oy', 'days' => 5],
    ];

    foreach ($rows as $row) {
        $followDate = date('Y-m-d', strtotime('+' . (int) $row['days'] . ' days', strtotime($today)));
        $patientId = ensureDemoPatient($conn, [
            'patient_id' => $row['pin'],
            'first_name' => $row['first'],
            'last_name'  => $row['last'],
            'sex'        => ($row['first'] === 'Victor' || $row['first'] === 'Felix') ? 'Male' : 'Female',
            'barangay'   => $row['barangay'],
            'status'     => WORKFLOW_PRINTED,
            'encoded_by' => $encodedBy,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $demoEmail = strtolower($row['first']) . '.demo@example.com';
        $stmtMail = $conn->prepare(
            'UPDATE patients SET reminder_email_opt_in = 1, reminder_sms_opt_in = 0, patient_email = ? WHERE id = ?'
        );
        $stmtMail->bind_param('si', $demoEmail, $patientId);
        $stmtMail->execute();
        $stmtMail->close();

        $consultId = ensureDemoConsultation($conn, $patientId, [
            'patient_id'           => $patientId,
            'date_of_consultation' => $today,
            'barangay'             => $row['barangay'],
            'consultation_types'   => ['Follow-up Visit'],
            'diagnosis'            => 'Hypertension follow-up — demo',
            'healthcare_provider'  => 'Dr. Pedro Reyes',
            'medication_treatment' => 'Continue maintenance meds',
            'diagnosis_id'         => $dxIds['Essential Hypertension'] ?? null,
        ]);

        $instructions = 'Return for blood pressure check and refill. Demo follow-up.';
        $stmt = $conn->prepare(
            'INSERT INTO patient_follow_ups
                (patient_id, consultation_id, follow_up_date, instructions, channel, send_reminder, status, created_by)
             VALUES (?, ?, ?, ?, \'email\', 0, \'scheduled\', ?)
             ON DUPLICATE KEY UPDATE
                follow_up_date = VALUES(follow_up_date),
                instructions = VALUES(instructions),
                status = \'scheduled\',
                updated_at = NOW()'
        );
        $stmt->bind_param('iissi', $patientId, $consultId, $followDate, $instructions, $doctorUserId);
        $stmt->execute();
        $stmt->close();
    }
}
