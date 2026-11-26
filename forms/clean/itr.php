<?php
/** Clean guided ITR — all paper ITR fields; mockup layout */
/** @var string $role */
/** @var bool $lockRecords */
$fgEnroll = 'records_enrollment';
$fgTreat = 'records_treatment';
$fgDoctor = 'doctor';
$P = PaperFormRenderer::class;
$consultTypes = [
    'General', 'Family Planning', 'Prenatal', 'Postpartum', 'Dental Care',
    'Tuberculosis', 'Child Care', 'Immunization', 'Child Nutrition',
    'Sick Children', 'Injury', 'Firecracker Injury', 'Adult Immunization',
];
?>
<div class="clean-guided" data-page-id="itr" data-stepped="1">
  <div class="clean-step-rail" aria-label="ITR sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Consultation</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · Vitals</button>
    <button type="button" class="clean-step-dot" data-goto="2">3 · Doctor</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Patient &amp; consultation</h2>
    <p class="clean-step-help">Same ITR fields — cleaner encoding; printed ITR stays official.</p>

    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Date of consultation</span><?= $P::line('date_of_consultation', $role, $fgTreat, $lockRecords, false, 'date', 'clean-input') ?></label>
      <label class="clean-field"><span>Mode of transaction</span><?= $P::select('mode_of_transaction', ['' => 'Select…', 'Walk-in' => 'Walk-in', 'Visited' => 'Visited', 'Referral' => 'Referral'], $role, $fgTreat, $lockRecords, false, 'Select…', 'clean-input') ?></label>
    </div>

    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Last name</span><?= $P::line('last_name', $role, $fgEnroll, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>First name</span><?= $P::line('first_name', $role, $fgEnroll, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Middle name</span><?= $P::line('middle_name', $role, $fgEnroll, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Suffix</span><?= $P::line('suffix', $role, $fgEnroll, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Age</span><?= $P::line('itr_age', $role, $fgTreat, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Consultation time</span><?= $P::line('consultation_time', $role, $fgTreat, $lockRecords, false, 'time', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>AM / PM</span><?= $P::select('consultation_period', ['' => '—', 'AM' => 'AM', 'PM' => 'PM'], $role, $fgTreat, $lockRecords, false, null, 'clean-input') ?></label>
      <div class="clean-field">
        <span>Nature of visit</span>
        <div class="clean-chips">
          <?= $P::radio('nature_of_visit', 'New Consultation/Case', 'New Consultation/Case', $role, $fgTreat, $lockRecords) ?>
          <?= $P::radio('nature_of_visit', 'New Admission', 'New Admission', $role, $fgTreat, $lockRecords) ?>
          <?= $P::radio('nature_of_visit', 'Follow-up Visit', 'Follow-up Visit', $role, $fgTreat, $lockRecords) ?>
        </div>
      </div>
    </div>

    <label class="clean-field"><span>Chief complaints</span><?= $P::area('chief_complaints', $role, $fgTreat, $lockRecords, false, 3, 'Main reason for visit…') ?></label>

    <div class="clean-field">
      <span>Consultation types</span>
      <div class="clean-type-pills">
        <?php foreach ($consultTypes as $opt): ?>
          <label class="pf-check">
            <input type="checkbox" name="consultation_types[]" value="<?= htmlspecialchars($opt) ?>"
              <?= $P::fg($fgTreat) ?> <?= $P::dis($role, $fgTreat, $lockRecords) ?>>
            <span><?= htmlspecialchars($opt) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">Vitals &amp; referral</h2>
    <p class="clean-step-help">Vitals plus referral block from paper ITR.</p>
    <div class="clean-grid clean-grid-4">
      <label class="clean-field"><span>Blood pressure</span><?= $P::line('blood_pressure', $role, $fgTreat, $lockRecords, false, 'text', 'clean-input', '120/80') ?></label>
      <label class="clean-field"><span>Temperature</span><?= $P::line('temperature', $role, $fgTreat, $lockRecords, false, 'text', 'clean-input', '36.5') ?></label>
      <label class="clean-field"><span>Height (cm)</span><?= $P::line('height_cm', $role, $fgTreat, $lockRecords, false, 'number', 'clean-input') ?></label>
      <label class="clean-field"><span>Weight (kg)</span><?= $P::line('weight_kg', $role, $fgTreat, $lockRecords, false, 'number', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Name of attending provider</span><?= $P::line('attending_provider', $role, $fgTreat, $lockRecords, false, 'text', 'clean-input') ?></label>

    <hr class="clean-divider">
    <p class="clean-more-label">Referral transaction</p>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Referred from</span><?= $P::line('referred_from', $role, $fgTreat, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Referred from (line 2)</span><?= $P::line('referred_from_2', $role, $fgTreat, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Referred to</span><?= $P::line('referred_to', $role, $fgTreat, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Referred to (line 2)</span><?= $P::line('referred_to_2', $role, $fgTreat, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Reason(s) for referral</span><?= $P::area('referral_reasons', $role, $fgTreat, $lockRecords, false, 3) ?></label>
    <label class="clean-field"><span>Referred by</span><?= $P::line('referred_by', $role, $fgTreat, $lockRecords, false, 'text', 'clean-input') ?></label>
  </div>

  <div class="clean-step" data-clean-step="2" hidden>
    <h2 class="clean-step-title">Doctor assessment</h2>
    <p class="clean-step-help">Clinical findings — same ITR doctor fields.</p>
    <label class="clean-field"><span>Diagnosis</span><?= $P::area('diagnosis', $role, $fgDoctor, $lockRecords, false, 3) ?></label>
    <label class="clean-field"><span>Medication / treatment</span><?= $P::area('medication_treatment', $role, $fgDoctor, $lockRecords, false, 3) ?></label>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Lab findings / impression</span><?= $P::area('laboratory_findings', $role, $fgDoctor, $lockRecords, false, 3) ?></label>
      <label class="clean-field"><span>Performed lab test</span><?= $P::area('performed_lab_test', $role, $fgDoctor, $lockRecords, false, 3) ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Treatment plan</span><?= $P::area('doctor_treatment_plan', $role, $fgDoctor, $lockRecords, true, 3) ?></label>
      <label class="clean-field"><span>Follow-up instructions</span><?= $P::area('doctor_follow_up', $role, $fgDoctor, $lockRecords, true, 3) ?></label>
    </div>
    <label class="clean-field"><span>Health care provider</span><?= $P::area('healthcare_provider', $role, $fgDoctor, $lockRecords, false, 2) ?></label>
  </div>
</div>
