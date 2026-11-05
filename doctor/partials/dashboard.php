<?php
/** @var array<string, mixed> $dd */
/** @var array<string, mixed> $user */
$stats = $dd['stats'];
$waiting = $dd['waiting'];
$recent = $dd['recent'];
$weekly = $dd['weekly'];
$surveillance = $dd['surveillance'];
$action = $dd['primaryAction'];
$todayLabel = date('l · M j, Y', strtotime($dd['today']));
$weeklyMax = max(1, ...array_column($weekly, 'count'));
$followUps = $dd['follow_ups'] ?? [];
?>

<div class="dd-page">
    <header class="dd-header">
        <div class="dd-header__text">
            <h2 class="dd-welcome">Welcome Back, <?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="dd-sub">Doctor dashboard · <?= htmlspecialchars($todayLabel) ?></p>
        </div>
        <div class="dd-header__actions">
            <span class="dd-date-pill"><i class="bi bi-calendar3"></i> Today</span>
            <a href="<?= htmlspecialchars($action['url']) ?>" class="btn btn-primary dd-primary-btn">
                <i class="bi <?= htmlspecialchars($action['icon']) ?>"></i>
                <?= htmlspecialchars($action['label']) ?>
            </a>
        </div>
    </header>

    <div class="row g-2 dd-stats">
        <div class="col-sm-6 col-xl-3">
            <div class="dd-stat-card">
                <div class="dd-stat-icon bg-primary-subtle text-primary"><i class="bi bi-clipboard2-pulse"></i></div>
                <div>
                    <span class="dd-stat-value"><?= (int) $stats['todays_consults'] ?></span>
                    <span class="dd-stat-label">Today's Consults</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dd-stat-card">
                <div class="dd-stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <span class="dd-stat-value"><?= (int) $stats['waiting_in_queue'] ?></span>
                    <span class="dd-stat-label">Waiting in Queue</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dd-stat-card">
                <div class="dd-stat-icon bg-info-subtle text-info"><i class="bi bi-capsule"></i></div>
                <div>
                    <span class="dd-stat-value"><?= (int) $stats['sent_to_pharmacy'] ?></span>
                    <span class="dd-stat-label">Sent to Pharmacy</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="dd-stat-card">
                <div class="dd-stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
                <div>
                    <span class="dd-stat-value"><?= (int) $stats['completed_today'] ?></span>
                    <span class="dd-stat-label">Completed Today</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 dd-row dd-row--panels">
        <div class="col-lg-7">
            <section class="dd-card">
                <div class="dd-card__head">
                    <div>
                        <h3 class="dd-card__title">Waiting for Doctor</h3>
                        <p class="dd-card__sub">Today's queue · FIFO</p>
                    </div>
                    <a href="<?= BASE_URL ?>/workflow/queue.php" class="dd-link">View full queue <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dd-card__body">
                <?php if (empty($waiting)): ?>
                    <div class="dd-empty">
                        <i class="bi bi-clipboard2-check"></i>
                        <p>No patients waiting right now.</p>
                    </div>
                <?php else: ?>
                    <ul class="dd-queue-list">
                        <?php foreach ($waiting as $row): ?>
                            <li class="dd-queue-item">
                                <span class="dd-qnum"><?= htmlspecialchars($row['queue_number']) ?></span>
                                <div class="dd-queue-body">
                                    <strong><?= htmlspecialchars($row['patient_name']) ?></strong>
                                    <span class="dd-queue-meta">
                                        <?= htmlspecialchars($row['barangay']) ?> · <?= htmlspecialchars($row['arrival_label']) ?>
                                    </span>
                                </div>
                                <a href="<?= htmlspecialchars($row['consult_url']) ?>" class="btn btn-sm btn-outline-primary dd-queue-start">
                                    Start
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="dd-card">
                <div class="dd-card__head">
                    <div>
                        <h3 class="dd-card__title">Weekly Consultations</h3>
                        <p class="dd-card__sub">Last 7 days</p>
                    </div>
                </div>
                <div class="dd-card__body dd-chart" role="img" aria-label="Weekly consultation bar chart">
                    <?php foreach ($weekly as $bar): ?>
                        <?php
                        $height = max(8, (int) round(($bar['count'] / $weeklyMax) * 100));
                        $classes = 'dd-chart__bar' . ($bar['is_today'] ? ' is-today' : '');
                        ?>
                        <div class="dd-chart__col">
                            <span class="dd-chart__count"><?= (int) $bar['count'] ?></span>
                            <div class="<?= $classes ?>" style="height: <?= $height ?>%"></div>
                            <span class="dd-chart__label"><?= htmlspecialchars($bar['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-2 dd-row dd-row--panels">
        <div class="col-lg-8">
            <section class="dd-card">
                <div class="dd-card__head">
                    <div>
                        <h3 class="dd-card__title">Recent Consultations</h3>
                        <p class="dd-card__sub">Today's activity</p>
                    </div>
                    <a href="<?= BASE_URL ?>/patient-records.php" class="dd-link">Patient Records <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dd-card__body">
                <?php if (empty($recent)): ?>
                    <div class="dd-empty">
                        <i class="bi bi-inbox"></i>
                        <p>No consultation activity yet today.</p>
                    </div>
                <?php else: ?>
                    <div class="dd-table-wrap">
                        <table class="dd-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>Queue #</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Consultation Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= htmlspecialchars($row['folder_url']) ?>" class="dd-patient-link">
                                                <?= htmlspecialchars($row['patient_name']) ?>
                                            </a>
                                            <span class="dd-pin"><?= htmlspecialchars($row['patient_id']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['queue_number']) ?></td>
                                        <td><?= htmlspecialchars($row['time_label']) ?></td>
                                        <td><?= $row['status_badge'] ?></td>
                                        <td><?= htmlspecialchars($row['consultation_type']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="dd-card">
                <div class="dd-card__head">
                    <div>
                        <h3 class="dd-card__title">Follow-up check-ups</h3>
                        <p class="dd-card__sub">Next 7 days</p>
                    </div>
                </div>
                <div class="dd-card__body">
                <?php if ($followUps === []): ?>
                    <div class="dd-empty dd-empty--compact">
                        <p>No scheduled follow-ups this week.</p>
                    </div>
                <?php else: ?>
                    <ul class="dd-barangay-list">
                        <?php foreach ($followUps as $fu): ?>
                            <li class="dd-barangay-item">
                                <span class="dd-barangay-name">
                                    <a href="<?= htmlspecialchars($fu['folder_url']) ?>" class="dd-patient-link">
                                        <?= htmlspecialchars($fu['patient_name']) ?>
                                    </a>
                                </span>
                                <span class="dd-barangay-count"><?= htmlspecialchars(date('M j', strtotime($fu['follow_up_date']))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </section>
            <section class="dd-card">
                <div class="dd-card__head">
                    <div>
                        <h3 class="dd-card__title">Disease Surveillance</h3>
                        <p class="dd-card__sub">This week</p>
                    </div>
                    <a href="<?= BASE_URL ?>/heat-map.php" class="dd-link">Heat Map <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dd-card__body">
                <div class="dd-surveillance-summary">
                    <div>
                        <span class="dd-surveillance-value"><?= (int) $surveillance['total_cases'] ?></span>
                        <span class="dd-surveillance-label">Active cases</span>
                    </div>
                    <div>
                        <span class="dd-surveillance-value"><?= (int) $surveillance['affected_barangays'] ?></span>
                        <span class="dd-surveillance-label">Barangays affected</span>
                    </div>
                </div>
                <?php if (empty($surveillance['top_barangays'])): ?>
                    <div class="dd-empty dd-empty--compact">
                        <p>No active cases this week.</p>
                    </div>
                <?php else: ?>
                    <ul class="dd-barangay-list">
                        <?php foreach ($surveillance['top_barangays'] as $bg): ?>
                            <li class="dd-barangay-item">
                                <span class="dd-barangay-dot" style="background:<?= htmlspecialchars($bg['color']) ?>"></span>
                                <span class="dd-barangay-name"><?= htmlspecialchars($bg['name']) ?></span>
                                <span class="dd-barangay-count"><?= (int) $bg['count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
