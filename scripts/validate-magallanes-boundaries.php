<?php
/**
 * Report Magallanes barangay boundary quality (vertex counts, source file).
 * Usage: php scripts/validate-magallanes-boundaries.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/MagallanesGeoJson.php';

$report = MagallanesGeoJson::boundaryQualityReport();

echo "Magallanes barangay boundaries\n";
echo "File: {$report['file']}\n";
echo "Source: {$report['source']}\n";
echo "Features: {$report['feature_count']}\n";
echo "Total vertices: {$report['vertex_count']}\n";
echo "Min vertices (single barangay): {$report['min_vertices_per_feature']}\n";
echo "Quality level: {$report['level']}\n\n";

foreach ($report['vertices_by_barangay'] as $name => $count) {
    echo "  - {$name}: {$count} vertices\n";
}

if ($report['message'] !== '') {
    echo "\n{$report['message']}\n";
    exit($report['level'] === 'adequate' ? 0 : 2);
}

echo "\nBoundaries meet configured vertex thresholds.\n";
exit(0);
