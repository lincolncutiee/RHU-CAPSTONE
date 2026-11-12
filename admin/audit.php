<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/AdminAudit.php';
require_once __DIR__ . '/../core/AccessLog.php';
requireAdminPermission('admin.audit');

$search = trim($_GET['search'] ?? '');
$eventFilter = trim($_GET['event'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
if (!isset(AccessLog::eventOptions()[$eventFilter])) {
    $eventFilter = '';
}
if (!isset(AccessLog::roleOptions()[$roleFilter])) {
    $roleFilter = '';
}

$conn = getDBConnection();
$accessCounts = AccessLog::counts($conn, $roleFilter);
$access = AccessLog::search($conn, $eventFilter, $roleFilter, 200);
$entries = AdminAudit::listRecent($conn, 250, $search !== '' ? $search : null);
$conn->close();
$accessTotal = (int) $access['total'];
$accessRows = $access['rows'];

$pageTitle = 'Administration — Audit Log';
$activePage = 'admin-audit';
$adminSection = 'audit';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/admin-module.css')];

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h5 mb-1">Sign-in logs</h2>
        <p class="text-muted small mb-3">Who signed in, who was rejected, and who signed out. The counts follow the role filter.</p>
        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-lg-3">
                <label class="form-label small mb-1" for="accessEvent">Event</label>
                <select class="form-select" id="accessEvent" name="event">
                    <option value="">All events</option>
                    <?php foreach (AccessLog::eventOptions() as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $eventFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-lg-3">
                <label class="form-label small mb-1" for="accessRole">Role</label>
                <select class="form-select" id="accessRole" name="role">
                    <option value="">All roles</option>
                    <?php foreach (AccessLog::roleOptions() as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $roleFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="badge text-bg-success">Logged in: <?= (int) $accessCounts['login'] ?></span>
            <span class="badge text-bg-danger">Failed login: <?= (int) $accessCounts['failed'] ?></span>
            <span class="badge text-bg-secondary">Logged out: <?= (int) $accessCounts['logout'] ?></span>
        </div>
        <p class="small text-muted mb-3">
            Showing <?= count($accessRows) ?> of <?= $accessTotal ?> matching record<?= $accessTotal === 1 ? '' : 's' ?>.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Account</th>
                        <th>Role</th>
                        <th>Event</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($accessRows === []): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No sign-in logs for this filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($accessRows as $row): ?>
                            <?php
                            $eventKey = (string) ($row['event'] ?? '');
                            $roleLabel = AccessLog::roleOptions()[(string) ($row['role_slug'] ?? '')] ?? (string) ($row['role_name'] ?? '');
                            ?>
                            <tr>
                                <td class="text-nowrap small"><?= htmlspecialchars((string) $row['created_at']) ?></td>
                                <td><?= htmlspecialchars((string) ($row['username'] ?? '')) ?></td>
                                <td><?= $roleLabel !== '' ? htmlspecialchars($roleLabel) : '—' ?></td>
                                <td><?= htmlspecialchars(AccessLog::eventOptions()[$eventKey] ?? $eventKey) ?></td>
                                <td class="small"><?= htmlspecialchars(AccessLog::reasonLabel($row['reason'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="admin-panel card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Administration actions</h2>
        <form method="get" class="d-flex gap-2 mb-3">
            <?php if ($eventFilter !== ''): ?>
                <input type="hidden" name="event" value="<?= htmlspecialchars($eventFilter) ?>">
            <?php endif; ?>
            <?php if ($roleFilter !== ''): ?>
                <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter) ?>">
            <?php endif; ?>
            <input type="search" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search action, user, target…">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No audit entries yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($entries as $row): ?>
                            <tr>
                                <td class="text-nowrap small"><?= htmlspecialchars($row['created_at']) ?></td>
                                <td><?= htmlspecialchars($row['actor_name'] ?? '') ?><br><span class="text-muted small"><?= htmlspecialchars($row['actor_username'] ?? '') ?></span></td>
                                <td><code><?= htmlspecialchars($row['action']) ?></code></td>
                                <td class="small"><?= htmlspecialchars(trim(($row['target_type'] ?? '') . ' ' . ($row['target_id'] ?? ''))) ?></td>
                                <td class="small text-muted"><?php
                                    if (!empty($row['details']) && is_array($row['details'])) {
                                        echo htmlspecialchars(json_encode($row['details'], JSON_UNESCAPED_UNICODE));
                                    }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
