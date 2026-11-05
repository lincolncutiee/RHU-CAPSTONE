<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdminPermission('admin.messaging');

$pageTitle = 'Administration — Messaging';
$activePage = 'admin-messaging';
$adminSection = 'messaging';
$hidePageTitleBar = true;
$extraCSS = [BASE_URL . '/assets/css/admin-module.css?v=' . (int) @filemtime(__DIR__ . '/../assets/css/admin-module.css')];
$extraJS = [BASE_URL . '/assets/js/admin-messaging.js?v=' . (int) @filemtime(__DIR__ . '/../assets/js/admin-messaging.js')];

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-panel card shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Follow-up reminders — email</h2>
                <p class="text-muted small mb-0">Primary channel: <strong>email</strong> (Gmail/SMTP, no per-message SMS load). Doctors set follow-ups in consultation.</p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnRunReminderQueue">
                <i class="bi bi-send"></i> Run pending reminders now
            </button>
        </div>

        <form id="adminMessagingForm" class="admin-messaging-form">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="msgEnabled" name="is_enabled" value="1">
                        <label class="form-check-label" for="msgEnabled">Enable automated reminders</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgDaysBefore">Send how many days before follow-up</label>
                    <input type="number" class="form-control" id="msgDaysBefore" name="days_before" min="0" max="14" value="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgFacilityPhone">RHU contact phone (in email footer)</label>
                    <input type="text" class="form-control" id="msgFacilityPhone" name="facility_phone" placeholder="09xx xxx xxxx">
                </div>
            </div>

            <hr class="my-4">

            <h3 class="h6 text-primary">Email (recommended)</h3>
            <p class="text-muted small">Use a Gmail account with an <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">App Password</a>, or your RHU SMTP server.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="msgEmailEnabled" name="email_enabled" value="1" checked>
                        <label class="form-check-label" for="msgEmailEnabled">Enable email reminders</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgSmtpHost">SMTP host</label>
                    <input type="text" class="form-control" id="msgSmtpHost" name="smtp_host" placeholder="smtp.gmail.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgSmtpPort">SMTP port</label>
                    <input type="number" class="form-control" id="msgSmtpPort" name="smtp_port" value="587">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgSmtpUser">SMTP username</label>
                    <input type="text" class="form-control" id="msgSmtpUser" name="smtp_user" placeholder="rhu@gmail.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgSmtpPass">SMTP password / app password</label>
                    <input type="password" class="form-control" id="msgSmtpPass" name="smtp_pass" autocomplete="new-password" placeholder="Leave blank to keep current">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="msgFromEmail">From email</label>
                    <input type="email" class="form-control" id="msgFromEmail" name="smtp_from_email">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="msgFromName">From name</label>
                    <input type="text" class="form-control" id="msgFromName" name="smtp_from_name" value="RHU Magallanes">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="msgEmailSubject">Email subject template</label>
                    <input type="text" class="form-control" id="msgEmailSubject" name="email_subject_template">
                </div>
                <div class="col-12">
                    <label class="form-label" for="msgEmailBody">Email body template</label>
                    <textarea class="form-control font-monospace" id="msgEmailBody" name="email_body_template" rows="5"></textarea>
                    <div class="form-text">Placeholders: {patient_name}, {follow_up_date}, {instructions}, {facility_name}, {facility_phone}</div>
                </div>
            </div>

            <hr class="my-4">

            <details class="admin-messaging-sms-fold">
                <summary class="h6 mb-0">Optional: SMS (paid gateway — Semaphore, etc.)</summary>
                <p class="text-muted small mt-2 mb-3">Skip this section if you only use email.</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="msgSmsMode">Provider</label>
                        <select class="form-select" id="msgSmsMode" name="sms_mode">
                            <option value="log_only">Log only (development)</option>
                            <option value="semaphore">Semaphore.co</option>
                            <option value="generic_http">Generic HTTP JSON API</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="msgSmsSender">Sender name</label>
                        <input type="text" class="form-control" id="msgSmsSender" name="sms_sender_name" maxlength="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="msgSmsApiKey">API key</label>
                        <input type="password" class="form-control" id="msgSmsApiKey" name="sms_api_key" autocomplete="new-password" placeholder="Leave blank to keep current">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="msgSmsApiUrl">Generic HTTP URL (if used)</label>
                        <input type="url" class="form-control" id="msgSmsApiUrl" name="sms_api_url" placeholder="https://...">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="msgSmsTemplate">SMS template</label>
                        <textarea class="form-control font-monospace" id="msgSmsTemplate" name="sms_template" rows="3"></textarea>
                    </div>
                </div>
            </details>

            <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" class="btn btn-primary" id="btnSaveMessaging">
                    <i class="bi bi-save"></i> Save settings
                </button>
                <span class="text-muted small" id="msgSaveStatus" aria-live="polite"></span>
            </div>
        </form>

        <div class="alert alert-info mt-4 mb-0 small">
            <strong>Daily job:</strong> <code>php scripts/send-follow-up-reminders.php</code> (Task Scheduler).
            Email requires SMTP above; leave SMS folded unless you have credits.
        </div>
    </div>
</div>

<script>window.ADMIN_MESSAGING = { baseUrl: <?= json_encode(BASE_URL) ?> };</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
