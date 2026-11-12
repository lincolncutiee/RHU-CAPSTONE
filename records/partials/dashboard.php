<?php
/** @var array<string, mixed> $rd */
/** @var array<string, mixed> $user */
$stats = $rd['stats'];
$workQueue = $rd['work_queue'] ?? [];
$recentEncoded = $rd['recent_encoded'] ?? [];
$weekly = $rd['weekly'] ?? [];
$action = $rd['primaryAction'];
$todayLabel = date('l · M j, Y', strtotime($rd['today']));
$queueTotal = count($workQueue);
$needsAction = (int) $stats['draft'] + (int) $stats['returned'] + (int) $stats['rejected'];
$weeklyMax = max(1, ...array_column($weekly, 'count'));
$recordsUrl = BASE_URL . '/patient-records.php';
?>

<div class="itd-page rcd-page">
    <header class="itd-header">
        <div class="itd-header__text">
            <p class="itd-kicker rcd-kicker"><i class="bi bi-folder2-open"></i> Records Office</p>
            <h2 class="itd-welcome">Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="itd-sub">Patient encoding, drafts, and returned records</p>
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
            <span class="itd-metrics__label">Needs your action</span>
            <div class="row g-2 itd-stats">
                <div class="col-6">
                    <a class="itd-stat-card itd-stat-card--returned" href="<?= htmlspecialchars($recordsUrl . '?status=' . rawurlencode(WORKFLOW_RETURNED_FOR_CORRECTION)) ?>">
                        <div class="itd-stat-icon"><i class="bi bi-arrow-return-left"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['returned'] ?></span>
                            <span class="itd-stat-label">Returned</span>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a class="itd-stat-card itd-stat-card--draft rcd-stat-card--draft" href="<?= htmlspecialchars($recordsUrl . '?status=' . rawurlencode(WORKFLOW_DRAFT)) ?>">
                        <div class="itd-stat-icon"><i class="bi bi-file-earmark"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['draft'] ?></span>
                            <span class="itd-stat-label">Draft</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        <div class="itd-metrics__group itd-metrics__group--today">
            <span class="itd-metrics__label">Output &amp; pipeline</span>
            <div class="row g-2 itd-stats">
                <div class="col-6">
                    <a class="itd-stat-card itd-stat-card--validated" href="<?= htmlspecialchars($recordsUrl . '?date=' . rawurlencode((string) $rd['today'])) ?>">
                        <div class="itd-stat-icon"><i class="bi bi-person-plus"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['encoded_today'] ?></span>
                            <span class="itd-stat-label">Encoded today</span>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a class="itd-stat-card itd-stat-card--ready" href="<?= htmlspecialchars($recordsUrl . '?status=' . rawurlencode(WORKFLOW_WAITING_FOR_DOCTOR)) ?>">
                        <div class="itd-stat-icon"><i class="bi bi-clipboard2-pulse"></i></div>
                        <div class="itd-stat-body">
                            <span class="itd-stat-value"><?= (int) $stats['waiting_for_doctor'] ?></span>
                            <span class="itd-stat-label">Waiting for Doctor</span>
                        </div>
                    </a>
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
                            Encoding worklist
                            <?php if ($queueTotal > 0): ?>
                                <span class="itd-count-pill"><?= $queueTotal ?></span>
                            <?php endif; ?>
                        </h3>
                        <p class="itd-card__sub">
                            Returned, draft, and rejected records — open to continue encoding
                            <?php if ($needsAction > 0): ?>
                                · <span class="itd-today-count"><?= $needsAction ?> need action</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/patient-records.php" class="itd-link">Patient Records <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="itd-card__body p-0">
                    <?php if ($workQueue === []): ?>
                        <div class="itd-empty-state">
                            <div class="itd-empty-state__icon"><i class="bi bi-check2-circle"></i></div>
                            <p class="itd-empty-state__title">No drafts in queue</p>
                            <p class="itd-empty-state__text">Start a new patient or search the registry.</p>
                            <a href="<?= BASE_URL ?>/forms/doh-form.php" class="btn btn-sm btn-primary mt-2">New Patient Encoding</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm itd-table mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Status</th>
                                        <th class="d-none d-md-table-cell">Last update</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workQueue as $row): ?>
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
                                            <td class="itd-cell-meta d-none d-md-table-cell"><?= htmlspecialchars((string) $row['updated_label']) ?></td>
                                            <td class="text-end">
                                                <a href="<?= htmlspecialchars((string) $row['encode_url']) ?>" class="btn btn-sm btn-primary itd-row-btn">
                                                    <i class="bi bi-pencil"></i><span>Encode</span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <footer class="itd-queue-foot">
                            <span><?= $queueTotal ?> shown · <?= $needsAction ?> total need action</span>
                            <a href="<?= BASE_URL ?>/patient-records.php" class="itd-link">View all</a>
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
                            <h3 class="itd-card__title">Encodings this week</h3>
                            <p class="itd-card__sub"><?= (int) $stats['encoded_week'] ?> patient(s) · last 7 days</p>
                        </div>
                    </div>
                    <div class="itd-card__body rcd-week-chart" role="img" aria-label="Weekly encodings bar chart">
                        <?php foreach ($weekly as $bar): ?>
                            <?php
                            $height = max(8, (int) round(($bar['count'] / $weeklyMax) * 100));
                            $barClass = 'rcd-chart__bar' . ($bar['is_today'] ? ' is-today' : '');
                            ?>
                            <div class="rcd-chart__col">
                                <span class="rcd-chart__count"><?= (int) $bar['count'] ?></span>
                                <div class="<?= $barClass ?>" style="height: <?= $height ?>%"></div>
                                <span class="rcd-chart__label"><?= htmlspecialchars($bar['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="itd-card itd-card--side">
                    <div class="itd-card__head itd-card__head--compact">
                        <div>
                            <h3 class="itd-card__title">Recently encoded</h3>
                            <p class="itd-card__sub">Latest registrations</p>
                        </div>
                    </div>
                    <div class="itd-card__body p-0">
                        <?php if ($recentEncoded === []): ?>
                            <p class="itd-side-empty">No new encodings in the last 7 days.</p>
                        <?php else: ?>
                            <ul class="rcd-recent-list">
                                <?php foreach ($recentEncoded as $row): ?>
                                    <li class="rcd-recent-item">
                                        <a href="<?= htmlspecialchars($row['folder_url']) ?>" class="rcd-recent-name"><?= htmlspecialchars($row['patient_name']) ?></a>
                                        <span class="rcd-recent-meta"><?= htmlspecialchars($row['encoded_label']) ?> · <?= htmlspecialchars($row['status_label']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="itd-card itd-card--side rcd-quick-links">
                    <div class="itd-card__body">
                        <a href="<?= BASE_URL ?>/forms/doh-form.php" class="btn btn-outline-primary btn-sm w-100 mb-2"><i class="bi bi-person-plus"></i> New Patient Encoding</a>
                        <a href="<?= BASE_URL ?>/patient-records.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-search"></i> Search Patient Records</a>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
