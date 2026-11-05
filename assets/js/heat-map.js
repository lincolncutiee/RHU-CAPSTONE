(function () {
    'use strict';

    const page = document.getElementById('heatMapPage');
    if (!page) {
        return;
    }

    const apiUrl = page.dataset.apiUrl;
    const geoUrl = page.dataset.geoUrl;
    const boundaryGeoUrl = page.dataset.boundaryGeoUrl || geoUrl;
    const mapStyle = page.dataset.mapStyle || 'surveillance_heat';
    const isSurveillanceHeat = mapStyle === 'surveillance_heat';
    const isChoropleth = mapStyle === 'choropleth';
    const labelAnchors = JSON.parse(page.dataset.labelAnchors || '{}');
    const mapCenter = JSON.parse(page.dataset.mapCenter || '[9.022, 125.517]');
    const mapZoom = parseInt(page.dataset.mapZoom || '13', 10);
    const mapBounds = JSON.parse(page.dataset.mapBounds || 'null');
    const mapOptions = JSON.parse(page.dataset.mapOptions || '{}');
    const fillOpacity = Object.assign(
        { zero: 0.35, case: 0.72, hover: 0.85, selected: 0.9 },
        JSON.parse(page.dataset.choropleth || '{}')
    );
    const basemapConfig = Object.assign(
        {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
            max_zoom: 19,
            attribution: 'Tiles &copy; Esri &mdash; World Topo',
        },
        JSON.parse(page.dataset.basemap || '{}')
    );

    const surveillanceOutline = Object.assign(
        { enabled: true, color: '#ffffff', weight: 2, opacity: 0.95, fill_opacity: 0 },
        JSON.parse(page.dataset.surveillanceOutline || '{}')
    );
    const surveillanceRenderMode = (page.dataset.surveillanceRenderMode || 'choropleth').toLowerCase();
    const isSurveillanceChoropleth = isSurveillanceHeat && surveillanceRenderMode !== 'density_heat';
    const isDensityHeat = isSurveillanceHeat && !isSurveillanceChoropleth;
    const surveillanceFillOpacity = Object.assign(
        { zero: 0.82, case: 0.88, hover: 0.92, selected: 0.96 },
        JSON.parse(page.dataset.surveillanceChoroplethFill || '{}')
    );
    const surveillanceChoroplethStroke = Object.assign(
        { color: '#334155', weight: 1.25, opacity: 0.9 },
        JSON.parse(page.dataset.surveillanceChoroplethStroke || '{}')
    );
    const surveillanceSoftHeat = page.dataset.surveillanceSoftHeat === '1';
    const surveillanceShowBoundaries = page.dataset.surveillanceShowBoundaries === '1';
    const surveillanceBoundaryLayer = Object.assign(
        {
            fill_color: '#ffffff',
            fill_opacity: 0.05,
            fill_hover: 0.12,
            fill_selected: 0.22,
            stroke: '#334155',
            stroke_hover: '#0284c7',
            stroke_selected: '#0369a1',
            weight: 1.35,
            weight_hover: 2.1,
            weight_selected: 3,
            opacity: 0.92,
        },
        JSON.parse(page.dataset.surveillanceBoundaryLayer || '{}')
    );
    let boundaryMeta = null;
    try {
        boundaryMeta = JSON.parse(page.dataset.boundaryMeta || 'null');
    } catch (e) {
        boundaryMeta = null;
    }

    const HEAT_UNDERLAY_GRADIENT = {
        0.0: '#16a34a',
        0.2: '#4ade80',
        0.4: '#fef08a',
        0.58: '#fde047',
        0.75: '#fb923c',
        1.0: '#dc2626',
    };

    const ZERO_HEAT_GRADIENT = {
        0.0: '#14532d',
        0.35: '#16a34a',
        0.65: '#22c55e',
        1.0: '#4ade80',
    };

    const heatConfig = Object.assign(
        {
            base_radius: 32,
            max_radius: 72,
            base_blur: 28,
            max_blur: 48,
            min_opacity: 0.55,
            weight_power: 0.72,
            spread_points: 8,
            spread_meters: 520,
            reference_zoom: 13,
            gradient: {
                0.0: '#1d4ed8',
                0.22: '#06b6d4',
                0.42: '#22c55e',
                0.58: '#eab308',
                0.78: '#f97316',
                1.0: '#dc2626',
            },
        },
        JSON.parse(page.dataset.heatLayer || '{}')
    );

    function parseHeatGradient(raw) {
        const fallback = {
            0.0: '#1d4ed8',
            0.22: '#06b6d4',
            0.42: '#22c55e',
            0.58: '#eab308',
            0.78: '#f97316',
            1.0: '#dc2626',
        };
        if (!raw || typeof raw !== 'object') {
            return fallback;
        }
        const out = {};
        Object.keys(raw).forEach((key) => {
            const n = Number(key);
            if (!Number.isNaN(n)) {
                out[n] = raw[key];
            }
        });
        return Object.keys(out).length ? out : fallback;
    }

    const HEAT_GRADIENT = parseHeatGradient(heatConfig.gradient);

    const OUTLINE = { color: '#475569', weight: 1.1, opacity: 0.55 };
    const CHORO_BOUNDARY = { stroke: '#334155', weight: 1.15, opacity: 0.75 };

    const els = {
        dateFrom: document.getElementById('hmDateFrom'),
        dateTo: document.getElementById('hmDateTo'),
        refreshBtn: document.getElementById('hmRefreshBtn'),
        diseaseFilter: document.getElementById('hmDiseaseFilter'),
        summaryScope: document.getElementById('hmSummaryScope'),
        statTotalCases: document.getElementById('hmStatTotalCases'),
        statBarangays: document.getElementById('hmStatBarangays'),
        statPuroks: document.getElementById('hmStatPuroks'),
        statConsultations: document.getElementById('hmStatConsultations'),
        legend: document.getElementById('hmLegend'),
        choroRamp: document.getElementById('hmChoroRamp'),
        mapLoading: document.getElementById('hmMapLoading'),
        mapEmpty: document.getElementById('hmMapEmpty'),
        detailEmpty: document.getElementById('hmDetailEmpty'),
        detailBody: document.getElementById('hmDetailBody'),
        detailClose: document.getElementById('hmDetailClose'),
        detailBarangay: document.getElementById('hmDetailBarangay'),
        detailTotal: document.getElementById('hmDetailTotal'),
        detailTotalLabel: document.getElementById('hmDetailTotalLabel'),
        purokList: document.getElementById('hmPurokList'),
        diseaseHeading: document.getElementById('hmDiseaseHeading'),
        diseaseList: document.getElementById('hmDiseaseList'),
    };

    let map = null;
    let outlineLayer = null;
    let choroplethLayer = null;
    let boundaryTopLayer = null;
    let boundaryInteractiveLayer = null;
    let heatLayer = null;
    let zeroHeatLayer = null;
    let markerLayer = null;
    let labelLayer = null;
    let hitLayer = null;
    let geoJsonCache = null;
    let selectedBarangay = null;
    let loadToken = 0;
    let initialLoad = true;
    let mapViewLocked = false;

    const barangayIndex = {};
    const NON_BARANGAY_ROLES = new Set(['water', 'municipality_outline', 'outline', 'mask']);

    function barangayOrder() {
        return [
            'Caloc-an', 'Taod-oy', 'Guiasan', 'Poblacion',
            'Buhang', 'Santo Niño', 'Santo Rosario', 'Marcos',
        ];
    }

    function showLoading(show) {
        els.mapLoading.classList.toggle('d-none', !show);
    }

    function showEmptyState(show) {
        els.mapEmpty.classList.toggle('d-none', !show);
    }

    function buildQuery(includeDates) {
        const params = new URLSearchParams();
        if (includeDates) {
            params.set('date_from', els.dateFrom.value);
            params.set('date_to', els.dateTo.value);
        }
        const disease = els.diseaseFilter.value;
        if (disease && disease !== 'all') {
            params.set('disease_id', disease);
        }
        return params.toString();
    }

    function formatLegend(legend) {
        els.legend.innerHTML = '';
        if (els.choroRamp && isSurveillanceHeat && !isSurveillanceChoropleth) {
            els.choroRamp.style.background = 'linear-gradient(to right, #1d4ed8, #06b6d4, #22c55e, #eab308, #f97316, #dc2626)';
        }
        const tiers = [...(legend || [])]
            .filter((t) => t.level !== 'base' && t.color !== 'transparent')
            .sort((a, b) => (b.min || 0) - (a.min || 0));

        if (els.choroRamp) {
            const colors = [...tiers].reverse().map((t) => t.color).join(', ');
            els.choroRamp.style.background = colors
                ? `linear-gradient(to right, ${colors})`
                : 'linear-gradient(to right, #cbd5e1, #fde047, #f59e0b, #ea580c, #b91c1c)';
        }

        tiers.forEach((tier) => {
            const li = document.createElement('li');
            li.innerHTML = `<span class="hm-gis-legend__swatch" style="background:${tier.color}"></span>${escapeHtml(tier.label || '')}`;
            els.legend.appendChild(li);
        });

        const base = (legend || []).find((t) => t.level === 'base');
        if (base) {
            const li = document.createElement('li');
            li.innerHTML = `<span class="hm-gis-legend__swatch hm-gis-legend__swatch--base"></span>${escapeHtml(base.label || 'No active cases')}`;
            els.legend.appendChild(li);
        }
    }

    function updateSummary(data) {
        const s = data.summary || {};
        els.statTotalCases.textContent = s.total_active_cases ?? 0;
        els.statBarangays.textContent = `${s.affected_barangays ?? 0}/${s.total_barangays ?? 0}`;
        els.statPuroks.textContent = s.puroks_with_cases ?? 0;
        els.statConsultations.textContent = s.total_consultations ?? 0;

        const diseaseVal = els.diseaseFilter.value;
        if (diseaseVal === 'all') {
            els.summaryScope.textContent = 'All Active Diseases';
            els.detailTotalLabel.textContent = 'Active Cases';
        } else {
            const opt = els.diseaseFilter.selectedOptions[0];
            const label = opt ? opt.textContent.replace(/\s*\(\d+\)\s*$/, '') : 'Selected Disease';
            els.summaryScope.textContent = label;
            els.detailTotalLabel.textContent = 'Active Cases';
        }

        showEmptyState(!(data.has_active_cases ?? (s.total_active_cases > 0)));
    }

    function populateDiseaseFilter(diseases, preserveSelection) {
        const previous = preserveSelection ? els.diseaseFilter.value : 'all';
        els.diseaseFilter.innerHTML = '<option value="all">All Active Diseases</option>';

        (diseases || []).forEach((d) => {
            if ((d.count ?? 0) <= 0) {
                return;
            }
            const opt = document.createElement('option');
            opt.value = String(d.id);
            opt.textContent = `${d.name} (${d.count})`;
            els.diseaseFilter.appendChild(opt);
        });

        const stillValid = previous === 'all' || (diseases || []).some((d) => String(d.id) === previous && (d.count ?? 0) > 0);
        els.diseaseFilter.value = stillValid ? previous : 'all';
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderBreakdownList(listEl, items, emptyMessage) {
        listEl.innerHTML = '';
        if (!items || items.length === 0) {
            const li = document.createElement('li');
            li.innerHTML = `<span>${escapeHtml(emptyMessage)}</span><span>0</span>`;
            listEl.appendChild(li);
            return;
        }
        items.forEach((item) => {
            const li = document.createElement('li');
            li.innerHTML = `<span>${escapeHtml(item.name)}</span><span>${item.count}</span>`;
            listEl.appendChild(li);
        });
    }

    function brgyDisplayName(name) {
        const n = String(name || '').trim();
        if (/^brgy\.?\s/i.test(n)) {
            return n;
        }
        return `Brgy. ${n}`;
    }

    function selectedDiseaseScopeLabel() {
        const val = els.diseaseFilter.value;
        if (val === 'all') {
            return 'All active diseases';
        }
        const opt = els.diseaseFilter.selectedOptions[0];
        if (!opt) {
            return 'Selected disease';
        }
        return opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
    }

    function primaryDiseaseLine(barangay) {
        const scope = selectedDiseaseScopeLabel();
        const breakdown = barangay.disease_breakdown || [];
        if (els.diseaseFilter.value !== 'all' && breakdown.length > 0) {
            return breakdown[0].name;
        }
        if (breakdown.length === 1) {
            return breakdown[0].name;
        }
        return scope;
    }

    function buildHoverTooltipHtml(barangay) {
        const total = barangay.total_cases ?? 0;
        const status = barangay.anomaly_status || 'Normal';
        let html = `<div class="hm-brgy-tooltip">`;
        html += `<p class="hm-brgy-tooltip__line"><span>Barangay:</span> <strong>${escapeHtml(barangay.name)}</strong></p>`;
        html += `<p class="hm-brgy-tooltip__line"><span>Disease:</span> ${escapeHtml(primaryDiseaseLine(barangay))}</p>`;
        html += `<p class="hm-brgy-tooltip__line"><span>Cases:</span> <strong>${total}</strong></p>`;
        html += `<p class="hm-brgy-tooltip__line"><span>Anomaly Status:</span> ${escapeHtml(status)}</p>`;
        html += '</div>';
        return html;
    }

    function buildPopupHtml(barangay) {
        let html = `<div class="hm-popup hm-popup--sat">
            <p class="hm-popup-title">${escapeHtml(brgyDisplayName(barangay.name))}</p>
            <p class="hm-popup-total">Total Cases: <strong class="hm-popup-total__num">${barangay.total_cases}</strong></p>`;

        html += '<p class="hm-popup-section">Disease breakdown</p><ul class="hm-popup-list">';
        if ((barangay.disease_breakdown || []).length === 0) {
            html += '<li><span>No active cases</span><span>0</span></li>';
        } else {
            barangay.disease_breakdown.forEach((d) => {
                html += `<li><span>${escapeHtml(d.name)}</span><span>${d.count}</span></li>`;
            });
        }
        html += '</ul>';

        html += '<p class="hm-popup-section">Purok Breakdown</p><ul class="hm-popup-list">';
        if ((barangay.purok_breakdown || []).length === 0) {
            html += '<li><span>Purok Not Specified</span><span>0</span></li>';
        } else {
            barangay.purok_breakdown.forEach((p) => {
                html += `<li><span>${escapeHtml(p.name)}</span><span>${p.count}</span></li>`;
            });
        }
        html += '</ul></div>';

        return html;
    }

    function featureName(feature) {
        const props = feature?.properties || {};
        return props.name || props.NAME || props.ADM4_EN || props.adm4_en || props.barangay || props.BRGY || '';
    }

    function isBarangayFeature(feature) {
        const name = featureName(feature);
        if (!name) {
            return false;
        }
        const role = String(feature?.properties?.role || 'barangay').toLowerCase();
        return !NON_BARANGAY_ROLES.has(role);
    }

    function barangayFeaturesFromGeo(geo) {
        return (geo?.features || []).filter(isBarangayFeature);
    }

    function boundsFromConfig() {
        if (!mapBounds || mapBounds.south == null) {
            return null;
        }
        return L.latLngBounds(
            [mapBounds.south, mapBounds.west],
            [mapBounds.north, mapBounds.east]
        );
    }

    function barangayBoundsFromFeatures(features) {
        const configured = boundsFromConfig();
        if (configured && configured.isValid()) {
            return configured;
        }
        const layer = L.geoJSON({ type: 'FeatureCollection', features });
        return layer.getBounds();
    }

    function lockMapToMunicipality(features) {
        const configured = boundsFromConfig();
        const bounds = (configured && configured.isValid())
            ? configured
            : barangayBoundsFromFeatures(features);
        if (!bounds.isValid()) {
            map.setView(mapCenter, mapZoom);
            return;
        }

        const maxBoundsPad = Number(mapOptions.maxBoundsPad ?? 0.004);
        const fitPadding = mapOptions.fitPadding ?? [24, 24];
        const maxZoomOffset = Number(mapOptions.maxZoomOffset ?? 2);

        map.fitBounds(bounds, { padding: fitPadding, animate: false, maxZoom: 15 });
        map.setMaxBounds(bounds.pad(maxBoundsPad));
        const fittedZoom = map.getZoom();
        map.setMinZoom(Math.max(fittedZoom - 0.5, 11));
        map.setMaxZoom(Math.min(fittedZoom + maxZoomOffset, 16));
    }

    function barangayData(name) {
        return barangayIndex[name] || {
            name,
            total_cases: 0,
            color: '#22c55e',
            disease_breakdown: [],
            purok_breakdown: [],
        };
    }

    const featureCentroidCache = {};

    function cacheFeatureCentroids(geo) {
        Object.keys(featureCentroidCache).forEach((k) => delete featureCentroidCache[k]);
        barangayFeaturesFromGeo(geo).forEach((feature) => {
            const name = featureName(feature);
            if (!name) {
                return;
            }
            const layer = L.geoJSON(feature);
            const center = layer.getBounds().getCenter();
            layer.remove();
            featureCentroidCache[name] = center;
        });
    }

    function anchorForBarangay(name) {
        if (featureCentroidCache[name]) {
            return featureCentroidCache[name];
        }
        const a = labelAnchors[name];
        if (a && Array.isArray(a)) {
            return L.latLng(a[0], a[1]);
        }
        return null;
    }

    function maxCaseCount() {
        let max = 0;
        Object.values(barangayIndex).forEach((b) => {
            max = Math.max(max, b.total_cases ?? 0);
        });
        return Math.max(max, 1);
    }

    function showBarangayDetail(barangay) {
        if (!barangay) {
            els.detailEmpty.classList.remove('d-none');
            els.detailBody.classList.add('d-none');
            els.detailClose.classList.add('d-none');
            selectedBarangay = null;
            if (!isSurveillanceHeat || isSurveillanceChoropleth) {
                renderCaseMarkers();
            }
            if (isChoropleth) {
                refreshChoroplethStyles();
            } else if (isSurveillanceChoropleth) {
                refreshSurveillanceChoroplethStyles();
            } else {
                refreshBoundaryInteractiveStyles();
            }
            return;
        }

        selectedBarangay = barangay.name;
        els.detailEmpty.classList.add('d-none');
        els.detailBody.classList.remove('d-none');
        els.detailClose.classList.remove('d-none');
        els.detailBarangay.textContent = barangay.name;
        els.detailTotal.textContent = barangay.total_cases;

        renderBreakdownList(els.diseaseList, barangay.disease_breakdown, 'No active cases');
        renderBreakdownList(els.purokList, barangay.purok_breakdown, 'Purok Not Specified');
        if (!isSurveillanceHeat || isSurveillanceChoropleth) {
            renderCaseMarkers();
        }
        if (isChoropleth) {
            refreshChoroplethStyles();
        } else if (isSurveillanceChoropleth) {
            refreshSurveillanceChoroplethStyles();
        } else {
            refreshBoundaryInteractiveStyles();
        }
    }

    function boundaryInteractiveStyle(name, state) {
        const cfg = surveillanceBoundaryLayer;
        const isHover = state === 'hover';
        const isSelected = state === 'selected' || name === selectedBarangay;
        const hasSelection = selectedBarangay !== null && selectedBarangay !== '';
        const dimOthers = hasSelection && !isSelected && state === 'default';

        let fillOpacity = Number(cfg.fill_opacity ?? 0.05);
        if (isSelected) {
            fillOpacity = Number(cfg.fill_selected ?? 0.22);
        } else if (isHover) {
            fillOpacity = Number(cfg.fill_hover ?? 0.12);
        } else if (dimOthers) {
            fillOpacity = Math.min(fillOpacity, 0.03);
        }

        let stroke = cfg.stroke || '#334155';
        let weight = Number(cfg.weight ?? 1.35);
        if (isSelected) {
            stroke = cfg.stroke_selected || cfg.stroke_hover || '#0369a1';
            weight = Number(cfg.weight_selected ?? 3);
        } else if (isHover) {
            stroke = cfg.stroke_hover || '#0284c7';
            weight = Number(cfg.weight_hover ?? 2.1);
        }

        return {
            fillColor: isSelected ? (cfg.stroke_selected || '#0ea5e9') : (cfg.fill_color || '#ffffff'),
            fillOpacity,
            color: stroke,
            weight,
            opacity: dimOthers ? Number(cfg.opacity ?? 0.92) * 0.55 : Number(cfg.opacity ?? 0.92),
        };
    }

    function boundaryLayerForBarangay(name) {
        if (!boundaryInteractiveLayer || !name) {
            return null;
        }
        let match = null;
        boundaryInteractiveLayer.eachLayer((layer) => {
            if (featureName(layer.feature) === name) {
                match = layer;
            }
        });
        return match;
    }

    function clearBoundaryInteractiveLayer() {
        if (boundaryInteractiveLayer) {
            map.removeLayer(boundaryInteractiveLayer);
            boundaryInteractiveLayer = null;
        }
    }

    function refreshBoundaryInteractiveStyles() {
        if (!boundaryInteractiveLayer) {
            return;
        }
        boundaryInteractiveLayer.eachLayer((layer) => {
            const name = featureName(layer.feature);
            const st = name === selectedBarangay ? 'selected' : 'default';
            layer.setStyle(boundaryInteractiveStyle(name, st));
        });
        const selectedLayer = boundaryLayerForBarangay(selectedBarangay);
        if (selectedLayer) {
            selectedLayer.bringToFront();
        }
    }

    function renderSurveillanceBoundaryInteractive(geo) {
        clearBoundaryInteractiveLayer();
        clearOutlineLayer();
        clearBoundaryTopLayer();

        if (!surveillanceShowBoundaries || !isSurveillanceHeat) {
            return barangayFeaturesFromGeo(geo);
        }

        const features = barangayFeaturesFromGeo(geo);
        boundaryInteractiveLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
            pane: 'hmBoundaryPane',
            className: 'hm-boundary-interactive',
            style: (feature) => boundaryInteractiveStyle(featureName(feature), 'default'),
            onEachFeature: (feature, layer) => {
                const name = featureName(feature);
                const data = barangayData(name);

                layer.bindTooltip(buildHoverTooltipHtml(data), {
                    sticky: true,
                    direction: 'top',
                    opacity: 0.96,
                    className: 'hm-brgy-tooltip-pane',
                });
                if (!isDensityHeat) {
                    layer.bindPopup(buildPopupHtml(data), {
                        maxWidth: 280,
                        className: 'hm-popup-wrap',
                    });
                }

                layer.on({
                    mouseover: (e) => {
                        e.target.setStyle(boundaryInteractiveStyle(name, 'hover'));
                        e.target.bringToFront();
                        e.target.openTooltip();
                    },
                    mouseout: (e) => {
                        const st = name === selectedBarangay ? 'selected' : 'default';
                        e.target.setStyle(boundaryInteractiveStyle(name, st));
                        e.target.closeTooltip();
                    },
                    click: (e) => {
                        L.DomEvent.stopPropagation(e);
                        activateBarangayFromMap(data, layer);
                    },
                });
            },
        }).addTo(map);

        return features;
    }

    function surveillancePolygonStyle(name, state) {
        const data = barangayData(name);
        const count = data.total_cases ?? 0;
        const isSelected = state === 'selected' || name === selectedBarangay;
        const isHover = state === 'hover';

        let opacity = count === 0 ? surveillanceFillOpacity.zero : surveillanceFillOpacity.case;
        if (isHover) {
            opacity = surveillanceFillOpacity.hover;
        }
        if (isSelected) {
            opacity = surveillanceFillOpacity.selected;
        }

        const strokeW = Number(surveillanceChoroplethStroke.weight ?? 1.25);
        const showStroke = surveillanceShowBoundaries;
        const hoverStroke = showStroke && (isSelected || isHover);

        return {
            fillColor: count > 0 ? (data.color || '#fde047') : (data.color || '#22c55e'),
            fillOpacity: opacity,
            color: showStroke ? (surveillanceChoroplethStroke.color || '#334155') : 'transparent',
            weight: hoverStroke ? strokeW + 0.75 : (showStroke ? strokeW : 0),
            opacity: showStroke ? Number(surveillanceChoroplethStroke.opacity ?? 0.9) : 0,
        };
    }

    function styleSurveillanceFeature(feature) {
        return surveillancePolygonStyle(featureName(feature), 'default');
    }

    function refreshSurveillanceChoroplethStyles() {
        if (!choroplethLayer) {
            return;
        }
        choroplethLayer.eachLayer((layer) => {
            const name = featureName(layer.feature);
            layer.setStyle(surveillancePolygonStyle(name, name === selectedBarangay ? 'selected' : 'default'));
        });
    }

    function renderSurveillanceChoropleth(geo) {
        clearOutlineLayer();

        const features = barangayFeaturesFromGeo(geo);
        if (choroplethLayer) {
            map.removeLayer(choroplethLayer);
        }

        choroplethLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
            pane: 'hmChoroPane',
            className: 'hm-choro-layer hm-choro-layer--surveillance',
            style: styleSurveillanceFeature,
            onEachFeature: (feature, layer) => {
                const name = featureName(feature);
                const data = barangayData(name);

                layer.bindTooltip(buildHoverTooltipHtml(data), {
                    sticky: true,
                    direction: 'top',
                    opacity: 0.96,
                    className: 'hm-brgy-tooltip-pane',
                });

                layer.on({
                    mouseover: (e) => {
                        e.target.setStyle(surveillancePolygonStyle(name, 'hover'));
                        e.target.bringToFront();
                        e.target.openTooltip();
                    },
                    mouseout: (e) => {
                        const st = name === selectedBarangay ? 'selected' : 'default';
                        e.target.setStyle(surveillancePolygonStyle(name, st));
                        e.target.closeTooltip();
                    },
                    click: (e) => {
                        L.DomEvent.stopPropagation(e);
                        activateBarangayFromMap(data, layer);
                    },
                });
            },
        }).addTo(map);

        renderBoundaryTopLines(geo);

        return features;
    }

    function renderOutline(geo, styleOverrides) {
        const features = barangayFeaturesFromGeo(geo);
        if (outlineLayer) {
            map.removeLayer(outlineLayer);
        }
        const stroke = styleOverrides || {
            fillColor: '#94a3b8',
            fillOpacity: 0.08,
            color: OUTLINE.color,
            weight: OUTLINE.weight,
            opacity: OUTLINE.opacity,
        };
        outlineLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
            pane: 'hmOutlinePane',
            className: 'hm-outline-layer',
            style: stroke,
        }).addTo(map);
        return features;
    }

    function renderSurveillanceOutline(geo) {
        if (!surveillanceOutline.enabled) {
            clearOutlineLayer();
            return barangayFeaturesFromGeo(geo);
        }
        return renderOutline(geo, {
            fillColor: '#ffffff',
            fillOpacity: Number(surveillanceOutline.fill_opacity ?? 0),
            color: surveillanceOutline.color || '#ffffff',
            weight: Number(surveillanceOutline.weight ?? 2),
            opacity: Number(surveillanceOutline.opacity ?? 0.95),
        });
    }

    function heatZoomScale() {
        const ref = Number(heatConfig.reference_zoom ?? 13);
        const z = map ? map.getZoom() : ref;
        return Math.pow(1.12, z - ref);
    }

    function heatRadiusPx() {
        const scale = heatZoomScale();
        const base = Number(heatConfig.base_radius ?? 26);
        const max = Number(heatConfig.max_radius ?? 62);
        return Math.min(max, base * scale);
    }

    function heatBlurPx() {
        const scale = heatZoomScale();
        const base = Number(heatConfig.base_blur ?? 22);
        const max = Number(heatConfig.max_blur ?? 40);
        return Math.min(max, base * scale);
    }

    function buildHeatPoints() {
        const cap = maxCaseCount();
        const power = Number(heatConfig.weight_power ?? 0.72);
        const spreadN = Math.max(0, Math.min(12, Number(heatConfig.spread_points ?? 6)));
        const spreadM = Number(heatConfig.spread_meters ?? 450);
        const points = [];

        barangayOrder().forEach((name) => {
            const count = barangayData(name).total_cases ?? 0;
            const latlng = anchorForBarangay(name);
            if (!latlng) {
                return;
            }

            if (count <= 0) {
                return;
            }

            const weight = Math.pow(count / cap, power);
            points.push([latlng.lat, latlng.lng, weight]);

            if (spreadN > 0 && count >= 2) {
                const ringScale = 0.25 + 0.55 * (count / cap);
                const latRad = (latlng.lat * Math.PI) / 180;
                const mPerDegLat = 111320;
                const mPerDegLng = 111320 * Math.cos(latRad);

                for (let i = 0; i < spreadN; i += 1) {
                    const angle = ((i + 0.5) / spreadN) * Math.PI * 2;
                    const dist = spreadM * ringScale * (0.55 + (i % 3) * 0.15);
                    const dLat = (dist / mPerDegLat) * Math.cos(angle);
                    const dLng = (dist / mPerDegLng) * Math.sin(angle);
                    points.push([
                        latlng.lat + dLat,
                        latlng.lng + dLng,
                        weight * (0.35 + 0.1 * (count / cap)),
                    ]);
                }
            }
        });

        return points;
    }

    function buildZeroHeatPoints() {
        const spreadN = 5;
        const spreadM = Number(heatConfig.spread_meters ?? 220) * 0.75;
        const points = [];

        barangayOrder().forEach((name) => {
            const count = barangayData(name).total_cases ?? 0;
            if (count > 0) {
                return;
            }
            const latlng = anchorForBarangay(name);
            if (!latlng) {
                return;
            }

            points.push([latlng.lat, latlng.lng, 1]);

            const latRad = (latlng.lat * Math.PI) / 180;
            const mPerDegLat = 111320;
            const mPerDegLng = 111320 * Math.cos(latRad);

            for (let i = 0; i < spreadN; i += 1) {
                const angle = ((i + 0.5) / spreadN) * Math.PI * 2;
                const dist = spreadM * (0.45 + (i % 4) * 0.12);
                const dLat = (dist / mPerDegLat) * Math.cos(angle);
                const dLng = (dist / mPerDegLng) * Math.sin(angle);
                points.push([
                    latlng.lat + dLat,
                    latlng.lng + dLng,
                    0.72,
                ]);
            }
        });

        return points;
    }

    function clearZeroHeatSurface() {
        if (zeroHeatLayer) {
            map.removeLayer(zeroHeatLayer);
            zeroHeatLayer = null;
        }
    }

    function renderZeroHeatSurface() {
        clearZeroHeatSurface();
        if (typeof L.heatLayer !== 'function') {
            return;
        }
        if (!isSurveillanceHeat || isSurveillanceChoropleth) {
            return;
        }

        const points = buildZeroHeatPoints();
        if (points.length === 0) {
            return;
        }

        zeroHeatLayer = L.heatLayer(points, {
            pane: 'hmZeroHeatPane',
            radius: heatRadiusPx() * 1.2,
            blur: heatBlurPx() * 1.1,
            maxZoom: 18,
            max: 1,
            minOpacity: 0.62,
            gradient: ZERO_HEAT_GRADIENT,
        }).addTo(map);
    }

    function renderHeatSurface(options) {
        const opts = options || {};
        if (heatLayer) {
            map.removeLayer(heatLayer);
            heatLayer = null;
        }
        if (typeof L.heatLayer !== 'function') {
            return;
        }

        const underlay = opts.underlay === true;
        if (!underlay) {
            renderZeroHeatSurface();
        } else {
            clearZeroHeatSurface();
        }

        const points = buildHeatPoints();
        if (points.length === 0 && !zeroHeatLayer) {
            return;
        }

        if (points.length === 0) {
            return;
        }

        heatLayer = L.heatLayer(points, {
            pane: 'hmHeatPane',
            radius: underlay ? heatRadiusPx() * 1.35 : heatRadiusPx(),
            blur: underlay ? heatBlurPx() * 1.25 : heatBlurPx(),
            maxZoom: 18,
            max: 1,
            minOpacity: underlay ? 0.22 : Number(heatConfig.min_opacity ?? 0.42),
            gradient: underlay ? HEAT_UNDERLAY_GRADIENT : HEAT_GRADIENT,
        }).addTo(map);
    }

    function clearOutlineLayer() {
        if (outlineLayer) {
            map.removeLayer(outlineLayer);
            outlineLayer = null;
        }
    }

    function clearBoundaryTopLayer() {
        if (boundaryTopLayer) {
            map.removeLayer(boundaryTopLayer);
            boundaryTopLayer = null;
        }
    }

    function renderBoundaryTopLines(geo) {
        clearBoundaryTopLayer();
        if (!surveillanceShowBoundaries || !isSurveillanceHeat) {
            return;
        }
        const features = barangayFeaturesFromGeo(geo);
        boundaryTopLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
            pane: 'hmBoundaryTopPane',
            className: 'hm-boundary-top-layer',
            interactive: false,
            style: {
                fillColor: 'transparent',
                fillOpacity: 0,
                color: surveillanceChoroplethStroke.color || '#1e293b',
                weight: Number(surveillanceChoroplethStroke.weight ?? 1.35),
                opacity: Number(surveillanceChoroplethStroke.opacity ?? 0.95),
            },
        }).addTo(map);
    }

    function updateBoundaryBanner(meta) {
        const banner = document.getElementById('hmBoundaryBanner');
        if (!meta || meta.level === 'adequate') {
            if (banner) {
                banner.classList.add('d-none');
            }
            return;
        }
        if (banner) {
            banner.classList.remove('d-none');
        }
    }

    function renderBarangayLabels() {
        if (!labelLayer) {
            return;
        }
        labelLayer.clearLayers();
        if (!isSurveillanceHeat) {
            return;
        }

        barangayOrder().forEach((name) => {
            const latlng = anchorForBarangay(name);
            if (!latlng) {
                return;
            }
            const label = L.marker(latlng, {
                pane: 'hmLabelPane',
                interactive: false,
                icon: L.divIcon({
                    className: 'hm-brgy-label-wrap',
                    html: `<span class="hm-brgy-map-label">${escapeHtml(brgyDisplayName(name))}</span>`,
                    iconSize: [0, 0],
                }),
            });
            label.addTo(labelLayer);
        });
    }

    function renderSurveillanceMap(geo) {
        let features;
        if (isSurveillanceChoropleth) {
            if (surveillanceSoftHeat) {
                renderHeatSurface({ underlay: true });
            } else if (heatLayer) {
                map.removeLayer(heatLayer);
                heatLayer = null;
            }
            features = renderSurveillanceChoropleth(geo);
            renderBarangayLabels();
            if (markerLayer) {
                markerLayer.clearLayers();
            }
        } else {
            if (choroplethLayer) {
                map.removeLayer(choroplethLayer);
                choroplethLayer = null;
            }
            renderHeatSurface();
            features = renderSurveillanceBoundaryInteractive(geo);
            renderBarangayLabels();
            clearHeatHitLayer();
            if (markerLayer) {
                markerLayer.clearLayers();
            }
        }
        return features;
    }

    function densityHeatHitRadiusPx() {
        return Math.max(52, Math.min(100, Math.round(heatRadiusPx() * 1.45)));
    }

    function activateBarangayFromMap(data, layer) {
        if (!data) {
            return;
        }
        showBarangayDetail(data);
        if (isDensityHeat || isSurveillanceChoropleth) {
            if (map && typeof map.closePopup === 'function') {
                map.closePopup();
            }
            const poly = layer
                || (isDensityHeat ? boundaryLayerForBarangay(data.name) : null);
            if (poly) {
                poly.bringToFront();
            }
            if (isDensityHeat) {
                refreshBoundaryInteractiveStyles();
            } else {
                refreshSurveillanceChoroplethStyles();
            }
            return;
        }
        if (layer && typeof layer.openPopup === 'function') {
            layer.openPopup();
        }
    }

    function markerHtml(name, count, selected) {
        const safeName = escapeHtml(String(name));
        const selectedClass = selected ? ' hm-case-marker--selected' : '';

        if (isSurveillanceHeat && !isSurveillanceChoropleth) {
            const title = count > 0
                ? `${safeName}: ${count} active case(s)`
                : `${safeName}: no active cases`;
            const pinClass = count > 0 ? '' : ' hm-case-marker__pin--zero';
            return `<div class="hm-case-marker hm-case-marker--heat-hit${selectedClass}" title="${title}" role="button" tabindex="0">
                <span class="hm-case-marker__pin${pinClass}" aria-hidden="true"></span>
            </div>`;
        }

        if (isSurveillanceHeat) {
            const title = count > 0
                ? `${safeName}: ${count} active case(s)`
                : `${safeName}: no active cases`;
            return `<div class="hm-case-marker hm-case-marker--pin${selectedClass}" title="${title}">
                <span class="hm-case-marker__pin" aria-hidden="true"></span>
            </div>`;
        }

        const upper = safeName.toUpperCase();
        if (count > 0) {
            return `<div class="hm-case-marker hm-case-marker--active${selectedClass}" title="${safeName}">
                <span class="hm-case-marker__count">${count}</span>
                <span class="hm-case-marker__label">${upper}</span>
            </div>`;
        }
        return `<div class="hm-case-marker hm-case-marker--zero${selectedClass}" title="${safeName}: no active cases">
            <span class="hm-case-marker__hollow" aria-hidden="true"></span>
        </div>`;
    }

    function clearHeatHitLayer() {
        if (hitLayer) {
            hitLayer.clearLayers();
        }
    }

    function renderHeatHitTargets() {
        if (!hitLayer || !isSurveillanceHeat || isSurveillanceChoropleth) {
            clearHeatHitLayer();
            return;
        }

        clearHeatHitLayer();
        const radius = densityHeatHitRadiusPx();

        barangayOrder().forEach((name) => {
            const latlng = anchorForBarangay(name);
            if (!latlng) {
                return;
            }
            const data = barangayData(name);

            const hit = L.circleMarker(latlng, {
                pane: 'hmHitPane',
                radius,
                fillColor: '#000',
                fillOpacity: 0.01,
                color: 'transparent',
                weight: 0,
                className: 'hm-heat-hit-target',
            });

            hit.bindTooltip(buildHoverTooltipHtml(data), {
                sticky: true,
                direction: 'top',
                opacity: 0.96,
                className: 'hm-brgy-tooltip-pane',
            });

            hit.on('click', (e) => {
                L.DomEvent.stopPropagation(e);
                activateBarangayFromMap(data, null);
            });
            hit.on('mouseover', () => hit.openTooltip());
            hit.on('mouseout', () => hit.closeTooltip());

            hit.addTo(hitLayer);
        });
    }

    function renderCaseMarkers() {
        if (!markerLayer) {
            return;
        }
        markerLayer.clearLayers();
        renderHeatHitTargets();

        const densityHeat = isSurveillanceHeat && !isSurveillanceChoropleth;
        const hitPx = densityHeat ? densityHeatHitRadiusPx() : 24;

        barangayOrder().forEach((name) => {
            const latlng = anchorForBarangay(name);
            if (!latlng) {
                return;
            }
            const data = barangayData(name);
            const count = data.total_cases ?? 0;
            const selected = name === selectedBarangay;

            const marker = L.marker(latlng, {
                pane: 'hmMarkerPane',
                interactive: true,
                keyboard: true,
                zIndexOffset: selected ? 600 : 400,
                icon: L.divIcon({
                    className: 'hm-case-marker-wrap',
                    html: markerHtml(name, count, selected),
                    iconSize: densityHeat ? [hitPx, hitPx] : [24, 24],
                    iconAnchor: densityHeat ? [hitPx / 2, hitPx / 2] : [12, 12],
                }),
            });

            marker.bindPopup(buildPopupHtml(data), {
                maxWidth: 280,
                className: 'hm-popup-wrap',
            });

            if (!densityHeat) {
                marker.bindTooltip(buildHoverTooltipHtml(data), {
                    sticky: true,
                    direction: 'top',
                    opacity: 0.96,
                    className: 'hm-brgy-tooltip-pane',
                });
            }

            marker.on('click', (e) => {
                L.DomEvent.stopPropagation(e);
                activateBarangayFromMap(data, marker);
            });

            marker.addTo(markerLayer);
        });
    }

    function choroplethStyle(name, state) {
        const data = barangayData(name);
        const count = data.total_cases ?? 0;
        const isSelected = state === 'selected' || name === selectedBarangay;
        const isHover = state === 'hover';

        let opacity = count === 0 ? fillOpacity.zero : fillOpacity.case;
        if (isHover) {
            opacity = fillOpacity.hover;
        }
        if (isSelected) {
            opacity = fillOpacity.selected;
        }

        return {
            fillColor: data.color || '#22c55e',
            fillOpacity: opacity,
            color: CHORO_BOUNDARY.stroke,
            weight: isSelected || isHover ? 1.75 : CHORO_BOUNDARY.weight,
            opacity: CHORO_BOUNDARY.opacity,
        };
    }

    function styleChoroplethFeature(feature) {
        return choroplethStyle(featureName(feature), 'default');
    }

    function refreshChoroplethStyles() {
        if (!choroplethLayer) {
            return;
        }
        choroplethLayer.eachLayer((layer) => {
            layer.setStyle(styleChoroplethFeature(layer.feature));
        });
    }

    function renderChoropleth(geo) {
        const features = barangayFeaturesFromGeo(geo);
        if (choroplethLayer) {
            map.removeLayer(choroplethLayer);
        }
        if (outlineLayer) {
            map.removeLayer(outlineLayer);
            outlineLayer = null;
        }

        choroplethLayer = L.geoJSON({ type: 'FeatureCollection', features }, {
            pane: 'hmChoroPane',
            className: 'hm-choro-layer',
            style: styleChoroplethFeature,
            onEachFeature: (feature, layer) => {
                const name = featureName(feature);
                const data = barangayData(name);

                layer.bindPopup(buildPopupHtml(data), {
                    maxWidth: 280,
                    className: 'hm-popup-wrap',
                });

                layer.on({
                    mouseover: (e) => {
                        e.target.setStyle(choroplethStyle(name, 'hover'));
                        e.target.bringToFront();
                    },
                    mouseout: (e) => {
                        choroplethLayer.resetStyle(e.target);
                        if (name === selectedBarangay) {
                            e.target.setStyle(choroplethStyle(name, 'selected'));
                        }
                    },
                    click: () => {
                        showBarangayDetail(data);
                        layer.openPopup();
                    },
                });
            },
        }).addTo(map);

        return features;
    }

    function geoJsonFetchUrl() {
        return isSurveillanceHeat ? boundaryGeoUrl : geoUrl;
    }

    function normalizeGeoPayload(raw) {
        if (raw && raw.success === true && Array.isArray(raw.features)) {
            if (raw.boundary_meta) {
                boundaryMeta = raw.boundary_meta;
                updateBoundaryBanner(boundaryMeta);
            }
            return {
                type: 'FeatureCollection',
                features: raw.features,
                boundary_meta: raw.boundary_meta || null,
            };
        }
        if (raw && raw.type === 'FeatureCollection' && Array.isArray(raw.features)) {
            return raw;
        }
        return raw;
    }

    function loadGeoJson() {
        const url = geoJsonFetchUrl();
        if (geoJsonCache && geoJsonCache._fetchUrl === url) {
            return Promise.resolve(geoJsonCache);
        }
        return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('Boundary load failed');
                }
                return r.json();
            })
            .then((raw) => {
                const geo = normalizeGeoPayload(raw);
                geo._fetchUrl = url;
                geoJsonCache = geo;
                cacheFeatureCentroids(geo);
                return geo;
            });
    }

    function renderMap(data) {
        Object.keys(barangayIndex).forEach((k) => delete barangayIndex[k]);
        (data.barangays || []).forEach((b) => {
            barangayIndex[b.name] = b;
        });

        formatLegend(data.legend);
        updateSummary(data);

        loadGeoJson()
            .then((geo) => {
                let features = barangayFeaturesFromGeo(geo);

                if (isChoropleth) {
                    if (heatLayer) {
                        map.removeLayer(heatLayer);
                        heatLayer = null;
                    }
                    features = renderChoropleth(geo);
                } else if (isSurveillanceHeat) {
                    features = renderSurveillanceMap(geo);
                } else {
                    if (choroplethLayer) {
                        map.removeLayer(choroplethLayer);
                        choroplethLayer = null;
                    }
                    features = renderOutline(geo);
                    renderHeatSurface();
                    renderCaseMarkers();
                }

                if (selectedBarangay && barangayIndex[selectedBarangay]) {
                    showBarangayDetail(barangayIndex[selectedBarangay]);
                } else {
                    showBarangayDetail(null);
                }

                if (!mapViewLocked) {
                    lockMapToMunicipality(features);
                    mapViewLocked = true;
                    setTimeout(() => map.invalidateSize(), 80);
                }
            })
            .catch(() => {
                if (!mapViewLocked) {
                    map.setView(mapCenter, mapZoom);
                    mapViewLocked = true;
                }
            });
    }

    async function loadData(options = {}) {
        const token = ++loadToken;
        showLoading(true);

        try {
            const query = buildQuery(!initialLoad || options.includeDates);
            const res = await fetch(`${apiUrl}?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (token !== loadToken) {
                return;
            }
            if (!json.success) {
                throw new Error(json.message || 'Load failed');
            }

            if (json.meta?.used_suggested_dates || initialLoad) {
                els.dateFrom.value = json.data.date_from;
                els.dateTo.value = json.data.date_to;
            }

            if (!options.skipDiseaseFilter) {
                populateDiseaseFilter(json.data.diseases, !initialLoad);
            }

            renderMap(json.data);
            initialLoad = false;
        } catch (err) {
            console.error(err);
            showEmptyState(true);
        } finally {
            if (token === loadToken) {
                showLoading(false);
            }
        }
    }

    function initMap() {
        map = L.map('hmMap', {
            zoomControl: false,
            scrollWheelZoom: true,
            maxBoundsViscosity: 1.0,
            zoomSnap: 0.25,
            zoomDelta: 0.25,
            attributionControl: true,
        }).setView(mapCenter, mapZoom);

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        map.createPane('hmOutlinePane');
        map.getPane('hmOutlinePane').style.zIndex = '420';
        map.createPane('hmZeroHeatPane');
        map.getPane('hmZeroHeatPane').style.zIndex = '425';
        map.createPane('hmHeatPane');
        map.getPane('hmHeatPane').style.zIndex = '430';
        map.createPane('hmChoroPane');
        map.getPane('hmChoroPane').style.zIndex = '440';
        map.createPane('hmBoundaryPane');
        map.getPane('hmBoundaryPane').style.zIndex = '445';
        map.createPane('hmHitPane');
        map.getPane('hmHitPane').style.zIndex = '470';
        map.createPane('hmBoundaryTopPane');
        map.getPane('hmBoundaryTopPane').style.zIndex = '450';
        map.createPane('hmLabelPane');
        map.getPane('hmLabelPane').style.zIndex = '460';
        map.createPane('hmMarkerPane');
        map.getPane('hmMarkerPane').style.zIndex = '480';

        const layers = Array.isArray(basemapConfig.layers) ? basemapConfig.layers : null;
        if (layers && layers.length > 0) {
            layers.forEach((layerCfg) => {
                if (!layerCfg || !layerCfg.url) {
                    return;
                }
                const tileOpts = {
                    maxZoom: Number(layerCfg.max_zoom ?? 19),
                    opacity: Number(layerCfg.opacity ?? 1),
                };
                if (layerCfg.subdomains) {
                    tileOpts.subdomains = layerCfg.subdomains;
                }
                L.tileLayer(layerCfg.url, tileOpts).addTo(map);
            });
        } else if (basemapConfig.url) {
            const tileOpts = {
                attribution: basemapConfig.attribution,
                maxZoom: Number(basemapConfig.max_zoom ?? 19),
            };
            if (basemapConfig.subdomains) {
                tileOpts.subdomains = basemapConfig.subdomains;
            }
            L.tileLayer(basemapConfig.url, tileOpts).addTo(map);
        }

        if (basemapConfig.attribution) {
            map.attributionControl.setPrefix('');
            map.attributionControl.addAttribution(basemapConfig.attribution);
        }

        labelLayer = L.layerGroup().addTo(map);
        hitLayer = L.layerGroup().addTo(map);
        markerLayer = L.layerGroup().addTo(map);

        map.on('zoomend', () => {
            if (!isSurveillanceHeat) {
                return;
            }
            if (isSurveillanceChoropleth) {
                if (heatLayer && surveillanceSoftHeat) {
                    renderHeatSurface({ underlay: true });
                }
                return;
            }
            if (heatLayer) {
                renderHeatSurface();
            }
            if (!isSurveillanceChoropleth) {
                refreshBoundaryInteractiveStyles();
            } else {
                renderCaseMarkers();
            }
        });

        window.addEventListener('resize', () => {
            map.invalidateSize({ animate: false });
        });
    }

    function bindLegendCollapse() {
        const panel = document.getElementById('hmLegendPanel');
        const toggle = document.getElementById('hmLegendToggle');
        const body = document.getElementById('hmLegendBody');
        if (!panel || !toggle || !body) {
            return;
        }

        const setCollapsed = (collapsed) => {
            panel.classList.toggle('hm-gis-legend--collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            body.hidden = collapsed;
        };

        setCollapsed(true);

        toggle.addEventListener('click', () => {
            setCollapsed(!panel.classList.contains('hm-gis-legend--collapsed'));
        });
    }

    function bindEvents() {
        els.refreshBtn.addEventListener('click', () => loadData({ includeDates: true }));
        els.dateFrom.addEventListener('change', () => loadData({ includeDates: true }));
        els.dateTo.addEventListener('change', () => loadData({ includeDates: true }));
        els.diseaseFilter.addEventListener('change', () => loadData({ includeDates: true, skipDiseaseFilter: true }));
        els.detailClose.addEventListener('click', () => showBarangayDetail(null));
        bindLegendCollapse();
    }

    updateBoundaryBanner(boundaryMeta);

    initMap();
    bindEvents();
    loadData();
})();
