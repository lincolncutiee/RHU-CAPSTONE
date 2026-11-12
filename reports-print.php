<?php
/**
 * Analytics summary report — explains on-screen charts for the selected period and filters.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/Reports.php';
require_once __DIR__ . '/core/ReportFilters.php';
require_once __DIR__ . '/core/ReportAnalyticsSummary.php';
require_once __DIR__ . '/core/FormTemplate.php';

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
$forecastDays = (int) ($_GET['forecast_days'] ?? 7);
$report = Reports::build($conn, $fromInput, $toInput, $filters, $forecastDays === 14 ? 14 : 7);
$conn->close();

$branding = FormTemplate::branding();
$siteTitle = $branding['site_title'] ?? 'RHU Magallanes';
$siteSubtitle = $branding['site_subtitle'] ?? 'Agusan del Norte';
$logoUrl = FormTemplate::logoUrl();

$range = $report['range'];
$summary = $report['summary'];
$chartKpis = $report['chart_kpis'] ?? [];
$daily = $report['consultations_daily'];
$brgy = $report['cases_by_barangay'];
$sex = $report['sex_mix'];
$age = $report['age_mix'];
$diseases = $report['common_diseases'];
$forecast = $report['case_forecast'] ?? ['rows' => []];
$forecastRows = array_slice($forecast['rows'] ?? [], 0, 8);
$filterLabels = $report['filters']['labels'] ?? [];

$intro = ReportAnalyticsSummary::intro($report);

$sections = [
    ['title' => 'Consultations & trend charts', 'text' => ReportAnalyticsSummary::consultationsNarrative($report)],
    ['title' => 'Cases by barangay chart', 'text' => ReportAnalyticsSummary::barangayNarrative($report)],
    ['title' => 'Consults by age group chart', 'text' => ReportAnalyticsSummary::ageNarrative($report)],
    ['title' => 'Consults by sex chart', 'text' => ReportAnalyticsSummary::sexNarrative($report)],
    ['title' => 'Common diseases chart', 'text' => ReportAnalyticsSummary::diseasesNarrative($report)],
    ['title' => 'Case forecast (projection)', 'text' => ReportAnalyticsSummary::forecastNarrative($report)],
    ['title' => 'Chart summary tiles', 'text' => ReportAnalyticsSummary::chartKpisNarrative($report)],
];

$ageRows = ReportAnalyticsSummary::legendRows($age['labels'] ?? [], $age['values'] ?? []);
$sexRows = ReportAnalyticsSummary::legendRows($sex['labels'] ?? [], $sex['values'] ?? []);
$diseaseRows = array_values(array_filter(
    ReportAnalyticsSummary::legendRows($diseases['labels'] ?? [], $diseases['values'] ?? []),
    static fn ($r) => $r['count'] > 0
));
$brgyRows = ReportAnalyticsSummary::legendRows($brgy['labels'] ?? [], $brgy['values'] ?? []);

$preparedBy = trim((string) ($user['full_name'] ?? ''));
$preparedRole = trim((string) ($user['role_name'] ?? $user['role'] ?? ''));
$generatedAt = (string) ($report['generated_at'] ?? date('M j, Y g:i A'));

$cssPath = __DIR__ . '/assets/css/reports-print.css';
$cssVer = (int) (@filemtime($cssPath) ?: 1);
$autoPrint = !isset($_GET['preview']) || $_GET['preview'] !== '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Summary | <?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png">
    <link href="<?= BASE_URL ?>/assets/css/reports-print.css?v=<?= $cssVer ?>" rel="stylesheet">
</head>
<body class="rpt-print-body">
    <div class="rpt-print-toolbar no-print">
        <p class="rpt-print-toolbar__title">Analytics summary — matches your Reports filters and charts. <strong>Print</strong> or <strong>Save as PDF</strong>.</p>
        <div class="rpt-print-toolbar__actions">
            <button type="button" class="btn btn-primary btn-sm" id="rptPrintDocBtn">Print / Save PDF</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
        </div>
    </div>

    <article class="rpt-print-doc rpt-print-doc--summary">
        <section class="rpt-print-page">
            <header class="rpt-print-header rpt-print-header--compact">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="rpt-print-logo" width="44" height="44">
                <div class="rpt-print-header__text">
                    <h1><?= htmlspecialchars($siteTitle) ?></h1>
                    <p class="rpt-print-subtitle"><?= htmlspecialchars($siteSubtitle) ?> · Analytics Summary Report</p>
                </div>
                <div class="rpt-print-header__meta">
                    <p><strong>Period:</strong> <?= htmlspecialchars($range['from_label']) ?> — <?= htmlspecialchars($range['to_label']) ?></p>
                    <?php if ($filterLabels !== []): ?>
                        <p><strong>Your selection:</strong> <?= htmlspecialchars(implode(' · ', $filterLabels)) ?></p>
                    <?php else: ?>
                        <p><strong>Your selection:</strong> All sexes · All ages</p>
                    <?php endif; ?>
                    <p><strong>Generated:</strong> <?= htmlspecialchars($generatedAt) ?></p>
                </div>
            </header>

            <div class="rpt-print-intro-box">
                <p><?= htmlspecialchars($intro) ?></p>
            </div>

            <div class="rpt-print-summary-grid rpt-print-summary-grid--4">
                <div class="rpt-print-summary-item"><span class="val"><?= (int) ($chartKpis['consultations'] ?? $summary['consultations']) ?></span><span class="lbl">Consultations</span></div>
                <div class="rpt-print-summary-item"><span class="val"><?= (int) ($chartKpis['avg_per_day'] ?? 0) ?></span><span class="lbl">Avg / day</span></div>
                <div class="rpt-print-summary-item"><span class="val"><?= (int) ($chartKpis['barangay_cases'] ?? 0) ?></span><span class="lbl">Barangay cases</span></div>
                <div class="rpt-print-summary-item"><span class="val"><?= (int) ($chartKpis['diagnosis_cases'] ?? 0) ?></span><span class="lbl">Diagnosis cases</span></div>
            </div>

            <?php foreach ($sections as $sec): ?>
                <div class="rpt-print-block rpt-print-block--narrative">
                    <h2 class="rpt-print-section-title"><?= htmlspecialchars($sec['title']) ?></h2>
                    <p class="rpt-print-narrative"><?= htmlspecialchars($sec['text']) ?></p>
                </div>
            <?php endforeach; ?>

            <div class="rpt-print-block">
                <h2 class="rpt-print-section-title">Chart data tables (same numbers as on Reports)</h2>
                <div class="rpt-print-row rpt-print-row--2">
                    <div>
                        <h3 class="rpt-print-h3">Age groups</h3>
                        <?php if ($ageRows === []): ?>
                            <p class="rpt-print-muted">No data.</p>
                        <?php else: ?>
                            <table class="rpt-print-legend rpt-print-legend--compact">
                                <thead><tr><th>Group</th><th class="text-end">Consults</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ageRows as $row): ?>
                                        <tr><td><?= htmlspecialchars($row['label']) ?></td><td class="text-end"><?= (int) $row['count'] ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="rpt-print-h3">Sex</h3>
                        <?php if ($sexRows === []): ?>
                            <p class="rpt-print-muted">No data.</p>
                        <?php else: ?>
                            <table class="rpt-print-legend rpt-print-legend--compact">
                                <thead><tr><th>Sex</th><th class="text-end">Consults</th></tr></thead>
                                <tbody>
                                    <?php foreach ($sexRows as $row): ?>
                                        <tr><td><?= htmlspecialchars($row['label']) ?></td><td class="text-end"><?= (int) $row['count'] ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($brgyRows !== []): ?>
                    <h3 class="rpt-print-h3">Barangay (cases)</h3>
                    <table class="rpt-print-legend rpt-print-legend--compact">
                        <thead><tr><th>Barangay</th><th class="text-end">Cases</th></tr></thead>
                        <tbody>
                            <?php foreach ($brgyRows as $row): ?>
                                <tr><td><?= htmlspecialchars($row['label']) ?></td><td class="text-end"><?= (int) $row['count'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($diseaseRows !== []): ?>
                    <h3 class="rpt-print-h3">Common diseases</h3>
                    <table class="rpt-print-legend rpt-print-legend--compact">
                        <thead><tr><th>Diagnosis</th><th class="text-end">Cases</th></tr></thead>
                        <tbody>
                            <?php foreach ($diseaseRows as $row): ?>
                                <tr><td><?= htmlspecialchars($row['label']) ?></td><td class="text-end"><?= (int) $row['count'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($forecastRows !== []): ?>
                    <h3 class="rpt-print-h3">Forecast</h3>
                    <table class="rpt-print-legend rpt-print-legend--compact rpt-print-forecast">
                        <thead><tr><th>Disease</th><th class="text-end">Last 7d</th><th>Trend</th><th class="text-end">Projected</th></tr></thead>
                        <tbody>
                            <?php foreach ($forecastRows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $row['disease']) ?></td>
                                    <td class="text-end"><?= (int) ($row['recent_7'] ?? $row['recent_14'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($row['alert_label'] ?? $row['trend_label'] ?? '')) ?></td>
                                    <td class="text-end"><?= $row['projected_mid'] === null ? 'Not estimated' : '~' . (int) $row['projected_mid'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="rpt-print-signatures rpt-print-signatures--compact">
                <div class="rpt-print-sig">
                    <div class="rpt-print-sig__line"></div>
                    <p class="rpt-print-sig__label">Prepared by</p>
                    <p class="rpt-print-sig__name"><?= htmlspecialchars($preparedBy) ?> · <?= htmlspecialchars($preparedRole) ?></p>
                </div>
                <div class="rpt-print-sig">
                    <div class="rpt-print-sig__line"></div>
                    <p class="rpt-print-sig__label">Authorized personnel</p>
                    <p class="rpt-print-sig__name">MHO / RHU Head / Designated Officer</p>
                </div>
            </div>

            <footer class="rpt-print-page-foot"><?= htmlspecialchars($siteTitle) ?> · Analytics summary · For official use only</footer>
        </section>
    </article>

    <script>
    const autoPrint = <?= $autoPrint ? 'true' : 'false' ?>;
    document.getElementById('rptPrintDocBtn')?.addEventListener('click', () => window.print());
    if (autoPrint) {
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 350);
        });
    }
    </script>
</body>
</html>
