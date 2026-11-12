<?php
/**
 * Topnav Reports dropdown — Analytics, Case Forecast, Heat Map.
 * Expects $canReports; optional $canHeatMap; uses $activePage.
 */
if (empty($canReports)) {
    return;
}
$canHeatMap = !empty($canHeatMap) || Permissions::can('heatmap.view');
$reportsNavActive = in_array(($activePage ?? ''), ['reports', 'forecast', 'heat-map'], true);
?>
<div class="dropdown topnav-dropdown">
    <button type="button"
            class="topnav-link topnav-dropdown-toggle<?= $reportsNavActive ? ' is-active' : '' ?>"
            data-bs-toggle="dropdown"
            aria-expanded="false">
        Reports <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu topnav-dropdown-menu">
        <li>
            <a class="dropdown-item<?= ($activePage ?? '') === 'reports' ? ' active' : '' ?>"
               href="<?= BASE_URL ?>/reports.php">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i> Analytics
            </a>
        </li>
        <li>
            <a class="dropdown-item<?= ($activePage ?? '') === 'forecast' ? ' active' : '' ?>"
               href="<?= BASE_URL ?>/reports.php?view=forecast">
                <i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Case Forecast
            </a>
        </li>
        <?php if ($canHeatMap): ?>
        <li>
            <a class="dropdown-item<?= ($activePage ?? '') === 'heat-map' ? ' active' : '' ?>"
               href="<?= BASE_URL ?>/heat-map.php">
                <i class="bi bi-map" aria-hidden="true"></i> Heat Map
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>
