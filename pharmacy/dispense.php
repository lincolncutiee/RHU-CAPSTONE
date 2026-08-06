<?php
/**
 * Pharmacy dispense screen — prescription from consultation_medications.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/Pharmacy.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/PatientSearch.php';

requireLogin();
requireRole([ROLE_PHARMACY]);

$user = currentUser();
$patientId = (int) ($_GET['id'] ?? 0);
$consultationId = (int) ($_GET['consultation_id'] ?? 0);

if ($patientId <= 0) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$conn = getDBConnection();
Pharmacy::ensureSchema($conn);
$case = Pharmacy::loadDispenseCase($conn, $patientId, $consultationId);
$conn->close();

if (!$case) {
    die('Patient or consultation not found.');
}

$patient = $case['patient'];
$treatment = $case['consultation'];
$medications = $case['medications'];
$pharmacy = $case['pharmacy'];
$status = $case['status'];
$consultationId = (int) $case['consultation_id'];

$middle = trim((string) ($patient['middle_name'] ?? ''));
$formalName = trim(
    ($patient['last_name'] ?? '') . ', ' . ($patient['first_name'] ?? '')
    . ($middle !== '' ? ' ' . $middle : '')
);
$barangay = PatientSearch::extractBarangay($patient['residential_address'] ?? null) ?: '—';
$age = calculateAge($patient['birth_date'] ?? null);
$canStart = $status === WORKFLOW_WAITING_FOR_PHARMACY;
$canDispense = in_array($status, [WORKFLOW_WAITING_FOR_PHARMACY, WORKFLOW_DISPENSING], true);
$isDone = $status === WORKFLOW_DISPENSED;

$pageTitle = 'Dispense Medicines';
$pageSubtitle = '';
$activePage = 'dashboard';
$hidePageTitleBar = true;
$hidePatientSearch = true;
$extraCSS = [BASE_URL . '/assets/css/pharmacy.css?v=3'];
$extraJS = [BASE_URL . '/assets/js/pharmacy.js?v=2'];

include __DIR__ . '/../includes/header.php';
?>

<div class="ph-page ph-dispense" id="pharmacyDispense"
     data-patient-id="<?= $patientId ?>"
     data-consultation-id="<?= $consultationId ?>">
    <a href="<?= BASE_URL ?>/dashboard.php" class="ph-back">
        <i class="bi bi-arrow-left"></i> Pharmacy Queue
    </a>

    <header class="ph-header">
        <div>
            <h1 class="ph-title">Dispense Medicines</h1>
            <p class="ph-subtitle">Prescription from Doctor Consultation — same visit, no duplicate records</p>
        </div>
        <div><?= WorkflowStatus::badgeHtml($status) ?></div>
    </header>

    <section class="ph-card">
        <h2 class="ph-section-title">Patient Information</h2>
        <div class="ph-info-grid">
            <div><span class="ph-label">Patient Name</span><strong><?= htmlspecialchars($formalName) ?></strong></div>
            <div><span class="ph-label">Patient ID</span><strong><?= htmlspecialchars($patient['patient_id'] ?? '—') ?></strong></div>
            <div><span class="ph-label">Age / Sex</span><strong><?= htmlspecialchars(($age ?? '—') . ' · ' . ($patient['sex'] ?? '—')) ?></strong></div>
            <div><span class="ph-label">Barangay</span><strong><?= htmlspecialchars($barangay) ?></strong></div>
            <div><span class="ph-label">Contact</span><strong><?= htmlspecialchars($patient['contact_number'] ?: '—') ?></strong></div>
            <div><span class="ph-label">Prescribing Doctor</span><strong><?= htmlspecialchars($case['doctor']) ?></strong></div>
        </div>
    </section>

    <section class="ph-card">
        <h2 class="ph-section-title">Prescription List</h2>
        <?php if (!$medications): ?>
        <p class="ph-muted mb-0">No medications linked to this consultation.</p>
        <?php else: ?>
        <div class="ph-rx-table-wrap">
            <table class="ph-table ph-rx-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Dosage / Strength</th>
                        <th>Form</th>
                        <th>Quantity</th>
                        <th>Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medications as $med): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($med['generic_name'] ?? $med['label']) ?></strong>
                            <?php if (!empty($med['brand_name'])): ?>
                            <div class="ph-sub"><?= htmlspecialchars($med['brand_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($med['strength'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($med['dosage_form'] ?: '—') ?></td>
                        <td>—</td>
                        <td class="ph-muted">As prescribed</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="ph-card">
        <h2 class="ph-section-title">Dispensing</h2>
        <?php if ($isDone): ?>
        <div class="ph-done-box">
            <p><strong>Dispensed By:</strong> <?= htmlspecialchars($pharmacy['dispensed_by_name'] ?? '—') ?></p>
            <p><strong>Date &amp; Time Dispensed:</strong>
                <?= !empty($pharmacy['dispensed_at'])
                    ? htmlspecialchars(date('M d, Y g:i A', strtotime($pharmacy['dispensed_at'])))
                    : '—' ?>
            </p>
            <?php if (!empty($pharmacy['remarks'])): ?>
            <p><strong>Remarks:</strong> <?= htmlspecialchars($pharmacy['remarks']) ?></p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="ph-field">
            <label for="pharmacyRemarks">Dispensing Remarks (optional)</label>
            <textarea id="pharmacyRemarks" class="form-control" rows="3"
                      placeholder="Optional notes for this dispense…"><?= htmlspecialchars($pharmacy['remarks'] ?? '') ?></textarea>
        </div>
        <div class="ph-dispense-actions">
            <?php if ($canStart): ?>
            <button type="button" class="btn btn-outline-primary" id="btnStartDispensing">
                <i class="bi bi-hourglass-split"></i> Mark as Dispensing
            </button>
            <?php endif; ?>
            <?php if ($canDispense): ?>
            <button type="button" class="btn btn-success" id="btnMarkDispensed">
                <i class="bi bi-check2-circle"></i> Mark as Dispensed
            </button>
            <?php endif; ?>
        </div>
        <p class="ph-hint mt-2 mb-0">
            Dispensed by will be recorded as <strong><?= htmlspecialchars($user['full_name'] ?? 'Pharmacist') ?></strong>.
        </p>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
