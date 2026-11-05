<?php
/**
 * Build magallanes-barangays.geojson from OpenStreetMap admin boundaries.
 * Targets Magallanes, Agusan del Norte only (bbox + official barangay names).
 *
 * Usage: php scripts/build-magallanes-geojson-osm.php
 */
declare(strict_types=1);

$outPath = dirname(__DIR__) . '/assets/geo/magallanes-barangays.geojson';

$officialNames = [
    'Buhang',
    'Caloc-an',
    'Guiasan',
    'Marcos',
    'Poblacion',
    'Santo Niño',
    'Santo Nino',
    'Santo Rosario',
    'Taod-oy',
];

$namePattern = implode('|', array_map(static fn (string $n) => preg_quote($n, '/'), $officialNames));

$query = <<<QL
[out:json][timeout:180];
(
  relation["boundary"="administrative"]["admin_level"="10"]["name"~"^({$namePattern})\$",i](8.95,125.44,9.08,125.58);
);
out geom;
QL;

$endpoints = [
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass-api.de/api/interpreter',
];

echo "Querying OpenStreetMap for Magallanes ADN barangays...\n";
$raw = false;
foreach ($endpoints as $endpoint) {
    echo "  Trying {$endpoint}...\n";
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: RHUWEB-GeoJSON-Builder\r\n",
            'content' => 'data=' . rawurlencode($query),
            'timeout' => 180,
        ],
    ]);
    $raw = @file_get_contents($endpoint, false, $ctx);
    if ($raw !== false && strlen($raw) > 100) {
        echo "  OK\n";
        break;
    }
    $raw = false;
}

if ($raw === false) {
    fwrite(STDERR, "Overpass request failed.\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data) || empty($data['elements'])) {
    fwrite(STDERR, "Unexpected Overpass response.\n");
    file_put_contents(dirname($outPath) . '/_overpass-debug.json', $raw);
    exit(1);
}

/** @var array<string, array<string,mixed>> $byName */
$byName = [];

foreach ($data['elements'] as $el) {
    if (($el['type'] ?? '') !== 'relation') {
        continue;
    }
    $tags = $el['tags'] ?? [];
    $name = normalizeName(trim((string) ($tags['name'] ?? '')));
    if ($name === '') {
        continue;
    }

    $rings = buildRings($el);
    if ($rings === []) {
        continue;
    }

    $geometry = count($rings) === 1
        ? ['type' => 'Polygon', 'coordinates' => [$rings[0]]]
        : ['type' => 'MultiPolygon', 'coordinates' => array_map(static fn ($r) => [$r], $rings)];

    // Keep largest geometry if duplicate names appear.
    $existing = $byName[$name] ?? null;
    if ($existing === null || ringPointCount($geometry) > ringPointCount($existing['geometry'])) {
        $byName[$name] = [
            'type'       => 'Feature',
            'properties' => ['name' => $name],
            'geometry'   => $geometry,
        ];
    }
}

$expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];
$features = [];
foreach ($expected as $name) {
    if (!isset($byName[$name])) {
        fwrite(STDERR, "Missing barangay from OSM: {$name}\n");
        continue;
    }
    $features[] = $byName[$name];
}

if (count($features) < 8) {
    fwrite(STDERR, 'Only found ' . count($features) . "/8 barangays. GeoJSON not overwritten.\n");
    exit(1);
}

$collection = ['type' => 'FeatureCollection', 'features' => $features];
$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    exit(1);
}

file_put_contents($outPath, $json . "\n");
echo 'Wrote ' . count($features) . " features to {$outPath}\n";
foreach ($features as $f) {
    echo '  - ' . ($f['properties']['name'] ?? '?') . "\n";
}

function normalizeName(string $name): string
{
    $map = [
        'Santo Nino'  => 'Santo Niño',
        'Santo Niño'  => 'Santo Niño',
        'Caloc An'    => 'Caloc-an',
        'Caloc-an'    => 'Caloc-an',
        'Taod Oy'     => 'Taod-oy',
        'Taod-oy'     => 'Taod-oy',
        'Poblacion'   => 'Poblacion',
    ];
    return $map[$name] ?? $name;
}

/** @return list<list<array{0:float,1:float}>> */
function buildRings(array $relation): array
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
    return $rings;
}

/** @param array<string,mixed> $geometry */
function ringPointCount(array $geometry): int
{
    $coords = $geometry['coordinates'] ?? [];
    $count = 0;
    if (($geometry['type'] ?? '') === 'Polygon') {
        foreach ($coords as $ring) {
            $count += is_array($ring) ? count($ring) : 0;
        }
    } elseif (($geometry['type'] ?? '') === 'MultiPolygon') {
        foreach ($coords as $poly) {
            foreach ($poly as $ring) {
                $count += is_array($ring) ? count($ring) : 0;
            }
        }
    }
    return $count;
}
