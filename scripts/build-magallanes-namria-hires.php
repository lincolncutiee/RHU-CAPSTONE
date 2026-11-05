<?php
/**
 * One-shot: download HDX NAMRIA boundaries (if needed) and extract Magallanes ADM4 GeoJSON.
 *
 * Usage: php scripts/build-magallanes-namria-hires.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$shpZip = $root . '/assets/geo/_hdx_phl_admin_boundaries.shp.zip';
$gdbZip = $root . '/assets/geo/_hdx_phl_admin_boundaries.gdb.zip';
$out = $root . '/assets/geo/magallanes-barangays-namria.geojson';

if (is_file($out)) {
    require_once $root . '/core/MagallanesGeoJson.php';
    // Force hires path check via temp rename — validate by reading out file directly
    $json = json_decode((string) file_get_contents($out), true);
    $features = $json['features'] ?? [];
    $total = 0;
    foreach ($features as $f) {
        $total += MagallanesGeoJson::geometryVertexCount($f['geometry'] ?? null);
    }
    if (count($features) >= 8 && $total >= 80) {
        echo "Already have adequate hires file ({$total} vertices).\n";
        exit(0);
    }
}

require_once __DIR__ . '/hdx-boundary-download.php';

$zipForExtract = null;
if (is_file($shpZip) && filesize($shpZip) >= 920_000_000 && hdx_verify_zip($shpZip)) {
    $zipForExtract = $shpZip;
}

if ($zipForExtract === null) {
    echo "HDX shapefile zip missing, incomplete, or corrupt.\n";
    echo "  php scripts/download-hdx-shp.php   (phl_admin_boundaries.shp.zip, ~928 MB from HDX cod-ab-phl)\n";
    if (is_file($gdbZip) && filesize($gdbZip) >= 355_000_000) {
        echo "\nNote: gdb zip is present but cannot be read without GDAL ogr2ogr. Use the shapefile zip.\n";
    }
    exit(1);
}

$node = trim((string) shell_exec('where node 2>nul') ?: '');
if ($node === '') {
    $node = 'node';
}

$cmd = escapeshellarg($node) . ' ' . escapeshellarg($root . '/scripts/extract-magallanes-hdx-adm4.mjs')
    . ' ' . escapeshellarg($zipForExtract);
echo "Running: {$cmd}\n";
passthru($cmd, $code);
exit($code);
