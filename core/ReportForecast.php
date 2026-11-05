<?php

require_once __DIR__ . '/ClinicalMaster.php';
require_once __DIR__ . '/ReportFilters.php';

/**
 * Case trend and short-range projection from RHU consultation history.
 */
class ReportForecast
{
    public const HISTORY_DAYS = 90;
    public const TREND_DAYS = 14;
    public const FORECAST_DAYS = 14;

    /**
     * @return array{
     *   horizon_days:int,
     *   history_from:string,
     *   history_to:string,
     *   rows:list<array<string,mixed>>,
     *   disclaimer:string
     * }
     */
    public static function diseaseForecasts(
        mysqli $conn,
        string $anchorTo,
        ReportFilters $filters,
        int $limit = 8,
    ): array {
        ClinicalMaster::ensureSchema($conn);
        $to = $anchorTo;
        $from = date('Y-m-d', strtotime('-' . (self::HISTORY_DAYS - 1) . ' days', strtotime($to)));

        $dailyByDisease = self::dailyDiagnosisCounts($conn, $from, $to, $filters);
        if ($dailyByDisease === []) {
            return self::emptyPayload($from, $to);
        }

        $totals = [];
        foreach ($dailyByDisease as $name => $byDay) {
            $totals[$name] = array_sum($byDay);
        }
        arsort($totals);
        $topNames = array_slice(array_keys($totals), 0, $limit);

        $trendEnd = $to;
        $trendStart = date('Y-m-d', strtotime('-' . (self::TREND_DAYS - 1) . ' days', strtotime($trendEnd)));
        $prevEnd = date('Y-m-d', strtotime('-1 day', strtotime($trendStart)));
        $prevStart = date('Y-m-d', strtotime('-' . (self::TREND_DAYS - 1) . ' days', strtotime($prevEnd)));

        $rows = [];
        foreach ($topNames as $name) {
            $byDay = $dailyByDisease[$name];
            $recent = self::sumRange($byDay, $trendStart, $trendEnd);
            $previous = self::sumRange($byDay, $prevStart, $prevEnd);
            $trend = self::classifyTrend($recent, $previous);
            $projection = self::projectNextDays($byDay, $to, self::FORECAST_DAYS);

            $rows[] = [
                'disease'           => $name,
                'recent_14'         => $recent,
                'previous_14'       => $previous,
                'pct_change'        => self::pctChange($recent, $previous),
                'trend'             => $trend['code'],
                'trend_label'       => $trend['label'],
                'projected_mid'     => $projection['mid'],
                'projected_low'     => $projection['low'],
                'projected_high'    => $projection['high'],
            ];
        }

        return [
            'horizon_days'  => self::FORECAST_DAYS,
            'history_from'  => $from,
            'history_to'    => $to,
            'rows'          => $rows,
            'disclaimer'    => 'Projection based on recorded RHU consultations only; not a clinical or epidemic forecast.',
        ];
    }

    /**
     * @return array{labels:list<string>,historical:list<int>,forecast:list<int|null>,split_index:int}
     */
    public static function topDiseaseSeries(
        mysqli $conn,
        string $anchorTo,
        ReportFilters $filters,
        int $historyDays = 42,
        int $forecastDays = 14,
    ): array {
        $to = $anchorTo;
        $from = date('Y-m-d', strtotime('-' . ($historyDays - 1) . ' days', strtotime($to)));
        $dailyByDisease = self::dailyDiagnosisCounts($conn, $from, $to, $filters);
        if ($dailyByDisease === []) {
            return ['labels' => [], 'historical' => [], 'forecast' => [], 'split_index' => 0, 'disease' => ''];
        }
        $totals = [];
        foreach ($dailyByDisease as $name => $byDay) {
            $totals[$name] = array_sum($byDay);
        }
        arsort($totals);
        $top = array_key_first($totals);
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

        $proj = self::projectDailySeries($byDay, $to, $forecastDays);
        $forecastLabels = [];
        $forecastValues = [];
        $fc = strtotime('+1 day', strtotime($to));
        for ($i = 0; $i < $forecastDays; $i++) {
            $forecastLabels[] = date('M j', $fc);
            $forecastValues[] = $proj[$i] ?? 0;
            $fc = strtotime('+1 day', $fc);
        }

        return [
            'disease'     => $top,
            'labels'      => array_merge($histLabels, $forecastLabels),
            'historical'  => array_merge($histValues, array_fill(0, $forecastDays, null)),
            'forecast'    => array_merge(array_fill(0, count($histValues), null), $forecastValues),
            'split_index' => count($histValues),
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

    /** @return array{code:string,label:string} */
    private static function classifyTrend(int $recent, int $previous): array
    {
        if ($recent === 0 && $previous === 0) {
            return ['code' => 'stable', 'label' => 'Stable'];
        }
        if ($previous === 0 && $recent > 0) {
            return ['code' => 'up', 'label' => 'Likely increasing'];
        }
        $pct = (($recent - $previous) / max(1, $previous)) * 100;
        if ($pct >= 15) {
            return ['code' => 'up', 'label' => 'Likely increasing'];
        }
        if ($pct <= -15) {
            return ['code' => 'down', 'label' => 'Likely decreasing'];
        }
        return ['code' => 'stable', 'label' => 'Stable'];
    }

    private static function pctChange(int $recent, int $previous): ?float
    {
        if ($previous === 0) {
            return $recent > 0 ? 100.0 : 0.0;
        }
        return round((($recent - $previous) / $previous) * 100, 1);
    }

    /**
     * @param array<string, int> $byDay
     * @return array{mid:int,low:int,high:int}
     */
    private static function projectNextDays(array $byDay, string $anchorTo, int $days): array
    {
        $daily = self::projectDailySeries($byDay, $anchorTo, $days);
        $mid = (int) round(array_sum($daily));
        $avg = count($daily) > 0 ? array_sum($daily) / count($daily) : 0;
        $low = (int) max(0, floor($mid * 0.85));
        $high = (int) ceil($mid * 1.15 + ($avg > 0 ? 1 : 0));
        return ['mid' => $mid, 'low' => $low, 'high' => $high];
    }

    /**
     * @param array<string, int> $byDay
     * @return list<float>
     */
    private static function projectDailySeries(array $byDay, string $anchorTo, int $days): array
    {
        $window = 14;
        $values = [];
        for ($i = $window - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days", strtotime($anchorTo)));
            $values[] = (float) ($byDay[$d] ?? 0);
        }
        $n = count($values);
        $avg = $n > 0 ? array_sum($values) / $n : 0;

        $slope = 0.0;
        if ($n >= 2) {
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;
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
        }

        $out = [];
        for ($f = 1; $f <= $days; $f++) {
            $projected = $avg + ($slope * ($n + $f - 1));
            $out[] = max(0.0, round($projected, 2));
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private static function emptyPayload(string $from, string $to): array
    {
        return [
            'horizon_days' => self::FORECAST_DAYS,
            'history_from' => $from,
            'history_to'   => $to,
            'rows'         => [],
            'disclaimer'   => 'Projection based on recorded RHU consultations only; not a clinical or epidemic forecast.',
        ];
    }
}
