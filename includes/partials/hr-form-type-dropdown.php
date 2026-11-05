<?php
/**
 * Multi-select form type dropdown (checkboxes inside collapsed menu).
 *
 * @var string $hrListId
 * @var string $hrToggleId
 * @var string $hrInputName
 * @var array<string, string> $hrRecordTypes
 * @var bool $hrDefaultOldPatient
 */
$hrDefaultOldPatient = $hrDefaultOldPatient ?? false;
?>
<div class="hr-form-type-dropdown">
    <button type="button"
            class="hr-form-type-toggle form-select form-select-sm"
            id="<?= htmlspecialchars($hrToggleId) ?>"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-controls="<?= htmlspecialchars($hrListId) ?>">
        <span class="hr-form-type-toggle-text">Select form type(s)…</span>
        <i class="bi bi-chevron-down hr-form-type-toggle-icon" aria-hidden="true"></i>
    </button>
    <div class="hr-form-type-menu hr-form-type-list"
         id="<?= htmlspecialchars($hrListId) ?>"
         role="listbox"
         aria-multiselectable="true"
         hidden>
        <label class="hr-form-type-option hr-form-type-option--all">
            <input type="checkbox" class="hr-form-type-all" aria-label="Select all forms">
            <span class="hr-form-type-check" aria-hidden="true"></span>
            <span class="hr-form-type-label">All Forms</span>
        </label>
        <?php foreach ($hrRecordTypes as $key => $label): ?>
            <label class="hr-form-type-option">
                <input type="checkbox"
                       name="<?= htmlspecialchars($hrInputName) ?>"
                       value="<?= htmlspecialchars($key) ?>"
                       data-hr-record-type="1"<?= $hrDefaultOldPatient && $key === 'pcsf' ? ' checked' : '' ?>>
                <span class="hr-form-type-check" aria-hidden="true"></span>
                <span class="hr-form-type-label"><?= htmlspecialchars($label) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</div>
