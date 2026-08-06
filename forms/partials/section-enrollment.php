<?php
/** Form 1 — Section I: Patient Information */
$fg = 'records_enrollment';
$ro = Permissions::isReadonly($role, $fg);
$dis = $ro ? 'disabled' : '';
$secClass = Permissions::sectionClass($role, $fg);
?>
<div class="<?= $secClass ?>" data-section="<?= $fg ?>">
    <div class="section-header">
        <h3>Section I — Patient Information</h3>
        <?php if (!$ro): ?><span class="section-badge editable">Editable</span><?php else: ?><span class="section-badge readonly">Read-only</span><?php endif; ?>
    </div>
    <div class="section-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Family Serial Number</label>
                <input type="text" class="form-control" name="family_serial_number" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label">Facility Code</label>
                <input type="text" class="form-control" name="facility_code" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label">Patient ID</label>
                <input type="text" class="form-control field-readonly" name="patient_id" readonly placeholder="Auto-generated on save">
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="last_name" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-3">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="first_name" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="middle_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-1">
                <label class="form-label">Suffix</label>
                <input type="text" class="form-control" name="suffix" data-field-group="<?= $fg ?>" placeholder="Jr." <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Maiden Name</label>
                <input type="text" class="form-control" name="maiden_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label">Sex <span class="text-danger">*</span></label>
                <select class="form-select" name="sex" data-field-group="<?= $fg ?>" <?= $dis ?> required>
                    <option value="">Select</option>
                    <option value="Female">Female</option>
                    <option value="Male">Male</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Birth Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="birth_date" data-field-group="<?= $fg ?>" <?= $dis ?> required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Birthplace</label>
                <input type="text" class="form-control" name="birthplace" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Blood Type</label>
                <input type="text" class="form-control" name="blood_type" data-field-group="<?= $fg ?>" placeholder="e.g. O+" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mother's Name</label>
                <input type="text" class="form-control" name="mothers_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label">Residential Address</label>
                <textarea class="form-control" name="residential_address" rows="2" data-field-group="<?= $fg ?>" <?= $dis ?>></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Civil Status</label>
                <select class="form-select" name="civil_status" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">Select</option>
                    <?php foreach (['Single','Married','Widower','Widow','Separated','Annulled','Co-Habitation'] as $cs): ?>
                        <option value="<?= $cs ?>"><?= $cs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control" name="contact_number" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label">DSWD NHTS?</label>
                <select class="form-select" name="dswd_nhts" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Facility Household No.</label>
                <input type="text" class="form-control" name="facility_household_no" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">4Ps Member?</label>
                <select class="form-select" name="four_ps_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Household No.</label>
                <input type="text" class="form-control" name="household_no" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">PhilHealth Member?</label>
                <select class="form-select" name="philhealth_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">PCB Member?</label>
                <select class="form-select" name="pcb_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
                </select>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-2">
                <label class="form-label">PhilHealth Status</label>
                <select class="form-select" name="philhealth_status_type" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option><option value="Member">Member</option><option value="Dependent">Dependent</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">PhilHealth No.</label>
                <input type="text" class="form-control" name="philhealth_no" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">PhilHealth Category</label>
                <select class="form-select" name="philhealth_category" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <option value="FE-Private">FE — Private</option>
                    <option value="FE-Government">FE — Government</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Spouse's Name</label>
                <input type="text" class="form-control" name="spouse_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label class="form-label">Educational Attainment</label>
                <select class="form-select" name="educational_attainment" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <?php foreach (['No Formal Education','Elementary','Highschool','High School','Vocational','Post Graduate'] as $ea): ?>
                        <option value="<?= $ea ?>"><?= $ea ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment Status</label>
                <select class="form-select" name="employment_status" data-field-group="<?= $fg ?>" <?= $dis ?>>
                    <option value="">—</option>
                    <?php foreach (['Student','Employed','Unemployed','Unknown','Retired','Others'] as $es): ?>
                        <option value="<?= $es ?>"><?= $es ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Specific Occupation</label>
                <input type="text" class="form-control" name="specific_occupation" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment Type</label>
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
                <label class="form-label">Father's Name</label>
                <input type="text" class="form-control" name="father_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mother's Name (Family)</label>
                <input type="text" class="form-control" name="mother_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Son</label>
                <input type="text" class="form-control" name="son_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Daughter</label>
                <input type="text" class="form-control" name="daughter_name" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
            <div class="col-md-2">
                <label class="form-label">Others</label>
                <input type="text" class="form-control" name="other_family_member" data-field-group="<?= $fg ?>" <?= $dis ?>>
            </div>
        </div>
    </div>
</div>
