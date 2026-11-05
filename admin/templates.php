<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdminPermission('admin.templates');

$pageTitle = 'Administration — Form & Logo';
$activePage = 'admin-templates';
$adminSection = 'templates';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/admin-module.css')];
$extraJS = [BASE_URL . '/assets/js/admin-templates.js?v=' . (int) @filemtime(__DIR__ . '/../assets/js/admin-templates.js')];

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-form-logo-page">
<ul class="nav nav-tabs admin-template-tabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabBranding" type="button">
            <i class="bi bi-image" aria-hidden="true"></i> Logo &amp; site
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabStrings" type="button">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Paper forms
        </button>
    </li>
</ul>

<div class="tab-content admin-template-tab-content">
    <div class="tab-pane fade show active" id="tabBranding">
        <div class="card shadow-sm admin-panel admin-branding-card">
            <div class="card-body">
                <div class="admin-branding-intro">
                    <h2 class="admin-branding-heading">Logo &amp; site identity</h2>
                    <p class="admin-branding-lead text-muted mb-0">Logo sa top bar, titles sa navigation, ug facility name sa paper forms.</p>
                </div>
                <form id="adminBrandingForm">
                    <div class="admin-branding-layout">
                        <aside class="admin-branding-logo-panel">
                            <p class="admin-branding-section-label">Facility logo</p>
                            <div class="admin-logo-frame">
                                <img src="" alt="Logo preview" id="adminLogoPreview" class="admin-logo-preview">
                            </div>
                            <label class="btn btn-outline-primary admin-logo-upload-btn">
                                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Upload logo
                                <input type="file" id="adminLogoFile" accept="image/png,image/jpeg,image/webp" hidden>
                            </label>
                            <p class="admin-branding-hint">PNG, JPG, or WebP · mas maayo ang square image</p>
                            <input type="hidden" id="adminLogoPath">
                        </aside>
                        <div class="admin-branding-fields">
                            <p class="admin-branding-section-label">Navigation &amp; forms</p>
                            <div class="mb-3">
                                <label class="form-label" for="adminSiteTitle"><i class="bi bi-type-h1 admin-field-icon" aria-hidden="true"></i> Site title (top navigation)</label>
                                <input type="text" class="form-control" id="adminSiteTitle" placeholder="e.g. RHU Magallanes">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="adminSiteSubtitle"><i class="bi bi-geo-alt admin-field-icon" aria-hidden="true"></i> Site subtitle</label>
                                <input type="text" class="form-control" id="adminSiteSubtitle" placeholder="e.g. Agusan del Norte">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="adminFacilityName"><i class="bi bi-building admin-field-icon" aria-hidden="true"></i> Facility name (forms / headers)</label>
                                <input type="text" class="form-control" id="adminFacilityName" placeholder="Name on printed forms">
                            </div>
                            <div class="admin-topnav-preview-wrap" aria-live="polite">
                                <p class="admin-branding-preview-label">Live preview — top bar</p>
                                <div class="admin-topnav-preview">
                                    <span class="admin-topnav-preview-logo">
                                        <img src="" alt="" id="adminPreviewLogoImg" width="32" height="32">
                                    </span>
                                    <span class="admin-topnav-preview-text">
                                        <span class="admin-topnav-preview-title" id="adminPreviewTitle">RHU Magallanes</span>
                                        <span class="admin-topnav-preview-sub" id="adminPreviewSubtitle">Agusan del Norte</span>
                                    </span>
                                </div>
                            </div>
                            <div class="admin-branding-save-bar">
                                <div class="admin-branding-password">
                                    <label class="form-label mb-1" for="adminBrandingPassword"><i class="bi bi-shield-lock admin-field-icon" aria-hidden="true"></i> Your password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="adminBrandingPassword" autocomplete="current-password" placeholder="Required to save changes">
                                </div>
                                <button type="submit" class="btn btn-primary admin-branding-save-btn">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Save changes
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tabStrings">
        <div class="card shadow-sm admin-panel admin-forms-tab-card">
            <div class="card-body">
                <div class="admin-forms-tab-intro">
                    <div>
                        <h2 class="admin-branding-heading">Paper forms</h2>
                        <p class="admin-branding-lead text-muted mb-0">7 ka official form — i-click ang card aron mo-zoom in ug ma-edit ang text sa form.</p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="adminReloadStrings" title="Reload forms">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Reload
                    </button>
                </div>
                <div class="admin-forms-toolbar">
                    <select class="form-select admin-filter-prefix" id="adminStringPrefix" aria-label="Filter by form">
                        <option value="">Tanang form (list)</option>
                    </select>
                    <div class="input-group admin-forms-search">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input type="search" class="form-control" id="adminStringSearch" placeholder="Search form name or label…" aria-label="Search labels">
                    </div>
                </div>
                <div id="adminFormsCatalog" class="admin-forms-catalog"></div>
                <div id="adminStringsList" class="admin-strings-list d-none"></div>
            </div>
        </div>
    </div>
</div>
</div>

<div id="adminFormZoom" class="admin-form-zoom" aria-hidden="true">
    <div class="admin-form-zoom-backdrop" id="adminFormZoomBackdrop" tabindex="-1"></div>
    <div class="admin-form-zoom-panel" role="dialog" aria-modal="true" aria-labelledby="adminFormZoomTitle">
        <header class="admin-form-zoom-header">
            <div>
                <p class="admin-form-zoom-kicker mb-0" id="adminFormZoomKicker">Form labels</p>
                <h2 class="admin-form-zoom-title h5 mb-0" id="adminFormZoomTitle"></h2>
                <p class="text-muted small mb-0" id="adminFormZoomSubtitle"></p>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="adminFormZoomClose">
                <i class="bi bi-arrows-angle-contract"></i> Close
            </button>
        </header>
        <div class="admin-form-zoom-body">
            <div class="admin-form-zoom-preview-wrap" id="adminFormZoomPreviewWrap">
                <iframe id="adminFormZoomFrame" class="admin-form-zoom-frame" title="Form preview" loading="lazy"></iframe>
            </div>
            <details class="admin-form-zoom-labels-drawer mt-3 d-none" id="adminFormZoomLabelsDrawer">
                <summary class="fw-semibold">Advanced: edit by template key</summary>
                <div class="admin-form-zoom-labels-body pt-2" id="adminFormZoomBody"></div>
            </details>
        </div>
    </div>
</div>

<script>
window.ADMIN_TPL_CFG = {
    apiUrl: <?= json_encode(BASE_URL . '/api/admin_templates.php') ?>,
    baseUrl: <?= json_encode(BASE_URL) ?>,
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
