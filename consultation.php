<?php
/**
 * Consultation view — ITR, EKAS, EPRESS for a single visit.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/PatientFolder.php';
require_once __DIR__ . '/core/Consultation.php';
require_once __DIR__ . '/core/ItPrintForms.php';
require_once __DIR__ . '/core/Workflow.php';
require_once __DIR__ . '/core/WorkflowStatus.php';
require_once __DIR__ . '/core/PaperFormRenderer.php';

requireLogin();

$user = currentUser();
$role = $user['role'] ?? '';
$patientId = (int) ($_GET['id'] ?? 0);
$consultationId = (int) ($_GET['consultation'] ?? 0);

if ($patientId <= 0 || $consultationId <= 0) {
    die('Invalid patient or consultation ID.');
}

$data = ItPrintForms::loadRecord($patientId, $consultationId);
if (empty($data['patient']) || empty($data['treatment']['id'])) {
    die('Consultation not found.');
}

$patient = $data['patient'];
$treatment = $data['treatment'];
$extended = $data['extended'];
$summary = PatientFolder::buildSummary($patient, $extended);

$consultationStatus = Consultation::resolveStatus($treatment, $patient['status']);
$itMeta = Consultation::parseItData($treatment);
$visitNumber = $itMeta['visit_number'] ?: 1;

$canEdit = PatientFolder::canEditConsultation($role, $consultationStatus);
$layoutTestMode = ItPrintForms::isLayoutTestMode();
// Printing is IT-only (layout test mode must not expose Print Forms to Records/Doctor)
$canPrint = Workflow::canPrint($role, $consultationStatus)
    || ($layoutTestMode && $role === ROLE_IT);
$isIt = $role === ROLE_IT;

// Validation happens in dedicated workspace — keep consultation page for printing only
if ($isIt && WorkflowStatus::isPendingItValidation($patient['status'])) {
    header('Location: ' . BASE_URL . '/it/validation-workspace.php?id=' . $patientId
        . ($consultationId > 0 ? '&consultation=' . $consultationId : ''));
    exit;
}

$consultForms = ['itr', 'ekas', 'epress'];
$bundles = ItPrintForms::bundles();
$lockAll = !$canEdit;

$pageTitle = 'Visit #' . $visitNumber . ' — ' . PatientFolder::formatPatientName($patient);
$pageSubtitle = $summary['patient_id'] . ' · ' . ($summary['consult_date_display'] ?? date('M d, Y'));
$activePage = $isIt ? 'it-print' : 'records';
$hidePatientSearch = true;
$hidePageTitleBar = true;
$extraCSS = [
    BASE_URL . '/assets/css/paper-form.css',
    BASE_URL . '/assets/css/patient-folder.css',
    BASE_URL . '/assets/css/it-print.css',
];
$extraJS = [
    BASE_URL . '/assets/js/it-folder.js',
    BASE_URL . '/assets/js/patient-folder.js',
];

$consultDate = !empty($treatment['date_of_consultation'])
    ? date('M d, Y', strtotime($treatment['date_of_consultation']))
    : '—';

$folderUrl = BASE_URL . '/patient-folder.php?id=' . $patientId;
// Doctor clinical editing is only via Start Consultation → workspace.
// "Edit" / continue from folder goes to original RHU forms (read-only for doctors).
$editUrl = BASE_URL . '/forms/doh-form.php?id=' . $patientId . '&consultation_id=' . $consultationId;
$workspaceUrl = BASE_URL . '/doctor/consultation.php?id=' . $patientId . '&consultation_id=' . $consultationId;
$showDoctorWorkspace = $role === ROLE_DOCTOR
    && in_array(WorkflowStatus::normalize($consultationStatus), [WORKFLOW_WAITING_FOR_DOCTOR, WORKFLOW_PENDING_DOCTOR, 'Pending Doctor'], true);

include __DIR__ . '/includes/header.php';
?>

<div class="emr-consultation-page" id="consultationPage">
    <header class="emr-folder-header emr-consultation-header">
        <div class="emr-folder-header-main">
            <a href="<?= htmlspecialchars($folderUrl) ?>&tab=consultations" class="emr-back-link">
                <i class="bi bi-arrow-left"></i> Back to Patient Folder
            </a>
            <div class="emr-patient-identity">
                <div>
                    <h2 class="emr-patient-name">Visit #<?= (int) $visitNumber ?></h2>
                    <div class="emr-patient-meta">
                        <span><strong><?= htmlspecialchars($summary['full_name']) ?></strong></span>
                        <span><?= htmlspecialchars($summary['patient_id']) ?></span>
                        <span><?= htmlspecialchars($consultDate) ?></span>
                    </div>
                </div>
            </div>
            <div class="emr-header-status">
                <?= WorkflowStatus::badgeHtml($consultationStatus) ?>
            </div>
        </div>
        <div class="emr-quick-actions">
            <?php if ($showDoctorWorkspace): ?>
            <a href="<?= htmlspecialchars($workspaceUrl) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-play-fill"></i> Open Consultation Workspace
            </a>
            <?php elseif ($canEdit && $role !== ROLE_DOCTOR): ?>
            <a href="<?= htmlspecialchars($editUrl) ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil"></i> Edit Consultation
            </a>
            <?php endif; ?>
            <?php if ($canPrint): ?>
            <a href="#consultation-print-panel" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-printer"></i> Print Forms
            </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="emr-consultation-layout<?= $canPrint ? ' has-print-panel' : '' ?>">
        <div class="emr-consultation-forms<?= $lockAll ? ' it-readonly-forms' : '' ?><?= $layoutTestMode ? ' it-layout-test-mode' : '' ?>" id="consultationForms">
            <?php
            PaperFormRenderer::setLayoutTestMode($layoutTestMode);
            foreach ($consultForms as $formId):
            ?>
            <section class="emr-consult-form-section" id="form-<?= htmlspecialchars($formId) ?>">
                <h3 class="emr-consult-form-title"><?= htmlspecialchars($bundles[$formId]['title'] ?? strtoupper($formId)) ?></h3>
                <?= PaperFormRenderer::renderPage($formId, $role, $lockAll) ?>
            </section>
            <?php endforeach;
            PaperFormRenderer::setLayoutTestMode(false);
            ?>
        </div>

        <?php if ($canPrint || $isIt): ?>
        <aside class="emr-consult-print-panel<?= $canPrint ? '' : ' it-print-panel-disabled' ?>" id="consultation-print-panel">
            <div class="it-print-panel-inner">
                <h3 class="it-print-panel-title"><i class="bi bi-printer"></i> Consultation Printing</h3>
                <?php if ($canPrint): ?>
                <p class="it-print-panel-desc">Print forms for this consultation only. Other visits are not affected.</p>
                <?php else: ?>
                <p class="it-print-panel-desc text-warning">Printing unlocks when this consultation is marked <strong>Ready for Printing</strong>.</p>
                <?php endif; ?>

                <dl class="it-print-panel-meta">
                    <dt>Visit</dt>
                    <dd>#<?= (int) $visitNumber ?></dd>
                    <dt>Consultation Date</dt>
                    <dd><?= htmlspecialchars($consultDate) ?></dd>
                </dl>

                <?php if ($canPrint): ?>
                <div class="it-print-actions">
                    <?php foreach (['itr', 'ekas', 'epress'] as $bundleKey): ?>
                    <button type="button"
                            class="btn btn-outline-primary btn-sm it-btn-preview"
                            data-bundle="<?= htmlspecialchars($bundleKey) ?>">
                        <i class="bi bi-eye"></i> <?= htmlspecialchars($bundles[$bundleKey]['label'] ?? $bundleKey) ?>
                    </button>
                    <?php endforeach; ?>
                    <hr class="my-2">
                    <button type="button"
                            class="btn btn-primary it-btn-preview"
                            data-bundle="consultation"
                            data-mark-printed="1">
                        <i class="bi bi-printer-fill"></i> Print All Consultation Forms
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($isIt && $consultationStatus === WORKFLOW_READY_FOR_PRINT): ?>
                <hr class="my-3">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm it-btn-preview" data-bundle="consultation">
                        <i class="bi bi-fullscreen"></i> Preview Final Print
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="btnMarkPrinted">
                        <i class="bi bi-printer"></i> Mark as Printed
                    </button>
                </div>
                <?php endif; ?>
                <?php if ($isIt && $consultationStatus === WORKFLOW_PRINTED): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2" id="btnArchiveRecord">
                    <i class="bi bi-archive"></i> Archive Consultation
                </button>
                <?php endif; ?>
            </div>
        </aside>
        <?php endif; ?>
    </div>
</div>

<?php if ($canPrint): ?>
<div class="modal fade" id="printPreviewModal" tabindex="-1" aria-labelledby="printPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content it-preview-modal">
            <div class="modal-header it-preview-header">
                <h5 class="modal-title" id="printPreviewModalLabel">Print Preview — Visit #<?= (int) $visitNumber ?></h5>
                <div class="it-preview-toolbar">
                    <button type="button" class="btn btn-primary btn-sm" id="btnPreviewPrint">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
            <div class="modal-body p-0">
                <iframe id="printPreviewFrame" title="Print preview" class="it-preview-frame"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.IT_FOLDER = {
    patientDbId: <?= $patientId ?>,
    consultationId: <?= $consultationId ?>,
    baseUrl: <?= json_encode(BASE_URL) ?>,
    status: <?= json_encode($consultationStatus) ?>,
    patient: <?= json_encode($patient, JSON_UNESCAPED_UNICODE) ?>,
    treatment: <?= json_encode($treatment, JSON_UNESCAPED_UNICODE) ?>,
    extended: <?= json_encode($extended, JSON_UNESCAPED_UNICODE) ?>,
    bundles: <?= json_encode(['itr' => 'ITR', 'ekas' => 'EKAS', 'epress' => 'EPRESS', 'consultation' => 'All Consultation Forms'], JSON_UNESCAPED_UNICODE) ?>,
    canPrint: <?= $canPrint ? 'true' : 'false' ?>,
    layoutTestMode: <?= $layoutTestMode ? 'true' : 'false' ?>,
    workflowActions: {
        mark_ready_for_print: false,
        approve_for_printing: false,
        reject_to_encoder: false,
        mark_printed: <?= $consultationStatus === WORKFLOW_READY_FOR_PRINT && $isIt ? 'true' : 'false' ?>,
        archive_record: <?= $consultationStatus === WORKFLOW_PRINTED && $isIt ? 'true' : 'false' ?>,
    },
    populateOnly: <?= $isIt ? 'false' : 'true' ?>,
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
