<?php
require_once __DIR__ . '/../core/Permissions.php';
$sidebarCanReports = Permissions::can('reports.view');
$sidebarCanHeatMap = Permissions::can('heatmap.view');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png"
                 alt="RHU Magallanes"
                 width="40" height="40">
        </div>
        <div class="brand-text">
            <span class="brand-title">RHU Magallanes</span>
            <span class="brand-subtitle">Agusan del Norte</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php if (($user['role'] ?? '') === ROLE_RECEPTIONIST): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/reception/queue.php" class="nav-link <?= $activePage === 'workflow' ? 'active' : '' ?>">
                    <i class="bi bi-inbox"></i>
                    <span>My Queue</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/patient-records.php" class="nav-link <?= $activePage === 'records' ? 'active' : '' ?>">
                    <i class="bi bi-folder2-open"></i>
                    <span>Patient Records (View Only)</span>
                </a>
            </li>
            <?php if ($sidebarCanReports): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/reports.php" class="nav-link <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($sidebarCanHeatMap): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/heat-map.php" class="nav-link <?= ($activePage ?? '') === 'heat-map' ? 'active' : '' ?>">
                    <i class="bi bi-map"></i>
                    <span>Heat Map</span>
                </a>
            </li>
            <?php endif; ?>
            <?php elseif (($user['role'] ?? '') === ROLE_IT): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/forms/doh-form.php" class="nav-link <?= $activePage === 'encoding' ? 'active' : '' ?>">
                    <i class="bi bi-person-plus"></i>
                    <span>Patient Encoding</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/patient-records.php" class="nav-link <?= $activePage === 'records' ? 'active' : '' ?>">
                    <i class="bi bi-folder2-open"></i>
                    <span>Patient Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/validation-status.php" class="nav-link <?= $activePage === 'validation' ? 'active' : '' ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>IT Review Queue</span>
                </a>
            </li>
            <li class="nav-item">
                <?php
                $printReadyCount = 0;
                try {
                    require_once __DIR__ . '/../core/ItPrintForms.php';
                    $printReadyCount = ItPrintForms::readyToPrintCount();
                } catch (Throwable $e) {
                    $printReadyCount = 0;
                }
                ?>
                <a href="<?= BASE_URL ?>/it/print-queue.php" class="nav-link <?= in_array($activePage, ['it-print', 'it-print-queue'], true) ? 'active' : '' ?>">
                    <i class="bi bi-printer"></i>
                    <span>Print</span>
                    <?php if ($printReadyCount > 0): ?>
                        <span class="nav-badge"><?= $printReadyCount > 99 ? '99+' : (int) $printReadyCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if ($sidebarCanReports): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/reports.php" class="nav-link <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($sidebarCanHeatMap): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/heat-map.php" class="nav-link <?= ($activePage ?? '') === 'heat-map' ? 'active' : '' ?>">
                    <i class="bi bi-map"></i>
                    <span>Heat Map</span>
                </a>
            </li>
            <?php endif; ?>
            <?php elseif (($user['role'] ?? '') === ROLE_PHARMACY): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= in_array($activePage, ['dashboard', 'pharmacy'], true) ? 'active' : '' ?>">
                    <i class="bi bi-capsule"></i>
                    <span>Pharmacy Queue</span>
                </a>
            </li>
            <?php elseif (($user['role'] ?? '') === ROLE_DOCTOR): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/workflow/queue.php" class="nav-link <?= $activePage === 'workflow' ? 'active' : '' ?>">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <span>Doctor Consultation</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/patient-records.php" class="nav-link <?= $activePage === 'records' ? 'active' : '' ?>">
                    <i class="bi bi-folder2-open"></i>
                    <span>Patient Records</span>
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/forms/doh-form.php" class="nav-link <?= $activePage === 'encoding' ? 'active' : '' ?>">
                    <i class="bi bi-person-plus"></i>
                    <span>Patient Information Encoding</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/patient-records.php" class="nav-link <?= $activePage === 'records' ? 'active' : '' ?>">
                    <i class="bi bi-folder2-open"></i>
                    <span>Patient Records</span>
                </a>
            </li>
            <?php if (($user['role'] ?? '') !== ROLE_RECORDS): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/workflow/queue.php" class="nav-link <?= $activePage === 'workflow' ? 'active' : '' ?>">
                    <i class="bi bi-inbox"></i>
                    <span>My Queue</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/notifications.php" class="nav-link <?= $activePage === 'notifications' ? 'active' : '' ?>">
                    <i class="bi bi-bell"></i>
                    <span>Notifications</span>
                    <span class="nav-badge">3</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-divider"></div>

        <ul class="nav-list nav-list-bottom">
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/profile.php" class="nav-link <?= $activePage === 'profile' ? 'active' : '' ?>">
                    <i class="bi bi-person-circle"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/logout.php" class="nav-link nav-link-logout">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
