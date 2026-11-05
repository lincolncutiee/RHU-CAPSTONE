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
    <?= $P::titleSub('Integrated Clinic Information System (iCLINICSYS)') ?>
    <?= $P::titleMain('INDIVIDUAL TREATMENT RECORD') ?>
    <?= $P::instructionsBlock('page.itr.instructions', "Instructions: For old, returning and/or referred patient. Please print legibly and mark appropriate boxes with 'X'.\nTagubilin: Para sa mga dating pasyente, bumabalik at/o nire-refer. Mangyaring sulatan nang mabuti at lagyan ng 'X' ang naaangkop na kahon.") ?>

    <?= $P::sectionBar('I. PATIENT INFORMATION (IMPORMASYON NG PASYENTE)') ?>

    <table class="pf-table">
        <tr>
            <td style="width:35%;">
                <?= $P::label('Last Name (Apelyido)', 'last_name', false) ?>
                <?= $P::line('last_name', $role, $fgEnroll, $lockRecords, false, 'text', '', '') ?>
            </td>
            <td style="width:15%;">
                <?= $P::label('Suffix (e.g., Jr., Sr., II, III)', 'suffix', false) ?>
                <?= $P::line('suffix', $role, $fgEnroll, $lockRecords) ?>
            </td>
            <td style="width:15%;">
                <?= $P::label('Age (Edad)', 'itr_age', false) ?>
                <?= $P::line('itr_age', $role, $fgTreat, $lockRecords, true) ?>
            </td>
            <td rowspan="3" style="width:35%;vertical-align:top;">
                <?= $P::label('Residential Address (Tirahan)', 'address_barangay', false) ?>
                <?= $P::magallanesAddress($role, $fgEnroll, $lockRecords, 'compact') ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <?= $P::label('First Name (Pangalan)', 'first_name', false) ?>
                <?= $P::line('first_name', $role, $fgEnroll, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <?= $P::label('Middle Name (Gitnang Pangalan)', 'middle_name', false) ?>
                <?= $P::line('middle_name', $role, $fgEnroll, $lockRecords) ?>
            </td>
        </tr>
    </table>

    <?= $P::sectionBar('II. FOR CHU / RHU PERSONNEL ONLY (PARA SA KINATAWAN NG CHU / RHU LAMANG)') ?>

    <table class="pf-table">
        <tr>
            <td style="width:50%;vertical-align:top;">
                <?= $P::label('Mode of Transaction:', 'mode_of_transaction') ?>
                <?= $P::radio('mode_of_transaction', 'Walk-in', 'Walk-in', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('mode_of_transaction', 'Visited', 'Visited', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('mode_of_transaction', 'Referral', 'Referral', $role, $fgTreat, $lockRecords) ?>

                <div class="pf-field-row" style="margin-top:6px;">
                    <?= $P::label('Date of Consultation (mm/dd/yyyy)', 'date_of_consultation') ?>
                    <?= $P::line('date_of_consultation', $role, $fgTreat, $lockRecords, false, 'date') ?>
                </div>
                <div class="pf-field-row pf-inline">
                    <div class="pf-field">
                        <?= $P::label('Consultation Time', 'consultation_time', false) ?>
                        <?= $P::line('consultation_time', $role, $fgTreat, $lockRecords, false, 'time') ?>
                    </div>
                    <div class="pf-field" style="max-width:100px;">
                        <?= $P::label("\xC2\xA0", 'consultation_period', false) ?>
                        <?= $P::select('consultation_period', ['' => '—', 'AM' => 'AM', 'PM' => 'PM'], $role, $fgTreat, $lockRecords) ?>
                    </div>
                </div>

                <table class="pf-table" style="margin-top:6px;">
                    <tr>
                        <td><?= $P::label('Blood Pressure', 'blood_pressure', false) ?><?= $P::line('blood_pressure', $role, $fgTreat, $lockRecords) ?></td>
                        <td><?= $P::label('Temperature', 'temperature', false) ?><?= $P::line('temperature', $role, $fgTreat, $lockRecords) ?></td>
                    </tr>
                    <tr>
                        <td><?= $P::label('Height (cm)', 'height_cm', false) ?><?= $P::line('height_cm', $role, $fgTreat, $lockRecords, false, 'number') ?></td>
                        <td><?= $P::label('Weight (kg)', 'weight_kg', false) ?><?= $P::line('weight_kg', $role, $fgTreat, $lockRecords, false, 'number') ?></td>
                    </tr>
                </table>

                <div class="pf-field-row" style="margin-top:6px;">
                    <?= $P::label('Name of Attending Provider', 'attending_provider', false) ?>
                    <?= $P::line('attending_provider', $role, $fgTreat, $lockRecords) ?>
                </div>
            </td>
            <td style="width:50%;vertical-align:top;">
                <strong style="font-size:8pt;">For REFERRAL Transaction only.</strong>
                <div class="pf-field-row">
                    <?= $P::label('REFERRED FROM:', 'referred_from', false) ?>
                    <?= $P::line('referred_from', $role, $fgTreat, $lockRecords) ?>
                    <?= $P::line('referred_from_2', $role, $fgTreat, $lockRecords, true) ?>
                </div>
                <div class="pf-field-row">
                    <?= $P::label('REFERRED TO:', 'referred_to', false) ?>
                    <?= $P::line('referred_to', $role, $fgTreat, $lockRecords) ?>
                    <?= $P::line('referred_to_2', $role, $fgTreat, $lockRecords, true) ?>
                </div>
                <div class="pf-field-row">
                    <?= $P::label('Reason(s) for Referral:', 'referral_reasons', false) ?>
                    <?= $P::area('referral_reasons', $role, $fgTreat, $lockRecords, false, 4) ?>
                </div>
                <div class="pf-field-row">
                    <?= $P::label('Referred by:', 'referred_by', false) ?>
                    <?= $P::line('referred_by', $role, $fgTreat, $lockRecords) ?>
                </div>
            </td>
        </tr>
    </table>

    <table class="pf-table">
        <tr>
            <td style="width:55%;vertical-align:top;">
                <?= $P::label('Nature of Visit:', 'nature_of_visit', false) ?>
                <?= $P::radio('nature_of_visit', 'New Consultation/Case', 'New Consultation/Case', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('nature_of_visit', 'New Admission', 'New Admission', $role, $fgTreat, $lockRecords) ?>
                <?= $P::radio('nature_of_visit', 'Follow-up Visit', 'Follow-up Visit', $role, $fgTreat, $lockRecords) ?>

                <div style="margin-top:6px;">
                    <?= $P::label('Type of Consultation / Purpose of Visit:', 'consultation_types', false) ?>
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
                <?= $P::label('Chief Complaints:', 'chief_complaints') ?>
                <?= $P::area('chief_complaints', $role, $fgTreat, $lockRecords, false, 8) ?>
            </td>
        </tr>
    </table>

    <table class="pf-table">
        <tr>
            <td colspan="2">
                <?= $P::label('Diagnosis:', 'diagnosis', false) ?>
                <?= $P::area('diagnosis', $role, $fgDoctor, $lockRecords, false, 2) ?>
            </td>
        </tr>
        <tr>
            <td style="width:65%;vertical-align:top;">
                <?= $P::label('Medication / Treatment:', 'medication_treatment', false) ?>
                <?= $P::area('medication_treatment', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
            <td style="width:35%;vertical-align:top;">
                <?= $P::label('Name of Health Care Provider:', 'healthcare_provider', false) ?>
                <?= $P::area('healthcare_provider', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top;">
                <?= $P::label('Assessment / Laboratory Findings / Impression:', 'laboratory_findings', false) ?>
                <?= $P::area('laboratory_findings', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
            <td style="vertical-align:top;">
                <?= $P::label('Performed Laboratory Test:', 'performed_lab_test', false) ?>
                <?= $P::area('performed_lab_test', $role, $fgDoctor, $lockRecords, false, 4) ?>
            </td>
        </tr>
        <tr>
            <td style="vertical-align:top;">
                <?= $P::label('Treatment Plan:', 'doctor_treatment_plan', false) ?>
                <?= $P::area('doctor_treatment_plan', $role, $fgDoctor, $lockRecords, true, 3) ?>
            </td>
            <td style="vertical-align:top;">
                <?= $P::label('Follow-up Instructions:', 'doctor_follow_up', false) ?>
                <?= $P::area('doctor_follow_up', $role, $fgDoctor, $lockRecords, true, 3) ?>
            </td>
        </tr>
    </table>

    <?= $P::formFooter('Clinic Information System | FORM 2 | Page 1') ?>
</div>
</div>
