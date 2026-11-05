<?php
/**
 * Surveillance map barangay boundaries (WGS84).
 * Default: PSA/NAMRIA-derived GeoJSON (see config heat-map.php).
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/MagallanesGeoJson.php';

requireLogin();

if (!Permissions::can('heatmap.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

try {
    $collection = MagallanesGeoJson::surveillanceBoundaryCollection();
    $features = MagallanesGeoJson::barangayFeaturesFromCollection($collection);
    echo json_encode([
        'success'        => true,
        'type'           => 'FeatureCollection',
        'features'       => $features,
        'boundary_meta'  => MagallanesGeoJson::boundaryQualityReport(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Boundary data unavailable.']);
}
