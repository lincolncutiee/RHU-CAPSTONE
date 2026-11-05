<?php
/**
 * Admin — full blank official paper form preview (for Forms & Branding editor).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/FormTemplate.php';
require_once __DIR__ . '/../core/ItPrintForms.php';
require_once __DIR__ . '/../core/PaperFormRenderer.php';

requireAdminAccess();

FormTemplate::setInlineEdit(true);
try {
    $syncConn = getDBConnection();
    FormTemplate::syncDefaults($syncConn);
    $syncConn->close();
} catch (Throwable $e) {
    // preview still renders
}

$formId = trim((string) ($_GET['form'] ?? ''));
$allowed = ItPrintForms::sanitizeFormIds([$formId]);
if ($allowed === []) {
    http_response_code(400);
    die('Invalid form.');
}
$formId = $allowed[0];

$layout = ItPrintForms::layoutForForm($formId);
$pageClass = match ($layout) {
    'legal-portrait'      => 'print-page print-page--legal',
    'a4-landscape-half'   => 'print-page print-page--landscape-half',
    default               => 'print-page print-page--a4',
};

$role = ROLE_SYSTEM_ADMIN;
PaperFormRenderer::setLayoutTestMode(ItPrintForms::isLayoutTestMode());

$formHtml = PaperFormRenderer::renderPage($formId, $role, true);

$cssPaper = (int) @filemtime(__DIR__ . '/../assets/css/paper-form.css');
$cssIt = (int) @filemtime(__DIR__ . '/../assets/css/it-print.css');
$cssOfficial = (int) @filemtime(__DIR__ . '/../assets/css/official-print.css');

$wizard = require __DIR__ . '/../config/form-wizard.php';
$title = $formId;
foreach ($wizard['pages'] ?? [] as $page) {
    if (($page['id'] ?? '') === $formId) {
        $title = (string) ($page['title'] ?? $formId);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Preview</title>
    <link href="<?= BASE_URL ?>/assets/css/paper-form.css?v=<?= $cssPaper ?>" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/it-print.css?v=<?= $cssIt ?>" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/official-print.css?v=<?= $cssOfficial ?>" rel="stylesheet">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #64748b;
            min-height: 100%;
        }
        body.admin-form-preview-embed {
            margin: 0;
            padding: 12px 12px 24px;
            box-sizing: border-box;
            overflow: auto;
            -webkit-overflow-scrolling: touch;
        }
        .admin-form-preview-fit {
            width: max-content;
            max-width: 100%;
            margin: 0 auto;
        }
        .admin-form-preview-sheet {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
            background: #fff;
        }
        /* Full paper size on screen — scroll to see the whole form (no shrink-to-fit). */
        body.admin-form-preview-embed .print-page {
            zoom: 1;
        }
        @media (max-width: 900px) {
            body.admin-form-preview-embed .print-page {
                zoom: 0.92;
            }
        }
        .admin-tpl-editable {
            cursor: text;
            border-radius: 3px;
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }
        th .admin-tpl-editable,
        .pf-title-main .admin-tpl-editable,
        .pf-title-sub .admin-tpl-editable,
        .pf-sig-label .admin-tpl-editable {
            display: inline;
        }
        .admin-tpl-editable:hover {
            background: rgba(26, 111, 196, 0.14);
            box-shadow: 0 0 0 1px rgba(26, 111, 196, 0.35);
        }
        .admin-tpl-editable.is-editing {
            background: #fff !important;
            box-shadow: 0 0 0 2px #1a6fc4;
        }
        .admin-tpl-editable.is-saving {
            opacity: 0.55;
        }
        .admin-tpl-hint {
            position: fixed;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            background: #1e293b;
            color: #f8fafc;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 999px;
            pointer-events: none;
        }
        .admin-tpl-toast {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%) translateY(12px);
            background: #0f766e;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 20;
        }
        .admin-tpl-toast.is-visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body class="admin-form-preview-embed">
<div class="admin-tpl-hint">I-click ang bisan unsang text/label sa form aron ma-edit · Enter = save · Esc = cancel</div>
<div class="admin-form-preview-fit" id="adminFormPreviewFit">
    <div class="admin-form-preview-sheet it-preview-pages it-preview-pages--<?= htmlspecialchars(preg_replace('/[^a-z0-9\-]/', '', strtolower($layout))) ?>">
        <div class="<?= htmlspecialchars($pageClass) ?> it-print-form-block form-container" data-form-id="<?= htmlspecialchars($formId) ?>">
            <?= $formHtml ?>
        </div>
    </div>
</div>
<script>
(function () {
    function reportSize() {
        if (window.parent === window) return;
        const h = Math.max(
            document.documentElement.scrollHeight,
            document.body.scrollHeight
        );
        window.parent.postMessage({ type: 'admin-form-preview-height', height: h }, '*');
    }
    window.addEventListener('load', reportSize);
    window.addEventListener('resize', reportSize);
    if (document.fonts?.ready) {
        document.fonts.ready.then(reportSize);
    }
})();
</script>
<script>
window.ADMIN_FORM_INLINE = {
    apiUrl: <?= json_encode(BASE_URL . '/api/admin_templates.php') ?>,
    formId: <?= json_encode($formId) ?>,
};
</script>
<script src="<?= BASE_URL ?>/assets/js/admin-form-inline-edit.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/admin-form-inline-edit.js') ?>"></script>
</body>
</html>
