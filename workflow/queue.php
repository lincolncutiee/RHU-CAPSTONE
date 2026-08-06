<?php
/**
 * Role-based workflow queue — shows records assigned to the logged-in office.
 * Doctor view: My Consultation Queue UI (display only — same query filters / open URL).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
requireLogin();

$user = currentUser();
$role = $user['role'];

// Receptionist uses the dedicated desk queue module
if ($role === ROLE_RECEPTIONIST) {
    header('Location: ' . BASE_URL . '/reception/queue.php');
    exit;
}

$isDoctor = $role === ROLE_DOCTOR;
$statuses = $isDoctor ? WorkflowStatus::doctorQueueStatuses() : Workflow::getQueueStatuses($role);

$pageTitle    = $isDoctor ? 'Doctor Consultation' : Workflow::getQueueTitle($role);
$pageSubtitle = $isDoctor
    ? 'Select a patient and click Start Consultation'
    : Workflow::getQueueSubtitle($role);
$activePage   = 'workflow';
$hidePageTitleBar = false;
if ($isDoctor) {
    $extraCSS = [BASE_URL . '/assets/css/doctor-queue.css'];
}

require_once __DIR__ . '/../includes/header.php';

$records = [];
if (!empty($statuses)) {
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $types = str_repeat('s', count($statuses));

    $conn = getDBConnection();
    WorkflowStatus::ensurePatientsStatusEnum($conn);

    if ($isDoctor) {
        require_once __DIR__ . '/../core/ReceptionQueue.php';
        ReceptionQueue::ensureSchema($conn);
        $today = ReceptionQueue::today();

        // Waiting for Doctor + today's queue ticket only + FIFO (oldest registration first)
        $sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                       p.sex, p.birth_date, p.residential_address, p.status, p.date_encoded, p.updated_at,
                       u.full_name AS encoded_by,
                       rq.queue_number, rq.created_at AS arrival_at, rq.sent_at,
                       rq.consultation_id AS queue_consultation_id,
                       t.id AS treatment_id, t.consultation_types, t.date_of_consultation
                FROM patients p
                INNER JOIN reception_queue rq
                        ON rq.patient_id = p.id
                       AND rq.queue_date = ?
                       AND rq.desk_status NOT IN ('completed', 'cancelled')
                LEFT JOIN users u ON u.id = p.encoded_by
                LEFT JOIN treatment_records t ON t.id = COALESCE(
                    NULLIF(rq.consultation_id, 0),
                    (SELECT id FROM treatment_records WHERE patient_id = p.id ORDER BY id DESC LIMIT 1)
                )
                WHERE p.is_archived = 0
                  AND p.status IN ({$placeholders})
                ORDER BY
                    p.date_encoded ASC,
                    CASE WHEN rq.queue_number IS NULL OR rq.queue_number = '' THEN 1 ELSE 0 END,
                    rq.queue_number ASC,
                    rq.created_at ASC";

        $stmt = $conn->prepare($sql);
        $bindTypes = 's' . $types;
        $stmt->bind_param($bindTypes, $today, ...$statuses);
    } else {
        $sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name,
                       p.status, p.date_encoded, p.updated_at, u.full_name AS encoded_by
                FROM patients p
                LEFT JOIN users u ON u.id = p.encoded_by
                WHERE p.is_archived = 0 AND p.status IN ({$placeholders})
                ORDER BY p.updated_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$statuses);
    }

    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
}

$grouped = [];
foreach ($records as $r) {
    $grouped[$r['status']][] = $r;
}

/**
 * Display helpers for doctor queue columns (UI only).
 */
function dqFormatName(array $r): string
{
    $middle = trim((string) ($r['middle_name'] ?? ''));
    $suffix = trim((string) ($r['suffix'] ?? ''));
    $name = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '')
        . ($middle !== '' ? ' ' . $middle : ''));
    if ($suffix !== '') {
        $name .= ' ' . $suffix;
    }
    return $name !== ',' ? $name : '—';
}

function dqConsultationType(array $r): string
{
    $raw = $r['consultation_types'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && $decoded) {
            return implode(', ', array_map('strval', $decoded));
        }
        return $raw;
    }
    if (is_array($raw) && $raw) {
        return implode(', ', array_map('strval', $raw));
    }
    return 'General Consultation';
}

function dqBarangay(array $r): string
{
    require_once __DIR__ . '/../core/PatientSearch.php';
    $brgy = PatientSearch::extractBarangay($r['residential_address'] ?? null);
    if ($brgy !== '') {
        return $brgy;
    }
    $addr = trim((string) ($r['residential_address'] ?? ''));
    return $addr !== '' ? $addr : '—';
}

function dqSentAt(array $r): string
{
    $ts = $r['sent_at'] ?? $r['arrival_at'] ?? null;
    if (!$ts) {
        return '—';
    }
    return date('M d, Y g:i A', strtotime((string) $ts));
}

function dqAge(array $r): string
{
    $bd = $r['birth_date'] ?? null;
    if (!$bd) {
        return '—';
    }
    $age = calculateAge($bd);
    return $age !== null ? (string) $age : '—';
}

function dqConsultDate(array $r): string
{
    if (!empty($r['date_of_consultation'])) {
        return date('M d, Y', strtotime((string) $r['date_of_consultation']));
    }
    if (!empty($r['date_encoded'])) {
        return date('M d, Y', strtotime((string) $r['date_encoded']));
    }
    return '—';
}

function dqStartUrl(array $r): string
{
    $cid = (int) ($r['queue_consultation_id'] ?? $r['treatment_id'] ?? 0);
    $url = BASE_URL . '/doctor/consultation.php?id=' . (int) $r['id'];
    if ($cid > 0) {
        $url .= '&consultation_id=' . $cid;
    }
    return $url;
}

function dqArrivalTime(array $r): string
{
    $ts = $r['arrival_at'] ?? $r['updated_at'] ?? null;
    if (!$ts) {
        return '—';
    }
    return date('M d, Y g:i A', strtotime((string) $ts));
}
?>

<?php if ($isDoctor): ?>
<div class="dq-page" id="doctorConsultationQueue">
    <div class="dq-toolbar">
        <div class="dq-toolbar-left">
            <span class="dq-count">
                <strong><?= count($records) ?></strong>
                waiting for doctor (today · FIFO)
            </span>
        </div>
        <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-folder2-open"></i> Patient Records
        </a>
    </div>

    <?php if (empty($records)): ?>
        <div class="dq-empty">
            <i class="bi bi-clipboard2-pulse"></i>
            <h3>Doctor Consultation</h3>
            <p>No patients Waiting for Doctor right now. After IT clicks <strong>Send to Doctor</strong> on Page 7 (EPRESS), the patient appears here.</p>
        </div>
    <?php else: ?>
        <section class="dq-panel">
            <div class="dq-table-wrap">
                <table class="dq-table">
                    <thead>
                        <tr>
                            <th>Queue Number</th>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Age</th>
                            <th>Sex</th>
                            <th>Barangay / Address</th>
                            <th>Date &amp; Time Sent</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $i => $r): ?>
                            <?php
                            $qNum = trim((string) ($r['queue_number'] ?? ''));
                            if ($qNum === '') {
                                $qNum = '—';
                            }
                            ?>
                            <tr>
                                <td data-label="Queue Number">
                                    <span class="dq-qnum"><?= htmlspecialchars($qNum) ?></span>
                                </td>
                                <td data-label="Patient ID">
                                    <span class="dq-pid"><?= htmlspecialchars($r['patient_id'] ?? '—') ?></span>
                                </td>
                                <td class="dq-name" data-label="Patient Name"><?= htmlspecialchars(dqFormatName($r)) ?></td>
                                <td data-label="Age"><?= htmlspecialchars(dqAge($r)) ?></td>
                                <td data-label="Sex"><?= htmlspecialchars($r['sex'] ?? '—') ?></td>
                                <td data-label="Barangay / Address"><?= htmlspecialchars(dqBarangay($r)) ?></td>
                                <td class="dq-time" data-label="Date &amp; Time Sent"><?= htmlspecialchars(dqSentAt($r)) ?></td>
                                <td data-label="Status">
                                    <?= WorkflowStatus::badgeHtml(WorkflowStatus::normalize($r['status'] ?? '')) ?>
                                </td>
                                <td class="text-end" data-label="">
                                    <a href="<?= htmlspecialchars(dqStartUrl($r)) ?>"
                                       class="btn btn-primary btn-sm dq-start">
                                        <i class="bi bi-play-fill"></i> Start Consultation
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="workflow-queue-page">
    <?php if (empty($records)): ?>
        <div class="card border-0 shadow-sm" style="border-radius:var(--radius-lg);">
            <div class="card-body">
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                    <h3 class="empty-state-title">No pending records</h3>
                    <p class="empty-state-text">There are no patient records currently assigned to your office.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($statuses as $status):
            $items = $grouped[$status] ?? [];
            if (empty($items)) continue;
        ?>
        <div class="card border-0 shadow-sm mb-3" style="border-radius:var(--radius-lg);">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold">
                    <span class="badge-status <?= htmlspecialchars(WorkflowStatus::badgeClass($status)) ?> me-2"><?= htmlspecialchars($status) ?></span>
                    <?= count($items) ?> record<?= count($items) !== 1 ? 's' : '' ?>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Encoded By</th>
                                <th>Last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $r):
                                $name = $r['first_name'] . ' ' . ($r['middle_name'] ? $r['middle_name'] . ' ' : '') . $r['last_name'];
                                $action = Workflow::getActionForRoleAndStatus($role, $r['status']);
                            ?>
                            <tr>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars($r['patient_id']) ?></td>
                                <td><?= htmlspecialchars($name) ?></td>
                                <td><?= htmlspecialchars($r['encoded_by'] ?? '—') ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($r['updated_at'])) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/forms/doh-form.php?id=<?= $r['id'] ?>"
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> Open Record
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
