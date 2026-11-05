<?php
/**
 * Fetch barangay boundaries from OSM Overpass (bbox over Magallanes municipality).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$outPath = $root . '/assets/geo/magallanes-barangays-namria.geojson';
$expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];

$query = <<<'QL'
[out:json][timeout:240];
(
  relation["boundary"="administrative"]["admin_level"="10"](8.985,125.505,9.075,125.580);
);
out geom;
QL;

$endpoints = [
    'https://overpass.kumi.systems/api/interpreter',
    'https://overpass-api.de/api/interpreter',
];

$raw = null;
foreach ($endpoints as $endpoint) {
    echo "POST {$endpoint}\n";
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'data=' . rawurlencode($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_USERAGENT      => 'RHUWEB-OSM-Bbox/1.0',
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw !== false && $code === 200 && strlen($raw) > 500) {
        break;
    }
    $raw = null;
}

if ($raw === null) {
    fwrite(STDERR, "Overpass failed\n");
    exit(1);
}

$data = json_decode($raw, true);
$elements = $data['elements'] ?? [];
echo 'elements: ' . count($elements) . "\n";

/** @var array<string, array<string,mixed>> $byName */
$byName = [];
foreach ($elements as $el) {
    if (($el['type'] ?? '') !== 'relation') {
        continue;
    }
    $tags = $el['tags'] ?? [];
    $nameRaw = trim((string) ($tags['name'] ?? $tags['name:en'] ?? ''));
    if ($nameRaw === '') {
        continue;
    }
    $name = normalizeName($nameRaw);
    if (!in_array($name, $expected, true)) {
        // try match without Brgy prefix
        foreach ($expected as $exp) {
            if (stripos($nameRaw, $exp) !== false || strcasecmp($name, $exp) === 0) {
                $name = $exp;
                break;
            }
        }
        if (!in_array($name, $expected, true)) {
            echo "  skip: {$nameRaw}\n";
            continue;
        }
    }
    $geometry = geometryFromOsmRelation($el);
    if ($geometry === null) {
        continue;
    }
    $v = vertexCount($geometry);
    echo "  match {$name}: {$v} vertices\n";
    $existing = $byName[$name] ?? null;
    if ($existing === null || $v > vertexCount($existing['geometry'] ?? null)) {
        $byName[$name] = [
            'type'       => 'Feature',
            'properties' => [
                'name'    => $name,
                'ADM4_EN' => $name,
                'ADM3_EN' => 'Magallanes',
                'ADM2_EN' => 'Agusan del Norte',
                'source'  => 'OpenStreetMap admin_level=10 (Overpass bbox)',
                'osm_id'  => (string) ($el['id'] ?? ''),
            ],
            'geometry'   => $geometry,
        ];
    }
}

$features = [];
foreach ($expected as $name) {
    if (!isset($byName[$name])) {
        fwrite(STDERR, "Missing {$name}\n");
        exit(1);
    }
    $features[] = $byName[$name];
}

$total = array_sum(array_map(static fn ($f) => vertexCount($f['geometry'] ?? null), $features));
echo "Total vertices: {$total}\n";
if ($total < 80) {
    fwrite(STDERR, "OSM geometry too coarse\n");
    exit(2);
}

usort($features, static fn ($a, $b) => strcasecmp($a['properties']['name'], $b['properties']['name']));
file_put_contents($outPath, json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo "Wrote {$outPath}\n";

function normalizeName(string $name): string
{
    $name = preg_replace('/^(Brgy\.?|Barangay)\s+/iu', '', $name) ?? $name;
    $name = trim($name);
    $map = ['Santo Nino' => 'Santo Niño', 'Sto. Niño' => 'Santo Niño', 'Sto. Rosario' => 'Santo Rosario'];
    return $map[$name] ?? $name;
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
