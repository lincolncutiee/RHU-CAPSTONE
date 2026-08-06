<?php
/**
 * IT Staff — Print module (print + reprint archive).
 * One row per patient; Consultation Date dropdown selects which visit to print.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/ItPrintForms.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';

requireRole([ROLE_IT]);

$pageTitle         = 'Print';
$pageSubtitle      = '';
$activePage        = 'it-print';
$hidePageTitleBar  = true;
$extraCSS          = [
    BASE_URL . '/assets/css/it-print.css?v=' . ((int) @filemtime(__DIR__ . '/../assets/css/it-print.css') ?: 1),
];
$extraJS           = [
    BASE_URL . '/assets/js/it-print-queue.js?v=' . ((int) @filemtime(__DIR__ . '/../assets/js/it-print-queue.js') ?: 1),
];

$officialDocuments = ItPrintForms::officialDocuments();
$docCount = count($officialDocuments);

$conn = getDBConnection();
$patients = ItPrintForms::listPrintPatients($conn);
$readyCount = ItPrintForms::readyToPrintCount($conn);
$conn->close();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="pq-page" id="printQueuePage">
    <div class="pq-page-head">
        <div>
            <h1 class="pq-page-title"><i class="bi bi-printer"></i> Print</h1>
            <p class="pq-page-sub mb-0">Search a patient, choose a consultation date, then print or reprint that visit only.</p>
        </div>
        <?php if ($readyCount > 0): ?>
            <span class="pq-ready-pill" title="Ready to print"><?= (int) $readyCount ?> ready</span>
        <?php endif; ?>
    </div>

    <div class="pq-toolbar">
        <div class="pq-search">
            <i class="bi bi-search"></i>
            <input
                type="search"
                id="pqSearch"
                placeholder="Search patient name or Patient ID…"
                autocomplete="off"
                aria-label="Search patient name or Patient ID"
            >
        </div>
        <select id="pqStatus" aria-label="Print Status">
            <option value="" selected>All</option>
            <option value="ready">Ready to Print</option>
            <option value="printed">Already Printed</option>
        </select>
        <button type="button" class="pq-reset" id="pqReset">Reset</button>
    </div>

    <section class="pq-panel">
        <?php if (empty($patients)): ?>
            <div class="pq-empty">
                <i class="bi bi-inbox"></i>
                <h3>No consultation records yet</h3>
                <p>Printable consultations will appear here for printing and reprinting.</p>
            </div>
        <?php else: ?>
            <div class="pq-table-wrap">
                <table class="pq-table" id="pqTable">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Consultation Date</th>
                            <th>Documents</th>
                            <th>Print Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="pqTableBody">
                        <?php foreach ($patients as $patient):
                            $name = ItPrintForms::formatPatientName($patient);
                            $consults = $patient['consultations'] ?? [];
                            if ($consults === []) {
                                continue;
                            }
                            // Newest first — default to latest consultation
                            $selected = $consults[0];
                            $cid = (int) ($selected['consultation_id'] ?? 0);
                            $isReady = ($selected['print_status'] ?? '') === 'ready';
                            $badgeClass = $isReady ? 'ready' : 'printed';
                            $badgeLabel = $isReady ? 'Ready to Print' : 'Already Printed';
                            $actionLabel = 'Print';
                            $formsCount = (int) ($selected['forms_count'] ?? $docCount);
                            $dateIso = (string) ($selected['date_iso'] ?? '');
                            $dateDisplay = (string) ($selected['date_display'] ?? '—');
                            $allDates = array_values(array_filter(array_map(
                                static fn($c) => (string) ($c['date_iso'] ?? ''),
                                $consults
                            )));
                            $allStatuses = array_values(array_unique(array_map(
                                static fn($c) => (string) ($c['print_status'] ?? 'printed'),
                                $consults
                            )));
                        ?>
                        <tr
                            class="pq-row"
                            data-patient-db-id="<?= (int) $patient['patient_db_id'] ?>"
                            data-patient-id="<?= htmlspecialchars($patient['patient_id']) ?>"
                            data-patient-name="<?= htmlspecialchars($name) ?>"
                            data-consultation-id="<?= $cid ?>"
                            data-date="<?= htmlspecialchars($dateIso) ?>"
                            data-date-display="<?= htmlspecialchars(strtolower($dateDisplay)) ?>"
                            data-status="<?= htmlspecialchars($badgeClass) ?>"
                            data-forms-count="<?= $formsCount ?>"
                            data-all-dates="<?= htmlspecialchars(implode(',', $allDates)) ?>"
                            data-all-statuses="<?= htmlspecialchars(implode(',', $allStatuses)) ?>"
                        >
                            <td data-label="Patient ID">
                                <span class="pq-pid"><?= htmlspecialchars($patient['patient_id']) ?></span>
                            </td>
                            <td class="pq-name" data-label="Patient Name"><?= htmlspecialchars($name) ?></td>
                            <td data-label="Consultation Date">
                                <div class="pq-consult-field">
                                    <label class="pq-consult-label visually-hidden" for="pqConsult-<?= (int) $patient['patient_db_id'] ?>">
                                        Consultation Date
                                    </label>
                                    <div class="pq-consult-select-wrap">
                                        <select
                                            class="pq-consult-select"
                                            id="pqConsult-<?= (int) $patient['patient_db_id'] ?>"
                                            aria-label="Consultation Date for <?= htmlspecialchars($name) ?>"
                                        >
                                            <?php foreach ($consults as $idx => $c):
                                                $optId = (int) ($c['consultation_id'] ?? 0);
                                                $optIso = (string) ($c['date_iso'] ?? '');
                                                $optDisplay = (string) ($c['date_display'] ?? 'No date');
                                                $optStatus = (string) ($c['print_status'] ?? 'printed');
                                                $optForms = (int) ($c['forms_count'] ?? $docCount);
                                                $isLatest = !empty($c['is_latest']) || $idx === 0;
                                                $label = $optDisplay . ($isLatest ? ' (Latest)' : '');
                                                $selectedAttr = $optId === $cid ? ' selected' : '';
                                            ?>
                                            <option
                                                value="<?= $optId ?>"
                                                data-date="<?= htmlspecialchars($optIso) ?>"
                                                data-date-display="<?= htmlspecialchars($optDisplay) ?>"
                                                data-status="<?= htmlspecialchars($optStatus) ?>"
                                                data-forms-count="<?= $optForms ?>"
                                                data-latest="<?= $isLatest ? '1' : '0' ?>"
                                                <?= $selectedAttr ?>
                                            ><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="bi bi-chevron-down pq-consult-caret" aria-hidden="true"></i>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Documents">
                                <span class="pq-forms-count"><?= $formsCount ?> Documents</span>
                            </td>
                            <td data-label="Print Status">
                                <span class="pq-badge pq-badge--<?= htmlspecialchars($badgeClass) ?>">
                                    <?= htmlspecialchars($badgeLabel) ?>
                                </span>
                            </td>
                            <td class="text-end" data-label="Action">
                                <button
                                    type="button"
                                    class="pq-btn pq-btn-print"
                                    data-patient-db-id="<?= (int) $patient['patient_db_id'] ?>"
                                    data-patient-id="<?= htmlspecialchars($patient['patient_id']) ?>"
                                    data-patient-name="<?= htmlspecialchars($name) ?>"
                                    data-consultation-id="<?= $cid ?>"
                                    data-status="<?= htmlspecialchars($badgeClass) ?>"
                                >
                                    <i class="bi bi-printer"></i>
                                    <span class="pq-btn-label"><?= htmlspecialchars($actionLabel) ?></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pq-foot">
                <span id="pqShowing">Showing <?= count($patients) ?> of <?= count($patients) ?></span>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Print Forms modal -->
<div class="modal fade" id="pqPrintModal" tabindex="-1" aria-labelledby="pqPrintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pq-print-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="pqPrintModalLabel">Print Forms</h5>
                    <div class="pq-modal-patient text-muted small" id="pqModalPatient"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pq-form-select-toolbar">
                    <button type="button" class="btn btn-sm btn-link px-0" id="pqSelectAll">Select all</button>
                    <span class="text-muted">·</span>
                    <button type="button" class="btn btn-sm btn-link px-0" id="pqSelectNone">Clear</button>
                </div>
                <ul class="pq-doc-checklist list-unstyled mb-0" id="pqFormChecklist">
                    <?php foreach ($officialDocuments as $doc): ?>
                        <li>
                            <label class="pq-doc-check">
                                <input type="checkbox" name="print_documents[]" value="<?= htmlspecialchars($doc['id']) ?>" checked>
                                <span class="pq-doc-check-body">
                                    <span class="pq-doc-check-label"><?= htmlspecialchars($doc['label']) ?></span>
                                    <span class="pq-doc-check-hint"><?= htmlspecialchars($doc['hint']) ?></span>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="text-muted small mt-3 mb-0">
                    Forms print for the <strong>selected consultation date</strong> only.
                    One form per print dialog. After each job, click <strong>Print Next</strong>.
                    Allow pop-ups. Turn off “Fit to page”.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="pqPrintSelected">
                    <i class="bi bi-printer"></i> Print Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview iframe modal -->
<div class="modal fade" id="pqPreviewModal" tabindex="-1" aria-labelledby="pqPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="pqPreviewModalLabel">Print Preview</h5>
                    <div class="text-muted small" id="pqPrintProgress"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="min-height:70vh;">
                <iframe id="pqPreviewFrame" title="Print preview" style="width:100%;height:70vh;border:0;"></iframe>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" id="pqDoPrint">
                    <i class="bi bi-printer"></i> Print This Form
                </button>
                <button type="button" class="btn btn-primary d-none" id="pqPrintNext">
                    <i class="bi bi-arrow-right-circle"></i> Print Next
                </button>
                <button type="button" class="btn btn-success d-none" id="pqMarkPrinted">
                    <i class="bi bi-check2-circle"></i> Mark as Printed
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.RHU_PRINT = {
    baseUrl: <?= json_encode(BASE_URL) ?>,
    documents: <?= json_encode($officialDocuments, JSON_UNESCAPED_UNICODE) ?>,
    readyCount: <?= (int) $readyCount ?>,
    documentCount: <?= (int) $docCount ?>,
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
