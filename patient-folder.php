<?php
/**
 * Patient Folder — EMR hub (summary, consultation records, print history).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/PatientFolder.php';
require_once __DIR__ . '/core/WorkflowStatus.php';
require_once __DIR__ . '/core/HistoricalRecord.php';
require_once __DIR__ . '/core/FollowUpReminder.php';
require_once __DIR__ . '/core/Permissions.php';

requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');
$patientId = (int) ($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'summary';

if ($patientId <= 0) {
    die('Invalid patient ID.');
}

$data = PatientFolder::load($patientId);
if (!$data) {
    die('Patient not found.');
}

$patient = $data['patient'];
$summary = $data['summary'];
$consultations = $data['consultations'];
$printHistory = $data['print_history'];
$latestConsultation = $data['latest_consultation'];
$status = $patient['status'];

$validTabs = ['summary', 'consultations', 'follow-ups', 'print-history', 'historical'];
if ($tab === 'profile') {
    $tab = 'consultations';
}
if (!in_array($tab, $validTabs, true)) {
    $tab = 'summary';
}

$canCreateConsultation = PatientFolder::canCreateConsultation($role) && $role !== ROLE_DOCTOR;
$canEditProfile = PatientFolder::canEditProfile($role, $status);
$canPrint = PatientFolder::canPrint($role, $status);
$canReprint = PatientFolder::canReprint($role);
$canAddHistorical = HistoricalRecord::canAdd($role);
$canViewHistorical = HistoricalRecord::canView($role);
$openFormLabel = $canEditProfile ? 'Open Form' : 'View Form';

$followUpTimeline = [];
$fuConn = getDBConnection();
FollowUpReminder::ensureSchema($fuConn);
$followUpTimeline = FollowUpReminder::listForPatient($fuConn, $patientId, 15);
$fuConn->close();

$historicalRecords = [];
$historicalGroups = [];
if ($canViewHistorical) {
    $hrConn = getDBConnection();
    $historicalRecords = HistoricalRecord::listForPatient($hrConn, $patientId);
    $historicalGroups = HistoricalRecord::groupRecordsForDisplay($historicalRecords);
    $hrConn->close();
}

$pageTitle = PatientFolder::formatPatientName($patient);
$pageSubtitle = '';
$activePage = 'records';
$hidePageTitleBar = true;
$hidePatientSearch = true;
$extraCSS = [BASE_URL . '/assets/css/patient-folder.css?v=6'];
if ($canAddHistorical) {
    $extraCSS[] = BASE_URL . '/assets/css/historical-record.css?v=8';
}
$extraJS = [BASE_URL . '/assets/js/patient-folder.js?v=5'];
if ($canAddHistorical) {
    $extraJS[] = BASE_URL . '/assets/js/historical-record.js?v=11';
}

include __DIR__ . '/includes/header.php';
?>

<div class="emr-folder" id="patientFolder" data-patient-id="<?= $patientId ?>">
    <div class="emr-folder-top">
        <a href="<?= BASE_URL ?>/patient-records.php" class="emr-back-link">
            <i class="bi bi-arrow-left"></i> Patient Records
        </a>
        <?php if ($canCreateConsultation || ($canPrint && $latestConsultation) || $canAddHistorical): ?>
        <div class="emr-folder-actions">
            <?php if ($canAddHistorical): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddExistingRecordFolder">
                <i class="bi bi-plus-lg"></i> Add Existing Record
            </button>
            <?php endif; ?>
            <?php if ($canCreateConsultation): ?>
            <button type="button" class="btn btn-primary btn-sm" id="btnNewConsultation">
                <i class="bi bi-plus-lg"></i> New Consultation
            </button>
            <?php endif; ?>
            <?php if ($canPrint && $latestConsultation): ?>
            <a href="<?= BASE_URL ?>/consultation.php?id=<?= $patientId ?>&consultation=<?= (int) $latestConsultation['id'] ?>#print"
               class="btn btn-outline-primary btn-sm">
                <i class="bi bi-printer"></i> Print Latest
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab navigation -->
    <nav class="emr-tabs" aria-label="Patient folder sections">
        <a href="?id=<?= $patientId ?>&tab=summary"
           class="emr-tab<?= $tab === 'summary' ? ' is-active' : '' ?>">
            <i class="bi bi-clipboard2-pulse"></i> Patient Summary
        </a>
        <a href="?id=<?= $patientId ?>&tab=consultations"
           class="emr-tab<?= $tab === 'consultations' ? ' is-active' : '' ?>">
            <i class="bi bi-journal-medical"></i> Consultation Records
            <?php if (count($consultations)): ?>
                <span class="emr-tab-badge"><?= count($consultations) ?></span>
            <?php endif; ?>
        </a>
        <a href="?id=<?= $patientId ?>&tab=follow-ups"
           class="emr-tab<?= $tab === 'follow-ups' ? ' is-active' : '' ?>">
            <i class="bi bi-calendar2-check"></i> Follow-ups
            <?php if (count($followUpTimeline)): ?>
                <span class="emr-tab-badge"><?= count($followUpTimeline) ?></span>
            <?php endif; ?>
        </a>
        <a href="?id=<?= $patientId ?>&tab=print-history"
           class="emr-tab<?= $tab === 'print-history' ? ' is-active' : '' ?>">
            <i class="bi bi-printer"></i> Print History
        </a>
        <?php if ($canViewHistorical): ?>
        <a href="?id=<?= $patientId ?>&tab=historical"
           class="emr-tab<?= $tab === 'historical' ? ' is-active' : '' ?>">
            <i class="bi bi-clock-history"></i> Historical Records
            <?php if (count($historicalRecords)): ?>
                <span class="emr-tab-badge"><?= count($historicalRecords) ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </nav>

    <div class="emr-tab-content">
        <?php if ($tab === 'summary'): ?>
        <?php
        require_once __DIR__ . '/core/PatientSearch.php';
        $summaryBarangay = PatientSearch::extractBarangay($patient['residential_address'] ?? null);
        if ($summaryBarangay === '') {
            $summaryBarangay = '—';
        }
        ?>
        <section class="emr-summary-card emr-summary-card--compact">
            <div class="accordion emr-summary-accordion" id="patientInfoAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="patientInfoHeading">
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#patientInfoCollapse"
                            aria-expanded="false"
                            aria-controls="patientInfoCollapse"
                        >
                            Patient Information
                        </button>
                    </h2>
                    <div
                        id="patientInfoCollapse"
                        class="accordion-collapse collapse"
                        aria-labelledby="patientInfoHeading"
                        data-bs-parent="#patientInfoAccordion"
                    >
                        <div class="accordion-body">
                            <div class="emr-summary-grid emr-summary-grid--details">
                                <div class="emr-summary-item emr-summary-item-wide">
                                    <span class="emr-summary-label">Patient Name</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['full_name']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">PIN</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['patient_id']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Age</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars((string) $summary['age']) ?> years old</span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Sex</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['sex']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Barangay</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summaryBarangay) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Current Status</span>
                                    <span class="emr-summary-value"><?= WorkflowStatus::badgeHtml($status) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">PhilHealth Number</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['philhealth_no']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Date of Birth</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['birth_date']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Civil Status</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['civil_status']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Contact Number</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['contact_number']) ?></span>
                                </div>
                                <div class="emr-summary-item">
                                    <span class="emr-summary-label">Registration Date</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['registration_date']) ?></span>
                                </div>
                                <div class="emr-summary-item emr-summary-item-wide">
                                    <span class="emr-summary-label">Complete Address</span>
                                    <span class="emr-summary-value"><?= htmlspecialchars($summary['address']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php elseif ($tab === 'follow-ups'): ?>
        <section class="emr-followups-section">
            <header class="emr-section-head">
                <h2 class="emr-section-title">Follow-up check-ups &amp; reminders</h2>
                <p class="text-muted small mb-0">Scheduled return visits and SMS/email reminder delivery log.</p>
            </header>
            <?php if ($followUpTimeline === []): ?>
                <div class="emr-empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <p>No follow-up schedules recorded for this patient yet.</p>
                </div>
            <?php else: ?>
                <div class="emr-followup-list">
                    <?php foreach ($followUpTimeline as $block): ?>
                        <?php
                        $f = $block['follow_up'];
                        $msgs = $block['messages'];
                        $status = (string) ($f['status'] ?? 'scheduled');
                        ?>
                        <article class="emr-followup-card">
                            <div class="emr-followup-card__head">
                                <div>
                                    <strong><?= htmlspecialchars(date('F j, Y', strtotime((string) $f['follow_up_date']))) ?></strong>
                                    <span class="badge bg-light text-dark ms-1"><?= htmlspecialchars(ucfirst($status)) ?></span>
                                    <?php if ((int) ($f['send_reminder'] ?? 0) === 1): ?>
                                        <span class="badge bg-primary-subtle text-primary ms-1">Reminders on</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-muted small"><?= htmlspecialchars(ucfirst((string) ($f['channel'] ?? 'sms'))) ?></span>
                            </div>
                            <?php if (!empty($f['instructions'])): ?>
                                <p class="emr-followup-instructions"><?= nl2br(htmlspecialchars((string) $f['instructions'])) ?></p>
                            <?php endif; ?>
                            <?php if ($msgs !== []): ?>
                                <table class="table table-sm emr-followup-msgs mb-0">
                                    <thead>
                                        <tr>
                                            <th>Channel</th>
                                            <th>To</th>
                                            <th>Status</th>
                                            <th>Scheduled</th>
                                            <th>Sent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($msgs as $m): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(strtoupper((string) $m['channel'])) ?></td>
                                                <td><?= htmlspecialchars((string) $m['recipient']) ?></td>
                                                <td>
                                                    <span class="badge text-bg-<?=
                                                        match ($m['status']) {
                                                            'sent' => 'success',
                                                            'failed' => 'danger',
                                                            'skipped' => 'secondary',
                                                            default => 'warning',
                                                        }
                                                    ?>"><?= htmlspecialchars((string) $m['status']) ?></span>
                                                    <?php if (!empty($m['error_message'])): ?>
                                                        <span class="text-danger small d-block"><?= htmlspecialchars((string) $m['error_message']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars(date('M j, g:i A', strtotime((string) $m['scheduled_for']))) ?></td>
                                                <td><?= !empty($m['sent_at']) ? htmlspecialchars(date('M j, g:i A', strtotime((string) $m['sent_at']))) : '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No reminder messages queued (check Admin messaging settings or patient consent).</p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php elseif ($tab === 'consultations'): ?>
        <section class="emr-consultations-section emr-consult-records">
            <?php if (empty($consultations)): ?>
            <div class="emr-empty-state">
                <i class="bi bi-calendar2-plus"></i>
                <h4>No consultation records yet</h4>
                <p>Visits and completed forms for this patient will appear here.</p>
            </div>
            <?php else: ?>
            <div class="accordion emr-visit-accordion" id="consultationRecordsAccordion">
                <?php foreach ($consultations as $i => $consultation):
                    $cid = (int) $consultation['id'];
                    $collapseId = 'visitCollapse' . $cid;
                    $headingId = 'visitHeading' . $cid;
                    $visitForms = $consultation['visit_forms'] ?? [];
                ?>
                <div class="accordion-item emr-visit-item">
                    <h2 class="accordion-header" id="<?= $headingId ?>">
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= $collapseId ?>"
                            aria-expanded="false"
                            aria-controls="<?= $collapseId ?>"
                        >
                            <span class="emr-visit-btn-main">
                                <span class="emr-visit-label">Visit #<?= (int) $consultation['visit_number'] ?></span>
                                <span class="emr-visit-date"><?= htmlspecialchars($consultation['consult_date_display']) ?></span>
                                <span class="emr-visit-doctor"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($consultation['doctor']) ?></span>
                            </span>
                            <span class="emr-visit-btn-meta">
                                <?= WorkflowStatus::badgeHtml($consultation['status']) ?>
                                <span class="emr-visit-form-count"><?= count($visitForms) ?> form<?= count($visitForms) === 1 ? '' : 's' ?></span>
                            </span>
                        </button>
                    </h2>
                    <div
                        id="<?= $collapseId ?>"
                        class="accordion-collapse collapse"
                        aria-labelledby="<?= $headingId ?>"
                        data-bs-parent="#consultationRecordsAccordion"
                    >
                        <div class="accordion-body">
                            <?php if ($consultation['chief_complaint'] || $consultation['diagnosis']): ?>
                            <div class="emr-visit-highlights">
                                <?php if ($consultation['chief_complaint']): ?>
                                <p><strong>Chief Complaint:</strong> <?= htmlspecialchars($consultation['chief_complaint']) ?></p>
                                <?php endif; ?>
                                <?php if ($consultation['diagnosis']): ?>
                                <p><strong>Diagnosis:</strong> <?= htmlspecialchars($consultation['diagnosis']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (empty($visitForms)): ?>
                            <div class="emr-visit-empty">
                                No completed forms for this visit yet.
                            </div>
                            <?php else: ?>
                            <div class="emr-visit-forms-grid">
                                <?php foreach ($visitForms as $form): ?>
                                <article class="emr-visit-form-card">
                                    <div class="emr-visit-form-icon">
                                        <i class="bi bi-file-earmark-medical"></i>
                                    </div>
                                    <div class="emr-visit-form-body">
                                        <h4><?= htmlspecialchars($form['label']) ?></h4>
                                        <p class="text-muted small mb-0">
                                            <?= ($form['kind'] ?? '') === 'profile'
                                                ? 'Permanent patient form'
                                                : 'Completed for this visit' ?>
                                        </p>
                                    </div>
                                    <a href="<?= htmlspecialchars($form['url']) ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <?= htmlspecialchars($openFormLabel) ?>
                                    </a>
                                </article>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <?php elseif ($tab === 'print-history'): ?>
        <section class="emr-print-history-section">
            <?php if (empty($printHistory)): ?>
            <div class="emr-empty-state">
                <i class="bi bi-printer"></i>
                <h4>No print history</h4>
                <p>Printed consultations will appear here after IT completes printing.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table emr-print-table">
                    <thead>
                        <tr>
                            <th>Visit</th>
                            <th>Consultation Date</th>
                            <th>Printed By</th>
                            <th>Printed Date</th>
                            <th>Printed Forms</th>
                            <?php if ($canReprint): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($printHistory as $entry): ?>
                        <tr>
                            <td>#<?= (int) ($entry['visit_number'] ?? 0) ?></td>
                            <td><?= htmlspecialchars($entry['consultation_date'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($entry['printed_by'] ?? '—') ?></td>
                            <td><?= !empty($entry['printed_at']) ? htmlspecialchars(date('M d, Y g:i A', strtotime($entry['printed_at']))) : '—' ?></td>
                            <td>
                                <?php
                                $forms = $entry['forms'] ?? [];
                                if (is_string($forms)) {
                                    $forms = json_decode($forms, true) ?: explode(',', $forms);
                                }
                                foreach ((array) $forms as $form): ?>
                                    <span class="emr-form-chip"><?= strtoupper(htmlspecialchars($form)) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <?php if ($canReprint): ?>
                            <td>
                                <a href="<?= BASE_URL ?>/consultation.php?id=<?= $patientId ?>&consultation=<?= (int) ($entry['consultation_id'] ?? 0) ?>#print"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-printer"></i> Reprint
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php elseif ($tab === 'historical'): ?>
        <section class="emr-consultations-section emr-historical-records">
            <?php if (empty($historicalGroups)): ?>
            <div class="emr-empty-state">
                <i class="bi bi-camera"></i>
                <h4>No historical records yet</h4>
                <p>Photographed old forms will appear here grouped by upload, with each official form available to open.</p>
                <?php if ($canAddHistorical): ?>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="btnAddExistingRecordFolderEmpty">
                    <i class="bi bi-plus-lg"></i> Add Existing Record
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="accordion emr-visit-accordion emr-historical-accordion" id="historicalRecordsAccordion">
                <?php foreach ($historicalGroups as $gi => $group):
                    $collapseId = 'historicalCollapse' . $gi;
                    $headingId = 'historicalHeading' . $gi;
                    $visitForms = $group['forms'] ?? [];
                ?>
                <div class="accordion-item emr-visit-item emr-historical-item">
                    <h2 class="accordion-header" id="<?= $headingId ?>">
                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= $collapseId ?>"
                            aria-expanded="false"
                            aria-controls="<?= $collapseId ?>"
                        >
                            <span class="emr-visit-btn-main">
                                <span class="emr-visit-label emr-historical-label">Photograph Form</span>
                                <span class="emr-visit-date"><?= htmlspecialchars($group['record_date_display']) ?></span>
                                <span class="emr-visit-doctor"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($group['staff_display']) ?></span>
                            </span>
                            <span class="emr-visit-btn-meta">
                                <span class="emr-visit-form-count"><?= (int) $group['form_count'] ?> form<?= (int) $group['form_count'] === 1 ? '' : 's' ?></span>
                            </span>
                        </button>
                    </h2>
                    <div
                        id="<?= $collapseId ?>"
                        class="accordion-collapse collapse"
                        aria-labelledby="<?= $headingId ?>"
                        data-bs-parent="#historicalRecordsAccordion"
                    >
                        <div class="accordion-body">
                            <div class="emr-visit-highlights emr-historical-highlights">
                                <p class="mb-0">
                                    <strong>Uploaded:</strong> <?= htmlspecialchars($group['created_at_display']) ?>
                                    · <strong>By:</strong> <?= htmlspecialchars($group['staff_display']) ?>
                                </p>
                            </div>

                            <div class="emr-visit-forms-grid">
                                <?php foreach ($visitForms as $form): ?>
                                <article class="emr-visit-form-card emr-historical-form-card">
                                    <div class="emr-visit-form-icon<?= !empty($form['has_ocr']) ? ' is-ocr' : '' ?>">
                                        <i class="bi bi-<?= !empty($form['has_ocr']) ? 'file-earmark-text' : 'camera' ?>"></i>
                                    </div>
                                    <div class="emr-visit-form-body">
                                        <h4><?= htmlspecialchars($form['label']) ?></h4>
                                        <p class="text-muted small mb-0"><?= htmlspecialchars($form['subtitle']) ?></p>
                                    </div>
                                    <a href="<?= htmlspecialchars($form['view_url']) ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <?= htmlspecialchars($openFormLabel) ?>
                                    </a>
                                </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</div>

<script>
window.PATIENT_FOLDER = {
    patientId: <?= $patientId ?>,
    baseUrl: <?= json_encode(BASE_URL) ?>,
    canCreateConsultation: <?= $canCreateConsultation ? 'true' : 'false' ?>,
    patientName: <?= json_encode($summary['full_name'] ?? '') ?>,
};
</script>

<?php if ($canAddHistorical): ?>
<script>window.HR_BARANGAYS = <?= json_encode(MagallanesAddress::barangays()) ?>;</script>
<?php include __DIR__ . '/includes/historical-record-modal.php'; ?>
<script>
document.getElementById('btnAddExistingRecordFolderEmpty')?.addEventListener('click', () => {
    if (typeof HistoricalRecordWizard !== 'undefined') {
        HistoricalRecordWizard.open({
            patientId: window.PATIENT_FOLDER?.patientId,
            patientLabel: window.PATIENT_FOLDER?.patientName || '',
        });
    }
});
window.HR_ON_SAVED = function () {
    window.location.href = `${BASE_URL}/patient-folder.php?id=${<?= $patientId ?>}&tab=historical`;
};
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
