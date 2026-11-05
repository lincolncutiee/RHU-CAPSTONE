<?php

require_once __DIR__ . '/Permissions.php';
require_once __DIR__ . '/FormTemplate.php';

class FormRenderer
{
    private static string $renderPageId = '';
    public static function getFlatPages(): array
    {
        $config = require __DIR__ . '/../config/form-wizard.php';
        $flat = [];

        // Flat pages list (official RHU paper form order)
        if (!empty($config['pages'])) {
            foreach ($config['pages'] as $index => $page) {
                $flat[] = array_merge($page, [
                    'page_number' => $index + 1,
                    'form_title'  => $page['title'],
                    'form_icon'   => $page['icon'] ?? 'bi-file-earmark',
                ]);
            }
            return $flat;
        }

        // Legacy grouped forms support
        foreach ($config['forms'] ?? [] as $form) {
            foreach ($form['pages'] as $page) {
                $flat[] = array_merge($page, [
                    'form_id'    => $form['id'],
                    'form_title' => $form['title'],
                    'form_icon'  => $form['icon'],
                ]);
            }
        }

        return $flat;
    }

    public static function getPageIndex(string $pageId): int
    {
        foreach (self::getFlatPages() as $i => $page) {
            if ($page['id'] === $pageId) {
                return $i;
            }
        }
        return 0;
    }

    public static function isFieldReadonly(string $role, string $fieldGroup, array $field = [], bool $lockRecords = false): bool
    {
        if (!empty($field['readonly'])) {
            return true;
        }
        $role = Permissions::normalizeRole($role);
        $group = Permissions::normalizeGroup($field['field_group'] ?? $fieldGroup);
        if ($lockRecords && in_array($group, ['records_enrollment', 'records_consent', 'records_treatment', 'it'], true)) {
            return true;
        }
        return Permissions::isReadonly($role, $group);
    }

    public static function renderPage(array $page, string $role, bool $lockRecords = false): string
    {
        require_once __DIR__ . '/PaperFormRenderer.php';

        $pageId = $page['id'] ?? '';
        self::$renderPageId = $pageId;
        $paperPath = __DIR__ . '/../forms/paper/' . $pageId . '.php';
        if (file_exists($paperPath) && empty($page['is_review'])) {
            ob_start();
            ?>
            <div class="wizard-page-shell" data-page-id="<?= htmlspecialchars($pageId) ?>">
                <?= PaperFormRenderer::renderPage($pageId, $role, $lockRecords) ?>
            </div>
            <?php
            return ob_get_clean();
        }

        $pageGroup = $page['field_group'] ?? 'records_enrollment';
        $pageNumber = $page['page_number'] ?? '';
        $sections = $page['sections'] ?? null;

        // Legacy: pages with flat fields array
        if ($sections === null && !empty($page['fields'])) {
            $sections = [['title' => $page['title'], 'fields' => $page['fields']]];
        }

        $sections = $sections ?? [];
        $isReview = !empty($page['is_review']);

        ob_start();
        ?>
        <div class="wizard-page-shell" data-page-id="<?= htmlspecialchars($page['id']) ?>">
            <div class="wizard-page-header-bar">
                <div>
                    <?php if ($pageNumber): ?>
                        <span class="wizard-page-number-label">Page <?= (int) $pageNumber ?></span>
                    <?php endif; ?>
                    <h3 class="wizard-page-title"><?= htmlspecialchars($page['title']) ?></h3>
                    <?php if (!empty($page['subtitle'])): ?>
                        <p class="wizard-page-subtitle"><?= htmlspecialchars($page['subtitle']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isReview): ?>
                <div class="document-review-center" id="documentReviewCenter">
                    <div class="drc-viewer-shell">
                        <div class="drc-preview-toolbar no-print" role="toolbar" aria-label="Document preview controls">
                            <h4 class="drc-preview-title" id="drcPreviewTitle">Document Preview</h4>
                            <div class="drc-preview-tools">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="drcZoomOut" title="Zoom out">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <span class="drc-zoom-label" id="drcZoomLabel">100%</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="drcZoomIn" title="Zoom in">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="drcFitWidth" title="Reset to full working width">
                                    Fit Width
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="drcFullPreview" title="Preview Final Print">
                                    <i class="bi bi-eye"></i> Preview Final Print
                                </button>
                            </div>
                        </div>

                        <div class="drc-document-scroll" id="drcDocumentScroll">
                            <div class="drc-document-scale" id="drcDocumentScale">
                                <div class="drc-document-stack" id="drcDocumentStack">
                                    <p class="text-muted small mb-0 p-3">Select a document from the sidebar to review…</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($sections as $section):
                    $sectionGroup = $section['field_group'] ?? $pageGroup;
                    $sectionReadonly = self::isFieldReadonly($role, $sectionGroup, [], $lockRecords);
                    $sectionClass = $sectionReadonly
                        ? 'form-section section-' . $sectionGroup . ' section-readonly'
                        : Permissions::sectionClass($role, $sectionGroup);
                ?>
                    <div class="wizard-section-card <?= $sectionClass ?>" data-field-group="<?= htmlspecialchars($sectionGroup) ?>">
                        <div class="wizard-section-header">
                            <h4 class="wizard-section-title"><?= htmlspecialchars($section['title']) ?></h4>
                            <?php if (!$sectionReadonly): ?>
                                <span class="section-badge editable">Editable</span>
                            <?php else: ?>
                                <span class="section-badge readonly">Read-only</span>
                            <?php endif; ?>
                        </div>
                        <div class="wizard-section-body">
                            <div class="row g-3">
                                <?php foreach ($section['fields'] as $field): ?>
                                    <?= self::renderField($field, $sectionGroup, $role, $lockRecords) ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function renderField(array $field, string $pageGroup, string $role, bool $lockRecords = false): string
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'consent_notice') {
            return '<div class="col-12"><div class="consent-text-box"><p class="small text-muted mb-0">The patient acknowledges that the information provided is true and correct, and grants permission to the CHU/RHU to encode their data into the iClinicSys. Medical results may be used for treatment and public health programs. The patient may refuse consent or withdraw it at any time.</p></div></div>';
        }

        if ($type === 'section_divider') {
            return '<div class="col-12"><div class="wizard-section-divider"><i class="bi bi-arrow-left-right"></i> ' . htmlspecialchars($field['label']) . '</div></div>';
        }

        if ($type === 'checkbox_group') {
            return self::renderCheckboxGroup($field, $pageGroup, $role, $lockRecords);
        }

        $fg = $field['field_group'] ?? $pageGroup;
        $ro = self::isFieldReadonly($role, $fg, $field, $lockRecords);
        $dis = $ro ? 'disabled' : '';
        $col = $field['col'] ?? 6;
        $name = $field['name'];
        $isExtended = !empty($field['extended']);
        $inputName = $isExtended ? "extended[{$name}]" : $name;
        $req = !empty($field['required']) && !$ro ? 'required' : '';
        $fgAttr = 'data-field-group="' . htmlspecialchars($fg) . '"';
        $extAttr = $isExtended ? ' data-extended="1"' : '';

        ob_start();
        ?>
        <div class="col-md-<?= (int) $col ?>">
            <label class="form-label">
                <?= htmlspecialchars(FormTemplate::fieldLabel((string) ($field['label'] ?? ''), $name, self::$renderPageId ?: null)) ?>
                <?php if (!empty($field['required'])): ?>
                    <span class="text-danger">*</span>
                <?php else: ?>
                    <span class="text-muted fw-normal">(optional)</span>
                <?php endif; ?>
            </label>
            <?php if ($type === 'textarea'): ?>
                <textarea name="<?= htmlspecialchars($inputName) ?>" rows="<?= (int) ($field['rows'] ?? 3) ?>"
                    class="form-control<?= $ro ? ' field-readonly' : '' ?>"
                    placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                    <?= $fgAttr ?><?= $extAttr ?> <?= $dis ?> <?= $req ?>></textarea>
            <?php elseif ($type === 'select'): ?>
                <select name="<?= htmlspecialchars($inputName) ?>" class="form-select<?= $ro ? ' field-readonly' : '' ?>" <?= $fgAttr ?><?= $extAttr ?> <?= $dis ?> <?= $req ?>>
                    <?php foreach ($field['options'] as $val => $label): ?>
                        <option value="<?= htmlspecialchars((string) $val) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="<?= htmlspecialchars($type) ?>"
                    name="<?= htmlspecialchars($inputName) ?>"
                    class="form-control<?= $ro ? ' field-readonly' : '' ?>"
                    placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                    <?= isset($field['step']) ? 'step="' . htmlspecialchars($field['step']) . '"' : '' ?>
                    <?= $fgAttr ?><?= $extAttr ?> <?= $dis ?> <?= $req ?>>
            <?php endif; ?>
            <?php if (!empty($field['hint'])): ?>
                <div class="form-text"><?= htmlspecialchars($field['hint']) ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function renderCheckboxGroup(array $field, string $pageGroup, string $role, bool $lockRecords = false): string
    {
        $fg = $field['field_group'] ?? $pageGroup;
        $ro = self::isFieldReadonly($role, $fg, $field, $lockRecords);
        $dis = $ro ? 'disabled' : '';
        $col = $field['col'] ?? 12;

        ob_start();
        ?>
        <div class="col-md-<?= (int) $col ?>">
            <label class="form-label">
                <?= htmlspecialchars($field['label']) ?>
                <?php if (!empty($field['required'])): ?>
                    <span class="text-danger">*</span>
                <?php else: ?>
                    <span class="text-muted fw-normal">(optional)</span>
                <?php endif; ?>
            </label>
            <div class="checkbox-grid">
                <?php foreach ($field['options'] as $opt): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                            name="<?= htmlspecialchars($field['name']) ?>[]"
                            value="<?= htmlspecialchars($opt) ?>"
                            id="cb_<?= htmlspecialchars($field['name'] . '_' . preg_replace('/\s+/', '_', $opt)) ?>"
                            data-field-group="<?= htmlspecialchars($fg) ?>" <?= $dis ?>>
                        <label class="form-check-label" for="cb_<?= htmlspecialchars($field['name'] . '_' . preg_replace('/\s+/', '_', $opt)) ?>">
                            <?= htmlspecialchars($opt) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
