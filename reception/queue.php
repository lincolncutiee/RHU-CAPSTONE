<?php
/**
 * Receptionist My Queue — desk / queue management only.
 * View patient info, call patients, mark serving/complete/skip.
 * Does not edit medical/encoding forms.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/ReceptionQueue.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';
requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');

if ($role !== ROLE_RECEPTIONIST) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle    = 'My Queue';
$pageSubtitle = 'IT-approved patients ready for consultation';
$activePage   = 'workflow';
$extraCSS     = [BASE_URL . '/assets/css/reception-queue.css'];
$extraJS      = [BASE_URL . '/assets/js/reception-queue.js'];

require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
ReceptionQueue::ensureSchema($conn);
$today = ReceptionQueue::today();
$stats = ReceptionQueue::dashboardStats($conn, $today);
$items = ReceptionQueue::listQueue($conn, ['date' => $today]);
$barangays = MagallanesAddress::barangays();
$conn->close();
?>

<div class="reception-queue-page" id="receptionQueuePage"
     data-api="<?= htmlspecialchars(BASE_URL . '/api/reception_queue.php') ?>"
     data-today="<?= htmlspecialchars($today) ?>">

    <div class="rq-header card border-0 shadow-sm">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1 class="rq-title mb-1">Reception Desk Queue</h1>
                <p class="rq-subtitle mb-0">
                    Patients appear here after IT approval. Call the next patient, mark them Serving,
                    then Complete after consultation. Encoding is handled by Records Office.
                </p>
            </div>
            <div class="rq-actions d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary btn-lg" id="btnCallNext">
                    <i class="bi bi-megaphone"></i> Call Next Patient
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnRefreshQueue">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <div class="rq-stats" id="rqStats">
        <div class="rq-stat rq-stat-waiting">
            <span class="rq-stat-value" data-stat="waiting"><?= (int) $stats['waiting'] ?></span>
            <span class="rq-stat-label">Today's Waiting</span>
        </div>
        <div class="rq-stat rq-stat-serving">
            <span class="rq-stat-value" data-stat="serving"><?= (int) $stats['serving'] ?></span>
            <span class="rq-stat-label">Currently Serving</span>
        </div>
        <div class="rq-stat rq-stat-completed">
            <span class="rq-stat-value" data-stat="completed"><?= (int) $stats['completed'] ?></span>
            <span class="rq-stat-label">Completed Today</span>
        </div>
        <div class="rq-stat rq-stat-skipped">
            <span class="rq-stat-value" data-stat="skipped"><?= (int) $stats['skipped'] ?></span>
            <span class="rq-stat-label">Skipped</span>
        </div>
        <div class="rq-stat rq-stat-wait-time">
            <span class="rq-stat-value" data-stat="avg_wait_label"><?= htmlspecialchars($stats['avg_wait_label']) ?></span>
            <span class="rq-stat-label">Average Waiting Time</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm rq-filters-card">
        <div class="card-body">
            <div class="rq-filters" id="rqFilters">
                <div class="rq-filter">
                    <label for="filterPatientId">Patient ID</label>
                    <input type="search" id="filterPatientId" class="form-control form-control-sm" placeholder="Search ID…" autocomplete="off">
                </div>
                <div class="rq-filter">
                    <label for="filterName">Patient Name</label>
                    <input type="search" id="filterName" class="form-control form-control-sm" placeholder="Search name…" autocomplete="off">
                </div>
                <div class="rq-filter">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="waiting">Waiting</option>
                        <option value="serving">Serving</option>
                        <option value="skipped">Skipped</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="rq-filter">
                    <label for="filterBarangay">Barangay</label>
                    <select id="filterBarangay" class="form-select form-select-sm">
                        <option value="">All barangays</option>
                        <?php foreach ($barangays as $brgy): ?>
                            <option value="<?= htmlspecialchars($brgy) ?>"><?= htmlspecialchars($brgy) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rq-filter">
                    <label for="filterDate">Date</label>
                    <input type="date" id="filterDate" class="form-control form-control-sm" value="<?= htmlspecialchars($today) ?>">
                </div>
                <div class="rq-filter rq-filter-actions">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearFilters">Clear</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rq-table-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="receptionQueueTable">
                    <thead class="table-light">
                        <tr>
                            <th>Queue Number</th>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Sex</th>
                            <th>Age</th>
                            <th>Barangay</th>
                            <th>Arrival Time</th>
                            <th>Queue Status</th>
                            <th>Assigned Doctor</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="receptionQueueBody">
                        <?php if (empty($items)): ?>
                            <tr class="rq-empty-row">
                                <td colspan="10" class="text-center text-muted py-5">
                                    No patients in the reception queue for this date.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $arrival = !empty($item['arrival_at'])
                                    ? date('g:i A', strtotime($item['arrival_at']))
                                    : '—';
                                $isServing = $item['desk_status'] === 'serving';
                                ?>
                                <tr data-queue-id="<?= (int) $item['queue_id'] ?>"
                                    data-desk-status="<?= htmlspecialchars($item['desk_status']) ?>"
                                    class="<?= $isServing ? 'rq-row-serving is-highlighted' : '' ?>">
                                    <td class="fw-bold text-primary rq-qnum"><?= htmlspecialchars($item['queue_number'] ?? '—') ?></td>
                                    <td class="small"><?= htmlspecialchars($item['patient_id']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($item['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($item['sex']) ?></td>
                                    <td><?= htmlspecialchars($item['age']) ?></td>
                                    <td><?= htmlspecialchars($item['barangay']) ?></td>
                                    <td class="small"><?= htmlspecialchars($arrival) ?></td>
                                    <td>
                                        <span class="rq-badge rq-badge-<?= htmlspecialchars($item['desk_status']) ?>">
                                            <?= htmlspecialchars($item['desk_status_label']) ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($item['assigned_doctor']) ?></td>
                                    <td class="text-end rq-row-actions">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($item['view_url']) ?>" title="View patient record (read-only)">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <?php if (in_array($item['desk_status'], ['waiting', 'skipped'], true)): ?>
                                            <button type="button" class="btn btn-sm btn-primary" data-action="call">Call Patient</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="serve">Mark Serving</button>
                                        <?php endif; ?>
                                        <?php if ($item['desk_status'] === 'serving'): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-action="complete">Complete</button>
                                        <?php endif; ?>
                                        <?php if (in_array($item['desk_status'], ['waiting', 'serving'], true)): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-action="skip">Skip</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-action="cancel">Cancel</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="rq-footnote text-muted small mt-3 mb-0">
        Reception manages the queue only. Patient records are view-only. Medical encoding and clinical forms stay with Records Office and Doctor.
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
