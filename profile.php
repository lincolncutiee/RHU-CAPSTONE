<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$user = currentUser();
$pageTitle = 'My profile';
$activePage = 'profile';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/assets/css/admin-module.css')];
$extraJS = [BASE_URL . '/assets/js/profile.js?v=' . (int) @filemtime(__DIR__ . '/assets/js/profile.js')];

include __DIR__ . '/includes/header.php';
?>

<div class="admin-panel card shadow-sm profile-page">
    <div class="card-body">
        <div class="profile-page-header">
            <div class="profile-page-avatar" id="profileAvatar" aria-hidden="true">—</div>
            <div>
                <h1 class="profile-page-title">My profile</h1>
                <p class="profile-page-sub text-muted mb-0">Update your contact info and password.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <section class="profile-section">
                    <h2 class="profile-section-title">Account</h2>
                    <form id="profileAccountForm">
                        <div class="mb-3">
                            <label class="form-label" for="profileUsername">Username</label>
                            <input type="text" class="form-control" id="profileUsername" readonly disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profileRole">Role</label>
                            <input type="text" class="form-control" id="profileRole" readonly disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profileFullName">Full name</label>
                            <input type="text" class="form-control" id="profileFullName" required autocomplete="name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profileEmail">Email <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="email" class="form-control" id="profileEmail" autocomplete="email">
                        </div>
                        <div class="profile-meta text-muted small mb-3" id="profileMeta"></div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i> Save profile
                        </button>
                    </form>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="profile-section profile-section--password">
                    <h2 class="profile-section-title">Change password</h2>
                    <form id="profilePasswordForm">
                        <div class="mb-3">
                            <label class="form-label" for="profileCurrentPassword">Current password</label>
                            <input type="password" class="form-control" id="profileCurrentPassword" autocomplete="current-password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profileNewPassword">New password</label>
                            <input type="password" class="form-control" id="profileNewPassword" minlength="8" autocomplete="new-password" required>
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profileConfirmPassword">Confirm new password</label>
                            <input type="password" class="form-control" id="profileConfirmPassword" minlength="8" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-key" aria-hidden="true"></i> Update password
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
window.PROFILE_CFG = {
    apiUrl: <?= json_encode(BASE_URL . '/api/profile.php') ?>,
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
