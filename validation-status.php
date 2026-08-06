<?php
/**
 * IT Review Queue — consultations awaiting validation (not printing).
 * UI redesigned as EMR validation dashboard; workflow routes unchanged.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/WorkflowStatus.php';
require_once __DIR__ . '/core/ItPrintForms.php';
require_once __DIR__ . '/core/PatientSearch.php';

requireRole([ROLE_IT]);

$pageTitle         = 'IT Review Queue';
$pageSubtitle      = '';
$activePage        = 'validation';
$hidePageTitleBar  = true;
$extraCSS          = [BASE_URL . '/assets/css/it-review-queue.css'];
$extraJS           = [BASE_URL . '/assets/js/it-review-queue.js'];

$conn = getDBConnection();

$pendingAliases = WorkflowStatus::pendingItAliases();
$queueStatuses = array_values(array_unique(array_merge(
    $pendingAliases,
    [
        WORKFLOW_READY_FOR_PRINT,
        WORKFLOW_RETURNED_FOR_CORRECTION,
        WORKFLOW_PRINTED,
    ]
)));

$placeholders = implode(',', array_fill(0, count($queueStatuses), '?'));
$types = str_repeat('s', count($queueStatuses));

$sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
               p.status, p.date_encoded, p.updated_at, p.philhealth_no, p.residential_address,
               u.full_name AS encoded_by,
               t.id AS consultation_id, t.date_of_consultation
        FROM patients p
        LEFT JOIN users u ON u.id = p.encoded_by
        LEFT JOIN treatment_records t ON t.id = (
            SELECT id FROM treatment_records WHERE patient_id = p.id ORDER BY id DESC LIMIT 1
        )
        WHERE p.is_archived = 0
          AND p.status IN ($placeholders)
        ORDER BY
            CASE
                WHEN p.status IN ('" . implode("','", array_map(static fn($s) => $conn->real_escape_string($s), $pendingAliases)) . "') THEN 0
                WHEN p.status = '" . $conn->real_escape_string(WORKFLOW_RETURNED_FOR_CORRECTION) . "' THEN 1
                WHEN p.status = '" . $conn->real_escape_string(WORKFLOW_READY_FOR_PRINT) . "' THEN 2
                ELSE 3
            END,
            p.date_encoded ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$queueStatuses);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary cards (read-only counts — no workflow changes)
$pendingReview = 0;
$pendingPlaceholders = implode(',', array_fill(0, count($pendingAliases), '?'));
$pendingTypes = str_repeat('s', count($pendingAliases));
$ps = $conn->prepare("SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status IN ($pendingPlaceholders)");
$ps->bind_param($pendingTypes, ...$pendingAliases);
$ps->execute();
$pendingReview = (int) ($ps->get_result()->fetch_assoc()['c'] ?? 0);
$ps->close();

$today = date('Y-m-d');
$ready = WORKFLOW_READY_FOR_PRINT;
$returned = WORKFLOW_RETURNED_FOR_CORRECTION;
$printed = WORKFLOW_PRINTED;

$st = $conn->prepare(
    "SELECT COUNT(*) AS c FROM patients
     WHERE is_archived = 0 AND status = ? AND DATE(updated_at) = ?"
);
$st->bind_param('ss', $ready, $today);
$st->execute();
$approvedToday = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$st = $conn->prepare('SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ?');
$st->bind_param('s', $returned);
$st->execute();
$returnedCount = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$st = $conn->prepare(
    "SELECT COUNT(*) AS c FROM patients
     WHERE is_archived = 0 AND status = ? AND DATE(updated_at) = ?"
);
$st->bind_param('ss', $printed, $today);
$st->execute();
$printedToday = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$conn->close();

$submitters = [];
$rows = [];
foreach ($records as $r) {
    $name = ItPrintForms::formatPatientName($r);
    $status = $r['status'];
    $isPending = WorkflowStatus::isPendingItValidation($status);
    $isReturned = $status === WORKFLOW_RETURNED_FOR_CORRECTION;
    $isApproved = $status === WORKFLOW_READY_FOR_PRINT;
    $isPrinted = $status === WORKFLOW_PRINTED;

    if ($isPending) {
        $badgeLabel = 'Pending IT Validation';
        $badgeClass = 'pending';
        $action = 'review';
    } elseif ($isReturned) {
        $badgeLabel = 'Returned';
        $badgeClass = 'returned';
        $action = 'review';
    } elseif ($isApproved) {
        $badgeLabel = 'Approved';
        $badgeClass = 'approved';
        $action = 'view';
    } else {
        $badgeLabel = 'Printed';
        $badgeClass = 'printed';
        $action = 'view';
    }

    $cid = (int) ($r['consultation_id'] ?? 0);
    $reviewUrl = BASE_URL . '/it/validation-workspace.php?id=' . (int) $r['id']
        . ($cid > 0 ? '&consultation=' . $cid : '');
    $viewUrl = BASE_URL . '/patient-folder.php?id=' . (int) $r['id'];

    $encodedBy = trim((string) ($r['encoded_by'] ?? '')) ?: '—';
    if ($encodedBy !== '—') {
        $submitters[$encodedBy] = true;
    }

    $rows[] = [
        'patient_id'        => $r['patient_id'],
        'patient_name'      => $name,
        'barangay'          => PatientSearch::extractBarangay($r['residential_address'] ?? null),
        'consult_date'      => !empty($r['date_of_consultation'])
            ? date('M d, Y', strtotime($r['date_of_consultation']))
            : '—',
        'consult_date_iso'  => !empty($r['date_of_consultation'])
            ? date('Y-m-d', strtotime($r['date_of_consultation']))
            : '',
        'submitted_by'      => $encodedBy,
        'submitted_at'      => !empty($r['date_encoded'])
            ? date('M d, Y g:i A', strtotime($r['date_encoded']))
            : '—',
        'submitted_at_iso'  => !empty($r['date_encoded'])
            ? date('Y-m-d', strtotime($r['date_encoded']))
            : '',
        'status'            => $status,
        'badge_label'       => $badgeLabel,
        'badge_class'       => $badgeClass,
        'action'            => $action,
        'action_url'        => $action === 'review' ? $reviewUrl : $viewUrl,
        'folder_url'        => $viewUrl,
    ];
}
ksort($submitters);

require_once __DIR__ . '/includes/header.php';
?>

<div class="wl-page" id="itReviewQueuePage">
    <div class="wl-summary" aria-label="Queue summary">
        <div class="wl-summary-item wl-s-pending">
            <i class="bi bi-clock-history"></i>
            <span class="wl-s-label">Pending Reviews:</span>
            <strong><?= (int) $pendingReview ?></strong>
        </div>
        <div class="wl-summary-item wl-s-approved">
            <i class="bi bi-check-circle"></i>
            <span class="wl-s-label">Approved Today:</span>
            <strong><?= (int) $approvedToday ?></strong>
        </div>
        <div class="wl-summary-item wl-s-returned">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span class="wl-s-label">Returned:</span>
            <strong><?= (int) $returnedCount ?></strong>
        </div>
        <div class="wl-summary-item wl-s-printed">
            <i class="bi bi-printer"></i>
            <span class="wl-s-label">Printed Today:</span>
            <strong><?= (int) $printedToday ?></strong>
        </div>
    </div>

    <div class="wl-filters" aria-label="Filters">
        <div class="wl-search">
            <i class="bi bi-search"></i>
            <input type="search" id="itqSearch" placeholder="Search Patient…" autocomplete="off" aria-label="Search Patient">
        </div>
        <select id="itqStatus" aria-label="Status">
            <option value="">Status</option>
            <option value="pending">Pending IT Validation</option>
            <option value="approved">Approved</option>
            <option value="returned">Returned</option>
            <option value="printed">Printed</option>
        </select>
        <select id="itqSubmitter" aria-label="Submitted By">
            <option value="">Submitted By</option>
            <?php foreach (array_keys($submitters) as $sub): ?>
                <option value="<?= htmlspecialchars($sub) ?>"><?= htmlspecialchars($sub) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="wl-dates">
            <i class="bi bi-calendar3"></i>
            <input type="date" id="itqDateFrom" aria-label="From date">
            <span>–</span>
            <input type="date" id="itqDateTo" aria-label="To date">
        </div>
        <button type="button" class="wl-reset" id="itqResetFilters">Reset</button>
    </div>

    <section class="wl-panel" aria-label="Consultation worklist">
        <div class="wl-table-wrap">
            <table class="wl-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Barangay</th>
                        <th>Consultation Date</th>
                        <th>Submitted By</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itReviewTableBody">
                    <?php if (empty($rows)): ?>
                        <tr class="wl-empty-row">
                            <td colspan="8">
                                <div class="wl-empty">No consultations in the IT queue.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr
                                class="wl-row"
                                data-patient-id="<?= htmlspecialchars($row['patient_id']) ?>"
                                data-patient-name="<?= htmlspecialchars($row['patient_name']) ?>"
                                data-badge="<?= htmlspecialchars($row['badge_class']) ?>"
                                data-submitter="<?= htmlspecialchars($row['submitted_by']) ?>"
                                data-submitted="<?= htmlspecialchars($row['submitted_at_iso']) ?>"
                            >
                                <td>
                                    <a class="wl-pid" href="<?= htmlspecialchars($row['folder_url']) ?>">
                                        <?= htmlspecialchars($row['patient_id']) ?>
                                    </a>
                                </td>
                                <td class="wl-name"><?= htmlspecialchars($row['patient_name']) ?></td>
                                <td><?= htmlspecialchars($row['barangay']) ?></td>
                                <td><?= htmlspecialchars($row['consult_date']) ?></td>
                                <td><?= htmlspecialchars($row['submitted_by']) ?></td>
                                <td class="wl-time"><?= htmlspecialchars($row['submitted_at']) ?></td>
                                <td>
                                    <span class="wl-badge wl-badge--<?= htmlspecialchars($row['badge_class']) ?>">
                                        <i></i><?= htmlspecialchars($row['badge_label']) ?>
                                    </span>
                                </td>
                                <td class="wl-actions">
                                    <?php if ($row['action'] === 'review'): ?>
                                        <a class="wl-btn wl-btn-primary" href="<?= htmlspecialchars($row['action_url']) ?>">
                                            <i class="bi bi-eye"></i> Review
                                        </a>
                                    <?php else: ?>
                                        <a class="wl-btn wl-btn-secondary" href="<?= htmlspecialchars($row['action_url']) ?>">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="wl-foot" id="itqTableFooter" <?= empty($rows) ? 'hidden' : '' ?>>
            <span id="itqShowing">Showing 0 to 0 of 0 entries</span>
            <div class="wl-pager" id="itqPager"></div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
