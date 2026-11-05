<?php
/** Form 1 — Section I: Patient Information */
$fg = 'records_enrollment';
$ro = Permissions::isReadonly($role, $fg);
$dis = $ro ? 'disabled' : '';
$secClass = Permissions::sectionClass($role, $fg);
$enrollmentRequired = ['last_name', 'first_name', 'sex', 'birth_date', 'contact_number', 'family_serial_number'];
$fl = static function (string $text, string $field) use ($enrollmentRequired): string {
    $safe = htmlspecialchars($text);
    if (in_array($field, $enrollmentRequired, true)) {
        return $safe . ' <span class="text-danger">*</span>';
    }
    return $safe . ' <span class="text-muted fw-normal">(optional)</span>';
};
?>
<div class="<?= $secClass ?>" data-section="<?= $fg ?>">
    <div class="section-header">
        <h3>Section I — Patient Information</h3>
        <?php if (!$ro): ?><span class="section-badge editable">Editable</span><?php else: ?><span class="section-badge readonly">Read-only</span><?php endif; ?>
    </div>
    <div class="section-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label"><?= $fl('Family Serial Number', 'family_serial_number') ?></label>
                <input type="text" class="form-control" name="family_serial_number" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $fl('Facility Code', 'facility_code') ?></label>
                <input type="text" class="form-control" name="facility_code" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $fl('PIN', 'patient_id') ?></label>
                <input type="text" class="form-control field-readonly" name="patient_id" readonly placeholder="Auto-generated on save">
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Last Name', 'last_name') ?></label>
                <input type="text" class="form-control" name="last_name" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('First Name', 'first_name') ?></label>
                <input type="text" class="form-control" name="first_name" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Middle Name', 'middle_name') ?></label>
                <input type="text" class="form-control" name="middle_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-1">
                <label class="form-label"><?= $fl('Suffix', 'suffix') ?></label>
                <input type="text" class="form-control" name="suffix" data-field-group="<?= $fg ?>" placeholder="Jr." <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Maiden Name', 'maiden_name') ?></label>
                <input type="text" class="form-control" name="maiden_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Sex', 'sex') ?></label>
                <select class="form-select" name="sex" data-field-group="<?= $fg ?>" <?= $dis ?> required>
                    <option value="">Select</option>
                    <option value="Female">Female</option>
                    <option value="Male">Male</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Birth Date', 'birth_date') ?></label>
                <input type="date" class="form-control" name="birth_date" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Birthplace', 'birthplace') ?></label>
                <input type="text" class="form-control" name="birthplace" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Blood Type', 'blood_type') ?></label>
                <input type="text" class="form-control" name="blood_type" data-field-group="<?= $fg ?>" placeholder="e.g. O+" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Mother\'s Name', 'mothers_name') ?></label>
                <input type="text" class="form-control" name="mothers_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label"><?= $fl('Residential Address', 'address_barangay') ?></label>
                <textarea class="form-control" name="residential_address" rows="2" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Civil Status', 'civil_status') ?></label>
                <select class="form-select" name="civil_status" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">Select</option>
                    <?php foreach (['Single','Married','Widower','Widow','Separated','Annulled','Co-Habitation'] as $cs): ?>
                        <option value="<?= $cs ?>"><?= $cs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Contact Number', 'contact_number') ?></label>
                <input type="text" class="form-control" name="contact_number" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label"><?= $fl('DSWD NHTS?', 'dswd_nhts') ?></label>
                <select class="form-select" name="dswd_nhts" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Facility Household No.', 'facility_household_no') ?></label>
                <input type="text" class="form-control" name="facility_household_no" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('4Ps Member?', 'four_ps_member') ?></label>
                <select class="form-select" name="four_ps_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Household No.', 'household_no') ?></label>
                <input type="text" class="form-control" name="household_no" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('PhilHealth Member?', 'philhealth_member') ?></label>
                <select class="form-select" name="philhealth_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('PCB Member?', 'pcb_member') ?></label>
                <select class="form-select" name="pcb_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label"><?= $fl('PhilHealth Status', 'philhealth_status_type') ?></label>
                <select class="form-select" name="philhealth_status_type" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Member">Member</option><option value="Dependent">Dependent</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('PhilHealth No.', 'philhealth_no') ?></label>
                <input type="text" class="form-control" name="philhealth_no" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('PhilHealth Category', 'philhealth_category') ?></label>
                <select class="form-select" name="philhealth_category" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <option value="FE-Private">FE — Private</option>
                    <option value="FE-Government">FE — Government</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= $fl('Spouse\'s Name', 'spouse_name') ?></label>
                <input type="text" class="form-control" name="spouse_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Educational Attainment', 'educational_attainment') ?></label>
                <select class="form-select" name="educational_attainment" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <?php foreach (['No Formal Education','Elementary','Highschool','High School','Vocational','Post Graduate'] as $ea): ?>
                        <option value="<?= $ea ?>"><?= $ea ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Employment Status', 'employment_status') ?></label>
                <select class="form-select" name="employment_status" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <?php foreach (['Student','Employed','Unemployed','Unknown','Retired','Others'] as $es): ?>
                        <option value="<?= $es ?>"><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Specific Occupation', 'specific_occupation') ?></label>
                <input type="text" class="form-control" name="specific_occupation" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Employment Type', 'employment_type') ?></label>
                <select class="form-select" name="employment_type" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <option value="CONTRACTUAL">CONTRACTUAL</option>
                    <option value="CASUAL">CASUAL</option>
                    <option value="PERMANENT">PERMANENT</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Father\'s Name', 'father_name') ?></label>
                <input type="text" class="form-control" name="father_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $fl('Mother\'s Name (Family)', 'mother_name') ?></label>
                <input type="text" class="form-control" name="mother_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Son', 'son_name') ?></label>
                <input type="text" class="form-control" name="son_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Daughter', 'daughter_name') ?></label>
                <input type="text" class="form-control" name="daughter_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= $fl('Others', 'other_family_member') ?></label>
                <input type="text" class="form-control" name="other_family_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>
    </div>
</div>
