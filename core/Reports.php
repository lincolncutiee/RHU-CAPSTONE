<?php
/**
 * Management reports — chart data and summaries.
 */

require_once __DIR__ . '/PatientSearch.php';
require_once __DIR__ . '/ReceptionQueue.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/HeatMap.php';
require_once __DIR__ . '/ClinicalMaster.php';
require_once __DIR__ . '/ReportFilters.php';
require_once __DIR__ . '/ReportForecast.php';

class Reports
{
    private static function consultedClause(): string
    {
        return "(
            TRIM(COALESCE(tr.healthcare_provider, '')) <> ''
            OR TRIM(COALESCE(tr.diagnosis, '')) <> ''
            OR TRIM(COALESCE(tr.medication_treatment, '')) <> ''
        )";
    }

    /** @return array{from:string,to:string} */
    public static function normalizeRange(?string $from, ?string $to): array
    {
        $today = ReceptionQueue::today();
        $to = self::validDate($to) ?? $today;
        $from = self::validDate($from) ?? date('Y-m-d', strtotime('-29 days', strtotime($to)));

        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $maxDays = 366;
        if ((strtotime($to) - strtotime($from)) / 86400 > $maxDays) {
            $from = date('Y-m-d', strtotime("-{$maxDays} days", strtotime($to)));
        }

        return ['from' => $from, 'to' => $to];
    }

    private static function validDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
    }

    /** @return list<string> */
    private static function dateLabels(string $from, string $to): array
    {
        $labels = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $labels[] = date('Y-m-d', $cur);
            $cur = strtotime('+1 day', $cur);
        }
        return $labels;
    }

    /** @return array<string, mixed> */
    public static function build(
        mysqli $conn,
        string $from,
        string $to,
        ?ReportFilters $filters = null,
        int $forecastDays = 7,
    ): array {
        WorkflowStatus::ensurePatientsStatusEnum($conn);
        $filters ??= new ReportFilters();
        $range = self::normalizeRange($from, $to);
        $from = $range['from'];
        $to = $range['to'];

        $dailyConsults = self::dailyConsultCounts($conn, $from, $to, $filters);
        $sexMix = self::consultSexMix($conn, $from, $to, $filters);
        $ageMix = self::consultAgeMix($conn, $from, $to, $filters);
        $summary = self::summary($conn, $from, $to, $dailyConsults);

        $heatmap = HeatMap::build($conn, $from, $to, null, $filters);
        $casesByBarangay = self::casesByBarangayChart($heatmap, 10);
        $commonDiseases = self::commonDiseasesInPeriod($conn, $from, $to, 8, $filters);
        $horizonDays = ReportForecast::normalizeHorizon($forecastDays);
        $forecasts = ReportForecast::diseaseForecasts($conn, $to, $filters, 8, $horizonDays);
        $chartDisease = $forecasts['rows'][0]['disease'] ?? null;
        $forecastSeries = ReportForecast::topDiseaseSeries($conn, $to, $filters, 42, $horizonDays, is_string($chartDisease) ? $chartDisease : null);

        $filterLabels = $filters->summaryLabels();

        return [
            'range' => [
                'from'          => $from,
                'to'            => $to,
                'from_label'    => date('M j, Y', strtotime($from)),
                'to_label'      => date('M j, Y', strtotime($to)),
                'days'          => count($dailyConsults['labels']),
            ],
            'filters' => [
                'sex'       => $filters->sex ?? '',
                'age_group' => $filters->ageGroup ?? '',
                'labels'    => $filterLabels,
            ],
            'summary'               => $summary,
            'consultations_daily'   => $dailyConsults,
            'cases_by_barangay'     => $casesByBarangay,
            'sex_mix'               => $sexMix,
            'age_mix'               => $ageMix,
            'common_diseases'       => $commonDiseases,
            'case_forecast'         => $forecasts,
            'forecast_series'       => $forecastSeries,
            'generated_at'          => date('M j, Y g:i A'),
            'chart_kpis'            => self::chartKpis(
                $dailyConsults,
                $casesByBarangay,
                $commonDiseases,
                count($dailyConsults['labels']),
            ),
        ];
    }

    /**
     * Headline numbers tied to on-page charts (not IT workflow counts).
     *
     * @param array{total:int} $dailyConsults
     * @param array{values:list<int>} $casesByBarangay
     * @param array{total_diagnoses:int,type_count:int} $commonDiseases
     * @return array{
     *   consultations:int,
     *   avg_per_day:int,
     *   barangay_cases:int,
     *   diagnosis_cases:int,
     *   disease_types:int
     * }
     */
    public static function chartKpis(
        array $dailyConsults,
        array $casesByBarangay,
        array $commonDiseases,
        int $daysInRange,
    ): array {
        $consultTotal = (int) ($dailyConsults['total'] ?? 0);
        $days = max(1, $daysInRange);
        $brgyValues = $casesByBarangay['values'] ?? [];

        return [
            'consultations'    => $consultTotal,
            'avg_per_day'      => (int) round($consultTotal / $days),
            'barangay_cases'   => (int) array_sum($brgyValues),
            'diagnosis_cases'  => (int) ($commonDiseases['total_diagnoses'] ?? 0),
            'disease_types'    => (int) ($commonDiseases['type_count'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $heatmapPayload */
    private static function casesByBarangayChart(array $heatmapPayload, int $limit): array
    {
        $rows = $heatmapPayload['barangays'] ?? [];
        usort($rows, static fn ($a, $b) => ($b['total_cases'] ?? 0) <=> ($a['total_cases'] ?? 0));
        $rows = array_values(array_filter($rows, static fn ($r) => (int) ($r['total_cases'] ?? 0) > 0));
        $slice = array_slice($rows, 0, $limit);

        return [
            'labels' => array_map(static fn ($r) => (string) ($r['name'] ?? ''), $slice),
            'values' => array_map(static fn ($r) => (int) ($r['total_cases'] ?? 0), $slice),
        ];
    }

    /**
     * All diagnoses recorded on consultations in the date range (not limited to surveillance “active” window).
     *
     * @return array{labels:list<string>,values:list<int>,type_count:int,total_diagnoses:int}
     */
    private static function commonDiseasesInPeriod(
        mysqli $conn,
        string $from,
        string $to,
        int $limit,
        ReportFilters $filters,
    ): array {
        ClinicalMaster::ensureSchema($conn);

        $types = ['ss'];
        $params = [$from, $to];
        $demo = $filters->appendSql('p', $types, $params);

        $sql = "SELECT
                    tr.id AS consultation_id,
                    tr.diagnosis AS legacy_diagnosis,
                    dm.id AS diagnosis_id,
                    dm.diagnosis_name
                FROM treatment_records tr
                INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
                LEFT JOIN consultation_diagnoses cd ON cd.consultation_id = tr.id
                LEFT JOIN diagnosis_master dm ON dm.id = cd.diagnosis_id AND dm.status = 'active'
                WHERE COALESCE(tr.date_of_consultation, DATE(tr.created_at)) BETWEEN ? AND ?{$demo}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        /** @var array<int, list<array{id:int,name:string}>> $dxByConsult */
        $dxByConsult = [];

        while ($row = $result->fetch_assoc()) {
            $cid = (int) $row['consultation_id'];
            if (!isset($dxByConsult[$cid])) {
                $dxByConsult[$cid] = [];
            }
            if (!empty($row['diagnosis_id'])) {
                $dxByConsult[$cid][(int) $row['diagnosis_id']] = [
                    'id'   => (int) $row['diagnosis_id'],
                    'name' => (string) $row['diagnosis_name'],
                ];
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
                $dxByConsult[$cid][$id] = [
                    'id'   => $id,
                    'name' => (string) ($dx['diagnosis_name'] ?? $dx['label'] ?? 'Unknown'),
                ];
            }
        }

        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($dxByConsult as $dxList) {
            foreach ($dxList as $dx) {
                $name = trim((string) ($dx['name'] ?? ''));
                if ($name === '') {
                    $name = 'Unknown';
                }
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        arsort($counts);
        $top = array_slice($counts, 0, $limit, true);

        if ($top === []) {
            return [
                'labels'            => [],
                'values'            => [],
                'type_count'        => 0,
                'total_diagnoses'   => 0,
            ];
        }

        return [
            'labels'            => array_keys($top),
            'values'            => array_values($top),
            'type_count'        => count($counts),
            'total_diagnoses'   => array_sum($counts),
        ];
    }

    /** @return array{labels:list<string>,short_labels:list<string>,values:list<int>,total:int} */
    private static function dailyConsultCounts(
        mysqli $conn,
        string $from,
        string $to,
        ReportFilters $filters,
    ): array {
        $consulted = self::consultedClause();
        $types = ['ss'];
        $params = [$from, $to];
        $demo = $filters->appendSql('p', $types, $params);
        $stmt = $conn->prepare(
            "SELECT DATE(COALESCE(tr.date_of_consultation, tr.created_at)) AS day, COUNT(*) AS cnt
             FROM treatment_records tr
             INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
             WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) BETWEEN ? AND ?
               AND {$consulted}{$demo}
             GROUP BY day
             ORDER BY day"
        );
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['cnt'];
        }

        $labels = self::dateLabels($from, $to);
        $values = [];
        $short = [];
        $total = 0;
        foreach ($labels as $day) {
            $c = $byDay[$day] ?? 0;
            $values[] = $c;
            $total += $c;
            $short[] = date('M j', strtotime($day));
        }

        return [
            'labels'       => $labels,
            'short_labels' => $short,
            'values'       => $values,
            'total'        => $total,
        ];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    private static function consultSexMix(
        mysqli $conn,
        string $from,
        string $to,
        ReportFilters $filters,
    ): array {
        $consulted = self::consultedClause();
        $types = ['ss'];
        $params = [$from, $to];
        $demo = $filters->appendSql('p', $types, $params);
        $stmt = $conn->prepare(
            "SELECT COALESCE(NULLIF(TRIM(p.sex), ''), 'Unknown') AS sex_label, COUNT(*) AS cnt
             FROM treatment_records tr
             INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
             WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) BETWEEN ? AND ?
               AND {$consulted}{$demo}
             GROUP BY sex_label"
        );
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $labels = [];
        $values = [];
        $order = ['Male', 'Female', 'Unknown'];
        $byLabel = [];
        foreach ($rows as $row) {
            $raw = strtolower(trim((string) $row['sex_label']));
            $norm = match ($raw) {
                'male', 'm' => 'Male',
                'female', 'f' => 'Female',
                default => 'Unknown',
            };
            $byLabel[$norm] = ($byLabel[$norm] ?? 0) + (int) $row['cnt'];
        }
        $labels = [];
        $values = [];
        foreach ($order as $label) {
            $labels[] = $label;
            $values[] = $byLabel[$label] ?? 0;
        }

        if (array_sum($values) === 0) {
            return ['labels' => ['No data'], 'values' => [0]];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels:list<string>,values:list<int>} */
    private static function consultAgeMix(
        mysqli $conn,
        string $from,
        string $to,
        ReportFilters $filters,
    ): array {
        $consulted = self::consultedClause();
        $types = ['ss'];
        $params = [$from, $to];
        $demo = $filters->appendSql('p', $types, $params);
        $youth = ReportFilters::AGE_YOUTH;
        $adult = ReportFilters::AGE_ADULT;
        $senior = ReportFilters::AGE_SENIOR;
        $stmt = $conn->prepare(
            "SELECT
                CASE
                    WHEN p.birth_date IS NULL THEN 'Unknown age'
                    WHEN " . ReportFilters::ageConditionSql($youth, 'p') . " THEN 'Youth (0–17)'
                    WHEN " . ReportFilters::ageConditionSql($adult, 'p') . " THEN 'Adult (18–59)'
                    WHEN " . ReportFilters::ageConditionSql($senior, 'p') . " THEN 'Senior (60+)'
                    ELSE 'Unknown age'
                END AS age_label,
                COUNT(*) AS cnt
             FROM treatment_records tr
             INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
             WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) BETWEEN ? AND ?
               AND {$consulted}{$demo}
             GROUP BY age_label"
        );
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $order = ['Youth (0–17)', 'Adult (18–59)', 'Senior (60+)', 'Unknown age'];
        $byLabel = [];
        foreach ($rows as $row) {
            $byLabel[(string) $row['age_label']] = (int) $row['cnt'];
        }
        $labels = [];
        $values = [];
        foreach ($order as $label) {
            $labels[] = $label;
            $values[] = $byLabel[$label] ?? 0;
        }
        if (array_sum($values) === 0) {
            return ['labels' => ['No data'], 'values' => [0]];
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param array{values:list<int>,total:int} $dailyConsults
     * @return array<string, int|float>
     */
    private static function summary(mysqli $conn, string $from, string $to, array $dailyConsults): array
    {
        $summary = [
            'consultations'   => (int) $dailyConsults['total'],
            'new_patients'    => 0,
            'validated'       => 0,
            'printed'         => 0,
            'pending_it'      => 0,
            'ready_to_print'  => 0,
            'returned'        => 0,
        ];

        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND DATE(date_encoded) BETWEEN ? AND ?'
        );
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $summary['new_patients'] = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $ready = WORKFLOW_READY_FOR_PRINT;
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ? AND DATE(updated_at) BETWEEN ? AND ?'
        );
        $stmt->bind_param('sss', $ready, $from, $to);
        $stmt->execute();
        $summary['validated'] = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $printed = WORKFLOW_PRINTED;
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ? AND DATE(updated_at) BETWEEN ? AND ?'
        );
        $stmt->bind_param('sss', $printed, $from, $to);
        $stmt->execute();
        $summary['printed'] = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();

        $pendingAliases = WorkflowStatus::pendingItAliases();
        $pendingPh = implode(',', array_fill(0, count($pendingAliases), '?'));
        $pendingTypes = str_repeat('s', count($pendingAliases));
        $ps = $conn->prepare("SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status IN ($pendingPh)");
        $ps->bind_param($pendingTypes, ...$pendingAliases);
        $ps->execute();
        $summary['pending_it'] = (int) ($ps->get_result()->fetch_assoc()['c'] ?? 0);
        $ps->close();

        $st = $conn->prepare('SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ?');
        $readyOnly = WORKFLOW_READY_FOR_PRINT;
        $st->bind_param('s', $readyOnly);
        $st->execute();
        $summary['ready_to_print'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        $ret = WORKFLOW_RETURNED_FOR_CORRECTION;
        $st = $conn->prepare('SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ?');
        $st->bind_param('s', $ret);
        $st->execute();
        $summary['returned'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        return $summary;
    }
}
