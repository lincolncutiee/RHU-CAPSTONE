<?php
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'it';
$P = PaperFormRenderer::class;
?>
<div class="paper-sheet">
<div class="paper-sheet-inner">
    <table class="pf-table" style="border:none;margin-bottom:8px;">
        <tr>
            <td style="border:none;width:30%;vertical-align:middle;">
                <strong style="font-size:11pt;color:#0066b3;">PhilHealth</strong>
            </td>
            <td style="border:none;text-align:center;vertical-align:middle;">
                <div class="pf-title-main" style="font-size:12pt;">PhilHealth Konsulta Registration Form (PKRF)</div>
            </td>
        </tr>
    </table>

    <div style="margin-bottom:8px;">
        <strong style="color:#0066b3;font-size:8.5pt;">INSTRUCTIONS</strong>
        <ol class="pf-instructions" style="margin-top:4px;">
            <li>All information should be written in UPPER CASE/ CAPITAL LETTER.</li>
            <li>All fields are mandatory.</li>
            <li>If the beneficiary is dependent, use the dependent PIN.</li>
            <li>If the beneficiary is below 21 years old, the signatory should be the parent/guardian.</li>
        </ol>
    </div>

    <?= $P::sectionBar('TO BE FILLED-OUT BY THE BENEFICIARY') ?>

    <div class="pf-field-row pf-inline" style="margin-top:6px;">
        <?= $P::radio('philhealth_status_type', 'Member', 'MEMBER', $role, $fg, $lockRecords) ?>
        <?= $P::radio('philhealth_status_type', 'Dependent', 'DEPENDENT', $role, $fg, $lockRecords) ?>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field" style="flex:1;">
            <span class="pf-label">PATIENT ID:</span>
            <?= $P::line('patient_id', $role, $fg, $lockRecords, false, 'text', 'pf-line', null, false, true) ?>
        </div>
        <div class="pf-field" style="flex:1;">
            <span class="pf-label">FAMILY SERIAL NO.:</span>
            <?= $P::line('family_serial_number', $role, $fg, $lockRecords) ?>
        </div>
        <div class="pf-field" style="flex:1;">
            <span class="pf-label">FACILITY CODE:</span>
            <?= $P::line('facility_code', $role, $fg, $lockRecords) ?>
        </div>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field" style="flex:2;">
            <span class="pf-label">PIN:</span>
            <?= $P::line('philhealth_no', $role, $fg, $lockRecords) ?>
        </div>
        <div class="pf-field" style="flex:1;">
            <span class="pf-label">DATE:</span>
            <?= $P::line('pkrf_registration_date', $role, $fg, $lockRecords, true, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">FULL NAME:</span>
        <table class="pf-table" style="margin:0;">
            <tr>
                <td style="width:25%;border:none;padding:0 4px 0 0;"><?= $P::line('last_name', $role, $fg, $lockRecords, false, 'text', '', '') ?></td>
                <td style="width:25%;border:none;padding:0 4px;"><?= $P::line('first_name', $role, $fg, $lockRecords, false, 'text', '', '') ?></td>
                <td style="width:25%;border:none;padding:0 4px;"><?= $P::line('middle_name', $role, $fg, $lockRecords) ?></td>
                <td style="width:25%;border:none;padding:0 0 0 4px;"><?= $P::line('suffix', $role, $fg, $lockRecords) ?></td>
            </tr>
            <tr>
                <td style="border:none;padding:0;"><span class="pf-sublabel">(LAST NAME)</span></td>
                <td style="border:none;padding:0;"><span class="pf-sublabel">(FIRST NAME)</span></td>
                <td style="border:none;padding:0;"><span class="pf-sublabel">(MIDDLE NAME)</span></td>
                <td style="border:none;padding:0;"><span class="pf-sublabel">(SUFFIX)</span></td>
            </tr>
        </table>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">ADDRESS:</span>
        <?= $P::magallanesAddress($role, $fg, $lockRecords, 'pkrf') ?>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field">
            <span class="pf-label">DATE OF BIRTH:</span>
            <?= $P::line('birth_date', $role, $fg, $lockRecords, false, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
        <div class="pf-field">
            <span class="pf-label">SEX:</span>
            <?= $P::radio('sex', 'Female', 'Female', $role, $fg, $lockRecords) ?>
            <?= $P::radio('sex', 'Male', 'Male', $role, $fg, $lockRecords) ?>
        </div>
        <div class="pf-field" style="flex:2;">
            <span class="pf-label">CONTACT NO.:</span>
            <?= $P::line('contact_number', $role, $fg, $lockRecords) ?>
        </div>
    </div>

    <div class="pf-field-row">
        <?= $P::checkbox('pkrf_register_kpp', '1', 'REGISTER TO A KONSULTA PACKAGE PROVIDER (KPP)', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <?= $P::checkbox('pkrf_register_dependents', '1', 'REGISTER ALL MY DECLARED MINOR DEPENDENTS (please use additional form if necessary)', $role, $fg, $lockRecords, true) ?>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">FULL NAME:</span>
        <table class="pf-table" style="margin:0;">
            <tr>
                <td style="width:33%;border:none;padding:0 4px 0 0;"><?= $P::line('pkrf_signatory_last', $role, $fg, $lockRecords, true) ?></td>
                <td style="width:33%;border:none;padding:0 4px;"><?= $P::line('pkrf_signatory_first', $role, $fg, $lockRecords, true) ?></td>
                <td style="width:34%;border:none;padding:0 0 0 4px;"><?= $P::line('pkrf_signatory_middle', $role, $fg, $lockRecords, true) ?></td>
            </tr>
            <tr>
                <td style="border:none;"><span class="pf-sublabel">(LAST NAME)</span></td>
                <td style="border:none;"><span class="pf-sublabel">(FIRST NAME)</span></td>
                <td style="border:none;"><span class="pf-sublabel">(MIDDLE NAME)</span></td>
            </tr>
        </table>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">1ST CHOICE KPP:</span>
        <?= $P::line('pkrf_kpp_1st', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <span class="pf-label">ADDRESS:</span>
        <table class="pf-table" style="margin:0;">
            <tr>
                <td style="width:33%;border:none;padding:0 4px 0 0;"><?= $P::line('pkrf_kpp1_barangay', $role, $fg, $lockRecords, true) ?></td>
                <td style="width:33%;border:none;padding:0 4px;"><?= $P::line('pkrf_kpp1_city', $role, $fg, $lockRecords, true) ?></td>
                <td style="width:34%;border:none;padding:0 0 0 4px;"><?= $P::line('pkrf_kpp1_province', $role, $fg, $lockRecords, true) ?></td>
            </tr>
            <tr>
                <td style="border:none;"><span class="pf-sublabel">(BARANGAY/TOWN)</span></td>
                <td style="border:none;"><span class="pf-sublabel">(MUNICIPALITY/CITY)</span></td>
                <td style="border:none;"><span class="pf-sublabel">(PROVINCE)</span></td>
            </tr>
        </table>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">2ND CHOICE KPP:</span>
        <?= $P::line('pkrf_kpp_2nd', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <span class="pf-label">ADDRESS:</span>
        <table class="pf-table" style="margin:0;">
            <tr>
                <td style="width:33%;border:none;padding:0 4px 0 0;"><?= $P::line('pkrf_kpp2_barangay', $role, $fg, $lockRecords, true) ?></td>
                <td style="width:33%;border:none;padding:0 4px;"><?= $P::line('pkrf_kpp2_city', $role, $fg, $lockRecords, true) ?></td>
                <td style="width:34%;border:none;padding:0 0 0 4px;"><?= $P::line('pkrf_kpp2_province', $role, $fg, $lockRecords, true) ?></td>
            </tr>
            <tr>
                <td style="border:none;"><span class="pf-sublabel">(BARANGAY/TOWN)</span></td>
                <td style="border:none;"><span class="pf-sublabel">(MUNICIPALITY/CITY)</span></td>
                <td style="border:none;"><span class="pf-sublabel">(PROVINCE)</span></td>
            </tr>
        </table>
    </div>

    <div class="pf-field-row">
        <?= $P::checkbox('pkrf_transfer', '1', 'TRANSFER', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <span class="pf-label">PREVIOUS KPP:</span>
        <?= $P::line('pkrf_previous_kpp', $role, $fg, $lockRecords, true) ?>
    </div>

    <p style="font-size:7.5pt;font-weight:bold;margin:10px 0 6px;">
        I HEREBY CERTIFY THAT I DID NOT AVAIL OF FIRST PATIENT ENCOUNTER (FPE) IN MY PREVIOUS KPP.
    </p>
    <div class="pf-sig-line"><?= $P::line('pkrf_signature', $role, $fg, $lockRecords, true) ?></div>
    <div class="pf-sig-label">(Signature over Printed Name)</div>
    <div style="text-align:right;font-size:7pt;font-weight:bold;margin-top:8px;">PHILHEALTH'S COPY</div>

    <div class="pf-tear-line"></div>

    <?= $P::sectionBar('TO BE FILLED-OUT BY PHILHEALTH KONSULTA PERSONNEL') ?>
    <div class="pf-title-main" style="font-size:10pt;">PHILHEALTH KONSULTA REGISTRATION CONFIRMATION SLIP</div>

    <div class="pf-field-row pf-inline" style="margin-top:8px;">
        <div class="pf-field" style="flex:2;">
            <span class="pf-label">REGISTRATION NO.:</span>
            <?= $P::line('pkrf_confirmation_no', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field">
            <span class="pf-label">DATE REGISTERED:</span>
            <?= $P::line('pkrf_date_registered', $role, $fg, $lockRecords, true, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">FULL NAME:</span>
        <?= $P::line('pkrf_confirm_full_name', $role, $fg, $lockRecords, true) ?>
        <p class="pf-sublabel" style="text-align:left;font-style:normal;">(Same as beneficiary information above)</p>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field" style="flex:2;">
            <span class="pf-label">PIN:</span>
            <?= $P::line('pkrf_confirm_pin', $role, $fg, $lockRecords, true) ?>
            <p class="pf-sublabel" style="text-align:left;font-style:normal;">(Same as above)</p>
        </div>
        <div class="pf-field">
            <span class="pf-label">DATE OF BIRTH:</span>
            <?= $P::line('pkrf_confirm_birth_date', $role, $fg, $lockRecords, true, 'date') ?>
            <p class="pf-sublabel" style="text-align:left;font-style:normal;">(Same as above)</p>
        </div>
    </div>

    <div class="pf-field-row">
        <span class="pf-label">KPP:</span>
        <?= $P::line('pkrf_konsulta_package', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <span class="pf-label">ADDRESS:</span>
        <?= $P::line('pkrf_confirm_address', $role, $fg, $lockRecords, true) ?>
        <p class="pf-sublabel" style="text-align:left;font-style:normal;">(Same as beneficiary address above)</p>
    </div>

    <div class="pf-sig-line"><?= $P::line('pkrf_personnel_signature', $role, $fg, $lockRecords, true) ?></div>
    <div class="pf-sig-label">(Signature over Printed Name of Authorized Personnel)</div>
    <div style="text-align:right;font-size:7pt;font-weight:bold;margin-top:8px;">BENEFICIARY'S COPY</div>
</div>
</div>
