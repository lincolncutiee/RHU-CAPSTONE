<?php
/** Form 1 — Section II: Patient's Consent */
$fg = 'records_consent';
$ro = Permissions::isReadonly($role, $fg);
$dis = $ro ? 'disabled' : '';
$secClass = Permissions::sectionClass($role, $fg);
?>
<div class="<?= $secClass ?>" data-section="<?= $fg ?>">
    <div class="section-header">
        <h3>Section II — Patient's Consent</h3>
        <?php if (!$ro): ?><span class="section-badge editable">Editable</span><?php else: ?><span class="section-badge readonly">Read-only</span><?php endif; ?>
    </div>
    <div class="section-body">
        <div class="consent-text-box">
            <p class="small text-muted mb-3">
                The patient acknowledges that the information provided is true and correct, and grants permission
                to the CHU/RHU to encode their data into the iClinicSys. Medical results may be used for treatment
                and public health programs. The patient may refuse consent or withdraw it at any time.
            </p>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Signature of Patient / Date</label>
                <input type="date" class="form-control" name="consent_signature_date" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-8">
                <label class="form-label">Name of CHU/RHU Representative</label>
                <input type="text" class="form-control" name="chu_representative" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>
    </div>
</div>
