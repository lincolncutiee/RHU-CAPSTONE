<?php
/**
 * View a single historical record (photo + optional OCR soft copy).
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/HistoricalRecord.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/PatientFolder.php';

requireLogin();

$role = Permissions::normalizeRole(currentUser()['role'] ?? '');
if (!HistoricalRecord::canView($role)) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$recordId = (int) ($_GET['id'] ?? 0);
if ($recordId <= 0) {
    die('Invalid record ID.');
}

$conn = getDBConnection();
HistoricalRecord::ensureSchema($conn);
$record = HistoricalRecord::find($conn, $recordId);
$conn->close();

if (!$record) {
    die('Historical record not found.');
}

$patientId = (int) $record['patient_id'];
$reviewed = $record['reviewed_data'] ?? [];
$hasOcr = ($record['storage_mode'] ?? '') === 'ocr_reviewed';

$pageTitle = $record['record_type_label'];
$pageSubtitle = $record['record_date_display'];
$activePage = 'records';
$hidePageTitleBar = true;
$hidePatientSearch = true;
$extraCSS = [BASE_URL . '/assets/css/historical-record.css?v=1'];

include __DIR__ . '/includes/header.php';
?>

<div class="hr-view-page">
    <div class="hr-view-header">
        <div>
            <a href="<?= BASE_URL ?>/patient-folder.php?id=<?= $patientId ?>&tab=historical" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left"></i> Back to Patient Folder
            </a>
            <h1 class="h5 fw-semibold mt-2 mb-1"><?= htmlspecialchars($record['record_type_label']) ?></h1>
            <p class="text-muted small mb-0">
                <?= htmlspecialchars($record['record_date_display']) ?>
                · <span class="emr-historical-badge<?= $hasOcr ? ' is-ocr' : '' ?>"><?= htmlspecialchars($record['storage_mode_label']) ?></span>
            </p>
        </div>
        <a href="<?= htmlspecialchars($record['photo_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-image"></i> Open Original Photo
        </a>
    </div>

    <?php if (!empty($record['remarks'])): ?>
    <div class="hr-view-card">
        <div class="small text-muted fw-semibold text-uppercase mb-1">Remarks</div>
        <p class="mb-0"><?= nl2br(htmlspecialchars($record['remarks'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="hr-view-card">
        <div class="hr-view-grid">
            <div>
                <div class="small text-muted fw-semibold text-uppercase mb-2">Original Form</div>
                <img class="hr-view-photo" src="<?= htmlspecialchars($record['photo_url']) ?>" alt="Original photographed form">
                <p class="text-muted small mt-2 mb-0">
                    Uploaded by <?= htmlspecialchars($record['uploaded_by_name']) ?>
                    · <?= htmlspecialchars($record['created_at_display']) ?>
                </p>
            </div>

            <?php if ($hasOcr && !empty($reviewed)): ?>
            <div class="hr-view-fields">
                <div class="small text-muted fw-semibold text-uppercase mb-2">Reviewed Information</div>
                <dl>
                    <?php
                    $labels = [
                        'patient_name' => 'Patient Name',
                        'address'      => 'Address',
                        'diagnosis'    => 'Diagnosis',
                        'record_date'  => 'Date',
                        'notes'        => 'Additional Notes',
                    ];
                    foreach ($labels as $key => $label):
                        if (empty($reviewed[$key])) continue;
                    ?>
                    <dt><?= htmlspecialchars($label) ?></dt>
                    <dd><?= nl2br(htmlspecialchars($reviewed[$key])) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
            <?php else: ?>
            <div class="d-flex align-items-center justify-content-center text-muted small">
                <div class="text-center p-4">
                    <i class="bi bi-camera fs-2 d-block mb-2"></i>
                    This record is stored as an original photograph only.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hasOcr && !empty($record['ocr_raw_text'])): ?>
    <details class="hr-view-card">
        <summary class="fw-semibold small">Raw OCR Text</summary>
        <pre class="small mt-2 mb-0 text-muted" style="white-space:pre-wrap;font-family:inherit;"><?= htmlspecialchars($record['ocr_raw_text']) ?></pre>
    </details>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
