<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/HeatMap.php';
require_once __DIR__ . '/../core/MagallanesGeoJson.php';

$conn = getDBConnection();

echo "=== Magallanes GeoJSON Check ===\n";
$names = MagallanesGeoJson::barangayNames();
echo 'Barangays in GeoJSON: ' . count($names) . "\n";
foreach ($names as $n) {
    echo "  - {$n}\n";
}

$features = MagallanesGeoJson::barangayFeatures();
$minLat = 999; $maxLat = -999; $minLng = 999; $maxLng = -999;
foreach ($features as $f) {
    $coords = $f['geometry']['coordinates'][0] ?? [];
    foreach ($coords as $c) {
        $lng = (float) $c[0];
        $lat = (float) $c[1];
        $minLat = min($minLat, $lat); $maxLat = max($maxLat, $lat);
        $minLng = min($minLng, $lng); $maxLng = max($maxLng, $lng);
    }
}
echo "\nGeoJSON bounds (lat/lng):\n";
echo "  Lat: {$minLat} to {$maxLat}\n";
echo "  Lng: {$minLng} to {$maxLng}\n";
echo "  Center approx: " . round(($minLat + $maxLat) / 2, 4) . ", " . round(($minLng + $maxLng) / 2, 4) . "\n";
echo "  (Magallanes ADN is roughly 9.02N, 125.52E)\n";

$suggested = HeatMap::suggestDateRange($conn);
echo "\n=== Heat Map Data ({$suggested['from']} to {$suggested['to']}) ===\n";
$payload = HeatMap::build($conn, $suggested['from'], $suggested['to'], null);
echo 'Total active cases: ' . ($payload['summary']['total_active_cases'] ?? 0) . "\n";
echo 'Affected barangays: ' . ($payload['summary']['affected_barangays'] ?? 0) . '/' . ($payload['summary']['total_barangays'] ?? 0) . "\n\n";

echo "Barangay colors from DB:\n";
foreach ($payload['barangays'] as $b) {
    echo sprintf("  %-14s %3d cases  color=%s\n", $b['name'], $b['total_cases'], $b['color']);
}

$conn->close();
