<?php
/**
 * Download HDX COD-AB phl_admin_boundaries.shp.zip (NAMRIA/PSA ADM4 shapefiles).
 *
 * Resource: https://data.humdata.org/dataset/cod-ab-phl
 * Expected size: 928515157 bytes (2026-05-28 HDX metadata).
 *
 * Usage: php scripts/download-hdx-shp.php
 */
declare(strict_types=1);

require_once __DIR__ . '/hdx-boundary-download.php';

$url = 'https://data.humdata.org/dataset/caf116df-f984-4deb-85ca-41b349d3f313/resource/4e4067d6-3fc8-4d2c-91ea-330c3d4f8e8e/download/phl_admin_boundaries.shp.zip';
$dest = dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.shp.zip';

/** HDX published size minus ~1% for CDN variance */
$minBytes = 920_000_000;

$result = hdx_download_resumable($url, $dest, $minBytes, 'HDX phl_admin_boundaries.shp.zip');
echo $result['message'] . "\n";
exit($result['ok'] ? 0 : 1);
