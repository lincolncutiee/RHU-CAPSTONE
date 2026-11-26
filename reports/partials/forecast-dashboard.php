<?php
/**
 * Disease Case Forecasting dashboard — grouped historical (green) vs OLS forecast (orange).
 *
 * @var array $fcSummary
 * @var array $fcChart
 * @var array $fcWarnings
 * @var array $fcTableRows
 * @var array|null $forecastDash
 * @var int $forecastDays
 * @var string $forecastViewMode
 * @var string $forecastInterval
 */
$fcSummary = $fcSummary ?? [];
$fcChart = $fcChart ?? [];
$fcWarnings = $fcWarnings ?? [];
$fcTableRows = $fcTableRows ?? [];
$trendCode = preg_replace('/[^a-z_]/', '', strtolower((string) ($fcSummary['trend_code'] ?? 'stable'))) ?: 'stable';
?>
<section class="fc-dash no-print" id="caseForecast">
    <div class="row g-2 fc-summary">
        <div class="col-6 col-lg-3">
            <div class="fc-summary-card fc-summary-card--hist">
                <span class="fc-summary-card__lbl">Total Historical Cases</span>
                <strong class="fc-summary-card__val"><?= (int) ($fcSummary['historical_total'] ?? 0) ?></strong>
                <span class="fc-summary-card__hint">Recorded in chart window</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fc-summary-card fc-summary-card--fcst">
                <span class="fc-summary-card__lbl">Total Forecasted Cases</span>
                <strong class="fc-summary-card__val"><?= (int) ($fcSummary['forecasted_total'] ?? 0) ?></strong>
                <span class="fc-summary-card__hint">OLS · next <?= (int) $forecastDays ?> days</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fc-summary-card">
                <span class="fc-summary-card__lbl">Disease Trend</span>
                <strong class="fc-summary-card__val fc-trend fc-trend--<?= htmlspecialchars($trendCode) ?>">
                    <?= htmlspecialchars((string) ($fcSummary['trend'] ?? 'Insufficient Data')) ?>
                </strong>
                <span class="fc-summary-card__hint"><?= htmlspecialchars((string) ($fcSummary['confidence'] ?? '')) ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fc-summary-card fc-summary-card--warn">
                <span class="fc-summary-card__lbl">Early Warning Status</span>
                <strong class="fc-summary-card__val"><?= htmlspecialchars((string) ($fcSummary['early_warning'] ?? 'None')) ?></strong>
                <span class="fc-summary-card__hint">Not a confirmed outbreak</span>
            </div>
        </div>
    </div>

    <div class="fc-panel">
        <div class="fc-panel__head">
            <div>
                <h2 class="fc-panel__title">Historical vs. Forecasted Disease Cases</h2>
                <p class="fc-panel__sub">
                    <?= htmlspecialchars((string) ($forecastDash['disease_label'] ?? 'All active diseases')) ?>
                    · <?= $forecastInterval === 'monthly' ? 'Monthly' : 'Weekly' ?>
                    · OLS linear regression
                </p>
            </div>
        </div>

        <?php if ($forecastViewMode === 'table'): ?>
            <div class="table-responsive fc-table-wrap">
                <table class="table table-sm rpt-forecast-table mb-0">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th class="text-end">Historical cases</th>
                            <th class="text-end">Forecasted cases</th>
                            <th class="text-end">Uncertainty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($fcTableRows === []): ?>
                            <tr><td colspan="4" class="text-muted">No diagnosis history available for this selection.</td></tr>
                        <?php else: ?>
                            <?php foreach ($fcTableRows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $row['period']) ?></td>
                                    <td class="text-end"><?= $row['historical'] === null ? '—' : (int) $row['historical'] ?></td>
                                    <td class="text-end"><?= $row['forecasted'] === null ? '—' : (int) $row['forecasted'] ?></td>
                                    <td class="text-end">
                                        <?php if ($row['forecasted'] === null || $row['low'] === null): ?>
                                            —
                                        <?php else: ?>
                                            <?= (int) $row['low'] ?>–<?= (int) $row['high'] ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="fc-chart-shell">
                <div class="fc-chart-wrap">
                    <canvas id="rptChartForecastBars" aria-label="Historical versus forecasted disease cases"></canvas>
                </div>
                <p class="fc-chart-note"><?= htmlspecialchars((string) ($fcChart['note'] ?? 'Historical cases represent actual recorded disease cases. Forecasted cases for past periods are generated using historical backtesting, while future forecasts are calculated using OLS Linear Regression.')) ?></p>
                <?php if (!empty($fcChart['backtest_n'])): ?>
                <p class="fc-chart-note fc-chart-note--metrics">
                    Backtest accuracy — MAE: <?= htmlspecialchars((string) $fcChart['backtest_mae']) ?>,
                    RMSE: <?= htmlspecialchars((string) $fcChart['backtest_rmse']) ?>
                    (<?= (int) $fcChart['backtest_n'] ?> period<?= (int) $fcChart['backtest_n'] === 1 ? '' : 's' ?>).
                </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="fc-panel fc-panel--warn">
        <div class="fc-panel__head">
            <div>
                <h2 class="fc-panel__title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Disease Outbreak Early Warning</h2>
                <p class="fc-panel__sub">Unusual recent increases compared with the previous 7 days. A predicted rise is not a confirmed outbreak.</p>
            </div>
        </div>
        <?php if ($fcWarnings === []): ?>
            <p class="rpt-forecast-note mb-0">No early-warning flags for this selection. Continue routine monitoring with Heat Map and Analytics.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm rpt-forecast-table mb-0">
                    <thead>
                        <tr>
                            <th>Disease</th>
                            <th class="text-end">Recent 7d</th>
                            <th class="text-end">Projected</th>
                            <th>Trend</th>
                            <th>Confidence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fcWarnings as $row): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars((string) $row['disease']) ?>
                                    <?php if (!empty($row['warning_points'])): ?>
                                        <details class="rpt-forecast-details">
                                            <summary>Why this was flagged</summary>
                                            <dl>
                                                <?php foreach ($row['warning_points'] as $point): ?>
                                                    <div>
                                                        <dt><?= htmlspecialchars((string) $point['label']) ?></dt>
                                                        <dd><?= htmlspecialchars((string) $point['text']) ?></dd>
                                                    </div>
                                                <?php endforeach; ?>
                                            </dl>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= (int) ($row['recent_7'] ?? 0) ?></td>
                                <td class="text-end">
                                    <?php if ($row['projected_7_mid'] === null): ?>
                                        Not estimated
                                    <?php else: ?>
                                        ~<?= (int) $row['projected_7_mid'] ?>
                                        <span class="rpt-muted-sm">(<?= (int) ($row['projected_7_low'] ?? 0) ?>–<?= (int) ($row['projected_7_high'] ?? 0) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="rpt-trend rpt-trend--<?= htmlspecialchars((string) ($row['alert'] ?? 'stable')) ?>"><?= htmlspecialchars((string) ($row['alert_label'] ?? '')) ?></span></td>
                                <td title="<?= htmlspecialchars((string) ($row['confidence_reason'] ?? '')) ?>"><?= htmlspecialchars((string) ($row['confidence_label'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <p class="fc-disclaimer"><?= htmlspecialchars((string) ($forecastDash['disclaimer'] ?? ReportForecast::DISCLAIMER)) ?></p>
</section>
