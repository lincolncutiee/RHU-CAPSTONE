<?php
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'records_enrollment';
$fgConsent = 'records_consent';
$P = PaperFormRenderer::class;
?>
<div class="paper-sheet">
<div class="paper-sheet-inner">
    <div class="pf-doh-header">
        <div class="pf-doh-logo">
            Republic of the Philippines<br>
            <strong>DEPARTMENT OF HEALTH</strong><br>
            <span class="small">Kagawaran ng Kalusugan</span>
        </div>
        <div style="text-align:right;">
            <div style="margin-bottom:6px;">
                <span class="pf-label">Patient ID</span>
                <?= $P::line('patient_id', $role, $fg, $lockRecords, false, 'text', 'pf-line pf-serial-single', null, false, true) ?>
            </div>
            <div style="margin-bottom:6px;">
                <span class="pf-label">Family Serial Number</span>
                <?= $P::line('family_serial_number', $role, $fg, $lockRecords, false, 'text', 'pf-line pf-serial-single', '') ?>
            </div>
            <div>
                <span class="pf-label">Facility Code</span>
                <?= $P::line('facility_code', $role, $fg, $lockRecords, false, 'text', 'pf-line pf-serial-single', '') ?>
            </div>
        </div>
    </div>

    <div class="pf-title-sub">Integrated Clinic Information System (iCLINICSYS)</div>
    <div class="pf-title-main">PATIENT ENROLLMENT RECORD</div>
    <p class="pf-instructions">Instructions: For new patient only. Please print legibly and mark appropriate boxes with 'X'.<br>
    <em>Tagubilin: Para sa mga bagong pasyente lamang. Mangyaring sulatan nang mabuti at lagyan ng 'X' ang naaangkop na kahon.</em></p>

    <?= $P::sectionBar('I. PATIENT INFORMATION (IMPORMASYON NG PASYENTE)') ?>

    <table class="pf-table">
        <tr>
            <td style="width:55%;">
                <span class="pf-label">Last Name (Apelyido)</span>
                <?= $P::line('last_name', $role, $fg, $lockRecords, false, 'text', '', '', true) ?>
            </td>
            <td style="width:20%;">
                <span class="pf-label">Suffix (e.g., Sr., Jr. III)</span>
                <?= $P::line('suffix', $role, $fg, $lockRecords) ?>
            </td>
            <td rowspan="2" style="width:25%;vertical-align:top;">
                <span class="pf-label" style="font-size:7pt;">Please write Maiden Name (for married women)<br>Pangalan sa pagkadalaga (para sa mga babaeng may asawa)</span>
                <?= $P::line('maiden_name', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">First Name (Pangalan)</span>
                <?= $P::line('first_name', $role, $fg, $lockRecords, false, 'text', '', '', true) ?>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="pf-label">Middle Name (Gitnang Pangalan)</span>
                <?= $P::line('middle_name', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td>
                <span class="pf-label">Sex (Kasarian)</span>
                <?= $P::radio('sex', 'Female', 'Female (Babae)', $role, $fg, $lockRecords) ?>
                <?= $P::radio('sex', 'Male', 'Male (Lalaki)', $role, $fg, $lockRecords) ?>
            </td>
            <td colspan="2">
                <span class="pf-label">Mother's Name (Pangalan ng Ina)</span>
                <?= $P::line('mothers_name', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td>
                <span class="pf-label">Birth Date (Kaarawan) (mm/dd/yyyy)</span>
                <?= $P::line('birth_date', $role, $fg, $lockRecords, false, 'date', '', '', true) ?>
            </td>
            <td colspan="2" rowspan="3" style="vertical-align:top;">
                <span class="pf-label">Residential Address (Tirahan)</span>
                <?= $P::magallanesAddress($role, $fg, $lockRecords, 'full') ?>
            </td>
        </tr>
        <tr>
            <td colspan="1">
                <span class="pf-label">Birthplace (Lugar ng Kapanganakan)</span>
                <?= $P::line('birthplace', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td>
                <span class="pf-label">Blood Type</span>
                <?= $P::line('blood_type', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align:top;">
                <span class="pf-label">Civil Status (Katayuang Sibil)</span>
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
                <span class="pf-label">Contact Number</span>
                <?= $P::line('contact_number', $role, $fg, $lockRecords) ?>
                <div style="margin-top:6px;">
                    <span class="pf-label">DSWD NHTS?</span>
                    <?= $P::radio('dswd_nhts', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('dswd_nhts', 'No', 'No', $role, $fg, $lockRecords) ?>
                </div>
                <div style="margin-top:4px;">
                    <span class="pf-label">Facility Household No.</span>
                    <?= $P::line('facility_household_no', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">Spouse's Name (Asawa)</span>
                <?= $P::line('spouse_name', $role, $fg, $lockRecords) ?>
            </td>
            <td>
                <span class="pf-label">4Ps Member?</span>
                <?= $P::radio('four_ps_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                <?= $P::radio('four_ps_member', 'No', 'No', $role, $fg, $lockRecords) ?>
                <div style="margin-top:4px;">
                    <span class="pf-label">Household No.</span>
                    <?= $P::line('household_no', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align:top;">
                <span class="pf-label">Educational Attainment (Pang-edukasyong Katinpuan)</span>
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
                <span class="pf-label">PhilHealth Member?</span>
                <?= $P::radio('philhealth_member', 'Yes', 'Yes', $role, $fg, $lockRecords) ?>
                <?= $P::radio('philhealth_member', 'No', 'No', $role, $fg, $lockRecords) ?>
                <div style="margin-top:4px;">
                    <span class="pf-label">Status Type:</span>
                    <?= $P::radio('philhealth_status_type', 'Member', 'Member', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('philhealth_status_type', 'Dependent', 'Dependent', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="vertical-align:top;">
                <span class="pf-label">Employment Status (Katayuan sa Pagtrabaho)</span>
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
                <span class="pf-label">PhilHealth No.</span>
                <?= $P::line('philhealth_no', $role, $fg, $lockRecords) ?>
                <div style="margin-top:4px;">
                    <span class="pf-label">IF Member, please FE - Private:</span>
                    <?= $P::line('philhealth_fe_private', $role, $fg, $lockRecords, true) ?>
                </div>
                <div style="margin-top:4px;">
                    <span class="pf-label">IF Member, please FE - Government:</span>
                    <?= $P::line('philhealth_fe_government', $role, $fg, $lockRecords, true) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">If Employed, Specific Occupation:</span>
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
                        <th>Family Member (Pauulyan sa Pamilya)</th>
                        <th>Father (Ama)</th>
                        <th>Mother (Ina)</th>
                        <th>Son (Anak na lalaki)</th>
                        <th>Daughter (Anak na babae)</th>
                        <th>Others (Iba)</th>
                        <th>PCB Member?</th>
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
            <p>I hereby authorize the CHU/RHU to collect, use, and disclose my personal and health information for treatment, public health programs, and reporting requirements under applicable laws.</p>
            <p>I understand that I may refuse to give consent or withdraw consent at any time, and that my medical care will not be conditioned on signing this form.</p>
        </div>
        <div>
            <p>Ako ay nagbibigay ng pahintulot sa CHU/RHU na kolektahin, gamitin, at ibunyag ang aking personal at impormasyong pangkalusugan para sa paggamot, mga programang pangkalusugan, at mga kinakailangang ulat sa ilalim ng mga naaangkop na batas.</p>
            <p>Nauunawaan ko na maaari akong tumanggi o bawiin ang aking pahintulot anumang oras, at ang aking pangangalaga ay hindi nakasalalay sa paglagda sa form na ito.</p>
        </div>
    </div>

    <table class="pf-table" style="margin-top:8px;border:none;">
        <tr>
            <td style="width:50%;border:none;vertical-align:bottom;">
                <div class="pf-sig-line"><?= $P::line('consent_signature', $role, $fgConsent, $lockRecords, true) ?></div>
                <div class="pf-sig-label">SIGNATURE OF PATIENT / DATE / PIRMA NG PASYENTE / PETSA</div>
                <?= $P::line('consent_signature_date', $role, $fgConsent, $lockRecords, false, 'date') ?>
            </td>
            <td style="width:50%;border:none;vertical-align:bottom;">
                <div class="pf-sig-line"><?= $P::line('chu_representative', $role, $fgConsent, $lockRecords) ?></div>
                <div class="pf-sig-label">NAME OF CHU/RHU REPRESENTATIVE / KINATAWAN NG CHU/RHU</div>
            </td>
        </tr>
    </table>

    <?= $P::formFooter('Clinic Information System | FORM 1') ?>
</div>
</div>
