<?php
/** Clean guided EPRESS — all paper fields */
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'doctor';
$P = PaperFormRenderer::class;
?>
<div class="clean-guided" data-page-id="epress" data-stepped="1">
  <div class="clean-step-rail" aria-label="EPRESS sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Prescription</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · Feedback</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Electronic Prescription Slip (EPRESS)</h2>
    <p class="clean-step-help">Same EPRESS fields — cleaner encoding; print stays official.</p>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>HCI name</span><?= $P::line('epress_hci_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Case no.</span><?= $P::line('epress_case_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Accreditation no.</span><?= $P::line('epress_accreditation_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Transaction no.</span><?= $P::line('epress_transaction_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>PIN</span><?= $P::line('philhealth_no', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Membership category</span><?= $P::line('philhealth_category', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-field">
      <span>Membership type</span>
      <div class="clean-chips">
        <?= $P::radio('philhealth_status_type', 'Member', 'Member', $role, $fg, $lockRecords) ?>
        <?= $P::radio('philhealth_status_type', 'Dependent', 'Dependent', $role, $fg, $lockRecords) ?>
      </div>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Patient name</span><?= $P::line('epress_patient_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Age</span><?= $P::line('itr_age', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Contact number</span><?= $P::line('contact_number', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Medicine / prescription (generic name)</span><?= $P::area('medication_treatment', $role, $fg, $lockRecords, false, 5) ?></label>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Physician</span><?= $P::line('epress_physician_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Next dispensing date</span><?= $P::line('epress_next_dispensing_date', $role, $fg, $lockRecords, true, 'date', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>PRC lic.</span><?= $P::line('epress_prc_lic', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>PTR no.</span><?= $P::line('epress_ptr_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>S2 no.</span><?= $P::line('epress_s2_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">Patient feedback</h2>
    <div class="clean-field">
      <span>Feedback received</span>
      <div class="clean-chips">
        <?= $P::radio('epress_feedback_received', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('epress_feedback_received', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>Satisfaction</span>
      <div class="clean-chips">
        <?= $P::radio('epress_satisfaction', 'Happy', 'Happy', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('epress_satisfaction', 'Neutral', 'Neutral', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('epress_satisfaction', 'Bad', 'Bad', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <label class="clean-field"><span>Comments</span><?= $P::area('epress_feedback_comments', $role, $fg, $lockRecords, true, 3) ?></label>
    <label class="clean-field"><span>Patient PhilHealth PIN (feedback)</span><?= $P::line('epress_feedback_pin', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
  </div>
</div>
