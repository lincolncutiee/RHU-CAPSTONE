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

/**
 * @return array{pin: string, from: string, remarks: string}
 */
function parseNotificationForTable(array $n): array
{
    $msg = (string) ($n['message'] ?? '');
    $pin = '—';
    $from = '—';
    $remarks = '—';

    if (preg_match('/Patient PIN:\s*([^\r\n]+)/u', $msg, $m)) {
        $pin = trim($m[1]);
    } elseif (preg_match('/Consultation\s+(\S+)\s+was approved/u', $msg, $m)) {
        $pin = trim($m[1]);
    }

    if (preg_match('/Returned for correction by\s+(.+?)\./us', $msg, $m)) {
        $from = trim($m[1]);
    } elseif (preg_match('/approved by\s+(.+?)\s+and is/us', $msg, $m)) {
        $from = trim($m[1]);
    }

    if (preg_match('/Reason \/ remarks:\s*\n([\s\S]*?)(?:\n\nOpen the DOH|\z)/u', $msg, $m)) {
        $remarks = trim($m[1]);
    } elseif (($n['title'] ?? '') === 'Consultation Approved') {
        $remarks = 'Approved — ready for printing';
    } elseif ($msg !== '') {
        $remarks = trim(preg_replace('/\s+/u', ' ', $msg));
        if (strlen($remarks) > 120) {
            $remarks = substr($remarks, 0, 117) . '…';
        }
    }

    return ['pin' => $pin, 'from' => $from, 'remarks' => $remarks];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="card border-0 shadow-sm" style="border-radius:var(--radius-lg);">
    <div class="card-body">
        <div class="d-flex justify-content-end mb-3">
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
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 notifications-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Notification</th>
                            <th scope="col">Patient PIN</th>
                            <th scope="col">From</th>
                            <th scope="col" class="notifications-remarks-col">Reason / remarks</th>
                            <th scope="col">Date</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $n):
                            $meta = parseNotificationForTable($n);
                            $unread = empty($n['is_read']);
                            $title = (string) ($n['title'] ?? 'Notification');
                            $openLabel = $title === 'Returned for Correction' ? 'Open Form' : 'Open';
                        ?>
                            <tr class="<?= $unread ? 'notifications-row-unread' : '' ?>">
                                <td>
                                    <span class="fw-semibold d-block"><?= htmlspecialchars($title) ?></span>
                                    <?php if ($unread): ?>
                                        <span class="badge text-bg-primary notifications-new-badge">New</span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="notifications-pin"><?= htmlspecialchars($meta['pin']) ?></code></td>
                                <td class="text-nowrap"><?= htmlspecialchars($meta['from']) ?></td>
                                <td class="notifications-remarks-col">
                                    <span class="notifications-remarks"><?= htmlspecialchars($meta['remarks']) ?></span>
                                </td>
                                <td class="text-nowrap text-muted small">
                                    <?= date('M d, Y g:i A', strtotime($n['created_at'])) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <?php if (!empty($n['link'])): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($n['link']) ?>">
                                                <?= htmlspecialchars($openLabel) ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($unread): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="?read=<?= (int) $n['id'] ?>">Mark read</a>
                                        <?php else: ?>
                                            <span class="text-muted small px-1">Read</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.notifications-table thead th {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    border-bottom-width: 1px;
    white-space: nowrap;
}
.notifications-row-unread {
    background: #f0f7ff;
}
.notifications-row-unread:hover {
    background: #e8f2fc;
}
.notifications-pin {
    font-size: 0.8rem;
    font-weight: 600;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.15rem 0.45rem;
}
.notifications-remarks-col {
    min-width: 12rem;
    max-width: 22rem;
}
.notifications-remarks {
    display: block;
    font-size: 0.875rem;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}
.notifications-new-badge {
    font-size: 0.65rem;
    margin-top: 0.25rem;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
