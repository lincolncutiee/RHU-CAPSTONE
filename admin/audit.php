<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/AdminAudit.php';
requireAdminPermission('admin.audit');

$search = trim($_GET['search'] ?? '');
$conn = getDBConnection();
$entries = AdminAudit::listRecent($conn, 250, $search !== '' ? $search : null);
$conn->close();

$pageTitle = 'Administration — Audit Log';
$activePage = 'admin-audit';
$adminSection = 'audit';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/admin-module.css')];

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel card shadow-sm">
    <div class="card-body">
        <form method="get" class="d-flex gap-2 mb-3">
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
