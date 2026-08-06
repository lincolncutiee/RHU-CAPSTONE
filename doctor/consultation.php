<?php
/**
 * Doctor Consultation Workspace — clinical entry only (not Patient Encoding).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/PatientSearch.php';
require_once __DIR__ . '/../core/ReceptionQueue.php';
require_once __DIR__ . '/../core/Consultation.php';

requireLogin();
requireRole([ROLE_DOCTOR]);

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');
$patientId = (int) ($_GET['id'] ?? 0);
$consultationId = (int) ($_GET['consultation_id'] ?? 0);

if ($patientId <= 0) {
    header('Location: ' . BASE_URL . '/workflow/queue.php');
    exit;
}

$conn = getDBConnection();
WorkflowStatus::ensurePatientsStatusEnum($conn);
$stmt = $conn->prepare('SELECT * FROM patients WHERE id = ? AND is_archived = 0');
$stmt->bind_param('i', $patientId);
$stmt->execute();
$patientRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patientRow) {
    $conn->close();
    die('Patient not found.');
}

$patientStatus = WorkflowStatus::normalize($patientRow['status'] ?? WORKFLOW_DRAFT);

// Resolve the consultation visit for this workspace (current / requested only).
if ($consultationId > 0) {
    $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE id = ? AND patient_id = ?');
    $stmt->bind_param('ii', $consultationId, $patientId);
    $stmt->execute();
    $crow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$crow) {
        $conn->close();
        die('Consultation not found.');
    }
    $consultationId = (int) $crow['id'];
} else {
    $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $crow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $consultationId = $crow ? (int) $crow['id'] : 0;
}

ReceptionQueue::ensureSchema($conn);
require_once __DIR__ . '/../core/ClinicalMaster.php';
ClinicalMaster::ensureSchema($conn);
$day = ReceptionQueue::today();
$qstmt = $conn->prepare(
    'SELECT queue_number FROM reception_queue WHERE patient_id = ? AND queue_date = ? LIMIT 1'
);
$qstmt->bind_param('is', $patientId, $day);
$qstmt->execute();
$qrow = $qstmt->get_result()->fetch_assoc();
$qstmt->close();
$conn->close();
$queueNumber = $qrow['queue_number'] ?? null;

$middle = trim($patientRow['middle_name'] ?? '');
$summaryName = trim(($patientRow['last_name'] ?? '') . ', ' . ($patientRow['first_name'] ?? '') . ($middle !== '' ? ' ' . $middle : ''));
$summaryId = $patientRow['patient_id'] ?? '—';
$summarySex = $patientRow['sex'] ?? '—';
$age = calculateAge($patientRow['birth_date'] ?? null);
$summaryAge = $age !== null ? $age . ' years old' : '—';
$summaryAddress = trim((string) ($patientRow['residential_address'] ?? '')) ?: '—';
$summaryBarangay = PatientSearch::extractBarangay($patientRow['residential_address'] ?? null) ?: '—';
$summaryPhilhealth = $patientRow['philhealth_no'] ?: '—';
$summaryContact = $patientRow['contact_number'] ?: '—';

$canEditConsultation = in_array($patientStatus, [WORKFLOW_WAITING_FOR_DOCTOR, WORKFLOW_PENDING_DOCTOR], true);

// Prefill Chief Complaint from this visit's intake encoding (no DB write on open).
$intakeChiefComplaint = '';
if ($consultationId > 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'SELECT chief_complaints FROM treatment_records WHERE id = ? AND patient_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $consultationId, $patientId);
    $stmt->execute();
    $ccRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    $intakeChiefComplaint = trim((string) ($ccRow['chief_complaints'] ?? ''));
}

$pageTitle = 'Doctor Consultation';
$pageSubtitle = '';
$activePage = 'workflow';
$hidePageTitleBar = true;
$extraCSS = [
    BASE_URL . '/assets/css/doctor-consultation.css?v=' . ((int) @filemtime(__DIR__ . '/../assets/css/doctor-consultation.css') ?: 1),
];
$extraJS = [
    BASE_URL . '/assets/js/clinical-autocomplete.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/clinical-autocomplete.js') ?: 1),
    BASE_URL . '/assets/js/doctor-consultation.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/doctor-consultation.js') ?: 1),
];

include __DIR__ . '/../includes/header.php';

require __DIR__ . '/../forms/partials/doctor-consultation-form.php';
?>
<script>
window.DOCTOR_CONSULTATION_CFG = {
    baseUrl: <?= json_encode(BASE_URL) ?>,
    patientId: <?= (int) $patientId ?>,
    consultationId: <?= (int) $consultationId ?>,
    canEdit: <?= $canEditConsultation ? 'true' : 'false' ?>,
    patientStatus: <?= json_encode($patientStatus) ?>,
    folderUrl: <?= json_encode(BASE_URL . '/patient-folder.php?id=' . $patientId . '&tab=consultations') ?>,
};
</script>
<?php
include __DIR__ . '/../includes/footer.php';
