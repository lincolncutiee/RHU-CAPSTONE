<?php
/**
 * Download official PSA/NAMRIA barangay boundaries for Magallanes (PSGC 160208)
 * and write assets/geo/magallanes-barangays.geojson
 *
 * Uses bendlikeabamboo/barangay-boundaries-repository (see build-magallanes-geojson-psa.php).
 *
 * Usage: php scripts/fetch-magallanes-geojson.php
 */
declare(strict_types=1);

require __DIR__ . '/build-magallanes-geojson-psa.php';
