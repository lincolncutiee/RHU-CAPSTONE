<?php
/**
 * Heat Map data API — active disease cases by barangay.
 *
 * Query params:
 *   date_from   — YYYY-MM-DD (omit to use suggested range from existing records)
 *   date_to     — YYYY-MM-DD (omit to use today)
 *   disease_id  — optional diagnosis_master.id (omit for all active diseases)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/HeatMap.php';

requireLogin();

if (!Permissions::can('heatmap.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$hasExplicitDates = isset($_GET['date_from']) || isset($_GET['date_to']);

try {
    $conn = getDBConnection();
    $suggested = HeatMap::suggestDateRange($conn);

    $dateFrom = trim($_GET['date_from'] ?? $suggested['from']);
    $dateTo = trim($_GET['date_to'] ?? $suggested['to']);

    $diseaseId = isset($_GET['disease_id']) && $_GET['disease_id'] !== '' && $_GET['disease_id'] !== 'all'
        ? (int) $_GET['disease_id']
        : null;

    $payload = HeatMap::build($conn, $dateFrom, $dateTo, $diseaseId);
    $conn->close();

    echo json_encode([
        'success' => true,
        'data'    => $payload,
        'meta'    => [
            'suggested_date_from' => $suggested['from'],
            'suggested_date_to'   => $suggested['to'],
            'has_diagnosis_records' => $suggested['has_records'],
            'used_suggested_dates'  => !$hasExplicitDates,
            'geojson_path'          => HeatMap::geoJsonPath(),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load heat map data.',
    ]);
}
