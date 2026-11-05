<?php
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'records_enrollment';
$fgConsent = 'records_consent';
$P = PaperFormRenderer::class;
$p = 'enrollment';
?>
<div class="paper-sheet">
<div class="paper-sheet-inner">
    <div class="pf-doh-header">
        <div class="pf-doh-logo">
            <?= $P::text('page.' . $p . '.doh.line1', 'Republic of the Philippines') ?><br>
            <strong><?= $P::text('page.' . $p . '.doh.line2', 'DEPARTMENT OF HEALTH') ?></strong><br>
            <span class="small"><?= $P::text('page.' . $p . '.doh.line3', 'Kagawaran ng Kalusugan') ?></span>
        </div>
        <div style="text-align:right;">
            <div style="margin-bottom:6px;">
                <?= $P::label('PIN:', 'patient_id', false) ?>
                <?= $P::line('patient_id', $role, $fg, $lockRecords, false, 'text', 'pf-line pf-serial-single', null, false, true) ?>
            </div>
            <div style="margin-bottom:6px;">
                <?= $P::label('Family Serial Number', 'family_serial_number') ?>
                <?= $P::line('family_serial_number', $role, $fg, $lockRecords, false, 'text', 'pf-line pf-serial-single', '') ?>
            </div>
            <div>
                <?= $P::label('Facility Code', 'facility_code', false) ?>
                <?= $P::line('facility_code', $role, $fg, $lockRecords, false, 'text', 'pf-line pf-serial-single', '') ?>
            </div>
        </div>
    </div>

    <?= $P::titleSub('Integrated Clinic Information System (iCLINICSYS)') ?>
    <?= $P::titleMain('PATIENT ENROLLMENT RECORD') ?>
    <?= $P::instructionsBlock('page.enrollment.instructions', "Instructions: For new patient only. Please print legibly and mark appropriate boxes with 'X'.\nTagubilin: Para sa mga bagong pasyente lamang. Mangyaring sulatan nang mabuti at lagyan ng 'X' ang naaangkop na kahon.") ?>

    <?= $P::sectionBar('I. PATIENT INFORMATION (IMPORMASYON NG PASYENTE)') ?>

    <table class="pf-table">
        <tr>
            <td style="width:55%;">
                <?= $P::label('Last Name (Apelyido)', 'last_name') ?>
                <?= $P::line('last_name', $role, $fg, $lockRecords, false, 'text', '', '', true) ?>
            </td>
            <td style="width:20%;">
                <?= $P::label('Suffix (e.g., Sr., Jr. III)', 'suffix', false) ?>
                <?= $P::line('suffix', $role, $fg, $lockRecords) ?>
            </td>
            <td rowspan="2" style="width:25%;vertical-align:top;">
                <?= $P::labelHtml('Please write Maiden Name (for married women)<br>Pangalan sa pagkadalaga (para sa mga babaeng may asawa)', 'maiden_name', false, '', 'font-size:7pt;') ?>
                <?= $P::line('maiden_name', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <?= $P::label('First Name (Pangalan)', 'first_name') ?>
                <?= $P::line('first_name', $role, $fg, $lockRecords, false, 'text', '', '', true) ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <?= $P::label('Middle Name (Gitnang Pangalan)', 'middle_name', false) ?>
                <?= $P::line('middle_name', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td>
                <?= $P::label('Sex (Kasarian)', 'sex') ?>
                <?= $P::radio('sex', 'Female', 'Female (Babae)', $role, $fg, $lockRecords) ?>
                <?= $P::radio('sex', 'Male', 'Male (Lalaki)', $role, $fg, $lockRecords) ?>
            </td>
            <td colspan="2">
                <?= $P::label('Mother\'s Name (Pangalan ng Ina)', 'mothers_name', false) ?>
                <?= $P::line('mothers_name', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td>
                <?= $P::label('Birth Date (Kaarawan) (mm/dd/yyyy)', 'birth_date') ?>
                <?= $P::line('birth_date', $role, $fg, $lockRecords, false, 'date', '', '', true) ?>
            </td>
            <td colspan="2" rowspan="3" style="vertical-align:top;">
                <?= $P::label('Residential Address (Tirahan)', 'address_barangay', false) ?>
                <?= $P::magallanesAddress($role, $fg, $lockRecords, 'full') ?>
            </td>
        </tr>
        <tr>
            <td colspan="1">
                <?= $P::label('Birthplace (Lugar ng Kapanganakan)', 'birthplace', false) ?>
                <?= $P::line('birthplace', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td>
                <?= $P::label('Blood Type', 'blood_type', false) ?>
                <?= $P::line('blood_type', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align:top;">
                <?= $P::label('Civil Status (Katayuang Sibil)', 'civil_status', false) ?>
                <div class="pf-check-grid">
                    <?= $P::radio('civil_status', 'Single', 'Single (Walang Asawa)', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('civil_status', 'Married', 'Married (May Asawa)', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('civil_status', 'Widower', 'Widower (Balo)', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('civil_status', 'Widow', 'Widow (Balo)', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('civil_status', 'Separated', 'Separated (Hiwalay)', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('civil_status', 'Annulled', 'Annulled (Anulado)', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('civil_status', 'Co-Habitation', 'Co-Habitation (Paninirahan na magkasama)', $role, $fg, $lockRecords) ?>
                </div>
            </td>
            <td style="vertical-align:top;">
                <?= $P::label('Contact Number', 'contact_number') ?>
                <?= $P::line('contact_number', $role, $fg, $lockRecords) ?>
                <div style="margin-top:6px;">
                    <?= $P::label('DSWD NHTS?', 'dswd_nhts', false) ?>
                    <?= $P::radio('dswd_nhts', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('dswd_nhts', 'No', 'No', $role, $fg, $lockRecords) ?>
                </div>
                <div style="margin-top:4px;">
                    <?= $P::label('Facility Household No.', 'facility_household_no', false) ?>
                    <?= $P::line('facility_household_no', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <?= $P::label('Spouse\'s Name (Asawa)', 'spouse_name', false) ?>
                <?= $P::line('spouse_name', $role, $fg, $lockRecords) ?>
            </td>
            <td>
                <?= $P::label('4Ps Member?', 'four_ps_member', false) ?>
                <?= $P::radio('four_ps_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                <?= $P::radio('four_ps_member', 'No', 'No', $role, $fg, $lockRecords) ?>
                <div style="margin-top:4px;">
                    <?= $P::label('Household No.', 'household_no', false) ?>
                    <?= $P::line('household_no', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align:top;">
                <?= $P::label('Educational Attainment (Pang-edukasyong Katinpuan)', 'educational_attainment', false) ?>
                <div class="pf-check-grid-1">
                    <?php
                    $eduOpts = ['No Formal Education', 'Elementary', 'Highschool', 'High School', 'Vocational', 'Post Graduate'];
                    foreach ($eduOpts as $opt):
                        echo $P::radio('educational_attainment', $opt, $opt, $role, $fg, $lockRecords);
                    endforeach;
                    ?>
                </div>
            </td>
            <td style="vertical-align:top;">
                <?= $P::label('PhilHealth Member?', 'philhealth_member', false) ?>
                <?= $P::radio('philhealth_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                <?= $P::radio('philhealth_member', 'No', 'No', $role, $fg, $lockRecords) ?>
                <div style="margin-top:4px;">
                    <?= $P::label('Status Type:', 'philhealth_status_type', false) ?>
                    <?= $P::radio('philhealth_status_type', 'Member', 'Member', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('philhealth_status_type', 'Dependent', 'Dependent', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align:top;">
                <?= $P::label('Employment Status (Katayuan sa Pagtrabaho)', 'employment_status', false) ?>
                <div class="pf-check-grid">
                    <?php
                    $empOpts = ['Student', 'Employed', 'Unemployed', 'Unknown', 'Retired', 'Others'];
                    foreach ($empOpts as $opt):
                        echo $P::radio('employment_status', $opt, $opt, $role, $fg, $lockRecords);
                    endforeach;
                    ?>
                </div>
            </td>
            <td style="vertical-align:top;">
                <?= $P::label('PhilHealth No.', 'philhealth_no', false) ?>
                <?= $P::line('philhealth_no', $role, $fg, $lockRecords) ?>
                <div style="margin-top:4px;">
                    <?= $P::label('IF Member, please FE - Private:', 'philhealth_fe_private', false) ?>
                    <?= $P::line('philhealth_fe_private', $role, $fg, $lockRecords, true) ?>
                </div>
                <div style="margin-top:4px;">
                    <?= $P::label('IF Member, please FE - Government:', 'philhealth_fe_government', false) ?>
                    <?= $P::line('philhealth_fe_government', $role, $fg, $lockRecords, true) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <?= $P::label('If Employed, Specific Occupation:', 'specific_occupation', false) ?>
                <?= $P::line('specific_occupation', $role, $fg, $lockRecords) ?>
            </td>
            <td>
                <?= $P::radio('employment_type', 'CONTRACTUAL', 'CONTRACTUAL', $role, $fg, $lockRecords) ?>
                <?= $P::radio('employment_type', 'CASUAL', 'CASUAL', $role, $fg, $lockRecords) ?>
                <?= $P::radio('employment_type', 'PERMANENT', 'PERMANENT', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <table class="pf-table">
                    <tr>
                        <?= $P::th('page.enrollment.table.family_member', 'Family Member (Pauulyan sa Pamilya)') ?>
                        <?= $P::th('page.enrollment.table.father', 'Father (Ama)') ?>
                        <?= $P::th('page.enrollment.table.mother', 'Mother (Ina)') ?>
                        <?= $P::th('page.enrollment.table.son', 'Son (Anak na lalaki)') ?>
                        <?= $P::th('page.enrollment.table.daughter', 'Daughter (Anak na babae)') ?>
                        <?= $P::th('page.enrollment.table.others', 'Others (Iba)') ?>
                        <?= $P::th('page.enrollment.table.pcb', 'PCB Member?') ?>
                    </tr>
                    <tr>
                        <td style="font-size:7pt;">&nbsp;</td>
                        <td><?= $P::line('father_name', $role, $fg, $lockRecords) ?></td>
                        <td><?= $P::line('mother_name', $role, $fg, $lockRecords) ?></td>
                        <td><?= $P::line('son_name', $role, $fg, $lockRecords) ?></td>
                        <td><?= $P::line('daughter_name', $role, $fg, $lockRecords) ?></td>
                        <td><?= $P::line('other_family_member', $role, $fg, $lockRecords) ?></td>
                        <td>
                            <?= $P::radio('pcb_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                            <?= $P::radio('pcb_member', 'No', 'No', $role, $fg, $lockRecords) ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <?= $P::sectionBar("II. PATIENT'S CONSENT (PAHINTULOT NG PASYENTE)") ?>

    <div class="pf-two-col">
        <div>
            <?= $P::para('page.enrollment.consent.en.1', 'I hereby authorize the CHU/RHU to collect, use, and disclose my personal and health information for treatment, public health programs, and reporting requirements under applicable laws.') ?>
            <?= $P::para('page.enrollment.consent.en.2', 'I understand that I may refuse to give consent or withdraw consent at any time, and that my medical care will not be conditioned on signing this form.') ?>
        </div>
        <div>
            <?= $P::para('page.enrollment.consent.tl.1', 'Ako ay nagbibigay ng pahintulot sa CHU/RHU na kolektahin, gamitin, at ibunyag ang aking personal at impormasyong pangkalusugan para sa paggamot, mga programang pangkalusugan, at mga kinakailangang ulat sa ilalim ng mga naaangkop na batas.') ?>
            <?= $P::para('page.enrollment.consent.tl.2', 'Nauunawaan ko na maaari akong tumanggi o bawiin ang aking pahintulot anumang oras, at ang aking pangangalaga ay hindi nakasalalay sa paglagda sa form na ito.') ?>
        </div>
    </div>

    <table class="pf-table" style="margin-top:8px;border:none;">
        <tr>
            <td style="width:50%;border:none;vertical-align:bottom;">
                <div class="pf-sig-line"><?= $P::line('consent_signature', $role, $fgConsent, $lockRecords, true) ?></div>
                <?= $P::sigLabel('page.enrollment.sig.patient', 'SIGNATURE OF PATIENT / DATE / PIRMA NG PASYENTE / PETSA') ?>
                <?= $P::line('consent_signature_date', $role, $fgConsent, $lockRecords, false, 'date') ?>
            </td>
            <td style="width:50%;border:none;vertical-align:bottom;">
                <div class="pf-sig-line"><?= $P::line('chu_representative', $role, $fgConsent, $lockRecords) ?></div>
                <?= $P::sigLabel('page.enrollment.sig.representative', 'NAME OF CHU/RHU REPRESENTATIVE / KINATAWAN NG CHU/RHU') ?>
            </td>
        </tr>
    </table>

    <?= $P::formFooter('Clinic Information System | FORM 1') ?>
</div>
</div>
