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

$roleLabels = [
    ROLE_RECORDS => 'Encoder',
    ROLE_IT => 'IT Staff',
    ROLE_RECEPTIONIST => 'Receptionist',
    ROLE_DOCTOR => 'Doctor',
];

$encodedBy = '—';
$encoderReturn = [
    'userId' => null,
    'fullName' => null,
    'username' => null,
    'roleLabel' => null,
];
$conn = getDBConnection();
if (!empty($patient['encoded_by'])) {
    $uid = (int) $patient['encoded_by'];
    $stmt = $conn->prepare(
        'SELECT u.full_name, u.username, r.slug AS role
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?'
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $urow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($urow) {
        $encodedBy = $urow['full_name'] ?? '—';
        $roleSlug = $urow['role'] ?? '';
        $encoderReturn = [
            'userId' => $uid,
            'fullName' => $urow['full_name'] ?? '',
            'username' => $urow['username'] ?? '',
            'roleLabel' => $roleLabels[$roleSlug] ?? ($roleSlug ?: 'Encoder'),
        ];
    }
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

$returnRoleLabels = [
    ROLE_RECORDS          => 'Records Office',
    ROLE_DOCTOR           => 'Doctor',
    ROLE_PHARMACY         => 'Pharmacy',
    ROLE_IT               => 'IT Department',
    ROLE_SYSTEM_ADMIN     => 'System Administrator',
    ROLE_RECEPTIONIST     => 'Receptionist',
];
$returnRoleOrder = [
    'Records Office',
    'Doctor',
    'Pharmacy',
    'IT Department',
    'Receptionist',
    'System Administrator',
];

$returnToUsers = [];
$stmt = $conn->prepare(
    'SELECT u.id, u.full_name, u.username, r.slug AS role_slug, r.name AS role_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE u.is_active = 1
     ORDER BY u.full_name ASC'
);
$stmt->execute();
$returnToUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$returnToUsersByRole = [];
foreach ($returnToUsers as $u) {
    $slug = (string) ($u['role_slug'] ?? '');
    $group = $returnRoleLabels[$slug] ?? trim((string) ($u['role_name'] ?? $slug ?: 'Staff'));
    $returnToUsersByRole[$group][] = $u;
}
uksort($returnToUsersByRole, static function (string $a, string $b) use ($returnRoleOrder): int {
    $ia = array_search($a, $returnRoleOrder, true);
    $ib = array_search($b, $returnRoleOrder, true);
    $ia = $ia === false ? 999 : $ia;
    $ib = $ib === false ? 999 : $ib;
    if ($ia === $ib) {
        return strcasecmp($a, $b);
    }

    return $ia <=> $ib;
});

$conn->close();

$documents = [
    ['id' => 'pcsf', 'short' => 'PCSF', 'label' => 'PhilHealth Primary Care Selection Form (PCSF)', 'title' => '1. PCSF (PhilHealth Primary Care Selection Form)'],
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
      <i class="bi bi-arrow-left"></i> Back to Review Queue
    </a>
    <div class="itv-dms-title-row">
      <div>
        <h1 class="itv-title">IT Validation Workspace</h1>
        <p class="itv-subtitle mb-0">Review and validate consultation forms before sending to Print Queue</p>
      </div>
      <?= WorkflowStatus::badgeHtml($patient['status'], 'Pending Validation') ?>
    </div>
  </header>

  <!-- Patient Information Banner -->
  <section class="itv-patient-banner" aria-label="Patient Information">
    <div class="itv-banner-item">
      <span class="itv-label">PIN</span>
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
          <li class="itv-chip is-pending" data-form="<?= htmlspecialchars($doc['id']) ?>">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($doc['short']) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="itv-doc-status-right">
      <span class="itv-label">Overall Completion</span>
      <strong class="itv-pct" id="itvCompletionPct">—</strong>
      <span class="itv-ready-text" id="itvReadyText">Checking required fields…</span>
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
            <span class="itv-folder-status" title="Checking…" aria-hidden="true"><i class="bi bi-dash-circle"></i></span>
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

      <div id="itvIssuesPanel" class="itv-issues-panel d-none" role="region" aria-live="polite" aria-label="Missing required fields">
        <div class="itv-issues-head">
          <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
          <span class="itv-issues-title">Missing information on this form</span>
        </div>
        <ul id="itvIssuesList" class="itv-issues-list mb-0"></ul>
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
        <label class="itv-label" for="itvRemarks">Remarks (optional for approve)</label>
        <textarea id="itvRemarks"
                  class="form-control itv-remarks-input"
                  maxlength="500"
                  rows="6"
                  placeholder="Optional notes for approval. Kung Return for Correction, ang reason ibutang sa dialog…"></textarea>
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
        <p class="itv-action-hint">IT can fill and save Records Office fields (enrollment, history, ITR intake, PCSF). Doctor clinical fields stay read-only.</p>
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

<?php
$returnPatientName = trim((string) ($summary['full_name'] ?? ''));
$returnPin = trim((string) ($summary['patient_id'] ?? ''));
$defaultReturnUserId = (int) ($patient['encoded_by'] ?? 0);
if ($defaultReturnUserId <= 0 && !empty($returnToUsers)) {
    $defaultReturnUserId = (int) $returnToUsers[0]['id'];
}
$nameInitial = $returnPatientName !== ''
    ? strtoupper(substr($returnPatientName, 0, 1))
    : '?';
?>

<!-- Return for Correction (Bootstrap modal — full layout control) -->
<div class="modal fade" id="itvReturnModal" tabindex="-1" aria-labelledby="itvReturnModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered itv-return-dialog">
    <div class="modal-content itv-return-shell border-0 shadow-lg">
      <div class="itv-return-shell-accent" aria-hidden="true"></div>

      <div class="modal-header itv-return-shell-header border-0 pb-0">
        <div class="itv-return-shell-brand">
          <div class="itv-return-shell-icon" aria-hidden="true">
            <i class="bi bi-arrow-counterclockwise"></i>
          </div>
          <div>
            <h2 class="modal-title itv-return-shell-title" id="itvReturnModalTitle">Return for Correction</h2>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body itv-return-shell-body pt-3">
        <?php if ($returnPin !== '' || $returnPatientName !== ''): ?>
          <div class="itv-return-patient" role="group" aria-label="Patient">
            <div class="itv-return-patient-avatar" aria-hidden="true"><?= htmlspecialchars($nameInitial) ?></div>
            <div class="itv-return-patient-text">
              <?php if ($returnPatientName !== ''): ?>
                <div class="itv-return-patient-name"><?= htmlspecialchars($returnPatientName) ?></div>
              <?php endif; ?>
              <?php if ($returnPin !== ''): ?>
                <div class="itv-return-patient-pin">
                  <span class="itv-return-patient-pin-label">PIN</span>
                  <code class="itv-return-patient-pin-value"><?= htmlspecialchars($returnPin) ?></code>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div id="itvReturnError" class="alert alert-danger py-2 px-3 small mb-3 d-none" role="alert"></div>

        <div class="mb-3">
          <label class="form-label itv-return-label" for="itvReturnToUser">
            <i class="bi bi-person-badge me-1" aria-hidden="true"></i>Return to
            <span class="text-muted fw-normal">(any active user)</span>
          </label>
          <select id="itvReturnToUser" class="form-select form-select-lg itv-return-control" required>
            <?php if (empty($returnToUsers)): ?>
              <option value="">— Walay active users —</option>
            <?php else: ?>
              <?php foreach ($returnToUsersByRole as $groupLabel => $groupUsers): ?>
                <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                  <?php foreach ($groupUsers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === $defaultReturnUserId ? ' selected' : '' ?>>
                      <?= htmlspecialchars($u['full_name']) ?> (@<?= htmlspecialchars($u['username']) ?>)
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="mb-0">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <label class="form-label itv-return-label mb-0" for="itvReturnReason">
              <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Reason for returning
              <span class="badge text-bg-danger-subtle text-danger ms-1">Required</span>
            </label>
            <span class="itv-return-counter text-muted"><span id="itvReturnReasonCount">0</span> / 500</span>
          </div>
          <textarea id="itvReturnReason"
                    class="form-control itv-return-control itv-return-textarea"
                    rows="5"
                    maxlength="500"
                    required></textarea>
        </div>

        <div class="itv-return-footnote mt-3">
          <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
          <span>
            Mobiya sa <strong>Review Queue</strong> · status <strong>Returned for Correction</strong>.
            Ang napili makadawat og notification uban sa imong reason ug link sa encoding form.
          </span>
        </div>
      </div>

      <div class="modal-footer itv-return-shell-footer border-0 pt-0">
        <button type="button" class="btn btn-light itv-return-btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning itv-return-btn-submit" id="itvReturnSubmit">
          <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Return for Correction
        </button>
      </div>
    </div>
  </div>
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
  patientPin: <?= json_encode($summary['patient_id'] ?? '') ?>,
  encoderReturn: <?= json_encode($encoderReturn, JSON_UNESCAPED_UNICODE) ?>,
  returnToUsers: <?= json_encode($returnToUsers, JSON_UNESCAPED_UNICODE) ?>,
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
