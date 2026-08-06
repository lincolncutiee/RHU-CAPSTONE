<?php
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
<div class="paper-sheet">
<div class="paper-sheet-inner">
    <div class="pf-title-sub">Integrated Clinic Information System (iCLINICSYS)</div>
    <div class="pf-title-main">INDIVIDUAL TREATMENT RECORD</div>
    <p class="pf-instructions">Instructions: For old, returning and/or referred patient. Please print legibly and mark appropriate boxes with 'X'.<br>
    <em>Tagubilin: Para sa mga dating pasyente, bumabalik at/o nire-refer. Mangyaring sulatan nang mabuti at lagyan ng 'X' ang naaangkop na kahon.</em></p>

    <?= $P::sectionBar('I. PATIENT INFORMATION (IMPORMASYON NG PASYENTE)') ?>

    <table class="pf-table">
        <tr>
            <td style="width:35%;">
                <span class="pf-label">Last Name (Apelyido)</span>
                <?= $P::line('last_name', $role, $fgEnroll, $lockRecords, false, 'text', '', '') ?>
            </td>
            <td style="width:15%;">
                <span class="pf-label">Suffix (e.g., Jr., Sr., II, III)</span>
                <?= $P::line('suffix', $role, $fgEnroll, $lockRecords) ?>
            </td>
            <td style="width:15%;">
                <span class="pf-label">Age (Edad)</span>
                <?= $P::line('itr_age', $role, $fgTreat, $lockRecords, true) ?>
            </td>
            <td rowspan="3" style="width:35%;vertical-align:top;">
                <span class="pf-label">Residential Address (Tirahan)</span>
                <?= $P::magallanesAddress($role, $fgEnroll, $lockRecords, 'compact') ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="pf-label">First Name (Pangalan)</span>
                <?= $P::line('first_name', $role, $fgEnroll, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="pf-label">Middle Name (Gitnang Pangalan)</span>
                <?= $P::line('middle_name', $role, $fgEnroll, $lockRecords) ?>
            </td>
        </tr>
    </table>

    <?= $P::sectionBar('II. FOR CHU / RHU PERSONNEL ONLY (PARA SA KINATAWAN NG CHU / RHU LAMANG)') ?>

    <table class="pf-table">
        <tr>
            <td style="width:50%;vertical-align:top;">
                <span class="pf-label">Mode of Transaction:</span><br>
                <?= $P::radio('mode_of_transaction', 'Walk-in', 'Walk-in', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('mode_of_transaction', 'Visited', 'Visited', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('mode_of_transaction', 'Referral', 'Referral', $role, $fgTreat, $lockRecords) ?>

                <div class="pf-field-row" style="margin-top:6px;">
                    <span class="pf-label">Date of Consultation (mm/dd/yyyy)</span>
                    <?= $P::line('date_of_consultation', $role, $fgTreat, $lockRecords, false, 'date') ?>
                </div>
                <div class="pf-field-row pf-inline">
                    <div class="pf-field">
                        <span class="pf-label">Consultation Time</span>
                        <?= $P::line('consultation_time', $role, $fgTreat, $lockRecords, false, 'time') ?>
                    </div>
                    <div class="pf-field" style="max-width:100px;">
                        <span class="pf-label">&nbsp;</span>
                        <?= $P::select('consultation_period', ['' => '—', 'AM' => 'AM', 'PM' => 'PM'], $role, $fgTreat, $lockRecords) ?>
                    </div>
                </div>

                <table class="pf-table" style="margin-top:6px;">
                    <tr>
                        <td><span class="pf-label">Blood Pressure</span><?= $P::line('blood_pressure', $role, $fgTreat, $lockRecords) ?></td>
                        <td><span class="pf-label">Temperature</span><?= $P::line('temperature', $role, $fgTreat, $lockRecords) ?></td>
                    </tr>
                    <tr>
                        <td><span class="pf-label">Height (cm)</span><?= $P::line('height_cm', $role, $fgTreat, $lockRecords, false, 'number') ?></td>
                        <td><span class="pf-label">Weight (kg)</span><?= $P::line('weight_kg', $role, $fgTreat, $lockRecords, false, 'number') ?></td>
                    </tr>
                </table>

                <div class="pf-field-row" style="margin-top:6px;">
                    <span class="pf-label">Name of Attending Provider</span>
                    <?= $P::line('attending_provider', $role, $fgTreat, $lockRecords) ?>
                </div>
            </td>
            <td style="width:50%;vertical-align:top;">
                <strong style="font-size:8pt;">For REFERRAL Transaction only.</strong>
                <div class="pf-field-row">
                    <span class="pf-label">REFERRED FROM:</span>
                    <?= $P::line('referred_from', $role, $fgTreat, $lockRecords) ?>
                    <?= $P::line('referred_from_2', $role, $fgTreat, $lockRecords, true) ?>
                </div>
                <div class="pf-field-row">
                    <span class="pf-label">REFERRED TO:</span>
                    <?= $P::line('referred_to', $role, $fgTreat, $lockRecords) ?>
                    <?= $P::line('referred_to_2', $role, $fgTreat, $lockRecords, true) ?>
                </div>
                <div class="pf-field-row">
                    <span class="pf-label">Reason(s) for Referral:</span>
                    <?= $P::area('referral_reasons', $role, $fgTreat, $lockRecords, false, 4) ?>
                </div>
                <div class="pf-field-row">
                    <span class="pf-label">Referred by:</span>
                    <?= $P::line('referred_by', $role, $fgTreat, $lockRecords) ?>
                </div>
            </td>
        </tr>
    </table>

    <table class="pf-table">
        <tr>
            <td style="width:55%;vertical-align:top;">
                <span class="pf-label">Nature of Visit:</span><br>
                <?= $P::radio('nature_of_visit', 'New Consultation/Case', 'New Consultation/Case', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('nature_of_visit', 'New Admission', 'New Admission', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('nature_of_visit', 'Follow-up Visit', 'Follow-up Visit', $role, $fgTreat, $lockRecords) ?>

                <div style="margin-top:6px;">
                    <span class="pf-label">Type of Consultation / Purpose of Visit:</span>
                    <div class="pf-check-grid" style="margin-top:4px;">
                        <?php foreach ($consultTypes as $opt): ?>
                            <label class="pf-check">
                                <input type="checkbox" name="consultation_types[]" value="<?= htmlspecialchars($opt) ?>"
                                    <?= $P::fg($fgTreat) ?> <?= $P::dis($role, $fgTreat, $lockRecords) ?>>
                                <span><?= htmlspecialchars($opt) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </td>
            <td style="width:45%;vertical-align:top;">
                <span class="pf-label">Chief Complaints:</span>
                <?= $P::area('chief_complaints', $role, $fgTreat, $lockRecords, false, 8) ?>
            </td>
        </tr>
    </table>

    <table class="pf-table">
        <tr>
            <td colspan="2">
                <span class="pf-label">Diagnosis:</span>
                <?= $P::area('diagnosis', $role, $fgDoctor, $lockRecords, false, 2) ?>
            </td>
        </tr>
        <tr>
            <td style="width:65%;vertical-align:top;">
                <span class="pf-label">Medication / Treatment:</span>
                <?= $P::area('medication_treatment', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
            <td style="width:35%;vertical-align:top;">
                <span class="pf-label">Name of Health Care Provider:</span>
                <?= $P::area('healthcare_provider', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top;">
                <span class="pf-label">Assessment / Laboratory Findings / Impression:</span>
                <?= $P::area('laboratory_findings', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
            <td style="vertical-align:top;">
                <span class="pf-label">Performed Laboratory Test:</span>
                <?= $P::area('performed_lab_test', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top;">
                <span class="pf-label">Treatment Plan:</span>
                <?= $P::area('doctor_treatment_plan', $role, $fgDoctor, $lockRecords, true, 3) ?>
            </td>
            <td style="vertical-align:top;">
                <span class="pf-label">Follow-up Instructions:</span>
                <?= $P::area('doctor_follow_up', $role, $fgDoctor, $lockRecords, true, 3) ?>
            </td>
        </tr>
    </table>

    <?= $P::formFooter('Clinic Information System | FORM 2 | Page 1') ?>
</div>
</div>
