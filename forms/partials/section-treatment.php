<?php
/** Form 2 — Section II: Records Office fields */
$fg = 'records_treatment';
$ro = Permissions::isReadonly($role, $fg);
$dis = $ro ? 'disabled' : '';
$secClass = Permissions::sectionClass($role, $fg);

$consultationTypes = [
    'General', 'Family Planning', 'Prenatal', 'Postpartum', 'Dental Care',
    'Tuberculosis', 'Child Care', 'Immunization', 'Child Nutrition',
    'Sick Children', 'Injury', 'Firecracker Injury', 'Adult Immunization',
];
?>
<div class="<?= $secClass ?>" data-section="<?= $fg ?>">
    <div class="section-header">
        <h3>Section II — Treatment Record</h3>
        <?php if (!$ro): ?><span class="section-badge editable">Editable</span><?php else: ?><span class="section-badge readonly">Read-only</span><?php endif; ?>
    </div>
    <div class="section-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Mode of Transaction</label>
                <select class="form-select" name="mode_of_transaction" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <option value="Walk-in">Walk-in</option>
                    <option value="Visited">Visited</option>
                    <option value="Referral">Referral</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of Consultation</label>
                <input type="date" class="form-control" name="date_of_consultation" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Consultation Time</label>
                <input type="time" class="form-control" name="consultation_time" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">AM / PM</label>
                <select class="form-select" name="consultation_period" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Nature of Visit</label>
                <select class="form-select" name="nature_of_visit" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <option value="New Consultation/Case">New Consultation/Case</option>
                    <option value="New Admission">New Admission</option>
                    <option value="Follow-up Visit">Follow-up Visit</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label">Blood Pressure</label>
                <input type="text" class="form-control" name="blood_pressure" data-field-group="<?= $fg ?>" placeholder="120/80" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Temperature</label>
                <input type="text" class="form-control" name="temperature" data-field-group="<?= $fg ?>" placeholder="36.5" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Height (cm)</label>
                <input type="number" step="0.1" class="form-control" name="height_cm" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Weight (kg)</label>
                <input type="number" step="0.1" class="form-control" name="weight_kg" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label">Name of Attending Provider</label>
                <input type="text" class="form-control" name="attending_provider" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-12">
                <label class="form-label">Type of Consultation / Purpose of Visit</label>
                <div class="checkbox-grid">
                    <?php foreach ($consultationTypes as $ct): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consultation_types[]"
                                   value="<?= htmlspecialchars($ct) ?>" id="ct_<?= preg_replace('/\s+/', '_', $ct) ?>"
                                   data-field-group="<?= $fg ?>" <?= $dis ?>>
                            <label class="form-check-label" for="ct_<?= preg_replace('/\s+/', '_', $ct) ?>"><?= $ct ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-12">
                <label class="form-label">Chief Complaints</label>
                <textarea class="form-control" name="chief_complaints" rows="3" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
        </div>

        <div class="referral-box mt-3">
            <h6 class="text-muted mb-3"><i class="bi bi-arrow-left-right"></i> For REFERRAL Transaction only</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Referred From</label>
                    <input type="text" class="form-control" name="referred_from" data-field-group="<?= $fg ?>" <?= $dis ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Referred To</label>
                    <input type="text" class="form-control" name="referred_to" data-field-group="<?= $fg ?>" <?= $dis ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Referred By</label>
                    <input type="text" class="form-control" name="referred_by" data-field-group="<?= $fg ?>" <?= $dis ?>>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Reason(s) for Referral</label>
                    <textarea class="form-control" name="referral_reasons" rows="2" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
