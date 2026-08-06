<?php
/**
 * IT Validation Workspace — DMS-style document review (validation ≠ printing).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/PatientFolder.php';
require_once __DIR__ . '/../core/Consultation.php';
require_once __DIR__ . '/../core/ItPrintForms.php';
require_once __DIR__ . '/../core/Workflow.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/PaperFormRenderer.php';

requireRole([ROLE_IT]);

$patientId = (int) ($_GET['id'] ?? 0);
$consultationId = (int) ($_GET['consultation'] ?? $_GET['consultation_id'] ?? 0);

if ($patientId <= 0) {
    die('Invalid patient ID.');
}

$data = ItPrintForms::loadRecord($patientId, $consultationId > 0 ? $consultationId : null);
if (empty($data['patient'])) {
    die('Patient record not found.');
}

$patient = $data['patient'];
$treatment = $data['treatment'] ?? [];
$extended = $data['extended'] ?? [];
$consultationId = (int) ($data['consultation_id'] ?? 0);

if (!WorkflowStatus::isPendingItValidation($patient['status'])) {
    if (in_array($patient['status'], [WORKFLOW_READY_FOR_PRINT, WORKFLOW_PRINTED], true) && $consultationId > 0) {
        header('Location: ' . BASE_URL . '/consultation.php?id=' . $patientId . '&consultation=' . $consultationId);
        exit;
    }
    header('Location: ' . BASE_URL . '/validation-status.php');
    exit;
}

$summary = PatientFolder::buildSummary($patient, $extended);
$itMeta = Consultation::parseItData($treatment);
$visitNumber = (int) ($itMeta['visit_number'] ?? 1);
$doctorName = trim((string) ($itMeta['doctor_name'] ?? $treatment['healthcare_provider'] ?? '')) ?: '—';
$consultDate = !empty($treatment['date_of_consultation'])
    ? date('M d, Y', strtotime($treatment['date_of_consultation']))
    : '—';

$encodedBy = '—';
$conn = getDBConnection();
if (!empty($patient['encoded_by'])) {
    $uid = (int) $patient['encoded_by'];
    $stmt = $conn->prepare(
        'SELECT u.full_name, r.slug AS role
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?'
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $urow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $encodedBy = $urow['full_name'] ?? '—';
}

$audit = [];
$stmt = $conn->prepare(
    'SELECT wl.action, wl.remarks, wl.created_at, wl.from_status, wl.to_status,
            u.full_name, r.slug AS role
     FROM workflow_logs wl
     LEFT JOIN users u ON u.id = wl.user_id
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE wl.patient_id = ?
     ORDER BY wl.created_at DESC
     LIMIT 20'
);
$stmt->bind_param('i', $patientId);
$stmt->execute();
$audit = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$documents = [
    ['id' => 'pkrf', 'short' => 'PKRF', 'label' => 'PhilHealth Konsulta Registration Form (PKRF)', 'title' => '1. PKRF (PhilHealth Konsulta Registration Form)'],
    ['id' => 'itr', 'short' => 'ITR', 'label' => 'Individual Treatment Record', 'title' => '2. Individual Treatment Record'],
    ['id' => 'enrollment', 'short' => 'Enrollment', 'label' => 'Patient Enrollment Record', 'title' => '3. Patient Enrollment Record'],
    ['id' => 'history', 'short' => 'Medical History', 'label' => 'Past Medical / Surgical / Family History', 'title' => '4. Past Medical / Surgical / Family History'],
    ['id' => 'personal', 'short' => 'Social History', 'label' => 'Personal / Social History, NCD & Photo Consent', 'title' => '5. Personal / Social History, NCD & Photo Consent'],
    ['id' => 'ekas', 'short' => 'EKAS', 'label' => 'Electronic Konsulta Availment Slip (EKAS)', 'title' => '6. Electronic Konsulta Availment Slip (EKAS)'],
    ['id' => 'epress', 'short' => 'EPRESS', 'label' => 'Electronic Prescription Slip (EPRESS)', 'title' => '7. Electronic Prescription Slip (EPRESS)'],
];

/** Lightweight readiness: core patient identity present (does not block optional forms). */
$hasCorePatient = trim((string) ($patient['last_name'] ?? '')) !== ''
    && trim((string) ($patient['first_name'] ?? '')) !== ''
    && trim((string) ($patient['sex'] ?? '')) !== ''
    && !empty($patient['birth_date']);
$hasConsultData = !empty($treatment['id']);
$formsReady = $hasCorePatient && $hasConsultData;
$formCount = count($documents);

$roleLabels = [
    ROLE_RECORDS => 'Encoder',
    ROLE_IT => 'IT Staff',
    ROLE_RECEPTIONIST => 'Receptionist',
    ROLE_DOCTOR => 'Doctor',
];

$pageTitle = 'IT Validation Workspace';
$pageSubtitle = 'Review and validate consultation forms before sending to Print Queue';
$hidePageTitleBar = true;
$activePage = 'validation';
$extraCSS = [
    BASE_URL . '/assets/css/paper-form.css',
    BASE_URL . '/assets/css/it-print.css',
    BASE_URL . '/assets/css/it-validation.css',
];
$extraJS = [
    BASE_URL . '/assets/js/it-folder.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/it-folder.js') ?: 1),
    BASE_URL . '/assets/js/it-validation.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/it-validation.js') ?: 1),
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="itv-dms" id="itValidationWorkspace">
  <header class="itv-dms-header">
    <a href="<?= BASE_URL ?>/validation-status.php" class="itv-back-link">
      <i class="bi bi-arrow-left"></i> Back to IT Review Queue
    </a>
    <div class="itv-dms-title-row">
      <div>
        <h1 class="itv-title">IT Validation Workspace</h1>
        <p class="itv-subtitle mb-0">Review and validate consultation forms before sending to Print Queue</p>
      </div>
      <?= WorkflowStatus::badgeHtml($patient['status'], 'Pending IT Validation') ?>
    </div>
  </header>

  <!-- Patient Information Banner -->
  <section class="itv-patient-banner" aria-label="Patient Information">
    <div class="itv-banner-item">
      <span class="itv-label">Patient ID</span>
      <strong><?= htmlspecialchars($summary['patient_id']) ?></strong>
    </div>
    <div class="itv-banner-item">
      <span class="itv-label">Patient Name</span>
      <strong><?= htmlspecialchars($summary['full_name']) ?></strong>
    </div>
    <div class="itv-banner-item">
      <span class="itv-label">Consultation Date</span>
      <strong><?= htmlspecialchars($consultDate) ?></strong>
    </div>
    <div class="itv-banner-item">
      <span class="itv-label">Visit Number</span>
      <strong>#<?= (int) $visitNumber ?></strong>
    </div>
    <div class="itv-banner-item">
      <span class="itv-label">Encoded By</span>
      <strong><?= htmlspecialchars($encodedBy) ?></strong>
    </div>
  </section>

  <!-- Compact Document Status -->
  <section class="itv-doc-status" aria-label="Document Validation Summary">
    <div class="itv-doc-status-left">
      <span class="itv-doc-status-heading">Document Validation Summary</span>
      <ul class="itv-chip-list">
        <?php foreach ($documents as $doc): ?>
          <li class="itv-chip is-ok" data-form="<?= htmlspecialchars($doc['id']) ?>">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($doc['short']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="itv-doc-status-right">
      <span class="itv-label">Overall Completion</span>
      <strong class="itv-pct is-ready">100%</strong>
      <span class="itv-ready-text"><?= $formsReady ? 'Forms Ready for Review' : 'Review patient data carefully' ?></span>
    </div>
  </section>

  <!-- Three-panel DMS workspace -->
  <div class="itv-dms-workspace">
    <!-- Left: Consultation Documents -->
    <aside class="itv-folder-panel" aria-label="Consultation Documents">
      <div class="itv-folder-head">
        <i class="bi bi-folder2-open"></i> Consultation Documents
      </div>
      <nav class="itv-folder-list" id="itvFolderList">
        <?php foreach ($documents as $i => $doc): ?>
          <button type="button"
                  class="itv-folder-item<?= $i === 0 ? ' is-active' : '' ?>"
                  data-form="<?= htmlspecialchars($doc['id']) ?>"
                  title="<?= htmlspecialchars($doc['label']) ?>">
            <span class="itv-folder-icon"><i class="bi bi-file-earmark-text"></i></span>
            <span class="itv-folder-label"><?= htmlspecialchars($doc['title']) ?></span>
            <span class="itv-folder-status" title="Available for review"><i class="bi bi-check-circle-fill"></i></span>
          </button>
        <?php endforeach; ?>
      </nav>
    </aside>

    <!-- Center: Document Viewer -->
    <section class="itv-viewer-panel" aria-label="Document Viewer">
      <div class="itv-viewer-toolbar">
        <div class="itv-viewer-nav">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="itvPrevForm" title="Previous form">
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="itv-page-indicator" id="itvFormIndicator">Form 1 of <?= count($documents) ?></span>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="itvNextForm" title="Next form">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
        <div class="itv-viewer-zoom">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="itvZoomOut" title="Zoom out"><i class="bi bi-dash-lg"></i></button>
          <span class="itv-zoom-label" id="itvZoomLabel">100%</span>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="itvZoomIn" title="Zoom in"><i class="bi bi-plus-lg"></i></button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="itvFitWidth" title="Fit width">Fit Width</button>
          <button type="button" class="btn btn-sm btn-outline-primary" id="itvFullscreen" title="Full screen preview">
            <i class="bi bi-fullscreen"></i> Full Preview
          </button>
        </div>
      </div>

      <div class="itv-viewer-stage" id="itvViewerStage">
        <div class="itv-viewer-canvas" id="itvViewerCanvas">
          <?php
          PaperFormRenderer::setLayoutTestMode(ItPrintForms::isLayoutTestMode());
          foreach ($documents as $i => $doc):
          ?>
            <div class="itv-form-pane<?= $i === 0 ? ' is-active' : '' ?>"
                 data-form="<?= htmlspecialchars($doc['id']) ?>"
                 id="itv-form-<?= htmlspecialchars($doc['id']) ?>">
              <?= PaperFormRenderer::renderPage($doc['id'], ROLE_IT, false) ?>
            </div>
          <?php
          endforeach;
          PaperFormRenderer::setLayoutTestMode(false);
          ?>
        </div>
      </div>
    </section>

    <!-- Right: Validation Panel -->
    <aside class="itv-action-panel">
      <section class="itv-remarks-card">
        <h2 class="itv-panel-title">Validation Remarks</h2>
        <label class="itv-label" for="itvRemarks">Remarks (optional)</label>
        <textarea id="itvRemarks"
                  class="form-control itv-remarks-input"
                  maxlength="500"
                  rows="6"
                  placeholder="Add remarks if there are incomplete information or corrections needed…"></textarea>
        <div class="itv-char-count"><span id="itvRemarkCount">0</span>/500</div>
      </section>

      <section class="itv-actions-card">
        <h2 class="itv-panel-title">Validation Actions</h2>
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-outline-primary" id="btnSaveRecordsFields">
            <i class="bi bi-save"></i> Save Records Fields
          </button>
          <a href="<?= BASE_URL ?>/forms/doh-form.php?id=<?= (int) $patientId ?><?= $consultationId > 0 ? '&consultation_id=' . (int) $consultationId : '' ?>"
             class="btn btn-outline-primary">
            <i class="bi bi-pencil-square"></i> Open Full Form Editor
          </a>
          <a href="<?= BASE_URL ?>/validation-status.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
          </a>
          <button type="button" class="btn btn-warning" id="btnReturnForCorrection">
            <i class="bi bi-arrow-counterclockwise"></i> Return for Correction
          </button>
          <button type="button" class="btn btn-success" id="btnApproveToPrint">
            <i class="bi bi-check2-circle"></i> Approve &amp; Send to Print Queue
          </button>
        </div>
        <p class="itv-action-hint">IT can fill and save Records Office fields (enrollment, history, ITR intake, PKRF). Doctor clinical fields stay read-only.</p>
      </section>
    </aside>
  </div>

  <!-- Validation History -->
  <section class="itv-history-card">
    <h2 class="itv-panel-title">Validation History</h2>
    <?php if (empty($audit)): ?>
      <p class="text-muted small mb-0">No validation history yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm itv-history-table align-middle mb-0">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Action</th>
              <th>Performed By</th>
              <th>Role</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($audit as $row):
                $roleKey = $row['role'] ?? '';
                $roleLabel = $roleLabels[$roleKey] ?? ($roleKey ?: '—');
            ?>
              <tr>
                <td><?= date('M j, Y g:i A', strtotime($row['created_at'])) ?></td>
                <td><?= htmlspecialchars($row['action']) ?></td>
                <td><?= htmlspecialchars($row['full_name'] ?? 'System') ?></td>
                <td><?= htmlspecialchars($roleLabel) ?></td>
                <td><?= htmlspecialchars($row['remarks'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<!-- Full-screen document preview overlay -->
<div class="itv-fs-overlay" id="itvFsOverlay" hidden>
  <div class="itv-fs-toolbar">
    <span id="itvFsTitle">Document Preview</span>
    <button type="button" class="btn btn-sm btn-light" id="itvFsClose">Close</button>
  </div>
  <div class="itv-fs-body" id="itvFsBody"></div>
</div>

<script>
window.IT_VALIDATION = {
  patientDbId: <?= (int) $patientId ?>,
  consultationId: <?= (int) $consultationId ?>,
  baseUrl: <?= json_encode(BASE_URL) ?>,
  formsReady: <?= $formsReady ? 'true' : 'false' ?>,
  documents: <?= json_encode(array_column($documents, 'id')) ?>,
  documentLabels: <?= json_encode(array_column($documents, 'label', 'id')) ?>,
  patient: <?= json_encode($patient, JSON_UNESCAPED_UNICODE) ?>,
  treatment: <?= json_encode($treatment, JSON_UNESCAPED_UNICODE) ?>,
  extended: <?= json_encode($extended, JSON_UNESCAPED_UNICODE) ?>,
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
