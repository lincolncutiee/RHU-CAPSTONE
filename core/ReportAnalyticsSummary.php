<?php

require_once __DIR__ . '/ReportFilters.php';

/**
 * Plain-language summaries for the printable analytics summary report.
 */
class ReportAnalyticsSummary
{
    /** @param array<string, mixed> $report */
    public static function intro(array $report): string
    {
        $range = $report['range'] ?? [];
        $from = (string) ($range['from_label'] ?? '');
        $to = (string) ($range['to_label'] ?? '');
        $days = (int) ($range['days'] ?? 0);
        $labels = $report['filters']['labels'] ?? [];

        if ($labels === []) {
            return "This summary describes all consultations and related charts on the Reports page for "
                . "{$from} through {$to} ({$days} day(s)). No sex or age filter was applied — figures include all patients in that period.";
        }

        $filterText = implode(' · ', $labels);
        return "This summary matches what you selected on Reports: {$filterText}, for {$from} through {$to} ({$days} day(s)). "
            . 'Every number below uses only patient records that match these filters (same data as the on-screen charts).';
    }

    /** @param array<string, mixed> $report */
    public static function consultationsNarrative(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $daily = $report['consultations_daily'] ?? [];
        $total = (int) ($summary['consultations'] ?? ($daily['total'] ?? 0));
        $labels = $daily['labels'] ?? [];
        $values = $daily['values'] ?? [];

        if ($total === 0) {
            return 'There were no consultations in this period for the selected filters. The column and line charts on Reports would appear empty.';
        }

        $peak = self::peakDay($labels, $values);
        $avg = count($values) > 0 ? round($total / count($values), 1) : 0;

        $peakPart = $peak !== null
            ? " The busiest day was {$peak['label']} with {$peak['value']} consultation(s)."
            : '';

        return "Total consultations: {$total} (average about {$avg} per day in range).{$peakPart} "
            . 'The Daily consultations bar chart and Consultation trend line chart both show this same daily series.';
    }

    /** @param array<string, mixed> $report */
    public static function barangayNarrative(array $report): string
    {
        $brgy = $report['cases_by_barangay'] ?? [];
        $labels = $brgy['labels'] ?? [];
        $values = $brgy['values'] ?? [];
        $sum = array_sum($values);

        if ($sum === 0 || $labels === []) {
            return 'No disease-linked cases by barangay in this period for the selected filters. The Cases by barangay chart would show no bars.';
        }

        $top = [];
        $limit = min(3, count($labels));
        for ($i = 0; $i < $limit; $i++) {
            $top[] = $labels[$i] . ' (' . (int) ($values[$i] ?? 0) . ')';
        }

        return 'The Cases by barangay chart counts diagnoses tied to consults, grouped by patient barangay. '
            . 'Top areas: ' . implode(', ', $top) . '. Total cases in chart: ' . $sum . '.';
    }

    /** @param array<string, mixed> $report */
    public static function ageNarrative(array $report): string
    {
        $age = $report['age_mix'] ?? [];
        $labels = $age['labels'] ?? [];
        $values = $age['values'] ?? [];
        $filterAge = (string) ($report['filters']['age_group'] ?? '');

        if ($labels === [] || ($labels[0] ?? '') === 'No data') {
            return 'No visits to classify by age for this selection.';
        }

        $parts = [];
        foreach ($labels as $i => $label) {
            $parts[] = $label . ': ' . (int) ($values[$i] ?? 0);
        }
        $breakdown = implode('; ', $parts);

        if ($filterAge === ReportFilters::AGE_ADULT) {
            return 'You filtered to Adult (18–59) only, so all consult counts above are for adults. '
                . 'The Consults by age group chart still lists every age band for context; only the Adult row should have counts: ' . $breakdown . '.';
        }
        if ($filterAge === ReportFilters::AGE_YOUTH) {
            return 'You filtered to Youth (0–17) only. Age breakdown in the chart: ' . $breakdown . '.';
        }
        if ($filterAge === ReportFilters::AGE_SENIOR) {
            return 'You filtered to Senior (60+) only. Age breakdown in the chart: ' . $breakdown . '.';
        }

        return 'The Consults by age group chart shows how visits split across Youth, Adult, Senior, and Unknown age: ' . $breakdown . '.';
    }

    /** @param array<string, mixed> $report */
    public static function sexNarrative(array $report): string
    {
        $sex = $report['sex_mix'] ?? [];
        $labels = $sex['labels'] ?? [];
        $values = $sex['values'] ?? [];
        $filterSex = (string) ($report['filters']['sex'] ?? '');

        if ($labels === [] || ($labels[0] ?? '') === 'No data') {
            return 'No sex breakdown available for this selection.';
        }

        $parts = [];
        foreach ($labels as $i => $label) {
            $parts[] = $label . ': ' . (int) ($values[$i] ?? 0);
        }

        if ($filterSex === 'Male' || $filterSex === 'Female') {
            return "You filtered to {$filterSex} only; all figures are for {$filterSex} patients. "
                . 'Chart labels: ' . implode('; ', $parts) . '.';
        }

        return 'The Consults by sex chart splits visits among Male, Female, and Unknown: ' . implode('; ', $parts) . '.';
    }

    /** @param array<string, mixed> $report */
    public static function diseasesNarrative(array $report): string
    {
        $d = $report['common_diseases'] ?? [];
        $labels = $d['labels'] ?? [];
        $values = $d['values'] ?? [];
        $total = (int) ($d['total_diagnoses'] ?? array_sum($values));
        $typeCount = (int) ($d['type_count'] ?? count($labels));

        if ($typeCount === 0 || $total === 0) {
            return 'No diagnoses linked to consults in this period. The Common diseases chart would be empty.';
        }

        $top = [];
        for ($i = 0; $i < min(3, count($labels)); $i++) {
            $top[] = $labels[$i] . ' (' . (int) ($values[$i] ?? 0) . ')';
        }

        return "The Common diseases chart shows {$typeCount} diagnosis type(s) and {$total} total case count(s) in range. "
            . 'Leading: ' . implode(', ', $top) . '.';
    }

    /** @param array<string, mixed> $report */
    public static function forecastNarrative(array $report): string
    {
        $forecast = $report['case_forecast'] ?? [];
        $rows = $forecast['rows'] ?? [];
        if ($rows === []) {
            return 'Not enough diagnosis history to show a case forecast for this filter and period.';
        }

        $first = $rows[0];
        $name = (string) ($first['disease'] ?? '');
        $recent = (int) ($first['recent_7'] ?? $first['recent_14'] ?? 0);
        $trend = (string) ($first['alert_label'] ?? $first['trend_label'] ?? '');
        $proj = $first['projected_mid'];
        $projText = $proj === null ? 'not estimated (insufficient data)' : ('~' . (int) $proj);
        $horizon = (int) ($forecast['horizon_days'] ?? 7);

        return "The Case forecast block compares the last 7 days vs the previous 7 days and projects the next {$horizon} days. "
            . "Highest projected change: {$name} — last 7d: {$recent}, trend: {$trend}, projected total {$projText}. "
            . (string) ($forecast['disclaimer'] ?? ReportForecast::DISCLAIMER);
    }

    /** @param array<string, mixed> $report */
    public static function chartKpisNarrative(array $report): string
    {
        $k = $report['chart_kpis'] ?? [];
        return 'Summary tiles at the top of Reports (same filters as the charts): '
            . (int) ($k['consultations'] ?? 0) . ' consultations (daily & trend charts); '
            . 'about ' . (string) ($k['avg_per_day'] ?? 0) . ' consults per day on average; '
            . (int) ($k['barangay_cases'] ?? 0) . ' barangay-linked diagnosis cases; '
            . (int) ($k['diagnosis_cases'] ?? 0) . ' total diagnosis cases across '
            . (int) ($k['disease_types'] ?? 0) . ' disease type(s) on the common diseases chart.';
    }

    /**
     * @param list<string> $labels
     * @param list<int>    $values
     * @return array{label:string,value:int}|null
     */
    private static function peakDay(array $labels, array $values): ?array
    {
        if ($labels === [] || $values === []) {
            return null;
        }
        $max = max($values);
        if ($max <= 0) {
            return null;
        }
        $idx = array_search($max, $values, true);
        if ($idx === false) {
            return null;
        }
        return ['label' => (string) $labels[$idx], 'value' => (int) $max];
    }

    /** @param array<string, mixed> $report @return list<array{label:string,count:int}> */
    public static function legendRows(array $labels, array $values): array
    {
        $rows = [];
        foreach ($labels as $i => $label) {
            if ($label === 'No data') {
                continue;
            }
            $rows[] = ['label' => (string) $label, 'count' => (int) ($values[$i] ?? 0)];
        }
        return $rows;
    }
}
