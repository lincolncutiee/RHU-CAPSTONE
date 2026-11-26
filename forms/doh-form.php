<?php
/**
 * Patient Forms Module — official RHU paper form sequence.
 * UI shell only redesigned; workflow and save logic unchanged.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/FormTemplate.php';

$user      = currentUser();
$roleRaw   = (string) ($user['role'] ?? '');
$role      = Permissions::normalizeRole($roleRaw);

Permissions::requireCanAny(['patient.create', 'patient.view']);

// Receptionist does not encode — queue management only
if ($role === ROLE_RECEPTIONIST) {
    header('Location: ' . BASE_URL . '/reception/queue.php');
    exit;
}

// Doctor does not register / encode new patients — consultation queue only
$patientId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$consultationId = isset($_GET['consultation_id']) ? (int) $_GET['consultation_id'] : 0;
$isNewConsultation = isset($_GET['new_consultation']) && (string) $_GET['new_consultation'] === '1';
$isNew     = $patientId === 0;

if ($isNewConsultation && $patientId <= 0) {
    header('Location: ' . BASE_URL . '/patient-records.php');
    exit;
}

if ($isNewConsultation && $consultationId > 0) {
    // Already have a visit id — open as normal consultation encoding
    $isNewConsultation = false;
}

// Doctors do not create new patients — use Doctor Consultation Queue / Start Consultation.
if ($role === ROLE_DOCTOR && $isNew) {
    header('Location: ' . BASE_URL . '/workflow/queue.php');
    exit;
}

// Doctors may VIEW original RHU forms (Patient Records → View Form) in read-only mode.
// Clinical editing happens only in doctor/consultation.php via Start Consultation.
$isDoctorViewOnly = $role === ROLE_DOCTOR;

$pageTitle    = $isDoctorViewOnly
    ? 'View RHU Forms'
    : ($isNewConsultation ? 'New Consultation Encoding' : 'Patient Information Encoding');
$pageSubtitle = $isDoctorViewOnly
    ? 'Original RHU / DOH consultation forms (read-only)'
    : ($isNewConsultation
        ? 'Patient profile is locked — encode consultation fields for a new visit'
        : 'Official RHU forms in paper-form sequence');
$activePage   = $isDoctorViewOnly ? 'records' : 'encoding';
// Focused form viewer — no page title bar / patient search chrome
$hidePageTitleBar = true;
$hidePatientSearch = true;

$formDisplayStyle = FormTemplate::formDisplayStyle();
$isCleanFormDisplay = $formDisplayStyle === 'clean';

$extraCSS = [
    BASE_URL . '/assets/css/doh-form.css',
    BASE_URL . '/assets/css/paper-form.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/paper-form.css'),
    BASE_URL . '/assets/css/form-wizard.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/form-wizard.css'),
];
if ($isCleanFormDisplay) {
    $extraCSS[] = BASE_URL . '/assets/css/clean-form-guide.css?v=' . ((int) @filemtime(__DIR__ . '/../assets/css/clean-form-guide.css') ?: 1);
}
$extraJS = [
    BASE_URL . '/assets/js/patient-profile-sync.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/patient-profile-sync.js') ?: 1),
    BASE_URL . '/assets/js/form-wizard.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/form-wizard.js') ?: 1),
];
if ($isCleanFormDisplay) {
    $extraJS[] = BASE_URL . '/assets/js/clean-form-guide.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/clean-form-guide.js') ?: 1);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/FormRenderer.php';
require_once __DIR__ . '/../core/PatientProfile.php';
require_once __DIR__ . '/../core/PatientSearch.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';

// After Records Office submits, their fields become read-only (UI lock only)
$patientStatus = WORKFLOW_DRAFT;
$patientRow = null;
if ($patientId > 0) {
    $conn = getDBConnection();
    WorkflowStatus::ensurePatientsStatusEnum($conn);
    $stmt = $conn->prepare('SELECT * FROM patients WHERE id = ? AND is_archived = 0');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $patientRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if ($patientRow) {
        $patientStatus = WorkflowStatus::normalize($patientRow['status'] ?? WORKFLOW_DRAFT);
    }
}
if ($patientId > 0 && $role === ROLE_IT && !$isNewConsultation) {
    // Ready for print / printed — use print/consultation views, not encoding
    if (in_array($patientStatus, [WORKFLOW_READY_FOR_PRINT, WORKFLOW_PRINTED, WORKFLOW_ARCHIVED], true)) {
        header('Location: ' . BASE_URL . '/patient-folder.php?id=' . $patientId . '&tab=consultations');
        exit;
    }
}

$summaryName = 'New Patient Record';
$summaryId = '—';
$summarySex = '—';
$summaryAge = '—';
$summaryBarangay = '—';
$summaryPhilhealth = '—';
if ($patientRow) {
    $middle = trim($patientRow['middle_name'] ?? '');
    $summaryName = trim(($patientRow['last_name'] ?? '') . ', ' . ($patientRow['first_name'] ?? '') . ($middle !== '' ? ' ' . $middle : ''));
    $summaryId = $patientRow['patient_id'] ?? '—';
    $summarySex = $patientRow['sex'] ?? '—';
    $age = calculateAge($patientRow['birth_date'] ?? null);
    $summaryAge = $age !== null ? $age . ' years old' : '—';
    $summaryBarangay = PatientSearch::extractBarangay($patientRow['residential_address'] ?? null);
    $summaryPhilhealth = $patientRow['philhealth_no'] ?: '—';
}

$isLocked = WorkflowStatus::isLocked($patientStatus);
$lockRecordsFields = WorkflowStatus::shouldLockRecordsFields($role, $patientStatus);
$waitingForDoctor = in_array($patientStatus, [WORKFLOW_WAITING_FOR_DOCTOR, WORKFLOW_PENDING_DOCTOR], true);

// Original RHU forms are always read-only for doctors (workspace is the edit UI).
if ($isDoctorViewOnly) {
    $isLocked = true;
    $lockRecordsFields = true;
}

// New consultation visit: unlock encoding UI; profile fields locked via PaperFormRenderer mode.
if ($isNewConsultation) {
    require_once __DIR__ . '/../core/PatientFolder.php';
    if (!PatientFolder::canCreateConsultation($role) || $role === ROLE_DOCTOR) {
        header('Location: ' . BASE_URL . '/patient-folder.php?id=' . $patientId . '&tab=consultations');
        exit;
    }
    // Only unlock if still encoder-editable (Draft). Waiting for Doctor stays locked.
    if (!$waitingForDoctor && !$lockRecordsFields) {
        $isLocked = false;
        $lockRecordsFields = false;
        require_once __DIR__ . '/../core/PaperFormRenderer.php';
        PaperFormRenderer::setNewConsultationMode(true);
    }
}

// Hard lock (Printed/Archived) or doctor view-only — no draft saves.
// Records Office also cannot save once records fields are workflow-locked.
$canSaveDraft = !$isLocked
    && !($role === ROLE_RECORDS && $lockRecordsFields)
    && !$isDoctorViewOnly;

// Day/time the patient came to RHU (reception → consultation → now)
$visitDate = date('Y-m-d');
$visitTime = date('H:i');
if ($patientId > 0) {
    $vconn = getDBConnection();
    try {
        require_once __DIR__ . '/../core/ReceptionQueue.php';
        ReceptionQueue::ensureSchema($vconn);
        $stmt = $vconn->prepare(
            'SELECT created_at, serving_at, called_at FROM reception_queue WHERE patient_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $arrival = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $arrivalTs = $arrival['serving_at'] ?? $arrival['called_at'] ?? $arrival['created_at'] ?? null;
        if (!empty($arrivalTs)) {
            $visitDate = date('Y-m-d', strtotime($arrivalTs));
            $visitTime = date('H:i', strtotime($arrivalTs));
        } else {
            $stmt = $vconn->prepare(
                'SELECT date_of_consultation, consultation_time FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->bind_param('i', $patientId);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!empty($tx['date_of_consultation'])) {
                $visitDate = date('Y-m-d', strtotime($tx['date_of_consultation']));
            } elseif (!empty($patientRow['date_encoded'])) {
                $visitDate = date('Y-m-d', strtotime($patientRow['date_encoded']));
            }
            if (!empty($tx['consultation_time'])) {
                $visitTime = date('H:i', strtotime($tx['consultation_time']));
            }
        }
    } catch (Throwable $e) {
        if (!empty($patientRow['date_encoded'])) {
            $visitDate = date('Y-m-d', strtotime($patientRow['date_encoded']));
        }
    }
    $vconn->close();
}

require_once __DIR__ . '/../core/ItPrintForms.php';
$skipPageValidation = ItPrintForms::isLayoutTestMode();

$wizardConfig = require __DIR__ . '/../config/form-wizard.php';
$flatPages    = FormRenderer::getFlatPages();
$totalPages   = count($flatPages);

$lastRhuFormPage = FormRenderer::getPageIndex($wizardConfig['last_rhu_form_page']);
$recordsLastPage = FormRenderer::getPageIndex($wizardConfig['records_submit_page']);
$doctorPage      = FormRenderer::getPageIndex($wizardConfig['doctor_primary_page']);

$startPage = 0;
$pageParam = trim((string) ($_GET['page'] ?? ''));
if ($pageParam !== '') {
    $requested = FormRenderer::getPageIndex($pageParam);
    if ($requested >= 0) {
        $startPage = $requested;
    }
} elseif ($isNewConsultation) {
    $startPage = FormRenderer::getPageIndex('itr');
} elseif ($isNew && $role === ROLE_RECORDS) {
    // PCSF is IT-owned — start Records on Enrollment so identity fields are editable
    $enrollIdx = FormRenderer::getPageIndex('enrollment');
    if ($enrollIdx >= 0) {
        $startPage = $enrollIdx;
    }
}

$summaryName = 'New Patient Record';
$summaryId = '—';
$summarySex = '—';
$summaryAge = '—';
$summaryBarangay = '—';
$summaryPhilhealth = '—';
if ($patientRow) {
    $middle = trim($patientRow['middle_name'] ?? '');
    $summaryName = trim(($patientRow['last_name'] ?? '') . ', ' . ($patientRow['first_name'] ?? '') . ($middle !== '' ? ' ' . $middle : ''));
    $summaryId = $patientRow['patient_id'] ?? '—';
    $summarySex = $patientRow['sex'] ?? '—';
    $age = calculateAge($patientRow['birth_date'] ?? null);
    $summaryAge = $age !== null ? $age . ' years old' : '—';
    $summaryBarangay = PatientSearch::extractBarangay($patientRow['residential_address'] ?? null);
    $summaryPhilhealth = $patientRow['philhealth_no'] ?: '—';
}

// Page titles for review summary (exclude review page itself)
$reviewPageMeta = [];
foreach ($flatPages as $page) {
    if (!empty($page['is_review'])) {
        continue;
    }
    $reviewPageMeta[] = [
        'id'       => $page['id'],
        'title'    => $page['title'],
        'sections' => array_map(static function ($section) {
            $fields = [];
            foreach ($section['fields'] as $field) {
                if (empty($field['name'])) {
                    continue;
                }
                $fields[] = [
                    'name'     => $field['name'],
                    'label'    => $field['label'] ?? $field['name'],
                    'extended' => !empty($field['extended']),
                    'type'     => $field['type'] ?? 'text',
                ];
            }
            return [
                'title'  => $section['title'],
                'fields' => $fields,
            ];
        }, $page['sections'] ?? []),
    ];
}
?>

<div class="emr-encoding emr-encoding--focused<?= $isCleanFormDisplay ? ' emr-encoding--clean' : '' ?>" id="emrEncodingRoot" data-form-display-style="<?= htmlspecialchars($formDisplayStyle) ?>">
  <?php if (!$isDoctorViewOnly && ($waitingForDoctor || $lockRecordsFields)): ?>
  <div class="emr-encoding-status-bar" id="encodingStatusBar">
    <?= WorkflowStatus::badgeHtml($patientStatus) ?>
    <?php if ($waitingForDoctor): ?>
    <span class="emr-encoding-status-note">Sent to Doctor Consultation Queue — encoding is read-only.</span>
    <?php elseif ($lockRecordsFields): ?>
    <span class="emr-encoding-status-note">This consultation is locked for editing.</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Hidden hooks for form-wizard.js (no visible patient summary) -->
  <div class="d-none" aria-hidden="true">
    <span id="summaryFullName"><?= htmlspecialchars($summaryName) ?></span>
    <span id="patientRefBadge"><?= htmlspecialchars($summaryId) ?></span>
    <span id="statusBadge" class="badge-status <?= htmlspecialchars(WorkflowStatus::badgeClass($patientStatus)) ?>"><?= htmlspecialchars($patientStatus) ?></span>
    <span id="wizardProgressText">Page <?= $startPage + 1 ?> of <?= $totalPages ?></span>
    <span id="saveStatus"></span>
    <span id="saveLastTime"></span>
    <span id="summarySex"><?= htmlspecialchars($summarySex) ?></span>
    <span id="summaryAge"><?= htmlspecialchars($summaryAge) ?></span>
    <span id="summaryBarangay"><?= htmlspecialchars($summaryBarangay) ?></span>
    <span id="summaryPhilhealth"><?= htmlspecialchars($summaryPhilhealth) ?></span>
    <div id="wizardProgressFill" style="width: <?= (int) round((($startPage + 1) / max(1, $totalPages)) * 100) ?>%"></div>
  </div>

  <!-- Review workspace chrome (shown only in review mode — not a form page) -->
  <div class="review-workspace-chrome d-none" id="reviewWorkspaceChrome">
    <div class="review-workspace-topbar">
      <header class="review-workspace-header">
        <h1 class="review-workspace-title">Review &amp; Submit</h1>
        <p class="review-workspace-subtitle">Review all encoded information before submitting to IT Review.</p>
      </header>

      <section class="drc-validation-banner" id="drcValidationSummary" aria-label="Document Validation Summary">
        <span class="drc-validation-banner-title">Validation</span>
        <ul class="drc-page-status-list" id="drcPageStatusList"></ul>
        <div class="drc-validation-banner-meta">
          <span class="drc-meta-label">Overall</span>
          <span class="drc-meta-value" id="drcCompletionPct">—</span>
          <span class="drc-status-pill" id="drcReadyStatus">Checking…</span>
        </div>
        <div class="drc-issues-panel d-none" id="drcIssuesPanel"></div>
      </section>
    </div>
  </div>

  <div class="wizard-layout" id="wizardLayout">
    <aside class="wizard-sidebar" id="wizardSidebar" aria-label="Official RHU Forms">
      <div class="wizard-sidebar-header">
        <button type="button"
                class="wizard-sidebar-toggle"
                id="btnToggleSidebar"
                title="Collapse sidebar"
                aria-label="Collapse sidebar"
                aria-expanded="true"
                aria-controls="wizardNav">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <span class="wizard-sidebar-title"><i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Official RHU Forms</span>
      </div>
      <nav class="wizard-nav" id="wizardNav">
        <?php
        $paperIndex = 0;
        foreach ($flatPages as $pageNum => $page):
          if (!empty($page['is_review'])) continue;
          $paperIndex++;
        ?>
          <button type="button"
                  class="wizard-nav-item<?= $pageNum === $startPage ? ' active' : '' ?>"
                  data-page="<?= $pageNum ?>"
                  data-page-id="<?= htmlspecialchars($page['id']) ?>"
                  data-doc-num="<?= $paperIndex ?>"
                  id="nav-page-<?= $pageNum ?>"
                  title="<?= htmlspecialchars($page['title']) ?>">
            <span class="wizard-nav-num" aria-hidden="true"><?= $paperIndex ?></span>
            <span class="wizard-nav-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
            <span class="wizard-nav-label">
              <span class="wizard-nav-title"><?= htmlspecialchars($page['short_title'] ?? $page['title']) ?></span>
            </span>
            <span class="wizard-nav-status" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
          </button>
        <?php endforeach; ?>
      </nav>
      <div class="wizard-nav-legend" id="wizardNavLegend">
        <span><i class="bi bi-check-circle-fill text-success"></i> Completed</span>
        <span><i class="bi bi-circle-fill text-primary"></i> Current</span>
        <span><i class="bi bi-x-circle-fill text-danger"></i> Incomplete</span>
      </div>
    </aside>

    <div class="wizard-main" id="wizardMain">
      <form id="dohForm" class="wizard-form-body" novalidate>
        <input type="hidden" name="patient_db_id" id="patientDbId" value="<?= $patientId ?>">
        <input type="hidden" name="form_progress" id="formProgress" value="<?= $startPage ?>">

        <div class="paper-viewport" id="paperViewport">
          <div class="paper-zoom-frame" id="paperZoomFrame">
            <div class="paper-zoom-stage paper-fit-width" id="paperZoomStage">
              <div class="wizard-pages" id="wizardPages">
                <?php foreach ($flatPages as $i => $page): ?>
                  <div class="wizard-page-pane<?= $i === $startPage ? ' active' : '' ?>"
                       data-page-index="<?= $i ?>"
                       data-page-id="<?= htmlspecialchars($page['id']) ?>"
                       data-is-review="<?= !empty($page['is_review']) ? '1' : '0' ?>">
                    <?= FormRenderer::renderPage($page, $role, $lockRecordsFields) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="form-nav-footer" id="formWorkspaceToolbar" role="navigation" aria-label="Form navigation">
          <div class="toolbar-nav-group" id="toolbarNavGroup">
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnPrev">
              <i class="bi bi-chevron-left"></i> Previous
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btnSaveDraft">
              <i class="bi bi-save"></i> <?= $role === ROLE_RECORDS ? 'Save' : 'Save Draft' ?>
            </button>
            <button type="button" class="btn btn-success btn-sm<?= (
                $role === ROLE_IT
                && !$lockRecordsFields
                && !$waitingForDoctor
                && (int) $startPage === (int) $lastRhuFormPage
            ) ? '' : ' d-none' ?>" id="btnSendToDoctor">
              <i class="bi bi-person-badge"></i> Send to Doctor
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnNext">
              Next <i class="bi bi-chevron-right"></i>
            </button>
            <button type="button" class="btn btn-primary btn-sm d-none" id="btnGoReview">
              Review &amp; Submit <i class="bi bi-chevron-right"></i>
            </button>
            <button type="button" class="btn btn-primary btn-sm d-none" id="btnPreviewFinalPrint">
              <i class="bi bi-eye"></i> Preview Final Print
            </button>
            <button type="button" class="btn btn-success btn-sm d-none" id="btnSubmitIT">
              <i class="bi bi-send"></i> Submit to IT Review
            </button>
            <button type="button" class="btn btn-success btn-sm d-none" id="btnCompleteConsultation">
              <i class="bi bi-capsule"></i> Send to Pharmacy
            </button>
          </div>
          <div class="toolbar-meta-group" id="toolbarMetaGroup">
            <span class="toolbar-page-hint" id="toolbarPageHint">Page <?= $startPage + 1 ?> of <?= $totalPages ?></span>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.RHU_FORM = {
    patientId: <?= $patientId ?>,
    consultationId: <?= $consultationId ?>,
    newConsultation: <?= $isNewConsultation ? 'true' : 'false' ?>,
    role: <?= json_encode($role) ?>,
    roleRaw: <?= json_encode($roleRaw) ?>,
    patientStatus: <?= json_encode($patientStatus) ?>,
    lockRecordsFields: <?= $lockRecordsFields ? 'true' : 'false' ?>,
    editableGroups: <?= json_encode(Permissions::getEditableGroups($role)) ?>,
    baseUrl: <?= json_encode(BASE_URL) ?>,
    totalPages: <?= $totalPages ?>,
    startPage: <?= $startPage ?>,
    lastRhuFormPage: <?= $lastRhuFormPage ?>,
    recordsLastPage: <?= $recordsLastPage ?>,
    doctorPage: <?= $doctorPage ?>,
    reviewMeta: <?= json_encode($reviewPageMeta, JSON_UNESCAPED_UNICODE) ?>,
    profileRequired: <?= json_encode(PatientProfile::requiredForCreate()) ?>,
    visitDate: <?= json_encode($visitDate) ?>,
    visitTime: <?= json_encode($visitTime) ?>,
    workflowActions: <?= json_encode([
        'submit_for_consultation' => Workflow::getActionButtonLabel('submit_for_consultation'),
        'submit_to_it_review' => Workflow::getActionButtonLabel('submit_to_it_review'),
        'send_to_doctor' => Workflow::getActionButtonLabel('send_to_doctor'),
        'complete_consultation' => Workflow::getActionButtonLabel('complete_consultation'),
    ]) ?>,
    isLocked: <?= $isLocked ? 'true' : 'false' ?>,
    canSaveDraft: <?= $canSaveDraft ? 'true' : 'false' ?>,
    waitingForDoctor: <?= $waitingForDoctor ? 'true' : 'false' ?>,
    canSendToDoctor: <?= ($role === ROLE_IT && !$lockRecordsFields && !$waitingForDoctor) ? 'true' : 'false' ?>,
    skipPageValidation: <?= $skipPageValidation ? 'true' : 'false' ?>,
    magallanesAddress: <?= json_encode([
        'province' => MagallanesAddress::province(),
        'municipality' => MagallanesAddress::municipality(),
        'barangays' => MagallanesAddress::barangays(),
    ], JSON_UNESCAPED_UNICODE) ?>,
    paperPageIds: <?= json_encode(array_values(array_map(static fn($p) => $p['id'], array_filter($flatPages, static fn($p) => empty($p['is_review']))))) ?>,
    completenessPageMap: <?= json_encode([
        'pcsf' => 'pcsf',
        'itr' => 'itr',
        'enrollment' => 'enrollment',
        'past_medical_history' => 'history',
        'past_surgical_history' => 'history',
        'family_history' => 'history',
        'personal_social_history' => 'personal',
        'ncd_assessment' => 'personal',
        'photo_consent' => 'personal',
        'ekas' => 'ekas',
        'epress' => 'epress',
    ]) ?>,
    completenessRules: <?= json_encode(require __DIR__ . '/../config/form-completeness.php') ?>,
    formDisplayStyle: <?= json_encode($formDisplayStyle) ?>,
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
