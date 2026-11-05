<?php
/**
 * Download full-resolution NAMRIA ADM4 barangays for Magallanes, Agusan del Norte.
 * Writes assets/geo/magallanes-barangays-namria.geojson when a detailed source succeeds.
 *
 * Usage: php scripts/fetch-magallanes-namria-hires.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$outPath = $root . '/assets/geo/magallanes-barangays-namria.geojson';
$expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];
$minTotal = 80;
$minPer = 12;

$sources = [
    'bendlikeabamboo-adm4-enriched' => [
        'https://media.githubusercontent.com/media/bendlikeabamboo/barangay-boundaries-repository/main/enriched_t0p005/adm4.geojson',
        'https://raw.githubusercontent.com/bendlikeabamboo/barangay-boundaries-repository/main/enriched_t0p005/adm4.geojson',
    ],
    'bendlikeabamboo-adm4-raw' => [
        'https://media.githubusercontent.com/media/bendlikeabamboo/barangay-boundaries-repository/main/raw_t0p005/adm4.geojson',
        'https://raw.githubusercontent.com/bendlikeabamboo/barangay-boundaries-repository/main/raw_t0p005/adm4.geojson',
    ],
    'bendlikeabamboo-release-barangays' => [
        'https://github.com/bendlikeabamboo/barangay-boundaries-repository/releases/download/v2026.4.13.0/barangays.geojson',
    ],
    'hdx-adm4-stream' => [], // filled below if local cache exists
];

$hdxCache = $root . '/assets/geo/_hdx-adm4.geojson';
if (is_readable($hdxCache)) {
    $sources['hdx-local-adm4'] = [$hdxCache];
}

foreach ($sources as $label => $urls) {
    foreach ($urls as $url) {
        echo "Trying {$label}: {$url}\n";
        $features = null;
        if (str_starts_with($url, 'http')) {
            $features = extractMagallanesFromRemoteGeoJson($url);
        } elseif (is_readable($url)) {
            $features = extractMagallanesFromFile($url);
        }
        if ($features === null) {
            echo "  -> failed or empty\n";
            continue;
        }
        $total = totalVertices($features);
        $min = min(array_map(static fn ($f) => vertexCount($f['geometry'] ?? null), $features));
        echo "  -> " . count($features) . " features, {$total} vertices (min {$min})\n";
        if (count($features) < 8) {
            continue;
        }
        if ($total >= $minTotal && $min >= $minPer) {
            writeCollection($outPath, $features, $label);
            echo "Wrote {$outPath}\n";
            exit(0);
        }
        echo "  -> too simplified for production threshold\n";
    }
}

$build = dirname(__FILE__) . '/build-magallanes-namria-hires.php';
if (is_file($build)) {
    fwrite(STDERR, "Trying HDX NAMRIA pipeline (scripts/build-magallanes-namria-hires.php)…\n");
    $phpBin = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    passthru(escapeshellarg($phpBin) . ' ' . escapeshellarg($build), $code);
    exit($code);
}

fwrite(STDERR, "No full-resolution source available automatically.\n");
fwrite(STDERR, "Run: php scripts/download-hdx-shp.php then php scripts/build-magallanes-namria-hires.php\n");
exit(1);

// -------------------------------------------------------------------------

/** @return list<array<string,mixed>>|null */
function extractMagallanesFromRemoteGeoJson(string $url): ?array
{
    $path = streamGeoJsonToTemp($url);
    if ($path === null) {
        return null;
    }
    try {
        return extractMagallanesFromFile($path);
    } finally {
        @unlink($path);
    }
}

/** @return list<array<string,mixed>>|null */
function extractMagallanesFromFile(string $path): ?array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    $buf = fread($handle, 8192);
    if ($buf === false) {
        fclose($handle);
        return null;
    }

    // Small FeatureCollection (project file)
    if (str_contains($buf, '"features"') && filesize($path) < 50_000_000) {
        fclose($handle);
        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return filterMagallanesFeatures($data['features'] ?? []);
    }

    // Stream scan for Magallanes ADM3 in large national files
    rewind($handle);
    $features = streamFilterMagallanes($handle);
    fclose($handle);

    return $features === [] ? null : pickExpected($features);
}

/**
 * Stream-read a huge GeoJSON FeatureCollection without loading entire file.
 *
 * @return array<string, array<string,mixed>>
 */
function streamFilterMagallanes($handle): array
{
    /** @var array<string, array<string,mixed>> $byName */
    $byName = [];
    $depth = 0;
    $inFeatures = false;
    $inFeature = false;
    $featureDepth = 0;
    $chunk = '';
    $maxChunk = 8_000_000;

    while (!feof($handle)) {
        $read = fread($handle, 65536);
        if ($read === false || $read === '') {
            break;
        }
        $chunk .= $read;

        if (!$inFeatures && preg_match('/"features"\s*:\s*\[/', $chunk)) {
            $inFeatures = true;
        }

        while ($inFeatures) {
            if (!$inFeature && preg_match('/\{\s*"type"\s*:\s*"Feature"/', $chunk, $m, PREG_OFFSET_CAPTURE)) {
                $start = $m[0][1];
                $sub = substr($chunk, $start);
                $featureDepth = 0;
                $len = strlen($sub);
                $end = null;
                for ($i = 0; $i < $len; $i++) {
                    $c = $sub[$i];
                    if ($c === '{') {
                        $featureDepth++;
                    } elseif ($c === '}') {
                        $featureDepth--;
                        if ($featureDepth === 0) {
                            $end = $i + 1;
                            break;
                        }
                    }
                }
                if ($end === null) {
                    break;
                }
                $featureJson = substr($sub, 0, $end);
                $chunk = substr($chunk, $start + $end);
                $feature = json_decode($featureJson, true);
                if (is_array($feature)) {
                    maybeKeepFeature($feature, $byName);
                }
                continue;
            }
            break;
        }

        if (strlen($chunk) > $maxChunk) {
            $chunk = substr($chunk, -2_000_000);
        }
    }

    return $byName;
}

/** @param array<string, array<string,mixed>> $byName */
function maybeKeepFeature(array $feature, array &$byName): void
{
    $props = $feature['properties'] ?? [];
    if (!is_array($props)) {
        return;
    }
    $adm2 = (string) ($props['ADM2_EN'] ?? $props['adm2_en'] ?? '');
    $adm3 = (string) ($props['ADM3_EN'] ?? $props['adm3_en'] ?? '');
    $pcode = (string) ($props['ADM4_PCODE'] ?? $props['adm4_pcode'] ?? $props['PSGC'] ?? '');
    $matchMuni = strcasecmp($adm3, 'Magallanes') === 0 && strcasecmp($adm2, 'Agusan del Norte') === 0;
    $matchPsgc = str_starts_with($pcode, '160208');
    if (!$matchMuni && !$matchPsgc) {
        return;
    }
    $name = normalizeName(trim((string) ($props['ADM4_EN'] ?? $props['name'] ?? '')));
    if ($name === '') {
        return;
    }
    $existing = $byName[$name] ?? null;
    $new = featureFrom($name, $feature['geometry'] ?? null, $props);
    if ($existing === null || vertexCount($new['geometry'] ?? null) > vertexCount($existing['geometry'] ?? null)) {
        $byName[$name] = $new;
    }
}

/** @param list<mixed> $features @return list<array<string,mixed>>|null */
function filterMagallanesFeatures(array $features): ?array
{
    /** @var array<string, array<string,mixed>> $byName */
    $byName = [];
    foreach ($features as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        maybeKeepFeature($feature, $byName);
    }
    return pickExpected($byName);
}

function streamGeoJsonToTemp(string $url): ?string
{
    $tmp = sys_get_temp_dir() . '/rhuweb-adm4-' . md5($url) . '.geojson';
    if (is_file($tmp) && filesize($tmp) > 1000) {
        echo "  (using cached temp " . basename($tmp) . ")\n";
        return $tmp;
    }

    $fp = fopen($tmp, 'wb');
    if ($fp === false) {
        return null;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        fclose($fp);
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 3600,
        CURLOPT_USERAGENT      => 'RHUWEB-Boundary-Fetch/1.0',
        CURLOPT_FAILONERROR    => true,
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $code >= 400 || !is_file($tmp) || filesize($tmp) < 500) {
        @unlink($tmp);
        return null;
    }

    return $tmp;
}

/** @param array<string, array<string,mixed>> $byName @return list<array<string,mixed>>|null */
function pickExpected(array $byName): ?array
{
    global $expected;
    $features = [];
    foreach ($expected as $name) {
        if (!isset($byName[$name])) {
            return null;
        }
        $features[] = $byName[$name];
    }
    return $features;
}

/** @param array<string,mixed> $props @return array<string,mixed> */
function featureFrom(string $name, ?array $geometry, array $props = []): array
{
    return [
        'type'       => 'Feature',
        'properties' => [
            'name'       => $name,
            'ADM4_EN'    => (string) ($props['ADM4_EN'] ?? $name),
            'ADM3_EN'    => (string) ($props['ADM3_EN'] ?? 'Magallanes'),
            'ADM2_EN'    => (string) ($props['ADM2_EN'] ?? 'Agusan del Norte'),
            'ADM4_PCODE' => (string) ($props['ADM4_PCODE'] ?? $props['PSGC'] ?? ''),
            'source'     => 'NAMRIA ADM4 (automated fetch)',
        ],
        'geometry'   => $geometry,
    ];
}

function normalizeName(string $name): string
{
    $map = ['Santo Nino' => 'Santo Niño'];
    return $map[$name] ?? $name;
}

/** @param array<string,mixed>|null $geometry */
function vertexCount(?array $geometry): int
{
    if ($geometry === null) {
        return 0;
    }
    $count = 0;
    $type = $geometry['type'] ?? '';
    $coords = $geometry['coordinates'] ?? [];
    if ($type === 'Polygon') {
        foreach ($coords as $ring) {
            $count += is_array($ring) ? count($ring) : 0;
        }
    } elseif ($type === 'MultiPolygon') {
        foreach ($coords as $poly) {
            foreach ($poly as $ring) {
                $count += is_array($ring) ? count($ring) : 0;
            }
        }
    }
    return $count;
}

/** @param list<array<string,mixed>> $features */
function totalVertices(array $features): int
{
    $t = 0;
    foreach ($features as $f) {
        $t += vertexCount($f['geometry'] ?? null);
    }
    return $t;
}

/** @param list<array<string,mixed>> $features */
function writeCollection(string $path, array $features, string $sourceLabel): void
{
    usort($features, static fn ($a, $b) => strcasecmp($a['properties']['name'], $b['properties']['name']));
    foreach ($features as &$f) {
        $f['properties']['fetch_source'] = $sourceLabel;
    }
    unset($f);
    $collection = ['type' => 'FeatureCollection', 'features' => $features];
    file_put_contents($path, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
}
