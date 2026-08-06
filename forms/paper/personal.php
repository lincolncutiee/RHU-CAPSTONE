<?php
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'records_enrollment';
$P = PaperFormRenderer::class;
$facilityName = 'MAGALLANES RURAL HEALTH & FAMILY PLANNING CENTER';
?>
<div class="paper-sheet">
<div class="paper-sheet-inner">
    <div class="pf-split-2" style="border:none;margin-bottom:8px;">
        <div style="border:1px solid #000;">
            <div class="pf-section-title-center" style="text-decoration:none;">PERSONAL/SOCIAL HISTORY</div>

            <div class="pf-field-row">
                <strong style="text-decoration:underline;">Smoking</strong><br>
                <?= $P::radio('psh_smoking', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('psh_smoking', 'No', 'No', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('psh_smoking', 'Quit', 'Quit', $role, $fg, $lockRecords, true) ?>
                <span style="margin-left:8px;font-size:7.5pt;">No. of packs/year?</span>
                <?= $P::line('psh_smoking_packs', $role, $fg, $lockRecords, true, 'text', 'pf-line pf-short') ?>
            </div>

            <div class="pf-field-row">
                <strong style="text-decoration:underline;">Alcohol</strong><br>
                <?= $P::radio('psh_alcohol', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('psh_alcohol', 'No', 'No', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('psh_alcohol', 'Quit', 'Quit', $role, $fg, $lockRecords, true) ?>
                <span style="margin-left:8px;font-size:7.5pt;">No. of bottles/day?</span>
                <?= $P::line('psh_alcohol_bottles', $role, $fg, $lockRecords, true) ?>
            </div>

            <div class="pf-field-row">
                <strong style="text-decoration:underline;">Illicit Drugs</strong><br>
                <?= $P::radio('psh_drugs', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('psh_drugs', 'No', 'No', $role, $fg, $lockRecords, true) ?>
            </div>

            <div class="pf-field-row">
                <strong style="text-decoration:underline;">Sexual History Screening</strong><br>
                <span class="pf-label">Sexually Active</span>
                <?= $P::radio('psh_sexually_active', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('psh_sexually_active', 'No', 'No', $role, $fg, $lockRecords, true) ?>
            </div>
        </div>

        <div style="border:1px solid #000;">
            <div class="pf-section-title-center" style="text-decoration:none;">NCD HIGH-RISK ASSESSMENT<br><span style="font-size:7pt;font-weight:normal;">(for 25 years old and above)</span></div>

            <div class="pf-field-row" style="font-size:7.5pt;">
                <strong>High Fat/High Salt Food Intake</strong><br>
                <em>Patient eats processed/fast foods and ihaw-ihaw more than 3x a week</em><br>
                <?= $P::radio('ncd_fat_salt', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_fat_salt', 'No', 'No', $role, $fg, $lockRecords, true) ?>
            </div>

            <div class="pf-field-row" style="font-size:7.5pt;">
                <strong>Dietary Fiber Intake</strong><br>
                3 Servings vegetables daily:
                <?= $P::radio('ncd_vegetables', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_vegetables', 'No', 'No', $role, $fg, $lockRecords, true) ?><br>
                2-3 servings of fruits daily:
                <?= $P::radio('ncd_fruits', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_fruits', 'No', 'No', $role, $fg, $lockRecords, true) ?>
            </div>

            <div class="pf-field-row" style="font-size:7.5pt;">
                <strong>Physical Activities</strong><br>
                <em>Does at least 2.5 hours a week of moderate-intensity physical activity</em><br>
                <?= $P::radio('ncd_physical', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_physical', 'No', 'No', $role, $fg, $lockRecords, true) ?>
            </div>

            <div class="pf-field-row" style="font-size:7.5pt;">
                <strong>Presence or absence of Diabetes</strong><br>
                1. Was patient diagnosed as having diabetes?<br>
                <?= $P::radio('ncd_diabetes', 'Yes', 'Yes', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_diabetes', 'No', 'No', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_diabetes', 'Do Not Know', 'Do Not Know', $role, $fg, $lockRecords, true) ?><br>
                If Yes:
                <?= $P::radio('ncd_diabetes_meds', 'With Medication', 'With Medication', $role, $fg, $lockRecords, true) ?>
                <?= $P::radio('ncd_diabetes_meds', 'Without Medication', 'Without Medication', $role, $fg, $lockRecords, true) ?>
            </div>
        </div>
    </div>

    <div class="pf-bordered-box">
        <div class="pf-title-main" style="font-size:11pt;">PHOTO CONSENT FORM</div>
        <p style="font-size:8pt;line-height:1.5;margin:10px 0;">
            I, <?= $P::line('photo_consent_name', $role, $fg, $lockRecords, true) ?>, with address
            <?= $P::line('photo_consent_address', $role, $fg, $lockRecords, true) ?>
            Grant permission and give my consent <strong><?= htmlspecialchars($facilityName) ?></strong> for the use of my photo or picture as one of the requirements in the availment of Konsulta benefit.
        </p>
        <div class="pf-sublabel" style="text-align:left;">Name of Konsulta Beneficiary</div>
        <div class="pf-sublabel" style="text-align:left;margin-top:20px;">Address of Konsulta Beneficiary</div>
        <div class="pf-sublabel" style="text-align:left;margin-top:20px;">Name of Konsulta Package Provider (KPP)</div>

        <p style="font-size:8pt;margin:12px 0;">By signing below, I hereby authorized my Konsulta facility to save my photo for Post-audit monitoring purposes of PhilHealth.</p>

        <table class="pf-table" style="border:none;margin-top:16px;">
            <tr>
                <td style="border:none;width:33%;">
                    <div class="pf-sig-line"><?= $P::line('photo_consent_sign_name', $role, $fg, $lockRecords, true) ?></div>
                    <div class="pf-sig-label">Name of Konsulta Beneficiary</div>
                </td>
                <td style="border:none;width:33%;">
                    <div class="pf-sig-line"><?= $P::line('photo_consent_signature', $role, $fg, $lockRecords, true) ?></div>
                    <div class="pf-sig-label">Signature</div>
                </td>
                <td style="border:none;width:34%;">
                    <div class="pf-sig-line"><?= $P::line('photo_consent_date', $role, $fg, $lockRecords, true, 'date') ?></div>
                    <div class="pf-sig-label">Date</div>
                </td>
            </tr>
        </table>
    </div>

    <?= $P::formFooter('Form 4') ?>
</div>
</div>
