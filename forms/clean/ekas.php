<?php
/** Clean guided EKAS — all paper fields including services */
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'doctor';
$P = PaperFormRenderer::class;
$ekasServices = [
    'consultation' => 'Consultation',
    'cbc'          => 'Complete Blood Count (CBC) w/ platelet count',
    'lipid'        => 'Lipid profile',
    'fbs'          => 'Fasting Blood Sugar (FBS)',
    'ogtt'         => 'Oral Glucose Tolerance Test',
    'hba1c'        => 'Glycosylated Hemoglobin (HbA1c)',
    'creatinine'   => 'Creatinine',
    'chest_xray'   => 'Chest X-Ray',
    'sputum'       => 'Sputum Microscopy',
    'ecg'          => 'Electrocardiogram (ECG)',
    'urinalysis'   => 'Urinalysis',
    'pap_smear'    => 'Pap smear',
    'fecalysis'    => 'Fecalysis',
    'fobt'         => 'Fecal Occult Blood Test',
];
?>
<div class="clean-guided" data-page-id="ekas" data-stepped="1">
  <div class="clean-step-rail" aria-label="EKAS sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Facility</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · Services</button>
    <button type="button" class="clean-step-dot" data-goto="2">3 · Feedback</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Electronic Konsulta Availment Slip (EKAS)</h2>
    <p class="clean-step-help">Facility / beneficiary details — print stays official.</p>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>HCI name</span><?= $P::line('ekas_hci_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Case no.</span><?= $P::line('ekas_case_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Accreditation no.</span><?= $P::line('ekas_accreditation_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Transaction no.</span><?= $P::line('ekas_transaction_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>PIN</span><?= $P::line('philhealth_no', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Membership category</span><?= $P::line('philhealth_category', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Patient name</span><?= $P::line('ekas_patient_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Age</span><?= $P::line('itr_age', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Contact number</span><?= $P::line('contact_number', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <div class="clean-field">
        <span>Membership type</span>
        <div class="clean-chips">
          <?= $P::radio('philhealth_status_type', 'Member', 'Member', $role, $fg, $lockRecords) ?>
          <?= $P::radio('philhealth_status_type', 'Dependent', 'Dependent', $role, $fg, $lockRecords) ?>
        </div>
      </div>
      <label class="clean-field"><span>ATC</span><?= $P::line('ekas_atc', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">Konsulta services</h2>
    <p class="clean-step-help">Mark performed services, date, and who performed.</p>
    <?php foreach ($ekasServices as $key => $label): ?>
      <div class="clean-grid clean-grid-3" style="align-items:end;">
        <div class="clean-checks" style="margin:0 0 0.75rem;">
          <?= $P::checkbox('ekas_svc_' . $key . '_done', '1', $label, $role, $fg, $lockRecords, true, 'ekas_svc_' . $key . '_done') ?>
        </div>
        <label class="clean-field"><span>Date performed</span><?= $P::line('ekas_svc_' . $key . '_date', $role, $fg, $lockRecords, true, 'date', 'clean-input') ?></label>
        <label class="clean-field"><span>Performed by</span><?= $P::line('ekas_svc_' . $key . '_by', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="clean-step" data-clean-step="2" hidden>
    <h2 class="clean-step-title">Patient feedback</h2>
    <p class="clean-step-help">Satisfaction and comments.</p>
    <div class="clean-field">
      <span>Feedback received</span>
      <div class="clean-chips">
        <?= $P::radio('ekas_feedback_received', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ekas_feedback_received', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>Satisfaction</span>
      <div class="clean-chips">
        <?= $P::radio('ekas_satisfaction', 'Happy', 'Happy', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ekas_satisfaction', 'Neutral', 'Neutral', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ekas_satisfaction', 'Bad', 'Bad', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <label class="clean-field"><span>Comments</span><?= $P::area('ekas_feedback_comments', $role, $fg, $lockRecords, true, 3) ?></label>
    <label class="clean-field"><span>Patient PhilHealth PIN (feedback)</span><?= $P::line('ekas_feedback_pin', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
  </div>
</div>
