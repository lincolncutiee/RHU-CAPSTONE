<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/Permissions.php';
requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');

$pageTitle    = 'Dashboard';
$pageSubtitle = 'RHU Management System Overview';
$activePage   = 'dashboard';

require_once __DIR__ . '/core/ReceptionQueue.php';
require_once __DIR__ . '/core/Pharmacy.php';
require_once __DIR__ . '/core/WorkflowStatus.php';

$conn = getDBConnection();
$stats = [];

$r = $conn->query("SELECT status, COUNT(*) AS cnt FROM patients WHERE is_archived = 0 GROUP BY status");
while ($row = $r->fetch_assoc()) {
    $stats[$row['status']] = (int) $row['cnt'];
}
$total = array_sum($stats);

$rqStats = null;
if ($role === ROLE_RECEPTIONIST) {
    ReceptionQueue::ensureSchema($conn);
    $rqStats = ReceptionQueue::dashboardStats($conn);
}

$pharmacyQueue = [];
$pharmacyStatusFilter = '';
$pharmacySearch = '';
$pharmacySort = 'newest';
if ($role === ROLE_PHARMACY) {
    Pharmacy::ensureSchema($conn);
    $pharmacyStatusFilter = trim((string) ($_GET['status'] ?? ''));
    $pharmacySearch = trim((string) ($_GET['q'] ?? ''));
    $pharmacySort = trim((string) ($_GET['sort'] ?? 'newest'));
    if (!in_array($pharmacySort, ['newest', 'oldest', 'status', 'name'], true)) {
        $pharmacySort = 'newest';
    }
    $pharmacyQueue = Pharmacy::listQueue($conn, [
        'status' => $pharmacyStatusFilter,
        'search' => $pharmacySearch,
        'sort' => $pharmacySort,
    ]);
    $pageTitle = 'Pharmacy Dashboard';
    $pageSubtitle = '';
    $hidePageTitleBar = true;
    $hidePatientSearch = true;
    $extraCSS = [BASE_URL . '/assets/css/pharmacy.css?v=3'];
}
$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($role === ROLE_PHARMACY): ?>
    <?php
    $queue = $pharmacyQueue;
    $statusFilter = $pharmacyStatusFilter;
    $searchQuery = $pharmacySearch;
    $sortBy = $pharmacySort;
    require __DIR__ . '/pharmacy/partials/queue-table.php';
    ?>
<?php elseif ($role === ROLE_RECEPTIONIST && $rqStats): ?>
<div class="row g-3 mb-4">
    <div class="col-md-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= (int) $rqStats['waiting'] ?></span>
                <span class="stat-label">Today's Waiting</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-person-check"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= (int) $rqStats['serving'] ?></span>
                <span class="stat-label">Currently Serving</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= (int) $rqStats['completed'] ?></span>
                <span class="stat-label">Completed Today</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-skip-forward"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= (int) $rqStats['skipped'] ?></span>
                <span class="stat-label">Skipped</span>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-stopwatch"></i></div>
            <div class="stat-info">
                <span class="stat-value" style="font-size:1.2rem"><?= htmlspecialchars($rqStats['avg_wait_label']) ?></span>
                <span class="stat-label">Average Waiting Time</span>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-welcome card border-0 shadow-sm">
    <div class="card-body p-4">
        <h5 class="fw-semibold mb-2">Welcome, <?= htmlspecialchars($user['full_name']) ?></h5>
        <p class="text-muted mb-3">You are logged in as <strong><?= htmlspecialchars($user['role_name']) ?></strong>.</p>
        <div class="quick-actions d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/reception/queue.php" class="btn btn-primary btn-sm"><i class="bi bi-megaphone"></i> Open My Queue</a>
            <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open"></i> Patient Records (View Only)</a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $total ?></span>
                <span class="stat-label">Total Patients</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= ($stats['Pending Receptionist'] ?? 0) + ($stats['Pending Doctor'] ?? 0) + ($stats['Pending IT Review'] ?? 0) + ($stats['Ready for Printing'] ?? 0) ?></span>
                <span class="stat-label">In Progress</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= ($stats['Printed'] ?? 0) + ($stats['Archived'] ?? 0) ?></span>
                <span class="stat-label">Completed</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $stats['Draft'] ?? 0 ?></span>
                <span class="stat-label">Drafts</span>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-welcome card border-0 shadow-sm">
    <div class="card-body p-4">
        <h5 class="fw-semibold mb-2">Welcome, <?= htmlspecialchars($user['full_name']) ?></h5>
        <p class="text-muted mb-3">You are logged in as <strong><?= htmlspecialchars($user['role_name']) ?></strong>.</p>

        <div class="quick-actions d-flex flex-wrap gap-2">
            <?php if ($role === ROLE_RECORDS): ?>
                <a href="<?= BASE_URL ?>/forms/doh-form.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> New Patient Encoding</a>
                <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Search Patient Records</a>
            <?php elseif ($role === ROLE_DOCTOR): ?>
                <a href="<?= BASE_URL ?>/workflow/queue.php" class="btn btn-primary btn-sm"><i class="bi bi-clipboard2-pulse"></i> Doctor Consultation</a>
                <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open"></i> Patient Records</a>
            <?php elseif ($role === ROLE_IT): ?>
                <a href="<?= BASE_URL ?>/forms/doh-form.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> New Patient Encoding</a>
                <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open"></i> Patient Records</a>
                <a href="<?= BASE_URL ?>/it/print-queue.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Print</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Patient Records</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($role !== ROLE_PHARMACY): ?>
<style>
.stat-card { display:flex; align-items:center; gap:1rem; background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius-lg); padding:1.25rem; box-shadow:var(--shadow-sm); }
.stat-icon { width:48px; height:48px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:1.25rem; }
.stat-value { display:block; font-size:1.5rem; font-weight:700; color:#0f172a; line-height:1.2; }
.stat-label { font-size:0.8125rem; color:var(--secondary); }
.dashboard-welcome { border-radius:var(--radius-lg) !important; }
</style>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
