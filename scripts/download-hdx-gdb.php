<?php
/**
 * Download HDX COD-AB phl_admin_boundaries.gdb.zip (File Geodatabase — needs GDAL to read).
 *
 * Expected size: 360586879 bytes. Extraction to GeoJSON requires ogr2ogr; prefer download-hdx-shp.php.
 *
 * Usage: php scripts/download-hdx-gdb.php
 */
declare(strict_types=1);

require_once __DIR__ . '/hdx-boundary-download.php';

$url = 'https://data.humdata.org/dataset/caf116df-f984-4deb-85ca-41b349d3f313/resource/3fa0dbcf-e07d-4506-9821-ba15baa6da07/download/phl_admin_boundaries.gdb.zip';
$dest = dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.gdb.zip';

$minBytes = 355_000_000;

$result = hdx_download_resumable($url, $dest, $minBytes, 'HDX phl_admin_boundaries.gdb.zip');
echo $result['message'] . "\n";
exit($result['ok'] ? 0 : 1);
