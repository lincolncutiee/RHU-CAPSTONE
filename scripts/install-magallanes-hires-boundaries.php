<?php
/**
 * Download HDX NAMRIA/PSA COD-AB boundaries (if needed) and build magallanes-barangays-namria.geojson.
 *
 * Prefers the shapefile zip (~928 MB) because the pipeline reads ADM4 .shp via Node shapefile.
 * The geodatabase zip alone cannot be converted without GDAL (ogr2ogr).
 *
 * Usage: php scripts/install-magallanes-hires-boundaries.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$php = (PHP_BINARY !== '') ? PHP_BINARY : 'php';

passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/download-hdx-shp.php'), $shpCode);
if ($shpCode !== 0) {
    fwrite(STDERR, "Shapefile download incomplete. Complete it, then re-run this script.\n");
    fwrite(STDERR, "  HDX: phl_admin_boundaries.shp.zip (cod-ab-phl, ~928 MB)\n");
    exit($shpCode);
}

passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/build-magallanes-namria-hires.php'), $buildCode);
if ($buildCode !== 0) {
    exit($buildCode);
}

passthru(escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/validate-magallanes-boundaries.php'), $valCode);
exit($valCode);
