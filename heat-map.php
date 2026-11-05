<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/HeatMap.php';
require_once __DIR__ . '/core/MagallanesGeoJson.php';

requireLogin();
require_once __DIR__ . '/core/Permissions.php';
Permissions::requireCan('heatmap.view');

$heatMapConfig = require __DIR__ . '/config/heat-map.php';
$mapView = MagallanesGeoJson::mapViewConfig();

$conn = getDBConnection();
$suggestedDates = HeatMap::suggestDateRange($conn);
$conn->close();

$defaultDateFrom = $suggestedDates['from'];
$defaultDateTo = $suggestedDates['to'];
$geoJsonUrl = BASE_URL . HeatMap::geoJsonPath();
$boundaryGeoUrl = BASE_URL . '/api/heat_map_boundaries.php';
$refMap = $heatMapConfig['admin_map_reference'] ?? [];
$refImage = (string) ($refMap['image'] ?? '/assets/img/magallanes-map-reference.png');
$refImagePath = __DIR__ . '/' . ltrim(str_replace('\\', '/', $refImage), '/');
$refImageVer = is_file($refImagePath) ? (int) filemtime($refImagePath) : time();
$refImageUrl = BASE_URL . '/' . ltrim($refImage, '/') . '?v=' . $refImageVer;
$mapStyle = (string) ($heatMapConfig['map_style'] ?? 'surveillance_heat');

$pageTitle = 'Heat Map';
$pageSubtitle = 'Disease Surveillance';
$activePage = 'heat-map';
$hidePageTitleBar = true;
$hidePatientSearch = true;
$assetVer = 56;
$extraCSS = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    BASE_URL . '/assets/css/heat-map.css?v=' . $assetVer,
];
$extraJS = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js',
    BASE_URL . '/assets/js/heat-map.js?v=' . $assetVer,
];

include __DIR__ . '/includes/header.php';
?>

<div class="hm-page hm-page--gis" id="heatMapPage"
     data-api-url="<?= htmlspecialchars(BASE_URL . '/api/heat_map_data.php') ?>"
     data-geo-url="<?= htmlspecialchars($geoJsonUrl . '?v=' . $assetVer) ?>"
     data-boundary-geo-url="<?= htmlspecialchars($boundaryGeoUrl . '?v=' . $assetVer) ?>"
     data-map-center="<?= htmlspecialchars(json_encode($mapView['center'])) ?>"
     data-map-zoom="<?= (int) ($heatMapConfig['map_zoom'] ?? 13) ?>"
     data-map-bounds="<?= htmlspecialchars(json_encode($mapView['bounds'])) ?>"
     data-map-style="<?= htmlspecialchars($mapStyle) ?>"
     data-label-anchors="<?= htmlspecialchars(json_encode($mapView['label_anchors'])) ?>"
     data-map-options="<?= htmlspecialchars(json_encode([
         'fitPadding' => $heatMapConfig['map_fit_padding_px'] ?? [24, 24],
         'maxBoundsPad' => $heatMapConfig['map_max_bounds_pad'] ?? 0.006,
         'maxZoomOffset' => $heatMapConfig['map_max_zoom_offset'] ?? 2,
     ])) ?>"
     data-choropleth="<?= htmlspecialchars(json_encode($heatMapConfig['choropleth_fill_opacity'] ?? [])) ?>"
     data-heat-layer="<?= htmlspecialchars(json_encode($heatMapConfig['heat_layer'] ?? [])) ?>"
     data-basemap="<?= htmlspecialchars(json_encode($heatMapConfig['basemap'] ?? [])) ?>"
     data-surveillance-outline="<?= htmlspecialchars(json_encode($heatMapConfig['surveillance_outline'] ?? [])) ?>"
     data-surveillance-render-mode="<?= htmlspecialchars((string) ($heatMapConfig['surveillance_render_mode'] ?? 'choropleth')) ?>"
     data-surveillance-choropleth-fill="<?= htmlspecialchars(json_encode($heatMapConfig['surveillance_choropleth_fill'] ?? [])) ?>"
     data-surveillance-choropleth-stroke="<?= htmlspecialchars(json_encode($heatMapConfig['surveillance_choropleth_stroke'] ?? [])) ?>"
     data-surveillance-soft-heat="<?= !empty($heatMapConfig['surveillance_choropleth_soft_heat']) ? '1' : '0' ?>"
     data-surveillance-show-boundaries="<?= !empty($heatMapConfig['surveillance_show_boundaries']) ? '1' : '0' ?>"
     data-surveillance-boundary-layer="<?= htmlspecialchars(json_encode($heatMapConfig['surveillance_boundary_layer'] ?? [])) ?>"
     data-date-from="<?= htmlspecialchars($defaultDateFrom) ?>"
     data-date-to="<?= htmlspecialchars($defaultDateTo) ?>">

  <div class="hm-gis-stage">
    <div id="hmMap" class="hm-gis-map" role="application"
         aria-label="Magallanes disease surveillance heat map"></div>

    <div class="hm-gis-loading d-none" id="hmMapLoading">
      <div class="spinner-border text-primary" role="status"></div>
      <span>Loading heat map…</span>
    </div>

    <div class="hm-gis-empty d-none" id="hmMapEmpty" role="status">
      <i class="bi bi-info-circle"></i>
      <span>No active cases for the selected period.</span>
    </div>

    <header class="hm-gis-panel hm-gis-toolbar">
      <div class="hm-gis-toolbar__brand">
        <i class="bi bi-activity"></i>
        <span>Disease heat map · Magallanes</span>
      </div>
      <div class="hm-gis-toolbar__filters">
        <input type="date" id="hmDateFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultDateFrom) ?>" aria-label="Date from">
        <span class="hm-gis-sep">–</span>
        <input type="date" id="hmDateTo" class="form-control form-control-sm" value="<?= htmlspecialchars($defaultDateTo) ?>" aria-label="Date to">
        <select id="hmDiseaseFilter" class="form-select form-select-sm" aria-label="Disease filter">
          <option value="all">All Active Diseases</option>
        </select>
        <button type="button" class="btn btn-primary btn-sm" id="hmRefreshBtn" title="Refresh">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
        <a href="<?= htmlspecialchars($refImageUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" title="Printed RHU barangay map — reference only, not the live heat map">
          <i class="bi bi-map" aria-hidden="true"></i>
          <span class="d-none d-md-inline">RHU map</span>
        </a>
      </div>
    </header>

    <aside class="hm-gis-panel hm-gis-stats">
      <p class="hm-gis-stats__scope" id="hmSummaryScope">All Active Diseases</p>
      <dl class="hm-gis-stats__grid">
        <div><dt>Cases</dt><dd id="hmStatTotalCases">—</dd></div>
        <div><dt>Barangays</dt><dd id="hmStatBarangays">—</dd></div>
        <div><dt>Puroks</dt><dd id="hmStatPuroks">—</dd></div>
        <div><dt>Consults</dt><dd id="hmStatConsultations">—</dd></div>
      </dl>
    </aside>

    <aside class="hm-gis-panel hm-gis-legend hm-gis-legend--collapsed" id="hmLegendPanel">
      <button type="button" class="hm-gis-legend__toggle" id="hmLegendToggle" aria-expanded="false" aria-controls="hmLegendBody">
        <span class="hm-gis-legend__toggle-label">Legend</span>
        <i class="bi bi-chevron-down hm-gis-legend__chevron" aria-hidden="true"></i>
      </button>
      <div class="hm-gis-legend__body" id="hmLegendBody" hidden>
        <h2 class="hm-gis-legend__title">Disease case density</h2>
        <div class="hm-choro-ramp hm-choro-ramp--heat" id="hmChoroRamp" aria-hidden="true"></div>
        <ul class="hm-gis-legend__list" id="hmLegend"></ul>
      </div>
    </aside>

    <aside class="hm-gis-panel hm-gis-detail" id="hmDetailPanel">
      <div class="hm-gis-detail__head">
        <h2 class="hm-gis-detail__title">Barangay</h2>
        <button type="button" class="hm-gis-detail__close d-none" id="hmDetailClose" aria-label="Close details">&times;</button>
      </div>
      <p class="hm-gis-detail__empty" id="hmDetailEmpty">Click a barangay on the map.</p>
      <div class="hm-gis-detail__body d-none" id="hmDetailBody">
        <p class="hm-gis-detail__name" id="hmDetailBarangay"></p>
        <p class="hm-gis-detail__total"><span id="hmDetailTotalLabel">Active Cases</span> <strong id="hmDetailTotal"></strong></p>
        <h3 class="hm-gis-detail__heading" id="hmDiseaseHeading">Diseases</h3>
        <ul class="hm-gis-breakdown" id="hmDiseaseList"></ul>
        <h3 class="hm-gis-detail__heading">Puroks</h3>
        <ul class="hm-gis-breakdown" id="hmPurokList"></ul>
      </div>
    </aside>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
