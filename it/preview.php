<?php
/**
 * IT Staff — Print preview (official RHU Magallanes / PhilHealth layouts).
 * Print fidelity over responsive web layout. Empty forms still render blank.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/ItPrintForms.php';

ItPrintForms::requireItRole();

$patientDbId = (int) ($_GET['id'] ?? 0);
$consultationId = (int) ($_GET['consultation'] ?? 0);
$documentKey = trim((string) ($_GET['document'] ?? ''));
$bundleKey   = trim((string) ($_GET['bundle'] ?? ''));
$formsParam  = trim((string) ($_GET['forms'] ?? ''));
$printJob    = isset($_GET['printjob']) && $_GET['printjob'] === '1';
$autoprint   = !$printJob && isset($_GET['autoprint']) && $_GET['autoprint'] === '1';

if ($patientDbId <= 0) {
    die('Invalid patient ID.');
}

$formIds = [];
$layout = 'a4-portrait';
$bundleTitle = 'Print Preview';
$duplexHint = '';
$pageSizeLabel = 'A4';
$orientationLabel = 'Portrait';

if ($documentKey !== '') {
    $doc = ItPrintForms::officialDocument($documentKey);
    if (!$doc) {
        die('Invalid print document.');
    }
    $formIds = $doc['forms'];
    $layout = $doc['layout'];
    $bundleTitle = $doc['label'];
    $duplexHint = $doc['duplex_hint'] ?? '';
    $pageSizeLabel = strtoupper((string) ($doc['page_size'] ?? 'A4'));
    $orientationLabel = ucfirst((string) ($doc['orientation'] ?? 'portrait'));
} elseif ($formsParam !== '') {
    $formIds = ItPrintForms::sanitizeFormIds(explode(',', $formsParam));
    if (empty($formIds)) {
        die('No valid forms selected.');
    }
    $labels = [];
    foreach (ItPrintForms::printableForms() as $f) {
        if (in_array($f['id'], $formIds, true)) {
            $labels[] = $f['label'];
        }
    }
    $bundleTitle = count($labels) === 1
        ? $labels[0]
        : ('Selected Forms (' . count($formIds) . ')');
    $layout = ItPrintForms::inferLayout($formIds);
    if ($layout === 'legal-portrait') {
        $pageSizeLabel = 'Legal';
        $orientationLabel = 'Portrait';
    } elseif ($layout === 'a4-landscape-half') {
        $pageSizeLabel = 'A4';
        $orientationLabel = 'Landscape';
        $duplexHint = 'Print on one landscape sheet, then cut in half.';
        // Always include both slips for half-half paper
        $formIds = ['ekas', 'epress'];
    } else {
        $pageSizeLabel = 'A4';
        $orientationLabel = 'Portrait';
    }
} else {
    if ($bundleKey === '') {
        $bundleKey = 'all';
    }
    $bundles = ItPrintForms::bundles();
    if (!isset($bundles[$bundleKey])) {
        die('Invalid print bundle.');
    }
    $formIds = ItPrintForms::resolveFormIds($bundleKey);
    $bundleTitle = ItPrintForms::bundleLabel($bundleKey);
    $layout = ItPrintForms::layoutForBundle($bundleKey);
    // Full set with mixed paper: prefer official Print jobs; still render all forms blank-ready
    if ($bundleKey === 'all') {
        $layout = ItPrintForms::inferLayout($formIds);
        $bundleTitle = 'Complete RHU Form Set (use Print module for correct paper per form)';
    }
    if ($layout === 'legal-portrait') {
        $pageSizeLabel = 'Legal';
    } elseif ($layout === 'a4-landscape-half') {
        $pageSizeLabel = 'A4';
        $orientationLabel = 'Landscape';
    }
}

$formIds = ItPrintForms::sortFormIds($formIds);

$data = ItPrintForms::loadRecord($patientDbId, $consultationId > 0 ? $consultationId : null);
if (empty($data['patient'])) {
    die('Patient not found.');
}

ItPrintForms::assertCanView($data['patient']);

$role = ROLE_IT;
$lockAll = true;
$patientName = ItPrintForms::formatPatientName($data['patient']);
$layoutTestMode = ItPrintForms::isLayoutTestMode();
$layoutClass = 'it-layout--' . preg_replace('/[^a-z0-9\-]/', '', strtolower($layout));
$cssPaper = (int) @filemtime(__DIR__ . '/../assets/css/paper-form.css');
$cssIt = (int) @filemtime(__DIR__ . '/../assets/css/it-print.css');
$cssOfficial = (int) @filemtime(__DIR__ . '/../assets/css/official-print.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Preview — <?= htmlspecialchars($bundleTitle) ?></title>
    <style>
        /* Hint to browsers: do not shrink-to-fit fixed paper templates */
        @page { margin: 0; }
    </style>
    <link href="<?= BASE_URL ?>/assets/css/paper-form.css?v=<?= $cssPaper ?>" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/it-print.css?v=<?= $cssIt ?>" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/official-print.css?v=<?= $cssOfficial ?>" rel="stylesheet">
</head>
<body class="it-preview-body <?= htmlspecialchars($layoutClass) ?><?= $printJob ? ' it-printjob' : '' ?><?= $autoprint ? ' it-autoprint' : '' ?><?= $layoutTestMode ? ' it-layout-test-mode' : '' ?>">

<?php if ($layoutTestMode): ?>
<div class="it-layout-test-banner no-print">
    Layout test mode — set <code>layout_test_mode</code> to <code>false</code> in <code>config/it-print.php</code> after layout approval.
</div>
<?php endif; ?>

<?php if (!$printJob): ?>
<div class="it-preview-chrome no-print">
    <div class="it-preview-chrome-inner it-preview-chrome-inner--wide">
        <div>
            <strong><?= htmlspecialchars($bundleTitle) ?></strong>
            <div class="it-preview-meta">
                <?= htmlspecialchars($data['patient']['patient_id']) ?> — <?= htmlspecialchars($patientName) ?>
                · <?= htmlspecialchars($pageSizeLabel) ?> <?= htmlspecialchars($orientationLabel) ?>
                <?php if ($duplexHint !== ''): ?>
                    · <?= htmlspecialchars($duplexHint) ?>
                <?php endif; ?>
                · Forms always print (blank fields stay blank)
            </div>
        </div>
        <div class="it-preview-chrome-actions">
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="it-preview-pages it-preview-pages--<?= htmlspecialchars(preg_replace('/[^a-z0-9\-]/', '', strtolower($layout))) ?>" id="itPreviewPages">
    <?= ItPrintForms::renderForms($formIds, $role, $lockAll, $layout) ?>
</div>

<script>
window.IT_PREVIEW = {
    patient: <?= json_encode($data['patient'], JSON_UNESCAPED_UNICODE) ?>,
    treatment: <?= json_encode($data['treatment'], JSON_UNESCAPED_UNICODE) ?>,
    extended: <?= json_encode($data['extended'], JSON_UNESCAPED_UNICODE) ?>,
    autoprint: <?= $autoprint ? 'true' : 'false' ?>,
    printjob: <?= $printJob ? 'true' : 'false' ?>,
    layoutTestMode: <?= $layoutTestMode ? 'true' : 'false' ?>,
    layout: <?= json_encode($layout) ?>,
    document: <?= json_encode($documentKey) ?>,
    formIds: <?= json_encode($formIds, JSON_UNESCAPED_UNICODE) ?>,
};
</script>
<script src="<?= BASE_URL ?>/assets/js/it-preview-populate.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/js/it-preview-populate.js') ?>"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</body>
</html>
