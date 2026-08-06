<?php
/** Form 2 — Doctor clinical findings */
$fg = 'doctor';
$ro = Permissions::isReadonly($role, $fg);
$dis = $ro ? 'disabled' : '';
$secClass = Permissions::sectionClass($role, $fg);
?>
<div class="<?= $secClass ?>" data-section="<?= $fg ?>">
    <div class="section-header">
        <h3>Clinical Findings — Doctor</h3>
        <?php if (!$ro): ?><span class="section-badge editable">Editable</span><?php else: ?><span class="section-badge readonly">Read-only</span><?php endif; ?>
    </div>
    <div class="section-body">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Diagnosis</label>
                <textarea class="form-control" name="diagnosis" rows="3" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
            <div class="col-md-8">
                <label class="form-label">Medication / Treatment</label>
                <textarea class="form-control" name="medication_treatment" rows="3" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Name of Health Care Provider</label>
                <input type="text" class="form-control" name="healthcare_provider" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-8">
                <label class="form-label">Laboratory Findings / Impression</label>
                <textarea class="form-control" name="laboratory_findings" rows="3" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Performed Laboratory Test</label>
                <textarea class="form-control" name="performed_lab_test" rows="3" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
        </div>
    </div>
</div>
