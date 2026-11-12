<?php

require_once __DIR__ . '/ClinicalMaster.php';
require_once __DIR__ . '/PatientSearch.php';
require_once __DIR__ . '/ReportFilters.php';

/**
 * Case trend and short-range projection from RHU consultation history.
 */
class ReportForecast
{
    public const HISTORY_DAYS = 90;
    public const TREND_DAYS = 7;
    public const DEFAULT_HORIZON = 7;
    public const DISCLAIMER = 'Forecasts are statistical estimates based on recorded RHU consultations. They are not clinical diagnoses or confirmed outbreak predictions. Forecast accuracy depends on the completeness and availability of historical data.';

    /** Preferred disease picks when present in recorded diagnosis names. */
    public const PRIORITY_DISEASES = ['Dengue', 'Influenza', 'Diarrhea'];

    public static function normalizeHorizon(int $days): int
    {
        if ($days === 14 || $days === 30) {
            return $days;
        }

        return self::DEFAULT_HORIZON;
    }

    public static function normalizeInterval(string $interval): string
    {
        return $interval === 'monthly' ? 'monthly' : 'weekly';
    }

    public static function normalizeViewMode(string $mode): string
    {
        return $mode === 'table' ? 'table' : 'chart';
    }

    public static function diseaseForecasts(
        mysqli $conn,
        string $anchorTo,
        ReportFilters $filters,
        int $limit = 8,
        int $horizonDays = self::DEFAULT_HORIZON,
    ): array {
        ClinicalMaster::ensureSchema($conn);
        $horizonDays = self::normalizeHorizon($horizonDays);
        $to = $anchorTo;
        $from = date('Y-m-d', strtotime('-' . (self::HISTORY_DAYS - 1) . ' days', strtotime($to)));

        $dailyByDisease = self::dailyDiagnosisCounts($conn, $from, $to, $filters);
        if ($dailyByDisease === []) {
            return self::emptyPayload($from, $to, $horizonDays);
        }

        $recordedDays = [];
        foreach ($dailyByDisease as $byDay) {
            foreach ($byDay as $day => $count) {
                if ($count > 0) {
                    $recordedDays[$day] = true;
                }
            }
        }
        $recordDayCount = count($recordedDays);

        $windows = self::trendWindows($to);
        $rows = [];
        foreach ($dailyByDisease as $name => $byDay) {
            $rows[] = self::buildDiseaseRow($name, $byDay, $to, $windows, $horizonDays, $recordDayCount);
        }

        usort($rows, static function (array $a, array $b): int {
            $aScore = $a['increase_score'];
            $bScore = $b['increase_score'];
            if ($aScore === null && $bScore === null) {
                return ((int) $b['recent_7']) <=> ((int) $a['recent_7']);
            }
            if ($aScore === null) {
                return 1;
            }
            if ($bScore === null) {
                return -1;
            }
            if ($aScore === $bScore) {
                return ((int) $b['projected_mid']) <=> ((int) $a['projected_mid']);
            }

            return $bScore <=> $aScore;
        });

        $warnings = array_values(array_filter(
            $rows,
            static fn (array $row): bool => in_array($row['alert'], ['increase', 'watch'], true)
                && $row['confidence'] !== 'insufficient'
        ));
        usort($warnings, static function (array $a, array $b): int {
            return ((int) $b['recent_7']) <=> ((int) $a['recent_7']);
        });
        $topIncreases = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['pace_change_7'] !== null && $row['pace_change_7'] > 0
        ));
        usort($topIncreases, static function (array $a, array $b): int {
            $byPace = $b['pace_change_7'] <=> $a['pace_change_7'];
            if ($byPace !== 0) {
                return $byPace;
            }

            return ((int) $b['projected_7_mid']) <=> ((int) $a['projected_7_mid']);
        });
        $topIncreases = array_slice($topIncreases, 0, 5);
        $rows = array_slice($rows, 0, $limit);

        return [
            'horizon_days'   => $horizonDays,
            'trend_days'     => self::TREND_DAYS,
            'history_from'   => $from,
            'history_to'     => $to,
            'method'         => '14-day ordinary least squares trend',
            'rows'           => $rows,
            'early_warnings' => $warnings,
            'top_increases'  => $topIncreases,
            'barangays'      => self::increasingBarangays($conn, $to, $filters),
            'disclaimer'     => self::DISCLAIMER,
        ];
    }

    /**
     * Disease Case Forecasting dashboard payload (grouped historical vs OLS forecast bars).
     *
     * @return array<string, mixed>
     */
    public static function dashboard(
        mysqli $conn,
        string $anchorTo,
        ReportFilters $filters,
        int $horizonDays = self::DEFAULT_HORIZON,
        string $interval = 'weekly',
        ?string $disease = null,
        string $viewMode = 'chart',
    ): array {
        ClinicalMaster::ensureSchema($conn);
        $horizonDays = self::normalizeHorizon($horizonDays);
        $interval = self::normalizeInterval($interval);
        $viewMode = self::normalizeViewMode($viewMode);
        $to = $anchorTo;
        $from = date('Y-m-d', strtotime('-' . (self::HISTORY_DAYS - 1) . ' days', strtotime($to)));

        $dailyByDisease = self::dailyDiagnosisCounts($conn, $from, $to, $filters);
        $diseaseOptions = self::diseaseOptionsFromCounts($dailyByDisease);
        $selectedDisease = self::resolveSelectedDisease($disease, $diseaseOptions, $dailyByDisease, $to);

        $byDay = [];
        if ($selectedDisease === '' || $selectedDisease === 'all') {
            foreach ($dailyByDisease as $series) {
                foreach ($series as $day => $count) {
                    $byDay[$day] = ($byDay[$day] ?? 0) + (int) $count;
                }
            }
            $selectedLabel = 'All recorded diseases';
        } else {
            $byDay = $dailyByDisease[$selectedDisease] ?? [];
            $selectedLabel = $selectedDisease;
        }

        $recordedDays = [];
        foreach ($dailyByDisease as $series) {
            foreach ($series as $day => $count) {
                if ($count > 0) {
                    $recordedDays[$day] = true;
                }
            }
        }
        $recordDayCount = count($recordedDays);

        $chart = self::groupedBarSeries($byDay, $to, $horizonDays, $interval, $recordDayCount);
        $windows = self::trendWindows($to);
        $row = self::buildDiseaseRow($selectedLabel, $byDay, $to, $windows, $horizonDays, $recordDayCount);

        $fullForecast = self::diseaseForecasts($conn, $to, $filters, 12, $horizonDays);
        $warnings = $fullForecast['early_warnings'] ?? [];
        if ($selectedDisease !== '' && $selectedDisease !== 'all') {
            $warnings = array_values(array_filter(
                $warnings,
                static fn (array $w): bool => strcasecmp((string) ($w['disease'] ?? ''), $selectedDisease) === 0
            ));
        }

        $histTotal = 0;
        foreach ($chart['historical'] as $v) {
            if ($v !== null) {
                $histTotal += (int) $v;
            }
        }
        // Summary "forecasted" = future OLS only (not historical backtests).
        $fcTotal = 0;
        $split = (int) ($chart['split_index'] ?? 0);
        foreach ($chart['forecasted'] as $i => $v) {
            if ($i >= $split && $v !== null) {
                $fcTotal += (int) $v;
            }
        }

        $warningStatus = 'None';
        if ($warnings !== []) {
            $hasIncrease = false;
            foreach ($warnings as $w) {
                if (($w['alert'] ?? '') === 'increase') {
                    $hasIncrease = true;
                    break;
                }
            }
            $warningStatus = $hasIncrease ? 'Potential increase' : 'Watch';
        } elseif (in_array($row['alert'] ?? '', ['increase', 'watch'], true) && ($row['confidence'] ?? '') !== 'insufficient') {
            $warningStatus = (string) ($row['alert_label'] ?? 'Watch');
        }

        return [
            'horizon_days'       => $horizonDays,
            'interval'           => $interval,
            'view_mode'          => $viewMode,
            'history_from'       => $from,
            'history_to'         => $to,
            'method'             => '14-day ordinary least squares trend',
            'disease'            => $selectedDisease === 'all' ? 'all' : $selectedDisease,
            'disease_label'      => $selectedLabel,
            'disease_options'    => $diseaseOptions,
            'chart'              => $chart,
            'summary'            => [
                'historical_total'   => $histTotal,
                'forecasted_total'   => $fcTotal,
                'trend'              => (string) ($row['alert_label'] ?? 'Insufficient Data'),
                'trend_code'         => (string) ($row['alert'] ?? 'stable'),
                'early_warning'      => $warningStatus,
                'confidence'         => (string) ($row['confidence_label'] ?? ''),
                'confidence_code'    => (string) ($row['confidence'] ?? 'insufficient'),
                'projected_low'      => $row['projected_low'],
                'projected_mid'      => $row['projected_mid'],
                'projected_high'     => $row['projected_high'],
            ],
            'row'                => $row,
            'early_warnings'     => $warnings,
            'top_increases'      => $fullForecast['top_increases'] ?? [],
            'barangays'          => $fullForecast['barangays'] ?? [],
            'table_rows'         => self::chartTableRows($chart),
            'disclaimer'         => self::DISCLAIMER,
        ];
    }

    /**
     * @param array<string, array<string, int>> $dailyByDisease
     * @return list<array{value:string,label:string}>
     */
    public static function diseaseOptionsFromCounts(array $dailyByDisease): array
    {
        $names = array_keys($dailyByDisease);
        natcasesort($names);
        $names = array_values($names);

        $options = [['value' => 'all', 'label' => 'All diseases']];
        $added = [];

        foreach (self::PRIORITY_DISEASES as $priority) {
            foreach ($names as $name) {
                if (stripos($name, $priority) !== false && !isset($added[$name])) {
                    $options[] = ['value' => $name, 'label' => $name];
                    $added[$name] = true;
                }
            }
        }

        foreach ($names as $name) {
            if (!isset($added[$name])) {
                $options[] = ['value' => $name, 'label' => $name];
                $added[$name] = true;
            }
        }

        return $options;
    }

    /**
     * @param array<string, array<string, int>> $dailyByDisease
     * @param list<array{value:string,label:string}> $options
     */
    private static function resolveSelectedDisease(
        ?string $disease,
        array $options,
        array $dailyByDisease,
        string $anchorTo,
    ): string {
        $disease = trim((string) $disease);
        if ($disease === '' || $disease === 'all') {
            return 'all';
        }
        foreach ($options as $opt) {
            if (($opt['value'] ?? '') === $disease) {
                return $disease;
            }
        }
        if (isset($dailyByDisease[$disease])) {
            return $disease;
        }
        $top = self::highestRecentDisease($dailyByDisease, $anchorTo);

        return $top ?? 'all';
    }

    /**
     * Grouped bars: green = actual history; orange = OLS backtest on past periods + future OLS.
     * Backtesting trains only on days before each target bucket (no leakage).
     *
     * @param array<string, int> $byDay
     * @return array<string, mixed>
     */
    private static function groupedBarSeries(
        array $byDay,
        string $anchorTo,
        int $horizonDays,
        string $interval,
        int $recordDayCount = 0,
    ): array {
        $band = self::projectDailyBand($byDay, $anchorTo, $horizonDays, $recordDayCount);
        $histBuckets = self::buildHistoryBuckets($byDay, $anchorTo, $interval);
        $fcBuckets = self::buildForecastBuckets($band, $anchorTo, $horizonDays, $interval);

        $labels = [];
        $historical = [];
        $forecasted = [];
        $forecastedLow = [];
        $forecastedHigh = [];
        $errors = [];

        foreach ($histBuckets as $bucket) {
            $labels[] = $bucket['label'];
            $actual = (int) $bucket['value'];
            $historical[] = $actual;

            $backtest = self::backtestPeriod(
                $byDay,
                (string) $bucket['from'],
                (string) $bucket['to']
            );
            if ($backtest === null) {
                $forecasted[] = null;
                $forecastedLow[] = null;
                $forecastedHigh[] = null;
            } else {
                $forecasted[] = (int) $backtest['value'];
                $forecastedLow[] = (int) $backtest['low'];
                $forecastedHigh[] = (int) $backtest['high'];
                $errors[] = abs($actual - (int) $backtest['value']);
            }
        }

        foreach ($fcBuckets as $bucket) {
            $labels[] = $bucket['label'];
            $historical[] = null;
            if ($band['estimated']) {
                $forecasted[] = (int) $bucket['value'];
                $forecastedLow[] = (int) $bucket['low'];
                $forecastedHigh[] = (int) $bucket['high'];
            } else {
                $forecasted[] = null;
                $forecastedLow[] = null;
                $forecastedHigh[] = null;
            }
        }

        $accuracy = self::backtestAccuracy($errors);
        $hasAnyBacktest = $errors !== [];
        $note = 'Historical cases represent actual recorded disease cases. Forecasted cases for past periods are generated using historical backtesting, while future forecasts are calculated using OLS Linear Regression.';
        if (!$band['estimated'] && !$hasAnyBacktest) {
            $note = 'Insufficient Data — not enough recorded cases to estimate OLS projections or historical backtests for this selection.';
        } elseif ($accuracy['n'] > 0) {
            $note .= ' Backtest MAE: ' . $accuracy['mae'] . ' · RMSE: ' . $accuracy['rmse'] . ' (n=' . $accuracy['n'] . ').';
        }

        return [
            'title'            => 'Historical vs. Forecasted Disease Cases',
            'labels'           => $labels,
            'historical'       => $historical,
            'forecasted'       => $forecasted,
            'forecasted_low'   => $forecastedLow,
            'forecasted_high'  => $forecastedHigh,
            'split_index'      => count($histBuckets),
            'estimated'        => $band['estimated'] || $hasAnyBacktest,
            'confidence'       => $band['confidence'],
            'interval'         => $interval,
            'y_axis_label'     => 'Number of Disease Cases',
            'x_axis_label'     => $interval === 'monthly' ? 'Month' : 'Week',
            'backtest_mae'     => $accuracy['mae'],
            'backtest_rmse'    => $accuracy['rmse'],
            'backtest_n'       => $accuracy['n'],
            'note'             => $note,
        ];
    }

    /**
     * Rolling backtest: fit OLS only on days before the target period, then project that period.
     *
     * @param array<string, int> $byDay
     * @return array{value:int,low:int,high:int}|null
     */
    private static function backtestPeriod(array $byDay, string $periodFrom, string $periodTo): ?array
    {
        $trainToTs = strtotime('-1 day', strtotime($periodFrom));
        if ($trainToTs === false) {
            return null;
        }
        $trainTo = date('Y-m-d', $trainToTs);

        $trainSeries = [];
        $trainActiveDays = 0;
        foreach ($byDay as $day => $count) {
            if ((string) $day <= $trainTo) {
                $trainSeries[(string) $day] = (int) $count;
                if ((int) $count > 0) {
                    $trainActiveDays++;
                }
            }
        }
        if ($trainSeries === []) {
            return null;
        }

        $periodDays = (int) floor((strtotime($periodTo) - strtotime($periodFrom)) / 86400) + 1;
        if ($periodDays < 1) {
            return null;
        }

        $band = self::projectDailyBand($trainSeries, $trainTo, $periodDays, $trainActiveDays);
        if (!$band['estimated']) {
            return null;
        }

        return [
            'value' => (int) max(0, round(array_sum($band['mid']))),
            'low'   => (int) max(0, round(array_sum($band['low']))),
            'high'  => (int) max(0, round(array_sum($band['high']))),
        ];
    }

    /**
     * @param list<int|float> $absoluteErrors
     * @return array{mae:?float,rmse:?float,n:int}
     */
    private static function backtestAccuracy(array $absoluteErrors): array
    {
        $n = count($absoluteErrors);
        if ($n === 0) {
            return ['mae' => null, 'rmse' => null, 'n' => 0];
        }
        $sum = 0.0;
        $sq = 0.0;
        foreach ($absoluteErrors as $err) {
            $e = (float) $err;
            $sum += $e;
            $sq += $e * $e;
        }

        return [
            'mae'  => round($sum / $n, 2),
            'rmse' => round(sqrt($sq / $n), 2),
            'n'    => $n,
        ];
    }

    /**
     * @param array<string, int> $byDay
     * @return list<array{label:string,from:string,to:string,value:int}>
     */
    private static function buildHistoryBuckets(array $byDay, string $anchorTo, string $interval): array
    {
        $buckets = [];
        if ($interval === 'monthly') {
            $months = 6;
            $cursor = strtotime(date('Y-m-01', strtotime($anchorTo)));
            $cursor = strtotime('-' . ($months - 1) . ' months', $cursor);
            for ($i = 0; $i < $months; $i++) {
                $from = date('Y-m-01', $cursor);
                $to = date('Y-m-t', $cursor);
                if ($to > $anchorTo) {
                    $to = $anchorTo;
                }
                $buckets[] = [
                    'label' => date('M Y', $cursor),
                    'from'  => $from,
                    'to'    => $to,
                    'value' => self::sumRange($byDay, $from, $to),
                ];
                $cursor = strtotime('+1 month', $cursor);
            }

            return $buckets;
        }

        $weeks = 8;
        $anchorTs = strtotime($anchorTo);
        $weekEnd = $anchorTs;
        $starts = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = strtotime('-6 days', $weekEnd);
            array_unshift($starts, [$weekStart, $weekEnd]);
            $weekEnd = strtotime('-1 day', $weekStart);
        }
        foreach ($starts as [$start, $end]) {
            $from = date('Y-m-d', $start);
            $to = date('Y-m-d', $end);
            $buckets[] = [
                'label' => date('M j', $start) . '–' . date('M j', $end),
                'from'  => $from,
                'to'    => $to,
                'value' => self::sumRange($byDay, $from, $to),
            ];
        }

        return $buckets;
    }

    /**
     * @param array{mid:list<float>,low:list<float>,high:list<float>,estimated:bool} $band
     * @return list<array{label:string,value:int,low:int,high:int}>
     */
    private static function buildForecastBuckets(array $band, string $anchorTo, int $horizonDays, string $interval): array
    {
        $mid = $band['mid'] ?? [];
        $low = $band['low'] ?? [];
        $high = $band['high'] ?? [];
        if ($mid === []) {
            return [];
        }

        $buckets = [];
        if ($interval === 'monthly') {
            $cursor = strtotime('+1 day', strtotime($anchorTo));
            $end = strtotime('+' . $horizonDays . ' days', strtotime($anchorTo));
            $dayIndex = 0;
            while ($cursor <= $end && $dayIndex < count($mid)) {
                $monthKey = date('Y-m', $cursor);
                $monthLabel = date('M Y', $cursor);
                $sumMid = 0.0;
                $sumLow = 0.0;
                $sumHigh = 0.0;
                while ($cursor <= $end && date('Y-m', $cursor) === $monthKey && $dayIndex < count($mid)) {
                    $sumMid += (float) $mid[$dayIndex];
                    $sumLow += (float) ($low[$dayIndex] ?? 0);
                    $sumHigh += (float) ($high[$dayIndex] ?? 0);
                    $cursor = strtotime('+1 day', $cursor);
                    $dayIndex++;
                }
                $buckets[] = [
                    'label' => $monthLabel . ' (fcst)',
                    'value' => (int) max(0, round($sumMid)),
                    'low'   => (int) max(0, round($sumLow)),
                    'high'  => (int) max(0, round($sumHigh)),
                ];
            }

            return $buckets;
        }

        $dayIndex = 0;
        $weekNum = 1;
        while ($dayIndex < count($mid)) {
            $take = min(7, count($mid) - $dayIndex);
            $sumMid = 0.0;
            $sumLow = 0.0;
            $sumHigh = 0.0;
            for ($i = 0; $i < $take; $i++) {
                $sumMid += (float) $mid[$dayIndex + $i];
                $sumLow += (float) ($low[$dayIndex + $i] ?? 0);
                $sumHigh += (float) ($high[$dayIndex + $i] ?? 0);
            }
            $start = strtotime('+' . ($dayIndex + 1) . ' days', strtotime($anchorTo));
            $finish = strtotime('+' . ($dayIndex + $take) . ' days', strtotime($anchorTo));
            $buckets[] = [
                'label' => 'Next ' . $weekNum . ' · ' . date('M j', $start) . '–' . date('M j', $finish),
                'value' => (int) max(0, round($sumMid)),
                'low'   => (int) max(0, round($sumLow)),
                'high'  => (int) max(0, round($sumHigh)),
            ];
            $dayIndex += $take;
            $weekNum++;
        }

        return $buckets;
    }

    /**
     * @param array<string, mixed> $chart
     * @return list<array{period:string,historical:?int,forecasted:?int,low:?int,high:?int}>
     */
    private static function chartTableRows(array $chart): array
    {
        $rows = [];
        $labels = $chart['labels'] ?? [];
        $hist = $chart['historical'] ?? [];
        $fc = $chart['forecasted'] ?? [];
        $low = $chart['forecasted_low'] ?? [];
        $high = $chart['forecasted_high'] ?? [];
        foreach ($labels as $i => $label) {
            $rows[] = [
                'period'     => (string) $label,
                'historical' => $hist[$i],
                'forecasted' => $fc[$i],
                'low'        => $low[$i] ?? null,
                'high'       => $high[$i] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array{labels:list<string>,historical:list<int>,forecast:list<int|null>,split_index:int}
     */
    public static function topDiseaseSeries(
        mysqli $conn,
        string $anchorTo,
        ReportFilters $filters,
        int $historyDays = 42,
        int $forecastDays = self::DEFAULT_HORIZON,
        ?string $disease = null,
    ): array {
        $forecastDays = self::normalizeHorizon($forecastDays);
        $to = $anchorTo;
        $from = date('Y-m-d', strtotime('-' . ($historyDays - 1) . ' days', strtotime($to)));
        $dailyByDisease = self::dailyDiagnosisCounts($conn, $from, $to, $filters);
        if ($dailyByDisease === []) {
            return self::emptySeries();
        }

        $top = $disease !== null && isset($dailyByDisease[$disease])
            ? $disease
            : self::highestRecentDisease($dailyByDisease, $to);
        if ($top === null) {
            return self::emptySeries();
        }
        $byDay = $dailyByDisease[$top];

        $histLabels = [];
        $histValues = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $d = date('Y-m-d', $cur);
            $histLabels[] = date('M j', $cur);
            $histValues[] = $byDay[$d] ?? 0;
            $cur = strtotime('+1 day', $cur);
        }

        $band = self::projectDailyBand($byDay, $to, $forecastDays);
        $forecastLabels = [];
        $fc = strtotime('+1 day', strtotime($to));
        for ($i = 0; $i < $forecastDays; $i++) {
            $forecastLabels[] = date('M j', $fc);
            $fc = strtotime('+1 day', $fc);
        }

        $histCount = count($histValues);
        if (!$band['estimated']) {
            $forecastMid = array_fill(0, $histCount + $forecastDays, null);
            $forecastLow = $forecastMid;
            $forecastHigh = $forecastMid;
        } else {
            $forecastMid = array_merge(array_fill(0, $histCount, null), $band['mid']);
            $forecastLow = array_merge(array_fill(0, $histCount, null), $band['low']);
            $forecastHigh = array_merge(array_fill(0, $histCount, null), $band['high']);
            if ($histCount > 0) {
                $bridge = $histValues[$histCount - 1];
                $forecastMid[$histCount - 1] = $bridge;
                $forecastLow[$histCount - 1] = $bridge;
                $forecastHigh[$histCount - 1] = $bridge;
            }
        }

        return [
            'disease'       => $top,
            'estimated'     => $band['estimated'],
            'confidence'    => $band['confidence'],
            'labels'        => array_merge($histLabels, $forecastLabels),
            'historical'    => array_merge($histValues, array_fill(0, $forecastDays, null)),
            'forecast'      => $forecastMid,
            'forecast_low'  => $forecastLow,
            'forecast_high' => $forecastHigh,
            'split_index'   => $histCount,
            'note'          => $band['estimated']
                ? 'Dashed line is the projected estimate. The shaded band is the uncertainty range.'
                : 'Not enough recorded cases to draw a projection. A flat zero is not shown as proof the disease cannot occur.',
        ];
    }

    /** @return array<string, array<string, int>> disease => day => count */
    private static function dailyDiagnosisCounts(
        mysqli $conn,
        string $from,
        string $to,
        ReportFilters $filters,
    ): array {
        $types = ['ss'];
        $params = [$from, $to];
        $demo = $filters->appendSql('p', $types, $params);

        $sql = "SELECT
                    tr.id AS consultation_id,
                    tr.diagnosis AS legacy_diagnosis,
                    DATE(COALESCE(tr.date_of_consultation, tr.created_at)) AS consult_day,
                    dm.id AS diagnosis_id,
                    dm.diagnosis_name
                FROM treatment_records tr
                INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
                LEFT JOIN consultation_diagnoses cd ON cd.consultation_id = tr.id
                LEFT JOIN diagnosis_master dm ON dm.id = cd.diagnosis_id AND dm.status = 'active'
                WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) BETWEEN ? AND ?{$demo}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        /** @var array<int, list<array{id:int,name:string}>> $dxByConsult */
        $dxByConsult = [];
        /** @var array<int, string> $dayByConsult */
        $dayByConsult = [];

        while ($row = $result->fetch_assoc()) {
            $cid = (int) $row['consultation_id'];
            $dayByConsult[$cid] = (string) $row['consult_day'];
            if (!isset($dxByConsult[$cid])) {
                $dxByConsult[$cid] = [];
            }
            if (!empty($row['diagnosis_id'])) {
                $dxByConsult[$cid][(int) $row['diagnosis_id']] = (string) $row['diagnosis_name'];
            }
        }
        $stmt->close();

        foreach ($dxByConsult as $cid => $existing) {
            if ($existing !== []) {
                continue;
            }
            $legacyStmt = $conn->prepare('SELECT diagnosis FROM treatment_records WHERE id = ? LIMIT 1');
            $legacyStmt->bind_param('i', $cid);
            $legacyStmt->execute();
            $legacyRow = $legacyStmt->get_result()->fetch_assoc();
            $legacyStmt->close();
            $legacy = trim((string) ($legacyRow['diagnosis'] ?? ''));
            if ($legacy === '') {
                continue;
            }
            foreach (ClinicalMaster::resolveDiagnosesFromLabels($conn, $legacy) as $dx) {
                $id = (int) ($dx['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $dxByConsult[$cid][$id] = (string) ($dx['diagnosis_name'] ?? $dx['label'] ?? 'Unknown');
            }
        }

        /** @var array<string, array<string, int>> $out */
        $out = [];
        foreach ($dxByConsult as $cid => $dxList) {
            $day = $dayByConsult[$cid] ?? null;
            if ($day === null) {
                continue;
            }
            foreach ($dxList as $name) {
                $name = trim($name) !== '' ? trim($name) : 'Unknown';
                if (!isset($out[$name])) {
                    $out[$name] = [];
                }
                $out[$name][$day] = ($out[$name][$day] ?? 0) + 1;
            }
        }

        return $out;
    }

    /** @param array<string, int> $byDay */
    private static function sumRange(array $byDay, string $from, string $to): int
    {
        $sum = 0;
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $d = date('Y-m-d', $cur);
            $sum += $byDay[$d] ?? 0;
            $cur = strtotime('+1 day', $cur);
        }
        return $sum;
    }

    /**
     * @return array{recent_start:string,recent_end:string,prev_start:string,prev_end:string}
     */
    private static function trendWindows(string $anchorTo): array
    {
        $recentEnd = $anchorTo;
        $recentStart = date('Y-m-d', strtotime('-' . (self::TREND_DAYS - 1) . ' days', strtotime($recentEnd)));
        $prevEnd = date('Y-m-d', strtotime('-1 day', strtotime($recentStart)));
        $prevStart = date('Y-m-d', strtotime('-' . (self::TREND_DAYS - 1) . ' days', strtotime($prevEnd)));

        return [
            'recent_start' => $recentStart,
            'recent_end'   => $recentEnd,
            'prev_start'   => $prevStart,
            'prev_end'     => $prevEnd,
        ];
    }

    /**
     * @param array<string, int> $byDay
     * @param array{recent_start:string,recent_end:string,prev_start:string,prev_end:string} $windows
     * @return array<string, mixed>
     */
    private static function buildDiseaseRow(
        string $name,
        array $byDay,
        string $anchorTo,
        array $windows,
        int $horizonDays,
        int $recordDayCount = 0,
    ): array {
        $recent = self::sumRange($byDay, $windows['recent_start'], $windows['recent_end']);
        $previous = self::sumRange($byDay, $windows['prev_start'], $windows['prev_end']);
        $sufficiency = self::assessSeries($byDay, $anchorTo, $recordDayCount);
        $alert = self::classifyAlert($recent, $previous, $sufficiency['code']);
        $canEstimate = $sufficiency['code'] !== 'insufficient';
        $projection = $canEstimate
            ? self::projectNextDays($byDay, $anchorTo, $horizonDays, $recordDayCount)
            : ['mid' => null, 'low' => null, 'high' => null];
        $projection7 = $canEstimate
            ? self::projectNextDays($byDay, $anchorTo, 7, $recordDayCount)
            : ['mid' => null, 'low' => null, 'high' => null];
        $baseline = $recent * ($horizonDays / self::TREND_DAYS);
        $increase = $projection['mid'] === null ? null : round($projection['mid'] - $baseline, 2);
        $paceChange = $projection7['mid'] === null ? null : round($projection7['mid'] - $recent, 2);
        $points = self::warningPoints($recent, $previous, $alert, $sufficiency, $projection7, $paceChange);

        $zeroNote = '';
        if ($recent === 0) {
            $zeroNote = $previous === 0
                ? 'No recorded cases in the last 14 days. Zero records do not mean this disease cannot occur later.'
                : 'No cases were recorded in the last 7 days. That does not mean this disease cannot occur.';
        }

        return [
            'disease'          => $name,
            'recent_7'         => $recent,
            'previous_7'       => $previous,
            'recent_14'        => $recent,
            'previous_14'      => $previous,
            'pct_change'       => self::pctChange($recent, $previous),
            'trend'            => $alert['code'],
            'trend_label'      => $alert['label'],
            'alert'            => $alert['code'],
            'alert_label'      => $alert['label'],
            'confidence'       => $sufficiency['code'],
            'confidence_label' => $sufficiency['label'],
            'confidence_reason'=> $sufficiency['reason'],
            'warning_points'   => $points,
            'projected_mid'    => $projection['mid'],
            'projected_low'    => $projection['low'],
            'projected_high'   => $projection['high'],
            'projected_7_mid'  => $projection7['mid'],
            'projected_7_low'  => $projection7['low'],
            'projected_7_high' => $projection7['high'],
            'increase_score'   => $increase,
            'expected_change'  => $paceChange,
            'pace_change_7'    => $paceChange,
            'above_recent_pace'=> $paceChange !== null && $paceChange > 0,
            'zero_note'        => $zeroNote,
        ];
    }

    /**
     * Ordinary least squares fit of the last $window daily counts.
     *
     * @param array<string, int> $byDay
     * @return array{values:list<float>,avg:float,std:float,slope:float,r2:float}
     */
    private static function fitWindow(array $byDay, string $anchorTo, int $window): array
    {
        $values = [];
        for ($i = $window - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days", strtotime($anchorTo)));
            $values[] = (float) ($byDay[$d] ?? 0);
        }
        $n = count($values);
        $avg = $n > 0 ? array_sum($values) / $n : 0.0;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $avg) ** 2;
        }
        $std = $n > 1 ? sqrt($variance / ($n - 1)) : 0.0;

        $slope = 0.0;
        $r2 = 0.0;
        if ($n >= 2) {
            $sumX = 0.0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumX2 = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $values[$i];
                $sumXY += $i * $values[$i];
                $sumX2 += $i * $i;
            }
            $den = ($n * $sumX2) - ($sumX * $sumX);
            if (abs($den) > 1e-9) {
                $slope = (($n * $sumXY) - ($sumX * $sumY)) / $den;
            }
            $meanX = $sumX / $n;
            $intercept = $avg - ($slope * $meanX);
            $ssTot = $variance;
            $ssRes = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $predicted = $intercept + ($slope * $i);
                $ssRes += ($values[$i] - $predicted) ** 2;
            }
            if ($ssTot <= 1e-9) {
                $r2 = 1.0;
            } else {
                $r2 = max(0.0, 1 - ($ssRes / $ssTot));
            }
        }

        return [
            'values' => $values,
            'avg' => $avg,
            'std' => $std,
            'slope' => $slope,
            'r2' => $r2,
        ];
    }

    /**
     * Confidence uses history span, how many days actually have cases, and how well a
     * 14-day straight line fits. A large recent count alone cannot produce High.
     *
     * @param array<string, int> $byDay
     * @return array{code:string,label:string,reason:string,span_days:int,active_days:int,cases:int,r2:float}
     */
    private static function assessSeries(array $byDay, string $anchorTo, int $recordDayCount = 0): array
    {
        $anchor = strtotime($anchorTo);
        $first = null;
        $cases = 0;
        $activeDays = 0;
        for ($i = self::HISTORY_DAYS - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days", $anchor));
            $count = (int) ($byDay[$d] ?? 0);
            $cases += $count;
            if ($count > 0) {
                $activeDays++;
                if ($first === null) {
                    $first = $d;
                }
            }
        }

        $spanDays = $first === null ? 0 : ((int) (($anchor - strtotime($first)) / 86400) + 1);
        $fit = self::fitWindow($byDay, $anchorTo, 14);
        $windowActive = 0;
        $windowCases = 0.0;
        foreach ($fit['values'] as $value) {
            $windowCases += $value;
            if ($value > 0) {
                $windowActive++;
            }
        }
        // A flat zero window has R² of 1 and is not evidence the disease has stopped.
        $r2 = $windowCases <= 0 ? 0.0 : $fit['r2'];

        if ($cases === 0 || $activeDays < 2) {
            return [
                'code' => 'insufficient',
                'label' => 'Insufficient Data',
                'reason' => 'cases were recorded on fewer than two separate days in the last 90 days, so a trend cannot be estimated',
                'span_days' => $spanDays,
                'active_days' => $activeDays,
                'cases' => $cases,
                'r2' => $r2,
            ];
        }

        if ($spanDays < 21 || $activeDays < 4 || $cases < 6 || $windowActive < 2) {
            $why = [];
            if ($windowCases <= 0 && $cases > 0) {
                $why[] = 'the last 14 days used by the projection have no recorded cases, while older records still exist';
            } elseif ($windowActive < 2) {
                $why[] = 'the last 14 days include cases on only ' . $windowActive . ' day(s)';
            }
            if ($spanDays < 21) {
                $why[] = 'the first recorded case is only ' . $spanDays . ' day(s) before the forecast date';
            }
            if ($activeDays < 4) {
                $why[] = 'cases appear on only ' . $activeDays . ' day(s) in the last 90 days';
            }
            if ($cases < 6) {
                $why[] = 'only ' . $cases . ' recorded case(s) are available';
            }

            return [
                'code' => 'limited',
                'label' => 'Low',
                'reason' => implode('; ', $why),
                'span_days' => $spanDays,
                'active_days' => $activeDays,
                'cases' => $cases,
                'r2' => $r2,
            ];
        }

        $fill = $spanDays > 0 ? $activeDays / $spanDays : 0.0;
        $relativeSpread = $fit['avg'] > 0 ? ($fit['std'] / $fit['avg']) : 9.0;
        $recordsAreDense = $recordDayCount === 0 || $recordDayCount >= 42;
        $reliableFit = $r2 >= 0.25 && $windowActive >= 4 && $windowCases >= 4 && $relativeSpread <= 1.25;
        $longHistory = $spanDays >= 42 && $activeDays >= 8 && $cases >= 12 && $fill >= 0.15;
        if ($longHistory && $reliableFit && $recordsAreDense && $windowCases > 0) {
            return [
                'code' => 'high',
                'label' => 'High',
                'reason' => 'cases span ' . $spanDays . ' days on ' . $activeDays . ' recorded days, the 14-day line fits with R² ' . number_format($r2, 2) . ', and the uncertainty stays within the recent daily variation',
                'span_days' => $spanDays,
                'active_days' => $activeDays,
                'cases' => $cases,
                'r2' => $r2,
            ];
        }

        $why = [];
        if (!$longHistory) {
            $why[] = 'history spans ' . $spanDays . ' days with cases on ' . $activeDays . ' day(s), short of the history used for High confidence';
        }
        if ($fill < 0.15) {
            $why[] = 'recorded cases cover only ' . (int) round($fill * 100) . '% of the days since the first case';
        }
        if (!$reliableFit) {
            $why[] = 'the 14-day straight-line fit is modest (R² ' . number_format($r2, 2) . ') or the uncertainty is wide relative to the daily average';
        }
        if ($recordDayCount > 0 && $recordDayCount < 42) {
            $why[] = 'the clinic has diagnosis records on only ' . $recordDayCount . ' of the last 90 days';
        }

        if (!$longHistory || ($recordDayCount > 0 && $recordDayCount < 21)) {
            return [
                'code' => 'limited',
                'label' => 'Low',
                'reason' => implode('; ', $why),
                'span_days' => $spanDays,
                'active_days' => $activeDays,
                'cases' => $cases,
                'r2' => $r2,
            ];
        }

        return [
            'code' => 'moderate',
            'label' => 'Moderate',
            'reason' => implode('; ', $why),
            'span_days' => $spanDays,
            'active_days' => $activeDays,
            'cases' => $cases,
            'r2' => $r2,
        ];
    }

    /**
     * @param array{code:string,label:string} $alert
     * @param array{code:string,label:string,reason:string} $sufficiency
     * @param array{mid:?int,low:?int,high:?int} $projection7
     * @return list<array{label:string,text:string}>
     */
    private static function warningPoints(
        int $recent,
        int $previous,
        array $alert,
        array $sufficiency,
        array $projection7,
        ?float $paceChange,
    ): array {
        if (!in_array($alert['code'], ['increase', 'watch'], true)) {
            return [];
        }

        if ($previous === 0 && $recent > 0) {
            $why = 'New recorded cases: ' . $recent . ' in the last 7 days and none in the previous 7 days. A new appearance is watched. It is not an outbreak declaration.';
        } elseif ($previous > 0) {
            $pct = round((($recent - $previous) / $previous) * 100, 1);
            $why = 'The last 7 days (' . $recent . ') are ' . ($pct >= 0 ? '+' : '') . $pct . '% compared with the previous 7 days (' . $previous . ').';
        } else {
            $why = 'Both recent weeks have no recorded cases, so no rise is claimed.';
        }

        if ($projection7['mid'] === null) {
            $projected = 'Not estimated.';
            $uncertainty = 'No uncertainty range is shown because the series is too thin to project.';
        } else {
            $projected = 'About ' . $projection7['mid'] . ' cases over the next 7 days.';
            $uncertainty = $projection7['low'] . ' to ' . $projection7['high'] . ' cases. The band uses the variation of the last 14 daily counts.';
        }

        $ranking = ($paceChange !== null && $paceChange > 0)
            ? 'The projected 7-day count is above the recent 7-day pace, so this disease is also listed under Top diseases likely to increase.'
            : 'The projected 7-day count does not exceed the recent 7-day pace, so this disease stays in Early Warning only.';

        return [
            ['label' => 'Why flagged', 'text' => $why],
            ['label' => 'Comparison', 'text' => 'Last 7 days versus the previous 7 days.'],
            ['label' => 'Method', 'text' => 'Straight-line fit of the last 14 recorded daily counts.'],
            ['label' => 'Projected count', 'text' => $projected],
            ['label' => 'Uncertainty', 'text' => $uncertainty],
            ['label' => 'Confidence', 'text' => $sufficiency['label'] . ' because ' . $sufficiency['reason'] . '.'],
            ['label' => 'Increase ranking', 'text' => $ranking],
        ];
    }

    /** @return array{code:string,label:string} */
    private static function classifyAlert(int $recent, int $previous, string $sufficiency): array
    {
        if ($sufficiency === 'insufficient') {
            return ['code' => 'insufficient', 'label' => 'Insufficient Data'];
        }

        if ($previous === 0 && $recent > 0) {
            return ['code' => 'watch', 'label' => 'Watch'];
        }

        $pct = $previous === 0 ? 0.0 : (($recent - $previous) / $previous) * 100;
        if ($sufficiency === 'limited') {
            if ($recent > $previous && $recent > 0) {
                return ['code' => 'watch', 'label' => 'Watch'];
            }
            if ($pct <= -15 && $previous >= 2) {
                return ['code' => 'down', 'label' => 'Decreasing'];
            }

            return ['code' => 'stable', 'label' => 'Stable'];
        }

        if ($pct >= 15 && $recent > $previous) {
            return ['code' => 'increase', 'label' => 'Potential Increase'];
        }
        if ($pct <= -15) {
            return ['code' => 'down', 'label' => 'Decreasing'];
        }

        return ['code' => 'stable', 'label' => 'Stable'];
    }

    private static function pctChange(int $recent, int $previous): ?float
    {
        if ($recent === 0 && $previous === 0) {
            return null;
        }
        if ($previous === 0) {
            return null;
        }

        return round((($recent - $previous) / $previous) * 100, 1);
    }

    /**
     * @param array<string, int> $byDay
     * @return array{mid:?int,low:?int,high:?int}
     */
    private static function projectNextDays(array $byDay, string $anchorTo, int $days, int $recordDayCount = 0): array
    {
        $band = self::projectDailyBand($byDay, $anchorTo, $days, $recordDayCount);
        if (!$band['estimated']) {
            return ['mid' => null, 'low' => null, 'high' => null];
        }

        return [
            'mid'  => (int) round(array_sum($band['mid'])),
            'low'  => (int) round(array_sum($band['low'])),
            'high' => (int) round(array_sum($band['high'])),
        ];
    }

    /**
     * @param array<string, int> $byDay
     * @return array{mid:list<float>,low:list<float>,high:list<float>,estimated:bool,confidence:string}
     */
    private static function projectDailyBand(array $byDay, string $anchorTo, int $days, int $recordDayCount = 0): array
    {
        $sufficiency = self::assessSeries($byDay, $anchorTo, $recordDayCount);
        $fit = self::fitWindow($byDay, $anchorTo, 14);
        $values = $fit['values'];
        $n = count($values);
        $avg = $fit['avg'];
        $std = $fit['std'];
        $slope = $sufficiency['code'] === 'insufficient' ? 0.0 : $fit['slope'];
        if ($sufficiency['code'] === 'limited') {
            $slope *= 0.35;
        } elseif ($sufficiency['code'] === 'moderate') {
            $slope *= 0.7;
        }

        $histMean = $sufficiency['cases'] > 0 ? $sufficiency['cases'] / self::HISTORY_DAYS : 0.0;
        $windowCases = array_sum($values);
        $mid = [];
        $low = [];
        $high = [];
        $estimated = $sufficiency['code'] !== 'insufficient';
        for ($f = 1; $f <= $days; $f++) {
            if (!$estimated) {
                $mid[] = 0.0;
                $low[] = 0.0;
                $high[] = 0.0;
                continue;
            }
            $projected = max(0.0, $avg + ($slope * ($n + $f - 1)));
            $spread = max($std, $projected * 0.2);
            if ($windowCases <= 0) {
                $spread = max($spread, $histMean);
            }
            $mid[] = round($projected, 2);
            $low[] = round(max(0.0, $projected - $spread), 2);
            $high[] = round($projected + $spread, 2);
        }

        return [
            'mid'        => $mid,
            'low'        => $low,
            'high'       => $high,
            'estimated'  => $estimated,
            'confidence' => $sufficiency['code'],
        ];
    }

    /**
     * @param array<string, array<string, int>> $dailyByDisease
     */
    private static function highestRecentDisease(array $dailyByDisease, string $anchorTo): ?string
    {
        $windows = self::trendWindows($anchorTo);
        $best = null;
        $bestScore = null;
        foreach ($dailyByDisease as $name => $byDay) {
            $row = self::buildDiseaseRow($name, $byDay, $anchorTo, $windows, self::DEFAULT_HORIZON);
            $score = $row['increase_score'];
            if ($score === null) {
                continue;
            }
            if ($bestScore === null || $score > $bestScore) {
                $best = $name;
                $bestScore = $score;
            }
        }
        if ($best !== null) {
            return $best;
        }

        $names = array_keys($dailyByDisease);
        return $names[0] ?? null;
    }

    /**
     * Barangays whose recorded diagnosis cases rose in the latest 7 days versus the prior 7 days.
     *
     * @return list<array{barangay:string,recent:int,previous:int,note:string}>
     */
    private static function increasingBarangays(mysqli $conn, string $anchorTo, ReportFilters $filters): array
    {
        $windows = self::trendWindows($anchorTo);
        $from = $windows['prev_start'];
        $types = ['ss'];
        $params = [$from, $anchorTo];
        $demo = $filters->appendSql('p', $types, $params);
        $sql = "SELECT p.residential_address,
                       DATE(COALESCE(tr.date_of_consultation, tr.created_at)) AS consult_day,
                       dm.diagnosis_name
                FROM treatment_records tr
                INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
                INNER JOIN consultation_diagnoses cd ON cd.consultation_id = tr.id
                INNER JOIN diagnosis_master dm ON dm.id = cd.diagnosis_id AND dm.status = 'active'
                WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) BETWEEN ? AND ?{$demo}";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        /** @var array<string, array{recent:int,previous:int}> $counts */
        $counts = [];
        while ($row = $result->fetch_assoc()) {
            $barangay = trim(PatientSearch::extractBarangay($row['residential_address'] ?? null));
            if ($barangay === '' || $barangay === '—') {
                continue;
            }
            $day = (string) $row['consult_day'];
            if (!isset($counts[$barangay])) {
                $counts[$barangay] = ['recent' => 0, 'previous' => 0];
            }
            if ($day >= $windows['recent_start'] && $day <= $windows['recent_end']) {
                $counts[$barangay]['recent']++;
            } elseif ($day >= $windows['prev_start'] && $day <= $windows['prev_end']) {
                $counts[$barangay]['previous']++;
            }
        }
        $stmt->close();

        $rows = [];
        foreach ($counts as $barangay => $pair) {
            $recent = $pair['recent'];
            $previous = $pair['previous'];
            if ($recent < 2 || $recent <= $previous) {
                continue;
            }
            if ($previous === 0 && $recent < 3) {
                continue;
            }
            $rows[] = [
                'barangay' => $barangay,
                'recent'   => $recent,
                'previous' => $previous,
                'note'     => 'Recorded cases rose versus the previous 7 days. This is not a predicted outbreak.',
            ];
        }
        usort($rows, static fn (array $a, array $b): int => ($b['recent'] - $b['previous']) <=> ($a['recent'] - $a['previous']));

        return array_slice($rows, 0, 6);
    }

    /** @return array<string, mixed> */
    private static function emptySeries(): array
    {
        return [
            'labels' => [],
            'historical' => [],
            'forecast' => [],
            'forecast_low' => [],
            'forecast_high' => [],
            'split_index' => 0,
            'disease' => '',
            'estimated' => false,
            'confidence' => 'insufficient',
            'note' => '',
        ];
    }

    /** @return array<string, mixed> */
    private static function emptyPayload(string $from, string $to, int $horizonDays): array
    {
        return [
            'horizon_days'   => self::normalizeHorizon($horizonDays),
            'trend_days'     => self::TREND_DAYS,
            'history_from'   => $from,
            'history_to'     => $to,
            'rows'           => [],
            'early_warnings' => [],
            'top_increases'  => [],
            'barangays'      => [],
            'disclaimer'     => self::DISCLAIMER,
        ];
    }
}
