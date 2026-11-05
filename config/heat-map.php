<?php
/**
 * Heat Map — disease surveillance configuration.
 *
 * Active case: consultation with a linked diagnosis within the selected date range,
 * and still within the disease monitoring window from the consultation date.
 */

return [
    /** Default monitoring period (days) when no disease-specific rule matches. */
    'default_monitoring_days' => 90,

    /** Fallback date window (days) when no consultation/diagnosis records exist. */
    'fallback_date_window_days' => 90,

    /**
     * Barangay boundary GeoJSON — geometries and names are the map source of truth.
     * Replace this file with official PSA/NAMRIA boundaries without changing HeatMap logic.
     */
    'geojson_path' => '/assets/geo/magallanes-barangays.geojson',

    /**
     * Optional full-resolution NAMRIA ADM4 extract (Magallanes barangays only).
     * When this file exists, it replaces geojson_path for map boundaries.
     * Obtain from NAMRIA Geoportal, PSA, or HDX COD-AB (phl_admin_boundaries).
     */
    'geojson_hires_path' => '/assets/geo/magallanes-barangays-namria.geojson',

    /** Vertex thresholds — below this, UI warns that boundaries are simplified. */
    'boundary_min_vertices_per_barangay' => 12,
    'boundary_min_total_vertices'       => 80,

    /** Yellow simplified-boundary warning on the map page (off = hidden). */
    'show_boundary_quality_banner' => false,

    /**
     * Barangay outlines traced on the official LGU poster (pixel coordinates, CRS.Simple).
     * Used when map_style = official on the Heat Map page.
     */
    'poster_geojson_path' => '/assets/geo/magallanes-poster-barangays.geojson',

    /**
     * Disease-specific monitoring windows (days).
     * Keys match diagnosis_name (case-insensitive substring) or icd_code prefix.
     */
    'monitoring_days' => [
        'dengue'        => 14,
        'tuberculosis'  => 180,
        'hypertension'  => 365,
        'diabetes'      => 365,
        'A90'           => 14,
        'A15'           => 180,
        'I10'           => 365,
        'E11'           => 365,
    ],

    /** Case intensity tiers — YlOrRd GIS choropleth ramp (epidemiology standard). */
    'intensity_tiers' => [
        ['min' => 31, 'level' => 'very_high', 'label' => 'Very High (31+)',  'color' => '#b91c1c'],
        ['min' => 16, 'level' => 'high',      'label' => 'High (16–30)',     'color' => '#ea580c'],
        ['min' => 6,  'level' => 'moderate',  'label' => 'Moderate (6–15)',  'color' => '#f59e0b'],
        ['min' => 1,  'level' => 'low',       'label' => 'Low (1–5)',        'color' => '#fde047'],
        ['min' => 0,  'level' => 'base',      'label' => 'No active cases',  'color' => '#22c55e'],
    ],

    /** Choropleth fill opacity (0–1). Zero = LGU barangay base colors; case = disease intensity. */
    'choropleth_fill_opacity' => [
        'zero'     => 0.82,
        'case'     => 0.88,
        'hover'    => 0.92,
        'selected' => 0.96,
    ],

    /** Heat Map: surveillance_heat (OSM + case heat from DB) | choropleth | magallanes_lgu (legacy poster). */
    'map_style' => 'surveillance_heat',

    /** Approximate map center — Magallanes, Agusan del Norte. */
    'map_center' => [9.022, 125.517],
    'map_zoom'   => 13,

    /** Light topographic basemap — standard for public-health choropleth maps (Esri, no API key). */
    'basemap' => [
        'url'         => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
        'max_zoom'    => 19,
        'attribution' => 'Tiles &copy; <a href="https://www.esri.com/">Esri</a> &mdash; World Topo',
    ],

    /**
     * Normal epi map = choropleth (colored barangay zones from case counts).
     * density_heat = Leaflet.heat blobs at barangay anchors (optional).
     */
    /** choropleth = whole barangay polygon filled by case tier (inside boundaries). */
    'surveillance_render_mode' => 'choropleth',

    /** Surveillance outlines: psa_geojson (NAMRIA/PSA) | poster_georef (legacy LGU trace — not for production). */
    'surveillance_boundary_source' => 'psa_geojson',

    /** Outline-only mode (density_heat). Choropleth uses stroke below. */
    'surveillance_outline' => [
        'enabled'      => false,
        'color'        => '#64748b',
        'weight'       => 1.25,
        'opacity'      => 0.85,
        'fill_opacity' => 0,
    ],

    /** Interactive barangay polygons (GeoJSON) visible above heat layer. */
    'surveillance_show_boundaries' => true,

    'surveillance_boundary_layer' => [
        'fill_color'    => '#ffffff',
        'fill_opacity'  => 0.05,
        'fill_hover'    => 0.12,
        'fill_selected' => 0.22,
        'stroke'        => '#334155',
        'stroke_hover'  => '#0284c7',
        'stroke_selected' => '#0369a1',
        'weight'        => 1.35,
        'weight_hover'  => 2.1,
        'weight_selected' => 3,
        'opacity'       => 0.92,
    ],

    'surveillance_choropleth_stroke' => [
        'color'  => '#334155',
        'weight' => 1.25,
        'opacity'=> 0.9,
    ],

    /** Fit/focus — keep view on Magallanes municipality only. */
    'map_fit_padding_px'    => [24, 24],
    'map_max_bounds_pad'    => 0.004,
    'map_max_zoom_offset'   => 2,

    /**
     * Map view + case/heat anchors for surveillance_heat (WGS84).
     * When use_poster_georef_anchors is true, anchors derive from RHU poster label pixels below.
     */
    /** Label / heat anchors from official polygon geometry (not LGU poster pixels). */
    'use_poster_georef_anchors' => false,
    'poster_georef' => [
        'north'  => 9.067,
        'south'  => 8.996,
        'west'   => 125.508,
        'east'   => 125.572,
        'width'  => 457,
        'height' => 532,
    ],
    'surveillance_bounds' => [
        'south' => 8.992,
        'west'  => 125.512,
        'north' => 9.070,
        'east'  => 125.575,
    ],

    /** Fill opacity when surveillance_render_mode = choropleth. */
    'surveillance_choropleth_fill' => [
        'zero'     => 0.82,
        'case'     => 0.88,
        'hover'    => 0.92,
        'selected' => 0.96,
    ],

    /** Soft YlOrRd glow under colored zones (standard blended heat look). */
    'surveillance_choropleth_soft_heat' => false,

    /** Leaflet.heat tuning — used when surveillance_render_mode = density_heat. */
    'heat_layer' => [
        'base_radius'     => 22,
        'max_radius'      => 46,
        'base_blur'       => 20,
        'max_blur'        => 34,
        'min_opacity'     => 0.48,
        'weight_power'    => 0.72,
        'spread_points'   => 3,
        'spread_meters'   => 220,
        'reference_zoom'  => 13,
        'gradient'        => [
            '0.0'  => '#fef08a',
            '0.35' => '#fde047',
            '0.58' => '#fb923c',
            '1.0'  => '#dc2626',
        ],
    ],

    /**
     * Fallback barangay anchors [lat, lng] when poster georef is disabled.
     * @var array<string, array{0: float, 1: float}>
     */
    'label_positions' => [
        'Caloc-an'      => [9.046, 125.559],
        'Buhang'        => [9.029, 125.543],
        'Taod-oy'       => [9.026, 125.559],
        'Guiasan'       => [9.014, 125.539],
        'Poblacion'     => [9.026, 125.533],
        'Santo Niño'    => [9.020, 125.522],
        'Marcos'        => [9.044, 125.536],
        'Santo Rosario' => [9.030, 125.523],
    ],

    /** Official LGU map — used as the heat map basemap (not a separate panel). */
    'admin_map_reference' => [
        'image' => '/assets/img/magallanes-map-reference.png',
        'title' => 'Magallanes barangay map',
        'caption' => 'Live map = disease data on Magallanes geography. Printed RHU map is reference only (toolbar).',
        'poster_max_width_px' => 720,
        /** Matches printed LGU map legend (approximate). Keys = official barangay names. */
        'barangay_colors' => [
            'Buhang'        => '#9e4b3c',
            'Caloc-an'      => '#c9a227',
            'Guiasan'       => '#7eb8c9',
            'Marcos'        => '#9ca3af',
            'Poblacion'     => '#2563eb',
            'Santo Niño'    => '#166534',
            'Santo Rosario' => '#22c55e',
            'Taod-oy'       => '#a78bfa',
        ],
        /** Label anchors on the poster image [x, y] pixels (optional; centroids used if omitted). */
        'poster_label_positions' => [
            'Caloc-an'      => [360, 158],
            'Taod-oy'       => [368, 310],
            'Guiasan'       => [218, 395],
            'Buhang'        => [248, 252],
            'Marcos'        => [198, 168],
            'Poblacion'     => [172, 308],
            'Santo Niño'    => [98, 352],
            'Santo Rosario' => [108, 248],
        ],
    ],
];
