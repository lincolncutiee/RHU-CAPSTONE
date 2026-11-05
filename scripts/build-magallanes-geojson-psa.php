<?php
/**
 * Download PSA/NAMRIA barangay boundaries and extract Magallanes (PSGC 160208).
 * Usage: php scripts/build-magallanes-geojson-psa.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$geoDir = $root . '/assets/geo';
$outPath = $geoDir . '/magallanes-barangays.geojson';
$zipPath = $geoDir . '/_psa-barangay.zip';
$zipUrl = 'https://github.com/bendlikeabamboo/barangay-boundaries-repository/releases/download/v2026.4.13.0/barangay-boundaries-2023-10-24-hierarchical.zip';

if (is_file($zipPath)) {
    echo "Using cached zip: {$zipPath}\n";
    $zipData = file_get_contents($zipPath);
} else {
    echo "Downloading PSA/NAMRIA barangay boundaries (~7 MB)...\n";
    $zipData = downloadUrl($zipUrl);
    if ($zipData === null) {
        fwrite(STDERR, "Download failed. Try: curl -L -o {$zipPath} {$zipUrl}\n");
        exit(1);
    }
    file_put_contents($zipPath, $zipData);
}
echo 'Zip size: ' . strlen($zipData) . " bytes\n";

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Cannot open zip.\n");
    exit(1);
}

$barangayJson = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name !== false && basename($name) === 'barangays.geojson') {
        $barangayJson = $zip->getFromIndex($i);
        break;
    }
}
$zip->close();
@unlink($zipPath);

if ($barangayJson === false || $barangayJson === null) {
    fwrite(STDERR, "barangays.geojson not found in zip.\n");
    exit(1);
}

echo "Parsing barangays.geojson (" . strlen($barangayJson) . " bytes)...\n";
$data = json_decode($barangayJson, true);
unset($barangayJson);
if (!is_array($data) || empty($data['features'])) {
    fwrite(STDERR, "Invalid barangays.geojson\n");
    exit(1);
}

/** @var array<string, array<string,mixed>> $byName */
$byName = [];
foreach ($data['features'] as $feature) {
    if (!is_array($feature)) {
        continue;
    }
    $props = $feature['properties'] ?? [];
    $adm2 = (string) ($props['ADM2_EN'] ?? '');
    $adm3 = (string) ($props['ADM3_EN'] ?? '');
    if (strcasecmp($adm2, 'Agusan del Norte') !== 0 || strcasecmp($adm3, 'Magallanes') !== 0) {
        continue;
    }

    $name = trim((string) ($props['ADM4_EN'] ?? $props['name'] ?? ''));
    if ($name === '') {
        continue;
    }
        $normalized = normalizeName($name);
        $byName[$normalized] = [
        'type'       => 'Feature',
        'properties' => [
            'name'       => $normalized,
            'ADM4_EN'    => $name,
            'ADM3_EN'    => (string) ($props['ADM3_EN'] ?? 'Magallanes'),
            'ADM2_EN'    => (string) ($props['ADM2_EN'] ?? 'Agusan del Norte'),
            'ADM4_PCODE' => (string) ($props['ADM4_PCODE'] ?? $props['PSGC'] ?? ''),
            'source'     => 'PSA PSGC + NAMRIA v2023-11-06 (bendlikeabamboo barangays.geojson)',
        ],
        'geometry'   => $feature['geometry'] ?? null,
    ];
}

$expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];
$features = [];
foreach ($expected as $name) {
    if (!isset($byName[$name])) {
        fwrite(STDERR, "Missing: {$name}\n");
        continue;
    }
    $features[] = $byName[$name];
}

if (count($features) < 8) {
    $sample = $data['features'][0]['properties'] ?? [];
    fwrite(STDERR, 'Found only ' . count($features) . '/8. Property keys: ' . json_encode(array_keys($sample)) . "\n");
    exit(1);
}

usort($features, static fn ($a, $b) => strcasecmp($a['properties']['name'], $b['properties']['name']));
$collection = ['type' => 'FeatureCollection', 'features' => $features];
file_put_contents($outPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

echo 'Wrote ' . count($features) . " features to {$outPath}\n";
foreach ($features as $f) {
    echo '  - ' . $f['properties']['name'] . "\n";
}

function normalizeName(string $name): string
{
    $map = ['Santo Nino' => 'Santo Niño'];
    return $map[$name] ?? $name;
}

function downloadUrl(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => ['timeout' => 300, 'header' => "User-Agent: RHUWEB-GeoJSON-Builder\r\n"],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false && strlen($raw) > 1000) {
        return $raw;
    }

    $curl = trim((string) shell_exec('where curl 2>nul'));
    if ($curl === '') {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'rhu-geo-');
    if ($tmp === false) {
        return null;
    }
    $cmd = 'curl.exe -sS -L --fail -o ' . escapeshellarg($tmp) . ' ' . escapeshellarg($url);
    exec($cmd, $out, $code);
    $data = ($code === 0 && is_file($tmp)) ? file_get_contents($tmp) : false;
    @unlink($tmp);

    return ($data !== false && strlen($data) > 1000) ? $data : null;
}
