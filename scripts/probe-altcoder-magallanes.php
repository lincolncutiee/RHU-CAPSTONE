<?php
$u = 'https://raw.githubusercontent.com/altcoder/philippines-psgc-shapefiles/main/geojson/barangays/hires/barangays-province-1602000000.0.1.json';
$ctx = stream_context_create(['http' => ['timeout' => 120, 'header' => "User-Agent: RHUWEB\r\n"]]);
$c = @file_get_contents($u, false, $ctx);
echo 'bytes: ' . strlen($c ?: '') . PHP_EOL;
if (!$c) {
    exit(1);
}
$j = json_decode($c, true);
echo 'features: ' . count($j['features'] ?? []) . PHP_EOL;
$n = 0;
foreach ($j['features'] ?? [] as $f) {
    $p = $f['properties'] ?? [];
    $psgc = (string) ($p['PSGC'] ?? $p['psgc'] ?? $p['ADM4_PCODE'] ?? $p['adm4_pcode'] ?? '');
    if ($psgc !== '' && str_starts_with($psgc, '160208')) {
        $n++;
        echo $psgc . ' ' . ($p['ADM4_EN'] ?? $p['name'] ?? '?') . PHP_EOL;
    }
}
echo 'magallanes count: ' . $n . PHP_EOL;
