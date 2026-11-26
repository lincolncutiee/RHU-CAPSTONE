<?php
/** Clean guided Enrollment — all paper PER fields */
/** @var string $role */
/** @var bool $lockRecords */
require_once __DIR__ . '/../../core/MagallanesAddress.php';
$fg = 'records_enrollment';
$fgConsent = 'records_consent';
$P = PaperFormRenderer::class;
$barangaySelect = $P::select('address_barangay', MagallanesAddress::barangayOptions(), $role, $fg, $lockRecords, true, 'Magallanes', 'clean-input', true);
$muniVal = htmlspecialchars(MagallanesAddress::municipality(), ENT_QUOTES, 'UTF-8');
$provVal = htmlspecialchars(MagallanesAddress::province(), ENT_QUOTES, 'UTF-8');
$eduOpts = ['No Formal Education', 'Elementary', 'Highschool', 'High School', 'Vocational', 'Post Graduate'];
$empOpts = ['Student', 'Employed', 'Unemployed', 'Unknown', 'Retired', 'Others'];
?>
<div class="clean-guided" data-page-id="enrollment" data-stepped="1">
  <div class="clean-step-rail" aria-label="Enrollment sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Patient info</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · PhilHealth / work</button>
    <button type="button" class="clean-step-dot" data-goto="2">3 · Family</button>
    <button type="button" class="clean-step-dot" data-goto="3">4 · Consent</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Patient enrollment</h2>
    <p class="clean-step-help">Same enrollment fields — cleaner layout.</p>

    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Patient ID</span><?= $P::line('patient_id', $role, $fg, $lockRecords, false, 'text', 'clean-input', null, false, true) ?></label>
      <label class="clean-field"><span>Family serial no.</span><?= $P::line('family_serial_number', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Facility code</span><?= $P::line('facility_code', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>

    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Last name</span><?= $P::line('last_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>First name</span><?= $P::line('first_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Middle name</span><?= $P::line('middle_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Suffix</span><?= $P::line('suffix', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Maiden name</span><?= $P::line('maiden_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <div class="clean-field">
        <span>Sex</span>
        <div class="clean-chips">
          <?= $P::radio('sex', 'Female', 'Female', $role, $fg, $lockRecords) ?>
          <?= $P::radio('sex', 'Male', 'Male', $role, $fg, $lockRecords) ?>
        </div>
      </div>
    </div>

    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Birth date</span><?= $P::line('birth_date', $role, $fg, $lockRecords, false, 'date', 'clean-input') ?></label>
      <label class="clean-field"><span>Birthplace</span><?= $P::line('birthplace', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Blood type</span><?= $P::line('blood_type', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>

    <div class="clean-grid clean-grid-3" data-magallanes-address>
      <label class="clean-field"><span>Barangay / Town</span><?= $barangaySelect ?></label>
      <label class="clean-field"><span>Municipality / City</span>
        <input type="text" name="extended[address_municipality]" class="pf-line clean-input" value="<?= $muniVal ?>" readonly data-perm-editable="0" data-magallanes-fixed="1" data-extended="1">
      </label>
      <label class="clean-field"><span>Province</span>
        <input type="text" name="extended[address_province]" class="pf-line clean-input" value="<?= $provVal ?>" readonly data-perm-editable="0" data-magallanes-fixed="1" data-extended="1">
      </label>
      <textarea name="extended[address_street]" hidden data-extended="1" data-magallanes-composed="1" readonly></textarea>
    </div>

    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Contact number</span><?= $P::line('contact_number', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Civil status</span>
        <?= $P::select('civil_status', [
          '' => '—', 'Single' => 'Single', 'Married' => 'Married', 'Widower' => 'Widower',
          'Widow' => 'Widow', 'Separated' => 'Separated', 'Annulled' => 'Annulled', 'Co-Habitation' => 'Co-Habitation',
        ], $role, $fg, $lockRecords, false, null, 'clean-input') ?>
      </label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Mother's name</span><?= $P::line('mothers_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Spouse's name</span><?= $P::line('spouse_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>

    <div class="clean-grid clean-grid-2">
      <div class="clean-field">
        <span>DSWD NHTS?</span>
        <div class="clean-chips">
          <?= $P::radio('dswd_nhts', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
          <?= $P::radio('dswd_nhts', 'No', 'No', $role, $fg, $lockRecords) ?>
        </div>
      </div>
      <label class="clean-field"><span>Facility household no.</span><?= $P::line('facility_household_no', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <div class="clean-field">
        <span>4Ps member?</span>
        <div class="clean-chips">
          <?= $P::radio('four_ps_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
          <?= $P::radio('four_ps_member', 'No', 'No', $role, $fg, $lockRecords) ?>
        </div>
      </div>
      <label class="clean-field"><span>Household no.</span><?= $P::line('household_no', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>

    <div class="clean-field">
      <span>Educational attainment</span>
      <div class="clean-chips">
        <?php foreach ($eduOpts as $opt): ?>
          <?= $P::radio('educational_attainment', $opt, $opt, $role, $fg, $lockRecords) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">PhilHealth &amp; employment</h2>
    <div class="clean-field">
      <span>PhilHealth member?</span>
      <div class="clean-chips">
        <?= $P::radio('philhealth_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
        <?= $P::radio('philhealth_member', 'No', 'No', $role, $fg, $lockRecords) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>Status type</span>
      <div class="clean-chips">
        <?= $P::radio('philhealth_status_type', 'Member', 'Member', $role, $fg, $lockRecords) ?>
        <?= $P::radio('philhealth_status_type', 'Dependent', 'Dependent', $role, $fg, $lockRecords) ?>
      </div>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>PhilHealth no.</span><?= $P::line('philhealth_no', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>FE — Private</span><?= $P::line('philhealth_fe_private', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>FE — Government</span><?= $P::line('philhealth_fe_government', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>

    <div class="clean-field">
      <span>Employment status</span>
      <div class="clean-chips">
        <?php foreach ($empOpts as $opt): ?>
          <?= $P::radio('employment_status', $opt, $opt, $role, $fg, $lockRecords) ?>
        <?php endforeach; ?>
      </div>
    </div>
    <label class="clean-field"><span>Specific occupation</span><?= $P::line('specific_occupation', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    <div class="clean-field">
      <span>Employment type</span>
      <div class="clean-chips">
        <?= $P::radio('employment_type', 'CONTRACTUAL', 'CONTRACTUAL', $role, $fg, $lockRecords) ?>
        <?= $P::radio('employment_type', 'CASUAL', 'CASUAL', $role, $fg, $lockRecords) ?>
        <?= $P::radio('employment_type', 'PERMANENT', 'PERMANENT', $role, $fg, $lockRecords) ?>
      </div>
    </div>
  </div>

  <div class="clean-step" data-clean-step="2" hidden>
    <h2 class="clean-step-title">Family members</h2>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Father</span><?= $P::line('father_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Mother</span><?= $P::line('mother_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Son</span><?= $P::line('son_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Daughter</span><?= $P::line('daughter_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Other family member</span><?= $P::line('other_family_member', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    <div class="clean-field">
      <span>PCB member?</span>
      <div class="clean-chips">
        <?= $P::radio('pcb_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
        <?= $P::radio('pcb_member', 'No', 'No', $role, $fg, $lockRecords) ?>
      </div>
    </div>
  </div>

  <div class="clean-step" data-clean-step="3" hidden>
    <h2 class="clean-step-title">Patient's consent</h2>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Consent signature</span><?= $P::line('consent_signature', $role, $fgConsent, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Consent date</span><?= $P::line('consent_signature_date', $role, $fgConsent, $lockRecords, false, 'date', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>CHU / RHU representative</span><?= $P::line('chu_representative', $role, $fgConsent, $lockRecords, false, 'text', 'clean-input') ?></label>
  </div>
</div>
