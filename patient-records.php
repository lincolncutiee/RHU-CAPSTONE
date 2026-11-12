<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/WorkflowStatus.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/MagallanesAddress.php';
require_once __DIR__ . '/core/HistoricalRecord.php';
require_once __DIR__ . '/core/MagallanesAddress.php';

requireLogin();
Permissions::requireCan('patient.view');

$userRole = Permissions::normalizeRole(($user = currentUser())['role'] ?? '');

$pageTitle         = 'Patient Registry';
$pageSubtitle      = '';
$activePage        = 'records';
$hidePageTitleBar  = true;
$hidePatientSearch = true;

$isReceptionistView = $userRole === ROLE_RECEPTIONIST;
$canEncodeNewPatient = in_array($userRole, [ROLE_RECORDS, ROLE_IT], true);
$canAddHistorical = HistoricalRecord::canAdd($userRole);

$extraCSS = [BASE_URL . '/assets/css/patient-records.css?v=13'];
if ($canAddHistorical) {
    $extraCSS[] = BASE_URL . '/assets/css/historical-record.css?v=8';
}
$extraJS = [BASE_URL . '/assets/js/main.js', BASE_URL . '/assets/js/patient-records.js?v=16'];
if ($canAddHistorical) {
    $extraJS[] = BASE_URL . '/assets/js/historical-record.js?v=11';
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($isReceptionistView): ?>
<div class="alert alert-info border-0 shadow-sm mb-3" style="border-radius:12px">
    <i class="bi bi-eye me-1"></i>
    <strong>View only.</strong> Receptionist can open patient folders for reference but cannot edit encoding or clinical forms.
</div>
<?php endif; ?>

<div class="pr-filters" id="recordsFilters" aria-label="Patient record filters">
    <div class="pr-search" data-patient-search data-patient-search-variant="records-filter">
        <div class="patient-search-widget">
            <div class="patient-search-input-wrap">
                <i class="bi bi-search"></i>
                <input
                    type="search"
                    class="patient-search-input"
                    placeholder="Search Patient ID, Name, PhilHealth No., Family Serial No…"
                    autocomplete="off"
                    aria-label="Search patients"
                    aria-autocomplete="list"
                    aria-controls="recordsPatientSearchResults"
                >
                <button type="button" class="patient-search-clear d-none" aria-label="Clear search">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="patient-search-dropdown" id="recordsPatientSearchResults">
                <div class="patient-search-statusbar" hidden></div>
                <div class="patient-search-loading" hidden>
                    <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    Searching…
                </div>
                <ul class="patient-search-results" role="listbox"></ul>
                <div class="patient-search-empty" hidden>Type to search patients…</div>
            </div>
        </div>
    </div>

    <select id="filterView" aria-label="View">
        <option value="card">Card View</option>
        <option value="folder">Folder View</option>
    </select>

    <select id="filterBarangay" aria-label="Filter by Barangay">
        <option value="">All Barangays</option>
        <?php foreach (MagallanesAddress::barangays() as $barangay): ?>
            <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
        <?php endforeach; ?>
    </select>

    <select id="filterStatus" aria-label="Filter by Status">
        <option value="">All Statuses</option>
        <?php foreach (WorkflowStatus::filterOptions() as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
        <option value="__recorded__">Recorded</option>
        <option value="__not_recorded__">Not Recorded</option>
    </select>

    <select id="filterSex" aria-label="Filter by Sex">
        <option value="">All Sex</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
    </select>

    <input
        type="date"
        id="filterDateEncoded"
        class="pr-date"
        aria-label="Filter by Date Encoded"
        title="Date Encoded"
    >

    <button type="button" class="pr-reset" id="btnClearFilters">Reset</button>

    <?php if ($canEncodeNewPatient): ?>
    <div class="pr-action-buttons">
        <a href="<?= BASE_URL ?>/forms/doh-form.php" class="btn btn-outline-primary btn-sm pr-new-patient">
            <i class="bi bi-plus-lg"></i> New Patient Record
        </a>
        <?php if ($canAddHistorical): ?>
        <button type="button" class="btn btn-outline-primary btn-sm pr-new-patient" id="btnAddExistingRecord">
            <i class="bi bi-plus-lg"></i> Add Existing Record
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="resultsArea">
    <div class="loading-state">
        <div class="loading-spinner"></div>
        <p class="loading-text">Loading patient records…</p>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="rejectionModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Returned by IT — Remarks
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="text-muted small mb-2" id="rejectionPatientName"></p>
                <div class="rejection-reason-box" id="rejectionReasonText"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>window.PR_USER_ROLE = <?= json_encode($userRole) ?>;</script>

<?php if ($canAddHistorical): ?>
<script>window.HR_BARANGAYS = <?= json_encode(MagallanesAddress::barangays()) ?>;</script>
<?php include __DIR__ . '/includes/historical-record-modal.php'; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
