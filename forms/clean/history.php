<?php
/** Clean guided History — all paper history fields */
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'records_enrollment';
$P = PaperFormRenderer::class;
$conditions = [
    'allergy' => 'Allergy', 'asthma' => 'Asthma', 'cancer' => 'Cancer',
    'cerebrovascular' => 'Cerebrovascular Disease', 'coronary' => 'Coronary Artery Disease',
    'diabetes' => 'Diabetes Mellitus', 'emphysema' => 'Emphysema', 'epilepsy' => 'Epilepsy/Seizure Disorder',
    'hepatitis' => 'Hepatitis', 'hyperlipidemia' => 'Hyperlipidemia', 'hypertension' => 'Hypertension',
    'peptic_ulcer' => 'Peptic Ulcer', 'pneumonia' => 'Pneumonia', 'thyroid' => 'Thyroid Disease',
    'pulmonary_tb' => 'Pulmonary Tuberculosis', 'extrapulmonary_tb' => 'Extrapulmonary Tuberculosis',
    'uti' => 'Urinary Tract Infection', 'mental_illness' => 'Mental Illness',
    'others' => 'Others', 'none' => 'None',
];

$renderSpecify = static function (string $prefix) use ($P, $role, $fg, $lockRecords): void {
    ?>
    <div class="clean-grid clean-grid-2">
      <label class="clean-field"><span>Specify allergy</span><?= $P::line("{$prefix}_allergy_specify", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Specify organ with cancer</span><?= $P::line("{$prefix}_cancer_specify", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Specify hepatitis type</span><?= $P::line("{$prefix}_hepatitis_specify", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <div class="clean-field">
        <span>Highest blood pressure</span>
        <div class="clean-grid clean-grid-2" style="margin:0;">
          <label class="clean-field" style="margin:0;"><span>Systolic</span><?= $P::line("{$prefix}_bp_systolic", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
          <label class="clean-field" style="margin:0;"><span>Diastolic</span><?= $P::line("{$prefix}_bp_diastolic", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
        </div>
      </div>
      <label class="clean-field"><span>Pulmonary TB category</span><?= $P::line("{$prefix}_pulmonary_tb_specify", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
      <label class="clean-field"><span>Extrapulmonary TB category</span><?= $P::line("{$prefix}_extrapulmonary_tb_specify", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
    </div>
    <label class="clean-field"><span>Others (specify)</span><?= $P::area("{$prefix}_others_specify", $role, $fg, $lockRecords, true, 3) ?></label>
    <?php
};
?>
<div class="clean-guided" data-page-id="history" data-stepped="1">
  <div class="clean-step-rail" aria-label="History sections">
    <button type="button" class="clean-step-dot is-active" data-goto="0">1 · Medical</button>
    <button type="button" class="clean-step-dot" data-goto="1">2 · Surgical</button>
    <button type="button" class="clean-step-dot" data-goto="2">3 · Family</button>
  </div>

  <div class="clean-step is-active" data-clean-step="0">
    <h2 class="clean-step-title">Past medical history</h2>
    <p class="clean-step-help">Same history checkboxes and specify fields as paper.</p>
    <div class="clean-checks clean-checks--grid">
      <?php foreach ($conditions as $key => $label): ?>
        <?= $P::checkbox("pmh_{$key}", '1', $label, $role, $fg, $lockRecords, true) ?>
      <?php endforeach; ?>
    </div>
    <?php $renderSpecify('pmh'); ?>
  </div>

  <div class="clean-step" data-clean-step="1" hidden>
    <h2 class="clean-step-title">Past surgical history</h2>
    <p class="clean-step-help">Operations and dates.</p>
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="clean-grid clean-grid-2">
        <label class="clean-field"><span>Operation <?= $i ?></span><?= $P::line("surgical_op_{$i}", $role, $fg, $lockRecords, true, 'text', 'clean-input') ?></label>
        <label class="clean-field"><span>Date <?= $i ?></span><?= $P::line("surgical_date_{$i}", $role, $fg, $lockRecords, true, 'date', 'clean-input') ?></label>
      </div>
    <?php endfor; ?>
  </div>

  <div class="clean-step" data-clean-step="2" hidden>
    <h2 class="clean-step-title">Family history</h2>
    <p class="clean-step-help">Relevant family conditions.</p>
    <div class="clean-checks clean-checks--grid">
      <?php foreach ($conditions as $key => $label): ?>
        <?= $P::checkbox("fh_{$key}", '1', $label, $role, $fg, $lockRecords, true) ?>
      <?php endforeach; ?>
    </div>
    <?php $renderSpecify('fh'); ?>
  </div>
</div>
