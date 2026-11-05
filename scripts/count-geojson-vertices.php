<?php
$path = __DIR__ . '/../assets/geo/magallanes-barangays.geojson';
$geo = json_decode(file_get_contents($path), true);
foreach ($geo['features'] as $f) {
    $name = $f['properties']['name'] ?? '?';
    $geom = $f['geometry'];
    $count = 0;
    if ($geom['type'] === 'Polygon') {
        foreach ($geom['coordinates'] as $ring) {
            $count += count($ring);
        }
    } elseif ($geom['type'] === 'MultiPolygon') {
        foreach ($geom['coordinates'] as $poly) {
            foreach ($poly as $ring) {
                $count += count($ring);
            }
        }
    }
    echo "{$name}: {$count} vertices ({$geom['type']})\n";
}
