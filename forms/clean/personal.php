<?php
/** Clean guided Personal / Social / NCD / Photo — all paper fields */
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'records_enrollment';
$P = PaperFormRenderer::class;
?>
<div class="clean-guided" data-page-id="personal" data-stepped="1">
  <div class="clean-step-rail" aria-label="Personal sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Social</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · NCD</button>
    <button type="button" class="clean-step-dot" data-goto="2">3 · Photo consent</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Personal / social history</h2>
    <p class="clean-step-help">Smoking, alcohol, drugs, and sexual history screening.</p>
    <div class="clean-field">
      <span>Smoking</span>
      <div class="clean-chips">
        <?= $P::radio('psh_smoking', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('psh_smoking', 'No', 'No', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('psh_smoking', 'Quit', 'Quit', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <label class="clean-field"><span>No. of packs / year</span><?= $P::line('psh_smoking_packs', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-field">
      <span>Alcohol</span>
      <div class="clean-chips">
        <?= $P::radio('psh_alcohol', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('psh_alcohol', 'No', 'No', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('psh_alcohol', 'Quit', 'Quit', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <label class="clean-field"><span>No. of bottles / day</span><?= $P::line('psh_alcohol_bottles', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-field">
      <span>Illicit drugs</span>
      <div class="clean-chips">
        <?= $P::radio('psh_drugs', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('psh_drugs', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>Sexually active</span>
      <div class="clean-chips">
        <?= $P::radio('psh_sexually_active', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('psh_sexually_active', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">NCD high-risk assessment</h2>
    <p class="clean-step-help">For 25 years old and above — same paper questions.</p>
    <div class="clean-field">
      <span>High fat / high salt food intake</span>
      <div class="clean-chips">
        <?= $P::radio('ncd_fat_salt', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_fat_salt', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>3 servings vegetables daily</span>
      <div class="clean-chips">
        <?= $P::radio('ncd_vegetables', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_vegetables', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>2–3 servings fruits daily</span>
      <div class="clean-chips">
        <?= $P::radio('ncd_fruits', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_fruits', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>Physical activity (≥ 2.5 hrs / week)</span>
      <div class="clean-chips">
        <?= $P::radio('ncd_physical', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_physical', 'No', 'No', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>Diagnosed with diabetes?</span>
      <div class="clean-chips">
        <?= $P::radio('ncd_diabetes', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_diabetes', 'No', 'No', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_diabetes', 'Do Not Know', 'Do Not Know', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
    <div class="clean-field">
      <span>If yes — medication</span>
      <div class="clean-chips">
        <?= $P::radio('ncd_diabetes_meds', 'With Medication', 'With Medication', $role, $fg, $lockRecords, true) ?>
        <?= $P::radio('ncd_diabetes_meds', 'Without Medication', 'Without Medication', $role, $fg, $lockRecords, true) ?>
      </div>
    </div>
  </div>

  <div class="clean-step" data-clean-step="2" hidden>
    <h2 class="clean-step-title">Photo consent</h2>
    <p class="clean-step-help">Beneficiary photo consent for Konsulta benefit.</p>
    <label class="clean-field"><span>Beneficiary name</span><?= $P::line('photo_consent_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <label class="clean-field"><span>Address</span><?= $P::line('photo_consent_address', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    <div class="clean-grid clean-grid-3">
      <label class="clean-field"><span>Signatory name</span><?= $P::line('photo_consent_sign_name', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Signature</span><?= $P::line('photo_consent_signature', $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Consent date</span><?= $P::line('photo_consent_date', $role, $fg, $lockRecords, true, 'date', 'clean-input') ?></label>
    </div>
  </div>
</div>
