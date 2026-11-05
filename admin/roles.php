<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/AdminAccess.php';

AdminAccess::requirePermission('admin.roles');

$pageTitle = 'Administration — Roles & permissions';
$activePage = 'admin-roles';
$adminSection = 'roles';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/admin-module.css')];
$extraJS = [BASE_URL . '/assets/js/admin-roles.js?v=' . (int) @filemtime(__DIR__ . '/../assets/js/admin-roles.js')];

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel card shadow-sm admin-roles-page">
    <div class="card-body p-0">
        <div class="admin-roles-header">
            <div class="admin-roles-header-text">
                <h2 class="admin-roles-title">Roles &amp; permissions</h2>
                <p class="admin-roles-subtitle mb-0">Choose a role, then toggle what that role may do. Staff inherit these settings from their assigned role.</p>
            </div>
            <div class="admin-roles-header-actions">
                <span class="admin-roles-dirty-badge d-none" id="adminRolesDirtyBadge">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Unsaved changes
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="adminRolesReset" disabled title="Restore factory defaults for this role">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset defaults
                </button>
                <button type="button" class="btn btn-primary" id="adminRolesSave" disabled>
                    <i class="bi bi-check2-circle"></i> Save
                </button>
            </div>
        </div>

        <div class="admin-roles-layout" id="adminRolesLayout">
            <aside class="admin-roles-sidebar" aria-label="Roles">
                <p class="admin-roles-sidebar-label">Roles</p>
                <div class="admin-roles-list" id="adminRolesList" role="tablist"></div>
            </aside>

            <div class="admin-roles-main">
                <div class="admin-roles-loading" id="adminRolesLoading">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span>Loading permissions…</span>
                </div>

                <div class="admin-roles-placeholder" id="adminRolesPlaceholder">
                    <i class="bi bi-hand-index-thumb" aria-hidden="true"></i>
                    <p class="mb-0">Pilia ang <strong>role</strong> sa wala — makita dinhi ang permissions para sa role nga iya lang.</p>
                </div>

                <div class="admin-roles-editor-wrap d-none" id="adminRolesEditorWrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary admin-roles-back d-lg-none mb-2" id="adminRolesBack">
                        <i class="bi bi-arrow-left"></i> Back to roles
                    </button>
                    <div class="admin-roles-editor-toolbar">
                        <div class="admin-roles-editor-title-block">
                            <h3 class="admin-roles-editor-title" id="adminRolesEditorTitle">—</h3>
                            <p class="admin-roles-editor-meta mb-0" id="adminRolesEditorMeta"></p>
                        </div>
                        <div class="admin-roles-search-wrap">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" class="form-control form-control-sm" id="adminRolesFilter" placeholder="Filter permissions…" autocomplete="off" aria-label="Filter permissions">
                        </div>
                    </div>
                    <div class="admin-roles-progress" aria-hidden="true">
                        <div class="admin-roles-progress-bar" id="adminRolesProgressBar"></div>
                    </div>
                    <div id="adminRolesEditor" class="admin-roles-editor"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-roles-savebar d-none" id="adminRolesSavebar" role="region" aria-label="Unsaved changes">
    <span>You have unsaved changes for this role.</span>
    <button type="button" class="btn btn-sm btn-light" id="adminRolesSavebarBtn">Save now</button>
</div>

<script>
window.ADMIN_ROLES_CFG = {
    apiUrl: <?= json_encode(BASE_URL . '/api/admin_roles.php') ?>
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
