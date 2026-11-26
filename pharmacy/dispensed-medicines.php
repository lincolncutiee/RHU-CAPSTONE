<?php
/**
 * Pharmacy — log of medicines already dispensed (per prescription line).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/Pharmacy.php';

requireLogin();
requireRole([ROLE_PHARMACY, ROLE_IT]);

if (!Permissions::can('pharmacy.dispensed_log') && !Permissions::can('pharmacy.queue')) {
    http_response_code(403);
    die('You do not have permission to view the dispensed medicines log.');
}

$user = currentUser();
$search = trim((string) ($_GET['q'] ?? ''));
$scope = ($_GET['scope'] ?? 'mine') === 'all' ? 'all' : 'mine';
$view = (string) ($_GET['view'] ?? 'lines');
if (!in_array($view, ['lines', 'patients', 'medicines'], true)) {
    $view = 'lines';
}

$conn = getDBConnection();
Pharmacy::ensureSchema($conn);
$rows = Pharmacy::listDispensedMedicationLog($conn, [
    'mine_only'      => $scope === 'mine',
    'pharmacist_id'  => (int) ($user['id'] ?? 0),
    'search'         => $search,
]);
$conn->close();

$rowCount = count($rows);
$patientGroups = [];
$medicineGroups = [];
foreach ($rows as $r) {
    $patientKey = (string) ($r['patient_db_id'] ?? $r['pin']);
    if (!isset($patientGroups[$patientKey])) {
        $patientGroups[$patientKey] = [
            'patient_name' => $r['patient_name'],
            'pin' => $r['pin'],
            'medicines' => [],
            'last' => $r['dispensed_at'],
            'sort' => (int) ($r['dispensed_sort_ts'] ?? 0),
            'view_url' => $r['view_url'],
        ];
    }
    $medNames = array_keys($r['medicines'] ?? []);
    if ($medNames === [] && !empty($r['medicine'])) {
        $medNames = [(string) $r['medicine']];
    }
    foreach ($medNames as $medName) {
        $patientGroups[$patientKey]['medicines'][(string) $medName] = true;
    }
    if ((int) ($r['dispensed_sort_ts'] ?? 0) >= $patientGroups[$patientKey]['sort']) {
        $patientGroups[$patientKey]['sort'] = (int) $r['dispensed_sort_ts'];
        $patientGroups[$patientKey]['last'] = $r['dispensed_at'];
        $patientGroups[$patientKey]['view_url'] = $r['view_url'];
    }

    foreach ($medNames as $medKey) {
        $medKey = (string) $medKey;
        if ($medKey === '') {
            $medKey = '—';
        }
        if (!isset($medicineGroups[$medKey])) {
            $medicineGroups[$medKey] = [
                'medicine' => $medKey,
                'brand' => (string) ($r['brand'] ?? ''),
                'form' => (string) ($r['form'] ?? '—'),
                'patients' => [],
                'lines' => 0,
                'last' => $r['dispensed_at'],
                'sort' => (int) ($r['dispensed_sort_ts'] ?? 0),
            ];
        }
        $medicineGroups[$medKey]['patients'][$patientKey] = true;
        $medicineGroups[$medKey]['lines']++;
        if ((int) ($r['dispensed_sort_ts'] ?? 0) >= $medicineGroups[$medKey]['sort']) {
            $medicineGroups[$medKey]['sort'] = (int) $r['dispensed_sort_ts'];
            $medicineGroups[$medKey]['last'] = $r['dispensed_at'];
        }
    }
}
$uniquePatients = count($patientGroups);
$uniqueMeds = count($medicineGroups);

$phLogHref = static function (string $nextView, bool $keepSearch = true) use ($search, $scope): string {
    $query = ['scope' => $scope];
    if ($keepSearch && $search !== '') {
        $query['q'] = $search;
    }
    if ($nextView !== 'lines') {
        $query['view'] = $nextView;
    }
    return BASE_URL . '/pharmacy/dispensed-medicines.php?' . http_build_query($query);
};

$patientInitials = static function (string $formalName): string {
    $parts = preg_split('/[\s,]+/', trim($formalName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    if (count($parts) === 1) {
        return strtoupper(substr($parts[0], 0, 2));
    }
    return '?';
};

$splitDispensedAt = static function (string $display): array {
    $display = trim($display);
    if ($display === '' || $display === '—') {
        return ['—', ''];
    }
    if (preg_match('/^(.+?)\s+(\d{1,2}:\d{2}\s+[AP]M)$/u', $display, $m)) {
        return [trim($m[1]), trim($m[2])];
    }

    return [$display, ''];
};

$pageTitle = 'Dispensed Medicines';
$pageSubtitle = 'Medicines recorded as dispensed — one row per consultation';
$activePage = 'pharmacy-dispensed';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/pharmacy.css?v=7'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="ph-page ph-dashboard ph-dispensed-log">
    <div class="ph-shell">
        <a href="<?= BASE_URL ?>/dashboard.php" class="ph-back">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Pharmacy Queue
        </a>

        <header class="ph-header">
            <div class="ph-header-main">
                <span class="ph-header-icon ph-header-icon--log" aria-hidden="true"><i class="bi bi-journal-medical"></i></span>
                <div>
                    <h1 class="ph-title">Dispensed Medicines</h1>
                    <p class="ph-subtitle">Medicines given to patients after dispensing is completed</p>
                </div>
            </div>
            <div class="ph-header-stats">
                <a class="ph-stat-pill ph-stat-pill--total<?= $view === 'lines' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($phLogHref('lines')) ?>">
                    <i class="bi bi-list-check" aria-hidden="true"></i>
                    <strong><?= (int) $rowCount ?></strong>
                    <?= $rowCount === 1 ? 'line' : 'lines' ?>
                </a>
                <?php if ($rowCount > 0): ?>
                <a class="ph-stat-pill ph-stat-pill--waiting<?= $view === 'patients' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($phLogHref('patients')) ?>" title="Show one row per patient">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <strong><?= (int) $uniquePatients ?></strong>
                    <?= $uniquePatients === 1 ? 'patient' : 'patients' ?>
                </a>
                <a class="ph-stat-pill ph-stat-pill--dispensed<?= $view === 'medicines' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($phLogHref('medicines')) ?>" title="Show one row per medicine">
                    <i class="bi bi-capsule" aria-hidden="true"></i>
                    <strong><?= (int) $uniqueMeds ?></strong>
                    <?= $uniqueMeds === 1 ? 'medicine' : 'medicines' ?>
                </a>
                <?php endif; ?>
            </div>
        </header>

        <form class="ph-toolbar ph-toolbar--log" method="get" action="">
            <?php if ($view !== 'lines'): ?>
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
            <?php endif; ?>
            <div class="ph-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    type="search"
                    name="q"
                    id="phLogSearch"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search patient name, PIN, or medicine…"
                    aria-label="Search dispensed medicines"
                    autocomplete="off"
                >
                <?php if ($search !== ''): ?>
                <a href="<?= htmlspecialchars($phLogHref($view, false)) ?>"
                   class="ph-search-clear"
                   aria-label="Clear search">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
            </div>

            <label class="ph-filter">
                <span>Show</span>
                <select name="scope" id="phLogScope" aria-label="Filter by pharmacist">
                    <option value="mine" <?= $scope === 'mine' ? 'selected' : '' ?>>My dispensing only</option>
                    <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All pharmacists</option>
                </select>
            </label>

            <button type="submit" class="btn btn-primary ph-apply">
                <i class="bi bi-funnel" aria-hidden="true"></i> Apply
            </button>
            <a href="<?= BASE_URL ?>/pharmacy/dispensed-medicines.php" class="btn btn-outline-secondary ph-reset">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset
            </a>
        </form>

        <?php if (empty($rows)): ?>
            <div class="ph-empty">
                <i class="bi bi-capsule"></i>
                <h3>No dispensed medicines yet</h3>
                <p>
                    <?php if ($search !== ''): ?>
                        No matches for <strong><?= htmlspecialchars($search) ?></strong>. Try another keyword or reset filters.
                    <?php elseif ($scope === 'mine'): ?>
                        Mark prescriptions as <strong>Dispensed</strong> on the Pharmacy Queue to build your log here.
                    <?php else: ?>
                        No dispensed prescription lines found in the system yet.
                    <?php endif; ?>
                </p>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary mt-3">
                    <i class="bi bi-hourglass-split"></i> Go to Pharmacy Queue
                </a>
            </div>
        <?php elseif ($view === 'patients'): ?>
            <div class="ph-table-wrap">
                <table class="ph-table ph-queue-table ph-log-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Medicines received</th>
                            <th>Last dispensed</th>
                            <th class="ph-actions-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patientGroups as $group):
                            [$datePart, $timePart] = $splitDispensedAt((string) ($group['last'] ?? ''));
                            $medNames = array_keys($group['medicines']);
                        ?>
                        <tr>
                            <td>
                                <div class="ph-patient-cell">
                                    <span class="ph-patient-avatar" aria-hidden="true"><?= htmlspecialchars($patientInitials($group['patient_name'])) ?></span>
                                    <div>
                                        <div class="ph-name"><?= htmlspecialchars($group['patient_name']) ?></div>
                                        <div class="ph-sub"><?= htmlspecialchars($group['pin']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="ph-name"><?= count($medNames) ?> <?= count($medNames) === 1 ? 'medicine' : 'medicines' ?></div>
                                <div class="ph-sub"><?= htmlspecialchars(implode(', ', $medNames)) ?></div>
                            </td>
                            <td>
                                <div class="ph-date"><?= htmlspecialchars($datePart) ?></div>
                                <?php if ($timePart !== ''): ?>
                                <div class="ph-sub"><?= htmlspecialchars($timePart) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="ph-actions ph-actions-col">
                                <a class="btn btn-outline-primary ph-action-btn ph-action-btn--view" href="<?= htmlspecialchars($group['view_url']) ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    <span class="ph-action-label">View</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($view === 'medicines'): ?>
            <div class="ph-table-wrap">
                <table class="ph-table ph-queue-table ph-log-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Form</th>
                            <th class="text-center">Patients</th>
                            <th class="text-center">Times dispensed</th>
                            <th>Last dispensed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicineGroups as $group):
                            [$datePart, $timePart] = $splitDispensedAt((string) ($group['last'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <span class="ph-med-line">
                                    <i class="bi bi-capsule-pill" aria-hidden="true"></i>
                                    <span>
                                        <span class="ph-med-generic"><?= htmlspecialchars($group['medicine']) ?></span>
                                        <?php if ($group['brand'] !== ''): ?>
                                        <span class="ph-sub d-block"><?= htmlspecialchars($group['brand']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td><span class="ph-form-tag"><?= htmlspecialchars($group['form']) ?></span></td>
                            <td class="text-center"><?= count($group['patients']) ?></td>
                            <td class="text-center"><?= (int) $group['lines'] ?></td>
                            <td>
                                <div class="ph-date"><?= htmlspecialchars($datePart) ?></div>
                                <?php if ($timePart !== ''): ?>
                                <div class="ph-sub"><?= htmlspecialchars($timePart) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="ph-table-wrap">
                <table class="ph-table ph-queue-table ph-log-table">
                    <thead>
                        <tr>
                            <th>Date dispensed</th>
                            <th>Patient</th>
                            <th>Medication / Prescription</th>
                            <?php if ($scope === 'all'): ?>
                            <th>Dispensed by</th>
                            <?php endif; ?>
                            <th class="ph-actions-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            [$datePart, $timePart] = $splitDispensedAt((string) ($r['dispensed_at'] ?? ''));
                            $prescription = trim((string) ($r['prescription'] ?? ''));
                            $prescriptionMissing = $prescription === '' || $prescription === '—';
                        ?>
                        <tr>
                            <td>
                                <div class="ph-date"><?= htmlspecialchars($datePart) ?></div>
                                <?php if ($timePart !== ''): ?>
                                <div class="ph-sub"><?= htmlspecialchars($timePart) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="ph-patient-cell">
                                    <span class="ph-patient-avatar" aria-hidden="true"><?= htmlspecialchars($patientInitials($r['patient_name'])) ?></span>
                                    <div>
                                        <div class="ph-name"><?= htmlspecialchars($r['patient_name']) ?></div>
                                        <div class="ph-sub"><?= htmlspecialchars($r['pin']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="ph-prescription-cell">
                                <?php if ($prescriptionMissing): ?>
                                <span class="ph-dose-missing">No prescription notes</span>
                                <?php else: ?>
                                <div class="ph-prescription-text"><?= htmlspecialchars($prescription) ?></div>
                                <?php endif; ?>
                            </td>
                            <?php if ($scope === 'all'): ?>
                            <td class="ph-sub"><?= htmlspecialchars($r['dispensed_by']) ?></td>
                            <?php endif; ?>
                            <td class="ph-actions ph-actions-col">
                                <a class="btn btn-outline-primary ph-action-btn ph-action-btn--view" href="<?= htmlspecialchars($r['view_url']) ?>" title="View dispense record">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    <span class="ph-action-label">View</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
