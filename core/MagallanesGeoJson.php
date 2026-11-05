<?php
/**
 * Magallanes barangay boundaries — names and geometries from GeoJSON only.
 */

class MagallanesGeoJson
{
    /** @var array<string, mixed>|null */
    private static ?array $collection = null;

    /** @var list<string>|null */
    private static ?array $barangayNames = null;

    /** @var array<string, string> */
    private static array $aliasMap = [
        'santo nino'  => 'Santo Niño',
        'santo niño'  => 'Santo Niño',
        'santo rosario' => 'Santo Rosario',
        'caloc an'    => 'Caloc-an',
        'caloc-an'    => 'Caloc-an',
        'taod oy'     => 'Taod-oy',
        'taod-oy'     => 'Taod-oy',
    ];

    public static function filePath(): string
    {
        $cfg = require __DIR__ . '/../config/heat-map.php';
        $hiresRel = (string) ($cfg['geojson_hires_path'] ?? '');
        if ($hiresRel !== '') {
            $hiresPath = __DIR__ . '/..' . $hiresRel;
            if (self::isAdequateBoundaryFile($hiresPath, $cfg)) {
                return $hiresPath;
            }
        }

        $relative = (string) ($cfg['geojson_path'] ?? '/assets/geo/magallanes-barangays.geojson');

        return __DIR__ . '/..' . $relative;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function isAdequateBoundaryFile(string $path, array $cfg): bool
    {
        if (!is_readable($path)) {
            return false;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json)) {
            return false;
        }

        $features = self::barangayFeaturesFromCollection($json);
        $minPer = max(1, (int) ($cfg['boundary_min_vertices_per_barangay'] ?? 12));
        $minTotal = max(8, (int) ($cfg['boundary_min_total_vertices'] ?? 80));

        $total = 0;
        $minFeature = PHP_INT_MAX;
        foreach ($features as $feature) {
            $count = self::geometryVertexCount($feature['geometry'] ?? null);
            $total += $count;
            $minFeature = min($minFeature, $count);
        }

        if (count($features) < 8) {
            return false;
        }

        return $total >= $minTotal && $minFeature >= $minPer;
    }

    /**
     * Assess whether loaded boundaries are detailed enough for production GIS display.
     *
     * @return array{
     *   feature_count: int,
     *   vertex_count: int,
     *   min_vertices_per_feature: int,
     *   vertices_by_barangay: array<string, int>,
     *   level: 'adequate'|'simplified',
     *   source: string,
     *   attribution: string,
     *   message: string,
     *   file: string
     * }
     */
    public static function boundaryQualityReport(): array
    {
        $cfg = require __DIR__ . '/../config/heat-map.php';
        $minPer = max(1, (int) ($cfg['boundary_min_vertices_per_barangay'] ?? 12));
        $minTotal = max(8, (int) ($cfg['boundary_min_total_vertices'] ?? 80));

        $features = self::barangayFeaturesFromCollection(self::surveillanceBoundaryCollection());
        $verticesByBarangay = [];
        $total = 0;
        foreach ($features as $feature) {
            $name = self::featureName($feature);
            if ($name === '') {
                continue;
            }
            $count = self::geometryVertexCount($feature['geometry'] ?? null);
            $verticesByBarangay[$name] = $count;
            $total += $count;
        }

        $minFeature = $verticesByBarangay === [] ? 0 : min($verticesByBarangay);
        $featureCount = count($verticesByBarangay);
        $adequate = $featureCount >= 8 && $total >= $minTotal && $minFeature >= $minPer;

        $activeFile = basename(self::filePath());
        $hiresBasename = basename((string) ($cfg['geojson_hires_path'] ?? ''));
        $firstSource = '';
        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            if (is_array($props) && isset($props['source']) && is_string($props['source'])) {
                $firstSource = trim($props['source']);
                break;
            }
        }

        if ($hiresBasename !== '' && $activeFile === $hiresBasename && $firstSource !== '') {
            $source = $firstSource;
        } elseif ($hiresBasename !== '' && $activeFile === $hiresBasename) {
            $source = 'HDX COD-AB NAMRIA/PSA administrative level 4 (barangay) boundaries';
        } else {
            $sourceKey = (string) ($cfg['surveillance_boundary_source'] ?? 'psa_geojson');
            $source = $sourceKey === 'poster_georef'
                ? 'LGU poster trace (georeferenced — not authoritative GIS)'
                : 'PSA/NAMRIA via bendlikeabamboo ph-psgc-shp (simplified ADM4 polygons — low vertex count)';
        }

        $message = '';
        if (!$adequate) {
            $message = 'Accurate barangay boundary data is required before implementing the final map. '
                . 'The current file uses simplified official polygons (low vertex count). '
                . 'Replace or add full-resolution NAMRIA ADM4 boundaries for Magallanes '
                . '(e.g. NAMRIA Geoportal, HDX COD-AB phl_admin_boundaries) as '
                . 'assets/geo/magallanes-barangays-namria.geojson or magallanes-barangays.geojson.';
        }

        return [
            'feature_count'              => $featureCount,
            'vertex_count'               => $total,
            'min_vertices_per_feature'   => $minFeature,
            'vertices_by_barangay'       => $verticesByBarangay,
            'level'                      => $adequate ? 'adequate' : 'simplified',
            'source'                     => $source,
            'attribution'                => 'Boundaries © NAMRIA / PSA PSGC (via verified GIS repository)',
            'message'                    => $message,
            'file'                       => basename(self::filePath()),
        ];
    }

    /** @return array<string, mixed> */
    public static function loadCollection(): array
    {
        if (self::$collection !== null) {
            return self::$collection;
        }

        $path = self::filePath();
        if (!is_readable($path)) {
            throw new RuntimeException('Barangay GeoJSON not found: ' . $path);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read barangay GeoJSON.');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'FeatureCollection') {
            throw new RuntimeException('Invalid barangay GeoJSON format.');
        }

        self::$collection = $data;
        return self::$collection;
    }

    /**
     * Barangay polygon features only — excludes decorative/non-barangay roles.
     *
     * @return list<array<string, mixed>>
     */
    public static function barangayFeatures(): array
    {
        $features = [];
        foreach (self::loadCollection()['features'] ?? [] as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $name = self::featureName($feature);
            if ($name === '') {
                continue;
            }
            $role = strtolower((string) ($feature['properties']['role'] ?? 'barangay'));
            if (in_array($role, ['water', 'municipality_outline', 'outline', 'mask'], true)) {
                continue;
            }
            $features[] = $feature;
        }

        return $features;
    }

    /** @return list<string> */
    public static function barangayNames(): array
    {
        if (self::$barangayNames !== null) {
            return self::$barangayNames;
        }

        $names = [];
        foreach (self::barangayFeatures() as $feature) {
            $name = self::featureName($feature);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        usort($names, static fn (string $a, string $b) => strcasecmp($a, $b));
        self::$barangayNames = $names;

        return self::$barangayNames;
    }

    /** Map a database / free-text barangay value to the GeoJSON feature name. */
    public static function normalizeBarangayName(?string $input): string
    {
        $input = trim((string) $input);
        if ($input === '' || $input === '—') {
            return '';
        }

        $names = self::barangayNames();
        foreach ($names as $official) {
            if (strcasecmp($official, $input) === 0) {
                return $official;
            }
        }

        $key = strtolower(preg_replace('/[^a-z0-9\s\-ñ]/iu', '', $input) ?? $input);
        $key = preg_replace('/\s+/', ' ', trim($key)) ?? $key;
        if (isset(self::$aliasMap[$key])) {
            $alias = self::$aliasMap[$key];
            if (in_array($alias, $names, true)) {
                return $alias;
            }
        }

        foreach ($names as $official) {
            if (stripos($input, $official) !== false) {
                return $official;
            }
        }

        // "Brgy. Poblacion" and similar
        if (preg_match('/\b(?:brgy\.?|barangay)\s*([^\.,]+)/iu', $input, $m)) {
            return self::normalizeBarangayName(trim($m[1]));
        }

        return '';
    }

    /**
     * Map view helpers for the heat map page.
     *
     * @return array{
     *   bounds: array{south: float, west: float, north: float, east: float},
     *   center: array{0: float, 1: float},
     *   label_anchors: array<string, array{0: float, 1: float}>
     * }
     */
    public static function mapViewConfig(): array
    {
        $cfg = require __DIR__ . '/../config/heat-map.php';
        $anchors = self::surveillanceAnchors($cfg);

        $surBounds = $cfg['surveillance_bounds'] ?? null;
        if (is_array($surBounds) && isset($surBounds['south'], $surBounds['north'], $surBounds['west'], $surBounds['east'])) {
            $south = (float) $surBounds['south'];
            $north = (float) $surBounds['north'];
            $west = (float) $surBounds['west'];
            $east = (float) $surBounds['east'];
        } else {
            $south = 90.0;
            $north = -90.0;
            $west = 180.0;
            $east = -180.0;
            foreach (self::barangayFeatures() as $feature) {
                foreach (self::geometryRings($feature['geometry'] ?? null) as $ring) {
                    foreach ($ring as $pt) {
                        if (!is_array($pt) || count($pt) < 2) {
                            continue;
                        }
                        $lng = (float) $pt[0];
                        $lat = (float) $pt[1];
                        $south = min($south, $lat);
                        $north = max($north, $lat);
                        $west = min($west, $lng);
                        $east = max($east, $lng);
                    }
                }
            }
        }

        return [
            'bounds' => [
                'south' => $south,
                'west'  => $west,
                'north' => $north,
                'east'  => $east,
            ],
            'center' => [
                round(($south + $north) / 2, 6),
                round(($west + $east) / 2, 6),
            ],
            'label_anchors' => $anchors,
        ];
    }

    /**
     * Barangay anchor points for heat layer and markers (not simplified GeoJSON centroids).
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function surveillanceAnchors(array $cfg): array
    {
        $usePoster = !empty($cfg['use_poster_georef_anchors']);
        $posterLabels = $cfg['admin_map_reference']['poster_label_positions'] ?? [];
        $georef = $cfg['poster_georef'] ?? null;

        if ($usePoster && is_array($georef) && $posterLabels !== []) {
            $anchors = [];
            foreach ($posterLabels as $name => $xy) {
                if (!is_string($name) || !is_array($xy) || count($xy) < 2) {
                    continue;
                }
                $anchors[$name] = self::posterPixelToLatLng((float) $xy[0], (float) $xy[1], $georef);
            }
            if ($anchors !== []) {
                return $anchors;
            }
        }

        $fixedPositions = $cfg['label_positions'] ?? [];
        $nudges = $cfg['label_nudges'] ?? [];
        $anchors = [];
        $hiresPath = __DIR__ . '/..' . (string) ($cfg['geojson_hires_path'] ?? '');
        $useBoundaryCentroids = self::isAdequateBoundaryFile($hiresPath, $cfg);
        $featureList = $useBoundaryCentroids
            ? self::barangayFeaturesFromCollection(self::surveillanceBoundaryCollection())
            : self::barangayFeatures();

        foreach (self::barangayNames() as $name) {
            if (!$useBoundaryCentroids && isset($fixedPositions[$name]) && is_array($fixedPositions[$name])) {
                $anchors[$name] = [
                    round((float) $fixedPositions[$name][0], 6),
                    round((float) $fixedPositions[$name][1], 6),
                ];
                continue;
            }
            foreach ($featureList as $feature) {
                if (self::featureName($feature) !== $name) {
                    continue;
                }
                [$centLat, $centLng] = self::featureCentroid($feature);
                $nudge = $nudges[$name] ?? [0.0, 0.0];
                $anchors[$name] = [
                    round($centLat + (float) ($nudge[0] ?? 0), 6),
                    round($centLng + (float) ($nudge[1] ?? 0), 6),
                ];
                break;
            }
        }

        return $anchors;
    }

    /**
     * Barangay boundaries for surveillance map overlay (WGS84 GeoJSON).
     *
     * @return array<string, mixed>
     */
    public static function surveillanceBoundaryCollection(): array
    {
        $cfg = require __DIR__ . '/../config/heat-map.php';
        $source = (string) ($cfg['surveillance_boundary_source'] ?? 'psa_geojson');
        if ($source === 'poster_georef') {
            return self::posterBoundariesAsWgs84($cfg);
        }

        return self::loadCollection();
    }

    /**
     * @param array<string, mixed> $collection
     * @return list<array<string, mixed>>
     */
    public static function barangayFeaturesFromCollection(array $collection): array
    {
        $features = [];
        foreach ($collection['features'] ?? [] as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $name = self::featureName($feature);
            if ($name === '') {
                continue;
            }
            $role = strtolower((string) ($feature['properties']['role'] ?? 'barangay'));
            if (in_array($role, ['water', 'municipality_outline', 'outline', 'mask'], true)) {
                continue;
            }
            $features[] = $feature;
        }

        return $features;
    }

    /** @param array<string, mixed>|null $geometry */
    public static function geometryVertexCount(?array $geometry): int
    {
        if ($geometry === null) {
            return 0;
        }
        $type = (string) ($geometry['type'] ?? '');
        $coords = $geometry['coordinates'] ?? [];
        $count = 0;

        if ($type === 'Polygon') {
            foreach ($coords as $ring) {
                if (is_array($ring)) {
                    $count += count($ring);
                }
            }
        } elseif ($type === 'MultiPolygon') {
            foreach ($coords as $poly) {
                if (!is_array($poly)) {
                    continue;
                }
                foreach ($poly as $ring) {
                    if (is_array($ring)) {
                        $count += count($ring);
                    }
                }
            }
        }

        return $count;
    }

    /** @param array<string, mixed> $cfg @return array<string, mixed> */
    private static function posterBoundariesAsWgs84(array $cfg): array
    {
        $relative = (string) ($cfg['poster_geojson_path'] ?? '/assets/geo/magallanes-poster-barangays.geojson');
        $path = __DIR__ . '/..' . $relative;
        if (!is_readable($path)) {
            throw new RuntimeException('Poster barangay GeoJSON not found: ' . $path);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read poster barangay GeoJSON.');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'FeatureCollection') {
            throw new RuntimeException('Invalid poster barangay GeoJSON.');
        }

        $georef = $cfg['poster_georef'] ?? [];
        $features = [];
        foreach ($data['features'] ?? [] as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $geometry = self::convertPosterGeometryToWgs84($feature['geometry'] ?? null, $georef);
            if ($geometry === null) {
                continue;
            }
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $features[] = [
                'type'       => 'Feature',
                'properties' => $props,
                'geometry'   => $geometry,
            ];
        }

        return [
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /** @param array<string, mixed>|null $geometry @param array<string, float|int> $georef @return array<string, mixed>|null */
    private static function convertPosterGeometryToWgs84(?array $geometry, array $georef): ?array
    {
        if ($geometry === null) {
            return null;
        }
        $type = (string) ($geometry['type'] ?? '');

        if ($type === 'Polygon') {
            $rings = [];
            foreach ($geometry['coordinates'] ?? [] as $ring) {
                if (!is_array($ring)) {
                    continue;
                }
                $rings[] = self::convertPosterRingToWgs84($ring, $georef);
            }
            return $rings === [] ? null : ['type' => 'Polygon', 'coordinates' => $rings];
        }

        if ($type === 'MultiPolygon') {
            $polys = [];
            foreach ($geometry['coordinates'] ?? [] as $poly) {
                if (!is_array($poly)) {
                    continue;
                }
                $rings = [];
                foreach ($poly as $ring) {
                    if (!is_array($ring)) {
                        continue;
                    }
                    $rings[] = self::convertPosterRingToWgs84($ring, $georef);
                }
                if ($rings !== []) {
                    $polys[] = $rings;
                }
            }
            return $polys === [] ? null : ['type' => 'MultiPolygon', 'coordinates' => $polys];
        }

        return null;
    }

    /**
     * @param list<mixed> $ring
     * @param array<string, float|int> $georef
     * @return list<array{0: float, 1: float}>
     */
    private static function convertPosterRingToWgs84(array $ring, array $georef): array
    {
        $out = [];
        foreach ($ring as $pt) {
            if (!is_array($pt) || count($pt) < 2) {
                continue;
            }
            $out[] = self::posterPixelToLngLat((float) $pt[0], (float) $pt[1], $georef);
        }
        return $out;
    }

    /** @param array<string, float|int> $georef @return array{0: float, 1: float} [lng, lat] */
    private static function posterPixelToLngLat(float $x, float $y, array $georef): array
    {
        [$lat, $lng] = self::posterPixelToLatLng($x, $y, $georef);

        return [round($lng, 6), round($lat, 6)];
    }

    /** @param array<string, float|int> $georef @return array{0: float, 1: float} [lat, lng] */
    private static function posterPixelToLatLng(float $x, float $y, array $georef): array
    {
        $north = (float) ($georef['north'] ?? 9.067);
        $south = (float) ($georef['south'] ?? 8.996);
        $west = (float) ($georef['west'] ?? 125.508);
        $east = (float) ($georef['east'] ?? 125.572);
        $width = max(1.0, (float) ($georef['width'] ?? 457));
        $height = max(1.0, (float) ($georef['height'] ?? 532));

        $lat = $north - ($y / $height) * ($north - $south);
        $lng = $west + ($x / $width) * ($east - $west);

        return [round($lat, 6), round($lng, 6)];
    }

    /** @param array<string, mixed> $feature @return array{0: float, 1: float} */
    private static function featureCentroid(array $feature): array
    {
        $latSum = 0.0;
        $lngSum = 0.0;
        $count = 0;
        foreach (self::geometryRings($feature['geometry'] ?? null) as $ring) {
            foreach ($ring as $pt) {
                if (!is_array($pt) || count($pt) < 2) {
                    continue;
                }
                $lngSum += (float) $pt[0];
                $latSum += (float) $pt[1];
                $count++;
            }
        }
        if ($count === 0) {
            return [9.022, 125.517];
        }
        return [$latSum / $count, $lngSum / $count];
    }

    /** @param array<string, mixed>|null $geometry @return list<list<array{0: float, 1: float}>> */
    private static function geometryRings(?array $geometry): array
    {
        if ($geometry === null) {
            return [];
        }
        $type = (string) ($geometry['type'] ?? '');
        $coords = $geometry['coordinates'] ?? [];
        if ($type === 'Polygon') {
            return is_array($coords[0] ?? null) ? [$coords[0]] : [];
        }
        if ($type === 'MultiPolygon') {
            $rings = [];
            foreach ($coords as $poly) {
                if (is_array($poly[0] ?? null)) {
                    $rings[] = $poly[0];
                }
            }
            return $rings;
        }
        return [];
    }

    /** @param array<string, mixed> $feature */
    private static function featureName(array $feature): string
    {
        $props = $feature['properties'] ?? [];
        if (!is_array($props)) {
            return '';
        }

        foreach (['name', 'NAME', 'ADM4_EN', 'adm4_en', 'barangay', 'BRGY'] as $key) {
            $value = trim((string) ($props[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
