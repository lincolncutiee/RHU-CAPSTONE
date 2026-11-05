<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdminPermission('admin.users');

$pageTitle = 'Administration — Users';
$activePage = 'admin-users';
$adminSection = 'users';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/admin-module.css')];
$extraJS = [BASE_URL . '/assets/js/admin-users.js?v=' . (int) @filemtime(__DIR__ . '/../assets/js/admin-users.js')];

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel card shadow-sm admin-users-page">
    <div class="card-body">
        <div class="admin-users-toolbar">
            <div class="admin-users-toolbar-start">
                <h2 class="admin-users-heading">Staff accounts</h2>
                <span class="admin-users-count badge rounded-pill text-bg-light" id="adminUserCount" aria-live="polite">—</span>
            </div>
            <div class="admin-users-toolbar-end">
                <div class="input-group admin-users-search">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control" id="adminUserSearch" placeholder="Search name, username, or email…" autocomplete="off" aria-label="Search users">
                    <button type="button" class="btn btn-outline-secondary admin-users-search-clear d-none" id="adminUserSearchClear" title="Clear search" aria-label="Clear search">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-primary admin-users-new-btn" id="adminBtnNewUser">
                    <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                    <span>New user</span>
                </button>
            </div>
        </div>
        <div class="admin-users-table-wrap table-responsive">
            <table class="table align-middle admin-users-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Username</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="admin-users-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminUsersTbody">
                    <tr class="admin-users-loading-row">
                        <td colspan="5">
                            <div class="admin-users-loading">
                                <div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div>
                                <span>Loading users…</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User modal -->
<div class="modal fade" id="adminUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminUserModalTitle">User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="adminUserForm">
                    <input type="hidden" id="adminUserId" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full name</label>
                            <input type="text" class="form-control" id="adminFullName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="adminUsername" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-muted">(optional)</span></label>
                            <input type="email" class="form-control" id="adminEmail" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="adminRoleSlug" required></select>
                        </div>
                        <div class="col-12" id="adminNewPasswordWrap">
                            <label class="form-label">Initial password</label>
                            <input type="password" class="form-control" id="adminNewUserPassword" minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="adminCanAccessAdmin">
                                <label class="form-check-label" for="adminCanAccessAdmin">
                                    Can access <strong>Administration</strong> (users, templates, audit)
                                </label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="mb-2">Field group privileges</h6>
                    <p class="small text-muted">Override role defaults per group. “Default” uses the role’s normal access.</p>
                    <div id="adminPrivilegeGrid" class="admin-privilege-grid"></div>
                    <hr>
                    <div class="admin-password-confirm">
                        <label class="form-label">Your password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="adminConfirmPassword" autocomplete="current-password" placeholder="Required to save">
                    </div>
                </form>
            </div>
            <div class="modal-footer flex-wrap">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="adminSaveUser">Save user</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset password modal -->
<div class="modal fade" id="adminResetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="adminResetUserId">
                <div class="mb-3">
                    <label class="form-label">New password for user</label>
                    <input type="password" class="form-control" id="adminResetNewPassword" minlength="8" autocomplete="new-password">
                </div>
                <div>
                    <label class="form-label">Your password</label>
                    <input type="password" class="form-control" id="adminResetConfirmPassword" autocomplete="current-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="adminResetSubmit">Reset password</button>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_USERS_CFG = {
    apiUrl: <?= json_encode(BASE_URL . '/api/admin_users.php') ?>,
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
