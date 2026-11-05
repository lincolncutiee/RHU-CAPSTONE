<?php
/** @var array<string, mixed> $itd */
/** @var array<string, mixed> $user */
$stats = $itd['stats'];
$queueToday = $itd['queue_today'] ?? [];
$queueBacklog = $itd['queue_backlog'] ?? [];
$queueAll = array_merge($queueToday, $queueBacklog);
$queueTotal = count($queueAll);
$printedToday = $itd['printed_today'];
$action = $itd['primaryAction'];
$todayLabel = date('l · M j, Y', strtotime($itd['today']));
$queueTodayCount = (int) ($stats['queue_today'] ?? count($queueToday));
$openWork = (int) $stats['pending_validation'] + (int) $stats['ready_to_print'] + (int) $stats['returned'];
?>

<div class="itd-page">
    <header class="itd-header">
        <div class="itd-header__text">
            <p class="itd-kicker"><i class="bi bi-pc-display"></i> IT Department</p>
            <h2 class="itd-welcome">Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="itd-sub">Validation, official forms, and print release</p>
        </div>
        <div class="itd-header__actions">
            <span class="itd-date-pill"><i class="bi bi-calendar3"></i> <?= htmlspecialchars($todayLabel) ?></span>
            <a href="<?= htmlspecialchars($action['url']) ?>" class="btn btn-primary itd-primary-btn">
                <i class="bi <?= htmlspecialchars($action['icon']) ?>"></i>
                <?= htmlspecialchars($action['label']) ?>
            </a>
        </div>
    </header>

    <div class="itd-metrics">
        <div class="itd-metrics__group">
            <span class="itd-metrics__label">Open workload</span>
            <div class="row g-2 itd-stats">
                <div class="col-4">
                    <div class="itd-stat-card itd-stat-card--pending">
                        <div class="itd-stat-icon"><i class="bi bi-shield-exclamation"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['pending_validation'] ?></span>
                            <span class="itd-stat-label">Pending validation</span>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="itd-stat-card itd-stat-card--ready">
                        <div class="itd-stat-icon"><i class="bi bi-printer"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['ready_to_print'] ?></span>
                            <span class="itd-stat-label">Ready to print</span>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="itd-stat-card itd-stat-card--returned">
                        <div class="itd-stat-icon"><i class="bi bi-arrow-return-left"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['returned'] ?></span>
                            <span class="itd-stat-label">Returned</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="itd-metrics__group itd-metrics__group--today">
            <span class="itd-metrics__label">Today</span>
            <div class="row g-2 itd-stats">
                <div class="col-6">
                    <div class="itd-stat-card itd-stat-card--validated">
                        <div class="itd-stat-icon"><i class="bi bi-check2-square"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['validated_today'] ?></span>
                            <span class="itd-stat-label">Validated</span>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="itd-stat-card itd-stat-card--printed">
                        <div class="itd-stat-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['printed_today'] ?></span>
                            <span class="itd-stat-label">Printed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 itd-row itd-row--main">
        <div class="col-xl-8">
            <section class="itd-card itd-card--queue">
                <div class="itd-card__head">
                    <div class="itd-card__head-main">
                        <h3 class="itd-card__title">
                            IT queue
                            <?php if ($queueTotal > 0): ?>
                                <span class="itd-count-pill"><?= $queueTotal ?></span>
                            <?php endif; ?>
                        </h3>
                        <p class="itd-card__sub">
                            Review pending records, then release prints
                            <?php if ($queueTodayCount > 0): ?>
                                · <span class="itd-today-count"><?= $queueTodayCount ?> touched today</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/validation-status.php" class="itd-link">Full queue <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="itd-card__body p-0">
                    <?php if (empty($queueAll)): ?>
                        <div class="itd-empty-state">
                            <div class="itd-empty-state__icon"><i class="bi bi-inbox"></i></div>
                            <p class="itd-empty-state__title">Queue is clear</p>
                            <p class="itd-empty-state__text">New submissions from encoding will appear here for IT validation.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm itd-table mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Status</th>
                                        <th class="d-none d-md-table-cell">Consult</th>
                                        <th class="d-none d-lg-table-cell">Updated</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($queueAll as $row): ?>
                                        <tr<?= !empty($row['is_today']) ? ' class="itd-row--today"' : '' ?>>
                                            <td class="itd-cell-patient">
                                                <span class="itd-pid"><?= htmlspecialchars((string) $row['patient_id']) ?></span>
                                                <span class="itd-name"><?= htmlspecialchars((string) $row['patient_name']) ?></span>
                                                <?php if (!empty($row['barangay'])): ?>
                                                    <span class="itd-barangay"><?= htmlspecialchars((string) $row['barangay']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="itd-badge itd-badge--<?= htmlspecialchars((string) $row['badge_class']) ?>">
                                                    <?= htmlspecialchars((string) $row['badge_label']) ?>
                                                </span>
                                                <?php if (!empty($row['is_today'])): ?>
                                                    <span class="itd-badge itd-badge--today">Today</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="itd-cell-meta d-none d-md-table-cell"><?= htmlspecialchars((string) $row['consult_date']) ?></td>
                                            <td class="itd-cell-meta d-none d-lg-table-cell"><?= htmlspecialchars((string) $row['updated_label']) ?></td>
                                            <td class="text-end">
                                                <?php if ($row['action'] === 'print'): ?>
                                                    <a href="<?= htmlspecialchars((string) $row['print_url']) ?>" class="btn btn-sm btn-primary itd-row-btn">
                                                        <i class="bi bi-printer"></i><span>Print</span>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars((string) $row['review_url']) ?>" class="btn btn-sm btn-outline-primary itd-row-btn">
                                                        <i class="bi bi-shield-check"></i><span>Review</span>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <footer class="itd-queue-foot">
                            <span><?= $queueTotal ?> in queue · <?= $openWork ?> need action</span>
                            <a href="<?= BASE_URL ?>/validation-status.php" class="itd-link">Manage all</a>
                        </footer>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <aside class="itd-aside">
                <section class="itd-card itd-card--side">
                    <div class="itd-card__head itd-card__head--compact">
                        <div>
                            <h3 class="itd-card__title">Printed today</h3>
                            <p class="itd-card__sub"><?= (int) $stats['printed_today'] ?> completed</p>
                        </div>
                        <a href="<?= BASE_URL ?>/it/print-queue.php" class="itd-link">Open print</a>
                    </div>
                    <div class="itd-card__body itd-card__body--side">
                        <?php if (empty($printedToday)): ?>
                            <div class="itd-empty-state itd-empty-state--sm">
                                <div class="itd-empty-state__icon"><i class="bi bi-printer"></i></div>
                                <p class="itd-empty-state__title">No prints yet</p>
                                <p class="itd-empty-state__text">Validated and printed counts above update when you finish review or print a record.</p>
                            </div>
                        <?php else: ?>
                            <ul class="itd-done-list">
                                <?php foreach ($printedToday as $row): ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($row['folder_url']) ?>" class="itd-done-item">
                                            <span class="itd-done-name"><?= htmlspecialchars($row['patient_name']) ?></span>
                                            <span class="itd-done-meta"><?= htmlspecialchars($row['patient_id']) ?> · <?= htmlspecialchars($row['printed_at']) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="itd-card itd-card--side">
                    <div class="itd-card__head itd-card__head--compact">
                        <h3 class="itd-card__title mb-0">Shortcuts</h3>
                    </div>
                    <div class="itd-card__body itd-card__body--side pt-0">
                        <div class="itd-action-grid">
                            <a href="<?= BASE_URL ?>/forms/doh-form.php" class="itd-action-tile">
                                <i class="bi bi-person-plus"></i>
                                <span>Encoding</span>
                            </a>
                            <a href="<?= BASE_URL ?>/patient-records.php" class="itd-action-tile">
                                <i class="bi bi-folder2-open"></i>
                                <span>Records</span>
                            </a>
                            <a href="<?= BASE_URL ?>/validation-status.php" class="itd-action-tile">
                                <i class="bi bi-shield-check"></i>
                                <span>Review</span>
                            </a>
                            <a href="<?= BASE_URL ?>/it/print-queue.php" class="itd-action-tile itd-action-tile--accent">
                                <i class="bi bi-printer"></i>
                                <span>Print</span>
                            </a>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
