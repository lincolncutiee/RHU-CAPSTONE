<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/Reports.php';
require_once __DIR__ . '/core/ReportFilters.php';
require_once __DIR__ . '/core/FormTemplate.php';
require_once __DIR__ . '/core/ReceptionQueue.php';

requireLogin();
Permissions::requireCan('reports.view');

$user = currentUser();
$fromInput = trim((string) ($_GET['from'] ?? ''));
$toInput = trim((string) ($_GET['to'] ?? ''));
$filters = ReportFilters::fromRequest(
    isset($_GET['sex']) ? (string) $_GET['sex'] : null,
    isset($_GET['age_group']) ? (string) $_GET['age_group'] : null,
);

$conn = getDBConnection();
$report = Reports::build($conn, $fromInput, $toInput, $filters);
$conn->close();

$filterSex = (string) ($report['filters']['sex'] ?? '');
$filterAge = (string) ($report['filters']['age_group'] ?? '');
$filterQuery = static function (array $extra = []) use ($filterSex, $filterAge): string {
    $q = array_merge(['sex' => $filterSex, 'age_group' => $filterAge], $extra);
    return http_build_query(array_filter($q, static fn ($v) => $v !== '' && $v !== null));
};
$forecast = $report['case_forecast'] ?? ['rows' => []];
$forecastRows = $forecast['rows'] ?? [];

$branding = FormTemplate::branding();
$siteTitle = $branding['site_title'] ?? 'RHU Magallanes';
$logoUrl = FormTemplate::logoUrl();

$pageTitle = 'Reports';
$pageSubtitle = '';
$activePage = 'reports';
$hidePageTitleBar = true;
$hidePatientSearch = true;

$cssPath = __DIR__ . '/assets/css/reports.css';
$jsPath = __DIR__ . '/assets/js/reports.js';
$extraCSS = [BASE_URL . '/assets/css/reports.css?v=' . ((int) @filemtime($cssPath) ?: 1)];
$extraJS = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    BASE_URL . '/assets/js/reports.js?v=' . ((int) @filemtime($jsPath) ?: 1),
];

$range = $report['range'];
$summary = $report['summary'];
$commonDiseases = $report['common_diseases'] ?? [];
$diseaseTypeCount = (int) ($commonDiseases['type_count'] ?? 0);
$diseaseTotal = (int) ($commonDiseases['total_diagnoses'] ?? 0);
$chartKpis = $report['chart_kpis'] ?? [];
$today = ReceptionQueue::today();
$toAnchor = $range['to'];

$presets = [
    ['id' => '7d', 'label' => '7 days', 'from' => date('Y-m-d', strtotime('-6 days', strtotime($toAnchor))), 'to' => $toAnchor],
    ['id' => '30d', 'label' => '30 days', 'from' => date('Y-m-d', strtotime('-29 days', strtotime($toAnchor))), 'to' => $toAnchor],
    ['id' => 'month', 'label' => 'This month', 'from' => date('Y-m-01', strtotime($toAnchor)), 'to' => $toAnchor],
];

$activePreset = '';
foreach ($presets as $p) {
    if ($range['from'] === $p['from'] && $range['to'] === $p['to']) {
        $activePreset = $p['id'];
        break;
    }
}
$activeFilterLabels = $report['filters']['labels'] ?? [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="rpt-page" id="reportsPage">
    <div class="rpt-screen">
        <header class="rpt-header no-print">
            <div class="rpt-header__text">
                <p class="rpt-kicker"><i class="bi bi-bar-chart-line"></i> Management reports</p>
                <h1 class="rpt-title">Analytics</h1>
            </div>
            <div class="rpt-header__actions">
                <span class="rpt-period-pill"><i class="bi bi-calendar3"></i> <?= htmlspecialchars($range['from_label']) ?> – <?= htmlspecialchars($range['to_label']) ?></span>
                <?php if ($activeFilterLabels !== []): ?>
                    <span class="rpt-period-pill rpt-period-pill--filter"><i class="bi bi-funnel"></i> <?= htmlspecialchars(implode(' · ', $activeFilterLabels)) ?></span>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm rpt-print-btn" id="rptPrintBtn">
                    <i class="bi bi-printer"></i> Print summary
                </button>
            </div>
        </header>

        <div class="rpt-toolbar no-print">
            <form class="rpt-filters" method="get" action="<?= BASE_URL ?>/reports.php" id="rptFilterForm">
                <div class="rpt-filters__row">
                    <span class="rpt-filters__group-label">Period</span>
                    <label class="rpt-field">
                        <span>From</span>
                        <input type="date" name="from" id="rptFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($range['from']) ?>" required>
                    </label>
                    <label class="rpt-field">
                        <span>To</span>
                        <input type="date" name="to" id="rptTo" class="form-control form-control-sm" value="<?= htmlspecialchars($range['to']) ?>" max="<?= htmlspecialchars($today) ?>" required>
                    </label>
                    <span class="rpt-filters__group-label rpt-filters__group-label--demographics">Demographics</span>
                    <label class="rpt-field">
                        <span>Sex</span>
                        <select name="sex" class="form-select form-select-sm">
                            <?php foreach (ReportFilters::sexOptions() as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"<?= $filterSex === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="rpt-field">
                        <span>Age group</span>
                        <select name="age_group" class="form-select form-select-sm">
                            <?php foreach (ReportFilters::ageGroupOptions() as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"<?= $filterAge === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Update</button>
                </div>
                <div class="rpt-presets" role="group" aria-label="Quick date ranges">
                    <?php foreach ($presets as $p): ?>
                        <?php $pq = $filterQuery(['from' => $p['from'], 'to' => $p['to']]); ?>
                        <a href="<?= BASE_URL ?>/reports.php?<?= htmlspecialchars($pq) ?>"
                           class="rpt-preset<?= $activePreset === $p['id'] ? ' is-active' : '' ?>"><?= htmlspecialchars($p['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <div class="row g-2 rpt-kpis no-print">
            <div class="col-6 col-md-3">
                <div class="rpt-kpi rpt-kpi--blue">
                    <span class="rpt-kpi__icon"><i class="bi bi-clipboard2-pulse"></i></span>
                    <span class="rpt-kpi__val"><?= (int) ($chartKpis['consultations'] ?? $summary['consultations']) ?></span>
                    <span class="rpt-kpi__lbl">Consultations</span>
                    <span class="rpt-kpi__hint">Daily &amp; trend charts</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rpt-kpi rpt-kpi--teal">
                    <span class="rpt-kpi__icon"><i class="bi bi-graph-up"></i></span>
                    <span class="rpt-kpi__val"><?= (int) ($chartKpis['avg_per_day'] ?? 0) ?></span>
                    <span class="rpt-kpi__lbl">Avg per day</span>
                    <span class="rpt-kpi__hint">Consultation trend</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rpt-kpi rpt-kpi--amber">
                    <span class="rpt-kpi__icon"><i class="bi bi-geo-alt"></i></span>
                    <span class="rpt-kpi__val"><?= (int) ($chartKpis['barangay_cases'] ?? 0) ?></span>
                    <span class="rpt-kpi__lbl">Barangay cases</span>
                    <span class="rpt-kpi__hint">Cases by barangay chart</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="rpt-kpi rpt-kpi--violet">
                    <span class="rpt-kpi__icon"><i class="bi bi-virus2"></i></span>
                    <span class="rpt-kpi__val"><?= (int) ($chartKpis['diagnosis_cases'] ?? $diseaseTotal) ?></span>
                    <span class="rpt-kpi__lbl">Diagnosis cases</span>
                    <span class="rpt-kpi__hint"><?= (int) ($chartKpis['disease_types'] ?? $diseaseTypeCount) ?> type(s) · diseases chart</span>
                </div>
            </div>
        </div>

        <section class="rpt-card rpt-card--forecast no-print">
            <div class="rpt-card__head">
                <span class="rpt-card__icon rpt-card__icon--violet"><i class="bi bi-graph-up-arrow"></i></span>
                <div>
                    <h2 class="rpt-card__title">Case forecast (projection)</h2>
                    <span class="rpt-type-badge">Trend · next <?= (int) ($forecast['horizon_days'] ?? 14) ?> days</span>
                    <p class="rpt-card__hint"><?= htmlspecialchars((string) ($forecast['disclaimer'] ?? '')) ?></p>
                </div>
            </div>
            <?php if ($forecastRows === []): ?>
                <p class="rpt-forecast-empty"><i class="bi bi-info-circle"></i> Not enough diagnosis history to project trends. Encode more consults with diagnoses.</p>
            <?php else: ?>
                <div class="row g-2">
                    <div class="col-lg-7">
                        <div class="table-responsive">
                            <table class="table table-sm rpt-forecast-table">
                                <thead>
                                    <tr>
                                        <th>Disease</th>
                                        <th class="text-end">Last 14d</th>
                                        <th class="text-end">Prior 14d</th>
                                        <th>Trend</th>
                                        <th class="text-end">Projected (next <?= (int) ($forecast['horizon_days'] ?? 14) ?>d)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($forecastRows as $row): ?>
                                        <?php
                                        $trend = (string) ($row['trend'] ?? 'stable');
                                        $badgeClass = match ($trend) {
                                            'up' => 'rpt-trend rpt-trend--up',
                                            'down' => 'rpt-trend rpt-trend--down',
                                            default => 'rpt-trend rpt-trend--stable',
                                        };
                                        $pct = $row['pct_change'];
                                        $pctLabel = $pct === null ? '—' : (($pct >= 0 ? '+' : '') . $pct . '%');
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) $row['disease']) ?></td>
                                            <td class="text-end"><?= (int) ($row['recent_14'] ?? 0) ?></td>
                                            <td class="text-end"><?= (int) ($row['previous_14'] ?? 0) ?> <span class="rpt-muted-sm">(<?= htmlspecialchars($pctLabel) ?>)</span></td>
                                            <td><span class="<?= $badgeClass ?>"><?= htmlspecialchars((string) ($row['trend_label'] ?? '')) ?></span></td>
                                            <td class="text-end">~<?= (int) ($row['projected_mid'] ?? 0) ?> <span class="rpt-muted-sm">(<?= (int) ($row['projected_low'] ?? 0) ?>–<?= (int) ($row['projected_high'] ?? 0) ?>)</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="rpt-chart-shell">
                            <p class="rpt-forecast-chart-title">Top disease — history &amp; projected daily cases</p>
                            <div class="rpt-chart-wrap rpt-chart-wrap--forecast"><canvas id="rptChartForecast"></canvas></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <div class="row g-2 rpt-main">
            <div class="col-xl-8">
                <div class="row g-2">
                    <div class="col-md-6">
                        <section class="rpt-card">
                            <div class="rpt-card__head">
                                <span class="rpt-card__icon rpt-card__icon--blue"><i class="bi bi-bar-chart-fill"></i></span>
                                <div>
                                    <h2 class="rpt-card__title">Daily consultations</h2>
                                    <span class="rpt-type-badge">Column</span>
                                </div>
                            </div>
                            <div class="rpt-chart-shell">
                                <div class="rpt-chart-wrap"><canvas id="rptChartColumn"></canvas></div>
                                <div class="rpt-chart-empty" data-empty-for="column" hidden>
                                    <i class="bi bi-graph-up-arrow"></i>
                                    <p>No completed consults in this range.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="rpt-card">
                            <div class="rpt-card__head">
                                <span class="rpt-card__icon rpt-card__icon--teal"><i class="bi bi-graph-up"></i></span>
                                <div>
                                    <h2 class="rpt-card__title">Consultation trend</h2>
                                    <span class="rpt-type-badge">Line</span>
                                </div>
                            </div>
                            <div class="rpt-chart-shell">
                                <div class="rpt-chart-wrap"><canvas id="rptChartLine"></canvas></div>
                                <div class="rpt-chart-empty" data-empty-for="line" hidden>
                                    <i class="bi bi-graph-up-arrow"></i>
                                    <p>No trend data yet.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="rpt-card">
                            <div class="rpt-card__head">
                                <span class="rpt-card__icon rpt-card__icon--amber"><i class="bi bi-geo-alt"></i></span>
                                <div>
                                    <h2 class="rpt-card__title">Cases by barangay</h2>
                                    <span class="rpt-type-badge">Horizontal bar</span>
                                </div>
                            </div>
                            <div class="rpt-chart-shell">
                                <div class="rpt-chart-wrap rpt-chart-wrap--barh" id="rptBarangayChartWrap"><canvas id="rptChartBarH"></canvas></div>
                                <div class="rpt-chart-empty" data-empty-for="barH" hidden>
                                    <i class="bi bi-map"></i>
                                    <p>No disease cases by barangay in this range.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="rpt-card">
                            <div class="rpt-card__head">
                                <span class="rpt-card__icon rpt-card__icon--green"><i class="bi bi-people"></i></span>
                                <div>
                                    <h2 class="rpt-card__title">Consults by age group</h2>
                                    <span class="rpt-type-badge">Horizontal bar</span>
                                    <p class="rpt-card__hint">All age groups always listed (Youth, Adult, Senior, Unknown).</p>
                                </div>
                            </div>
                            <div class="rpt-chart-shell">
                                <div class="rpt-chart-wrap rpt-chart-wrap--barh rpt-chart-wrap--demo" id="rptAgeChartWrap"><canvas id="rptChartAgeBar"></canvas></div>
                                <div class="rpt-chart-empty" data-empty-for="ageBar" hidden>
                                    <i class="bi bi-pie-chart"></i>
                                    <p>No visits to classify.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="rpt-card">
                            <div class="rpt-card__head">
                                <span class="rpt-card__icon rpt-card__icon--rose"><i class="bi bi-pie-chart"></i></span>
                                <div>
                                    <h2 class="rpt-card__title">Consults by sex</h2>
                                    <span class="rpt-type-badge">Horizontal bar</span>
                                </div>
                            </div>
                            <div class="rpt-chart-shell">
                                <div class="rpt-chart-wrap rpt-chart-wrap--barh rpt-chart-wrap--demo" id="rptSexChartWrap"><canvas id="rptChartSexBar"></canvas></div>
                                <div class="rpt-chart-empty" data-empty-for="sexBar" hidden>
                                    <i class="bi bi-pie-chart"></i>
                                    <p>No visits to classify.</p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="rpt-aside">
                    <section class="rpt-card">
                        <div class="rpt-card__head">
                                <span class="rpt-card__icon rpt-card__icon--slate"><i class="bi bi-virus2"></i></span>
                                <div>
                                    <h2 class="rpt-card__title">Common diseases</h2>
                                    <span class="rpt-type-badge">Doughnut</span>
                                    <?php if ($diseaseTypeCount === 0): ?>
                                        <p class="rpt-card__hint">No diagnoses linked to consults in this period.</p>
                                    <?php elseif ($diseaseTypeCount === 1): ?>
                                        <p class="rpt-card__hint">Only <strong>1</strong> disease type recorded (<?= $diseaseTotal ?> case<?= $diseaseTotal === 1 ? '' : 's' ?>). Add more consults with diagnosis to see a breakdown.</p>
                                    <?php else: ?>
                                        <p class="rpt-card__hint"><?= $diseaseTypeCount ?> disease types · <?= $diseaseTotal ?> total case(s)</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rpt-chart-shell rpt-chart-shell--donut">
                                <div class="rpt-chart-wrap rpt-chart-wrap--workflow"><canvas id="rptChartDoughnutDiseases"></canvas></div>
                                <div class="rpt-chart-empty" data-empty-for="doughnutDiseases" hidden>
                                    <i class="bi bi-virus"></i>
                                    <p>No diagnosed cases in this range.</p>
                                </div>
                        </div>
                    </section>

                    <?php if (Permissions::can('heatmap.view')): ?>
                    <a href="<?= BASE_URL ?>/heat-map.php" class="rpt-heatmap-link no-print">
                        <i class="bi bi-map"></i> Open disease heat map for geographic detail
                    </a>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>

</div>

<script>
window.RHU_REPORTS = <?= json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
