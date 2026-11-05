<?php
/**
 * Server-rendered SVG charts and tables for reports-print.php (vector, not page screenshots).
 */

class ReportPrintRenderer
{
    /** @param list<string> $labels @param list<int|float> $values */
    public static function columnChart(array $labels, array $values, int $width = 680, int $height = 200): string
    {
        if ($labels === [] || $values === []) {
            return self::emptyChart($width, $height, 'No consultation data');
        }

        $padL = 36;
        $padR = 12;
        $padT = 14;
        $padB = 36;
        $max = max(1, (float) max($values));
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        $n = count($values);
        $gap = 2;
        $barW = max(4, ($plotW - ($n - 1) * $gap) / $n);

        $bars = '';
        foreach ($values as $i => $v) {
            $h = ($v / $max) * $plotH;
            $x = $padL + $i * ($barW + $gap);
            $y = $padT + ($plotH - $h);
            $bars .= sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="#1a6fc4" rx="2"/>',
                $x,
                $y,
                $barW,
                max(0, $h)
            );
        }

        $xLabels = self::sparseLabels($labels, $n, 12);
        $labelSvg = '';
        foreach ($xLabels as $item) {
            $x = $padL + $item['index'] * ($barW + $gap) + $barW / 2;
            $labelSvg .= sprintf(
                '<text x="%.1f" y="%d" text-anchor="middle" font-size="9" fill="#64748b">%s</text>',
                $x,
                $height - 8,
                self::esc($item['text'])
            );
        }

        $yGrid = self::yGridLines($padL, $padT, $plotW, $plotH, $max);

        return self::svgWrap($width, $height, $yGrid . $bars . $labelSvg);
    }

    /** @param list<string> $labels @param list<int|float> $values */
    public static function lineChart(array $labels, array $values, int $width = 680, int $height = 200): string
    {
        if ($labels === [] || $values === []) {
            return self::emptyChart($width, $height, 'No trend data');
        }

        $padL = 36;
        $padR = 12;
        $padT = 14;
        $padB = 36;
        $max = max(1, (float) max($values));
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        $n = count($values);
        $step = $n > 1 ? $plotW / ($n - 1) : 0;

        $points = [];
        foreach ($values as $i => $v) {
            $x = $padL + ($n > 1 ? $i * $step : $plotW / 2);
            $y = $padT + $plotH - ($v / $max) * $plotH;
            $points[] = sprintf('%.2f,%.2f', $x, $y);
        }
        $polyline = '<polyline fill="none" stroke="#0d9488" stroke-width="2" points="' . implode(' ', $points) . '"/>';
        $area = '<polygon fill="rgba(13,148,136,0.12)" points="' . $padL . ',' . ($padT + $plotH) . ' ' . implode(' ', $points) . ' ' . ($padL + $plotW) . ',' . ($padT + $plotH) . '"/>';

        $xLabels = self::sparseLabels($labels, $n, 12);
        $labelSvg = '';
        foreach ($xLabels as $item) {
            $x = $padL + ($n > 1 ? $item['index'] * $step : $plotW / 2);
            $labelSvg .= sprintf(
                '<text x="%.1f" y="%d" text-anchor="middle" font-size="9" fill="#64748b">%s</text>',
                $x,
                $height - 8,
                self::esc($item['text'])
            );
        }

        $yGrid = self::yGridLines($padL, $padT, $plotW, $plotH, $max);

        return self::svgWrap($width, $height, $yGrid . $area . $polyline . $labelSvg);
    }

    /** @param list<string> $labels @param list<int|float> $values */
    public static function horizontalBarChart(array $labels, array $values, int $width = 680, int $height = 240): string
    {
        if ($labels === [] || $values === []) {
            return self::emptyChart($width, $height, 'No cases by barangay');
        }

        $n = count($labels);
        $height = max($height, 40 + $n * 28);
        $padL = 120;
        $padR = 40;
        $padT = 12;
        $padB = 12;
        $max = max(1, (float) max($values));
        $plotW = $width - $padL - $padR;
        $rowH = ($height - $padT - $padB) / $n;

        $svg = '';
        foreach ($labels as $i => $label) {
            $v = (float) ($values[$i] ?? 0);
            $y = $padT + $i * $rowH + 4;
            $barH = max(10, $rowH - 8);
            $w = ($v / $max) * $plotW;
            $svg .= sprintf(
                '<text x="%d" y="%.1f" font-size="10" fill="#334155" dominant-baseline="middle">%s</text>',
                4,
                $y + $barH / 2,
                self::esc(self::truncate($label, 18))
            );
            $svg .= sprintf(
                '<rect x="%d" y="%.1f" width="%.2f" height="%.1f" fill="#d97706" rx="3"/>',
                $padL,
                $y,
                $w,
                $barH
            );
            $svg .= sprintf(
                '<text x="%.1f" y="%.1f" font-size="10" fill="#64748b" dominant-baseline="middle">%s</text>',
                $padL + $w + 6,
                $y + $barH / 2,
                self::esc((string) (int) $v)
            );
        }

        return self::svgWrap($width, $height, $svg);
    }

    /** @param list<string> $labels @param list<int|float> $values */
    public static function doughnutChart(array $labels, array $values, int $size = 220): string
    {
        $total = array_sum($values);
        if ($total <= 0 || $labels === []) {
            return self::emptyChart($size, $size, 'No data');
        }

        $cx = $size / 2;
        $cy = $size / 2;
        $r = $size * 0.38;
        $ri = $size * 0.24;
        $colors = ['#1a6fc4', '#0d9488', '#d97706', '#e11d48', '#7c3aed', '#16a34a', '#64748b', '#6366f1'];

        $paths = '';
        $start = -M_PI / 2;
        foreach ($values as $i => $v) {
            if ($v <= 0) {
                continue;
            }
            $slice = ((float) $v / $total) * 2 * M_PI;
            $end = $start + $slice;
            $color = $colors[$i % count($colors)];
            $paths .= self::donutSlice($cx, $cy, $r, $ri, $start, $end, $color);
            $start = $end;
        }

        return self::svgWrap((int) $size, (int) $size, $paths);
    }

    /** @param list<string> $labels @param list<int|float> $values @return list<array{label:string,count:int,percent:float}> */
    public static function legendRows(array $labels, array $values): array
    {
        $total = array_sum($values);
        $rows = [];
        foreach ($labels as $i => $label) {
            $count = (int) ($values[$i] ?? 0);
            $pct = $total > 0 ? round($count / $total * 100, 1) : 0.0;
            $rows[] = [
                'label'   => (string) $label,
                'count'   => $count,
                'percent' => $pct,
            ];
        }
        return $rows;
    }

    private static function donutSlice(float $cx, float $cy, float $r, float $ri, float $start, float $end, string $fill): string
    {
        if ($end - $start >= 2 * M_PI - 0.0001) {
            return sprintf(
                '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="none" stroke="%s" stroke-width="%.2f"/>',
                $cx,
                $cy,
                ($r + $ri) / 2,
                self::esc($fill),
                $r - $ri
            );
        }

        $x1 = $cx + $r * cos($start);
        $y1 = $cy + $r * sin($start);
        $x2 = $cx + $r * cos($end);
        $y2 = $cy + $r * sin($end);
        $x3 = $cx + $ri * cos($end);
        $y3 = $cy + $ri * sin($end);
        $x4 = $cx + $ri * cos($start);
        $y4 = $cy + $ri * sin($start);
        $large = ($end - $start) > M_PI ? 1 : 0;

        return sprintf(
            '<path d="M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z" fill="%s"/>',
            $x1,
            $y1,
            $r,
            $r,
            $large,
            $x2,
            $y2,
            $x3,
            $y3,
            $ri,
            $ri,
            $large,
            $x4,
            $y4,
            self::esc($fill)
        );
    }

    /** @return list<array{index:int,text:string}> */
    private static function sparseLabels(array $labels, int $n, int $maxTicks): array
    {
        if ($n === 0) {
            return [];
        }
        $step = max(1, (int) ceil($n / $maxTicks));
        $out = [];
        for ($i = 0; $i < $n; $i += $step) {
            $out[] = ['index' => $i, 'text' => (string) ($labels[$i] ?? '')];
        }
        if (($n - 1) % $step !== 0) {
            $out[] = ['index' => $n - 1, 'text' => (string) ($labels[$n - 1] ?? '')];
        }
        return $out;
    }

    private static function yGridLines(int $padL, int $padT, float $plotW, float $plotH, float $max): string
    {
        $svg = sprintf(
            '<rect x="%d" y="%d" width="%.1f" height="%.1f" fill="#fafbfc" stroke="#e2e8f0"/>',
            $padL,
            $padT,
            $plotW,
            $plotH
        );
        for ($t = 0; $t <= 4; $t++) {
            $y = $padT + $plotH - ($t / 4) * $plotH;
            $val = (int) round($max * $t / 4);
            $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#e2e8f0"/>', $padL, $y, $padL + $plotW, $y);
            $svg .= sprintf(
                '<text x="%d" y="%.1f" text-anchor="end" font-size="9" fill="#94a3b8" dominant-baseline="middle">%d</text>',
                $padL - 4,
                $y,
                $val
            );
        }
        return $svg;
    }

    private static function emptyChart(int $width, int $height, string $message): string
    {
        $text = sprintf(
            '<text x="50%%" y="50%%" text-anchor="middle" font-size="12" fill="#94a3b8">%s</text>',
            self::esc($message)
        );
        return self::svgWrap($width, $height, $text);
    }

    private static function svgWrap(int $width, int $height, string $inner): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="100%%" height="auto" role="img">%s</svg>',
            $width,
            $height,
            $inner
        );
    }

    private static function truncate(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max - 1) . '…';
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
