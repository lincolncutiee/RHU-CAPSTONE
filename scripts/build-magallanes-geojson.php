<?php
/**
 * Build magallanes-barangays.geojson — tries detailed sources, falls back to PSA.
 * Usage: php scripts/build-magallanes-geojson.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$outPath = $root . '/assets/geo/magallanes-barangays.geojson';
$expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];
$minVertices = 40;

$sources = [
    'altcoder-province-hires' => downloadFilteredProvinceBarangays(
        'https://raw.githubusercontent.com/altcoder/philippines-psgc-shapefiles/main/geojson/barangays/hires/barangays-province-1602000000.0.1.json',
        '160208'
    ),
    'osm-area' => fetchFromOsm(),
    'psa-namria' => fetchFromPsaZip($root),
];

foreach ($sources as $label => $features) {
    if (!is_array($features) || count($features) < 8) {
        echo "Skip {$label}: insufficient features\n";
        continue;
    }
    $vertices = totalVertices($features);
    echo "Source {$label}: " . count($features) . " features, {$vertices} vertices\n";
    if ($vertices >= $minVertices || $label === 'psa-namria') {
        writeGeoJson($outPath, $features, $expected);
        echo "Wrote GeoJSON from {$label}\n";
        foreach ($features as $f) {
            echo '  - ' . ($f['properties']['name'] ?? '?') . "\n";
        }
        exit(0);
    }
}

fwrite(STDERR, "All sources failed.\n");
exit(1);

// -------------------------------------------------------------------------

function httpGet(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 300,
            'header'  => "User-Agent: RHUWEB-GeoJSON-Builder\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return ($raw !== false && strlen($raw) > 200) ? $raw : null;
}

function httpPost(string $url, string $body): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: RHUWEB-GeoJSON-Builder\r\n",
            'content' => $body,
            'timeout' => 180,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return ($raw !== false && strlen($raw) > 200) ? $raw : null;
}

/** @return list<array<string,mixed>>|null */
function downloadFilteredProvinceBarangays(string $url, string $psgcPrefix): ?array
{
    echo "Trying {$url}...\n";
    $raw = httpGet($url);
    if ($raw === null) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['features'])) {
        return null;
    }

    $byName = [];
    foreach ($data['features'] as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $props = $feature['properties'] ?? [];
        $psgc = (string) ($props['PSGC'] ?? $props['psgc'] ?? $props['ADM4_PCODE'] ?? $props['adm4_pcode'] ?? '');
        if ($psgc !== '' && !str_starts_with($psgc, $psgcPrefix)) {
            continue;
        }
        $name = normalizeName(trim((string) ($props['ADM4_EN'] ?? $props['name'] ?? '')));
        if ($name === '') {
            continue;
        }
        $byName[$name] = featureFrom($name, $feature['geometry'] ?? null);
    }
    return pickExpected($byName);
}

/** @return list<array<string,mixed>>|null */
function fetchFromOsm(): ?array
{
    $query = <<<'QL'
[out:json][timeout:180];
area["name"="Agusan del Norte"]["admin_level"="4"]->.prov;
(
  relation(area.prov)["admin_level"="10"]["boundary"="administrative"]["name"~"^(Buhang|Caloc-an|Guiasan|Marcos|Poblacion|Santo Niño|Santo Nino|Santo Rosario|Taod-oy)$",i];
);
out geom;
QL;

    echo "Trying OpenStreetMap Overpass...\n";
    $endpoints = [
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];
    $raw = null;
    foreach ($endpoints as $endpoint) {
        $raw = httpPost($endpoint, 'data=' . rawurlencode($query));
        if ($raw !== null) {
            break;
        }
    }
    if ($raw === null) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['elements'])) {
        return null;
    }

    /** @var array<string, array<string,mixed>> $byName */
    $byName = [];
    foreach ($data['elements'] as $el) {
        if (($el['type'] ?? '') !== 'relation') {
            continue;
        }
        $name = normalizeName(trim((string) (($el['tags']['name'] ?? ''))));
        if ($name === '') {
            continue;
        }
        $geometry = geometryFromOsmRelation($el);
        if ($geometry === null) {
            continue;
        }
        $existing = $byName[$name] ?? null;
        if ($existing === null || vertexCount($geometry) > vertexCount($existing['geometry'])) {
            $byName[$name] = featureFrom($name, $geometry);
        }
    }
    return pickExpected($byName);
}

/** @return list<array<string,mixed>>|null */
function fetchFromPsaZip(string $root): ?array
{
    $geoDir = $root . '/assets/geo';
    $zipPath = $geoDir . '/_psa-barangay.zip';
    $zipUrl = 'https://github.com/bendlikeabamboo/barangay-boundaries-repository/releases/download/v2026.4.13.0/barangay-boundaries-2023-10-24-hierarchical.zip';

    if (!is_file($zipPath)) {
        echo "Downloading PSA zip...\n";
        $zipData = httpGet($zipUrl);
        if ($zipData === null) {
            return null;
        }
        file_put_contents($zipPath, $zipData);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return null;
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
    if ($barangayJson === false || $barangayJson === null) {
        return null;
    }

    $data = json_decode($barangayJson, true);
    unset($barangayJson);
    if (!is_array($data) || empty($data['features'])) {
        return null;
    }

    /** @var array<string, array<string,mixed>> $byName */
    $byName = [];
    foreach ($data['features'] as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $props = $feature['properties'] ?? [];
        if (strcasecmp((string) ($props['ADM2_EN'] ?? ''), 'Agusan del Norte') !== 0
            || strcasecmp((string) ($props['ADM3_EN'] ?? ''), 'Magallanes') !== 0) {
            continue;
        }
        $name = normalizeName(trim((string) ($props['ADM4_EN'] ?? '')));
        if ($name === '') {
            continue;
        }
        $byName[$name] = featureFrom($name, $feature['geometry'] ?? null);
    }
    return pickExpected($byName);
}

/** @param array<string, array<string,mixed>> $byName @return list<array<string,mixed>>|null */
function pickExpected(array $byName): ?array
{
    $expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];
    $features = [];
    foreach ($expected as $name) {
        if (!isset($byName[$name])) {
            return null;
        }
        $features[] = $byName[$name];
    }
    return $features;
}

/** @return array<string,mixed> */
function featureFrom(string $name, ?array $geometry): array
{
    return [
        'type'       => 'Feature',
        'properties' => ['name' => $name],
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
    $total = 0;
    foreach ($features as $f) {
        $total += vertexCount($f['geometry'] ?? null);
    }
    return $total;
}

/** @param list<array<string,mixed>> $features @param list<string> $expected */
function writeGeoJson(string $path, array $features, array $expected): void
{
    usort($features, static fn ($a, $b) => strcasecmp($a['properties']['name'], $b['properties']['name']));
    $collection = ['type' => 'FeatureCollection', 'features' => $features];
    file_put_contents($path, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
}

/** @return array<string,mixed>|null */
function geometryFromOsmRelation(array $relation): ?array
{
    $rings = [];
    foreach ($relation['members'] ?? [] as $member) {
        if (($member['type'] ?? '') !== 'way' || ($member['role'] ?? '') !== 'outer' || empty($member['geometry'])) {
            continue;
        }
        $ring = [];
        foreach ($member['geometry'] as $pt) {
            if (!isset($pt['lon'], $pt['lat'])) {
                continue;
            }
            $ring[] = [(float) $pt['lon'], (float) $pt['lat']];
        }
        if (count($ring) >= 4) {
            if ($ring[0] !== $ring[count($ring) - 1]) {
                $ring[] = $ring[0];
            }
            $rings[] = $ring;
        }
    }
    if ($rings === []) {
        return null;
    }
    return count($rings) === 1
        ? ['type' => 'Polygon', 'coordinates' => [$rings[0]]]
        : ['type' => 'MultiPolygon', 'coordinates' => array_map(static fn ($r) => [$r], $rings)];
}
