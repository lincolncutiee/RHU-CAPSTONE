<?php
/**
 * User notifications — workflow events for encoders and staff.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/NotificationService.php';

requireLogin();

$user = currentUser();
$pageTitle = 'Notifications';
$pageSubtitle = 'Workflow updates for your RHU records';
$activePage = 'notifications';

$conn = getDBConnection();
NotificationService::ensureTable($conn);

if (isset($_POST['mark_all_read'])) {
    NotificationService::markRead($conn, (int) $user['id']);
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
}

if (isset($_GET['read'])) {
    NotificationService::markRead($conn, (int) $user['id'], (int) $_GET['read']);
    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
}

$notes = NotificationService::listForUser($conn, (int) $user['id']);
$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="card border-0 shadow-sm" style="border-radius:var(--radius-lg);">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="h5 mb-0">Notifications</h2>
            <form method="post" class="m-0">
                <button type="submit" name="mark_all_read" value="1" class="btn btn-sm btn-outline-secondary">
                    Mark all as read
                </button>
            </form>
        </div>

        <?php if (empty($notes)): ?>
            <div class="empty-state py-4">
                <div class="empty-state-icon"><i class="bi bi-bell"></i></div>
                <h3 class="empty-state-title">No notifications yet</h3>
                <p class="empty-state-text">You will be notified when IT returns or approves a consultation.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notes as $n): ?>
                    <div class="list-group-item px-0 <?= empty($n['is_read']) ? 'bg-primary-subtle' : '' ?>">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($n['title']) ?></div>
                                <div class="small" style="white-space:pre-wrap;"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="text-muted small mt-1"><?= date('M d, Y g:i A', strtotime($n['created_at'])) ?></div>
                            </div>
                            <div class="d-flex gap-2 align-items-start">
                                <?php if (!empty($n['link'])): ?>
                                    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($n['link']) ?>">
                                        Continue Editing
                                    </a>
                                <?php endif; ?>
                                <?php if (empty($n['is_read'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="?read=<?= (int) $n['id'] ?>">Mark read</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
