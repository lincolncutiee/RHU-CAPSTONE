<?php
/** Clean guided PCSF — matches mockup layout; same field names as paper PCSF */
/** @var string $role */
/** @var bool $lockRecords */
require_once __DIR__ . '/../../core/MagallanesAddress.php';
$fg = 'it';
$P = PaperFormRenderer::class;

$barangaySelect = $P::select(
    'address_barangay',
    MagallanesAddress::barangayOptions(),
    $role,
    $fg,
    $lockRecords,
    true,
    'Magallanes',
    'clean-input',
    true
);
$muniVal = htmlspecialchars(MagallanesAddress::municipality(), ENT_QUOTES, 'UTF-8');
$provVal = htmlspecialchars(MagallanesAddress::province(), ENT_QUOTES, 'UTF-8');
?>
<div class="clean-guided" data-page-id="pcsf" data-stepped="1">
  <div class="clean-step-rail" aria-label="PCSF sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Beneficiary</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · PCC choices</button>
    <button type="button" class="clean-step-dot" data-goto="2">3 · Transfer</button>
    <button type="button" class="clean-step-dot" data-goto="3">4 · Confirm</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Beneficiary information</h2>
    <p class="clean-step-help">To be filled-out by the beneficiary — same PCSF fields as today.</p>

    <div class="clean-chips">
      <?= $P::radio('philhealth_status_type', 'Member', 'Member', $role, $fg, $lockRecords) ?>
      <?= $P::radio('philhealth_status_type', 'Dependent', 'Dependent', $role, $fg, $lockRecords) ?>
    </div>

    <div class="clean-grid clean-grid-2">
      <label class="clean-field">
        <span>PIN</span>
        <?= $P::line('philhealth_no', $role, $fg, $lockRecords, false, 'text', 'clean-input', '12-345678901-2') ?>
      </label>
      <label class="clean-field">
        <span>Date</span>
        <?= $P::line('pcsf_date', $role, $fg, $lockRecords, true, 'date', 'clean-input') ?>
      </label>
    </div>

    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Last name</span><?= $P::line('last_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>First name</span><?= $P::line('first_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Middle name</span><?= $P::line('middle_name', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>

    <div class="clean-grid clean-grid-3" data-magallanes-address>
      <label class="clean-field">
        <span>Barangay / Town</span>
        <?= $barangaySelect ?>
      </label>
      <label class="clean-field">
        <span>Municipality / City</span>
        <input type="text" name="extended[address_municipality]" class="pf-line clean-input" value="<?= $muniVal ?>"
               readonly data-perm-editable="0" data-perm-reason="system_readonly" data-magallanes-fixed="1" data-extended="1">
      </label>
      <label class="clean-field">
        <span>Province</span>
        <input type="text" name="extended[address_province]" class="pf-line clean-input" value="<?= $provVal ?>"
               readonly data-perm-editable="0" data-perm-reason="system_readonly" data-magallanes-fixed="1" data-extended="1">
      </label>
      <textarea name="extended[address_street]" rows="1" class="pf-area" hidden data-extended="1"
                data-magallanes-composed="1" data-perm-editable="0" data-perm-reason="system_readonly" readonly></textarea>
    </div>

    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Date of birth</span><?= $P::line('birth_date', $role, $fg, $lockRecords, false, 'date', 'clean-input') ?></label>
      <label class="clean-field"><span>Contact number</span><?= $P::line('contact_number', $role, $fg, $lockRecords, false, 'text', 'clean-input') ?></label>
    </div>

    <label class="clean-field">
      <span>Email address</span>
      <?= $P::line('pcsf_email', $role, $fg, $lockRecords, true, 'email', 'clean-input', 'name@email.com') ?>
    </label>

    <div class="clean-checks">
      <?= $P::checkbox('pcsf_register_pcc', '1', 'Register to a Primary Care Clinic (PCC)', $role, $fg, $lockRecords, true) ?>
      <?= $P::checkbox('pcsf_register_dependents', '1', 'Register all declared minor dependents', $role, $fg, $lockRecords, true) ?>
    </div>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">Primary Care Clinic choices</h2>
    <p class="clean-step-help">Makita ra ni after Next from Beneficiary.</p>

    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Signatory last name</span><?= $P::line('pcsf_signatory_last', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Signatory first name</span><?= $P::line('pcsf_signatory_first', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Signatory middle name</span><?= $P::line('pcsf_signatory_middle', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>

    <label class="clean-field"><span>1st choice PCC</span><?= $P::line('pcsf_pcc_1st', $role, $fg, $lockRecords, true, 'text', 'clean-input', 'RHU Magallanes') ?></label>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>PCC barangay</span><?= $P::line('pcsf_pcc1_barangay', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Municipality / City</span><?= $P::line('pcsf_pcc1_city', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Province</span><?= $P::line('pcsf_pcc1_province', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>

    <label class="clean-field"><span>2nd choice PCC</span><?= $P::line('pcsf_pcc_2nd', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>PCC barangay</span><?= $P::line('pcsf_pcc2_barangay', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Municipality / City</span><?= $P::line('pcsf_pcc2_city', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Province</span><?= $P::line('pcsf_pcc2_province', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
  </div>

  <div class="clean-step clean-step--muted" data-clean-step="2" hidden>
    <h2 class="clean-step-title">Transfer <span class="clean-optional">optional</span></h2>
    <div class="clean-checks">
      <?= $P::checkbox('pcsf_transfer', '1', 'Transfer from previous PCC', $role, $fg, $lockRecords, true) ?>
    </div>
    <label class="clean-field"><span>Previous PCC</span><?= $P::line('pcsf_previous_pcc', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <label class="clean-field"><span>1st choice PCC (transfer)</span><?= $P::line('pcsf_transfer_pcc_1st', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Barangay</span><?= $P::line('pcsf_transfer_pcc1_barangay', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Municipality / City</span><?= $P::line('pcsf_transfer_pcc1_city', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Province</span><?= $P::line('pcsf_transfer_pcc1_province', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>2nd choice PCC (transfer)</span><?= $P::line('pcsf_transfer_pcc_2nd', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Barangay</span><?= $P::line('pcsf_transfer_pcc2_barangay', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Municipality / City</span><?= $P::line('pcsf_transfer_pcc2_city', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Province</span><?= $P::line('pcsf_transfer_pcc2_province', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Signature (over printed name)</span><?= $P::line('pcsf_signature', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
  </div>

  <div class="clean-step" data-clean-step="3" hidden>
    <h2 class="clean-step-title">PhilHealth confirmation slip</h2>
    <p class="clean-step-help">To be filled-out by PhilHealth primary care personnel.</p>

    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Selection no.</span><?= $P::line('pcsf_selection_no', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Confirm date</span><?= $P::line('pcsf_confirm_date', $role, $fg, $lockRecords, true, 'date', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Last name</span><?= $P::line('pcsf_confirm_last', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>First name</span><?= $P::line('pcsf_confirm_first', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Middle name</span><?= $P::line('pcsf_confirm_middle', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>PIN</span><?= $P::line('pcsf_confirm_pin', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Date of birth</span><?= $P::line('pcsf_confirm_birth_date', $role, $fg, $lockRecords, true, 'date', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Primary Care Clinic (PC)</span><?= $P::line('pcsf_primary_care_clinic', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Barangay</span><?= $P::line('pcsf_confirm_barangay', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Municipality / City</span><?= $P::line('pcsf_confirm_city', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Province</span><?= $P::line('pcsf_confirm_province', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Personnel signature (over printed name)</span><?= $P::line('pcsf_personnel_signature', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
  </div>
</div>
