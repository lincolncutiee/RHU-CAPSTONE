<?php
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'records_enrollment';
$P = PaperFormRenderer::class;

$conditions = [
    'allergy' => 'Allergy',
    'asthma' => 'Asthma',
    'cancer' => 'Cancer',
    'cerebrovascular' => 'Cerebrovascular Disease',
    'coronary' => 'Coronary Artery Disease',
    'diabetes' => 'Diabetes Mellitus',
    'emphysema' => 'Emphysema',
    'epilepsy' => 'Epilepsy/Seizure Disorder',
    'hepatitis' => 'Hepatitis',
    'hyperlipidemia' => 'Hyperlipidemia',
    'hypertension' => 'Hypertension',
    'peptic_ulcer' => 'Peptic Ulcer',
    'pneumonia' => 'Pneumonia',
    'thyroid' => 'Thyroid Disease',
    'pulmonary_tb' => 'Pulmonary Tuberculosis',
    'extrapulmonary_tb' => 'Extrapulmonary Tuberculosis',
    'uti' => 'Urinary Tract Infection',
    'mental_illness' => 'Mental Illness',
    'others' => 'Others',
    'none' => 'None',
];

function renderHistorySection(string $prefix, string $title, array $conditions, string $role, string $fg, bool $lockRecords, string $P): void {
    ?>
    <div class="pf-section-title-center"><?= htmlspecialchars($title) ?></div>
    <div class="pf-split-2">
        <div>
            <div class="pf-check-grid-1">
                <?php foreach ($conditions as $key => $label): ?>
                    <?= PaperFormRenderer::checkbox("{$prefix}_{$key}", '1', $label, $role, $fg, $lockRecords, true) ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <div class="pf-field-row">
                <?= PaperFormRenderer::label('Specify Allergy:', "{$prefix}_allergy_specify", false) ?>
                <?= PaperFormRenderer::line("{$prefix}_allergy_specify", $role, $fg, $lockRecords, true) ?>
            </div>
            <div class="pf-field-row">
                <?= PaperFormRenderer::label('Specify organ with cancer:', "{$prefix}_cancer_specify", false) ?>
                <?= PaperFormRenderer::line("{$prefix}_cancer_specify", $role, $fg, $lockRecords, true) ?>
            </div>
            <div class="pf-field-row">
                <?= PaperFormRenderer::label('Specify hepatitis type:', "{$prefix}_hepatitis_specify", false) ?>
                <?= PaperFormRenderer::line("{$prefix}_hepatitis_specify", $role, $fg, $lockRecords, true) ?>
            </div>
            <div class="pf-field-row pf-inline">
                <div class="pf-field">
                    <?= PaperFormRenderer::label('Highest blood pressure:', "{$prefix}_bp_systolic", false) ?>
                    <div class="pf-inline">
                        <?= PaperFormRenderer::line("{$prefix}_bp_systolic", $role, $fg, $lockRecords, true, 'text', 'pf-line', '') ?>
                        <span>/</span>
                        <?= PaperFormRenderer::line("{$prefix}_bp_diastolic", $role, $fg, $lockRecords, true, 'text', 'pf-line', '') ?>
                        <span style="font-size:7pt;">mmHg</span>
                    </div>
                </div>
            </div>
            <div class="pf-field-row">
                <?= PaperFormRenderer::label('Specify Pulmonary Tuberculosis category:', "{$prefix}_pulmonary_tb_specify", false) ?>
                <?= PaperFormRenderer::line("{$prefix}_pulmonary_tb_specify", $role, $fg, $lockRecords, true) ?>
            </div>
            <div class="pf-field-row">
                <?= PaperFormRenderer::label('Specify Extrapulmonary Tuberculosis category:', "{$prefix}_extrapulmonary_tb_specify", false) ?>
                <?= PaperFormRenderer::line("{$prefix}_extrapulmonary_tb_specify", $role, $fg, $lockRecords, true) ?>
            </div>
            <div class="pf-field-row">
                <?= PaperFormRenderer::label('Others, please specify:', "{$prefix}_others_specify", false) ?>
                <?= PaperFormRenderer::area("{$prefix}_others_specify", $role, $fg, $lockRecords, true, 4) ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<div class="paper-sheet">
<div class="paper-sheet-inner">
    <?php renderHistorySection('pmh', 'PAST MEDICAL HISTORY', $conditions, $role, $fg, $lockRecords, $P); ?>

    <div style="margin:12px 0;border-top:2px solid #000;"></div>

    <div class="pf-section-title-center">PAST SURGICAL HISTORY</div>
    <table class="pf-table">
        <tr>
            <th style="width:70%;">OPERATION</th>
            <th style="width:30%;">DATE</th>
        </tr>
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <tr>
            <td><?= $P::line("surgical_op_{$i}", $role, $fg, $lockRecords, true) ?></td>
            <td><?= $P::line("surgical_date_{$i}", $role, $fg, $lockRecords, true, 'date') ?></td>
        </tr>
        <?php endfor; ?>
    </table>

    <div style="margin:12px 0;border-top:2px solid #000;"></div>

    <?php renderHistorySection('fh', 'FAMILY HISTORY', $conditions, $role, $fg, $lockRecords, $P); ?>

    <?= $P::formFooter('Form 3') ?>
</div>
</div>
