<?php
/** @var string $role */
/** @var bool $lockRecords */
$fg = 'it';
$P = PaperFormRenderer::class;

$nameRow = static function (string $last, string $first, string $middle, bool $extended = false) use ($P, $role, $fg, $lockRecords): string {
    return '<table class="pf-table pf-name-row" style="margin:0;"><tr>'
        . '<td style="width:33%;border:none;padding:0 4px 0 0;">' . $P::line($last, $role, $fg, $lockRecords, $extended) . '</td>'
        . '<td style="width:33%;border:none;padding:0 4px;">' . $P::line($first, $role, $fg, $lockRecords, $extended) . '</td>'
        . '<td style="width:34%;border:none;padding:0 0 0 4px;">' . $P::line($middle, $role, $fg, $lockRecords, $extended) . '</td>'
        . '</tr><tr>'
        . '<td style="border:none;"><span class="pf-sublabel">(LAST NAME)</span></td>'
        . '<td style="border:none;"><span class="pf-sublabel">(FIRST NAME)</span></td>'
        . '<td style="border:none;"><span class="pf-sublabel">(MIDDLE NAME)</span></td>'
        . '</tr></table>';
};

$addrRow = static function (string $brgy, string $city, string $prov) use ($P, $role, $fg, $lockRecords): string {
    return '<table class="pf-table" style="margin:0;"><tr>'
        . '<td style="width:33%;border:none;padding:0 4px 0 0;">' . $P::line($brgy, $role, $fg, $lockRecords, true) . '</td>'
        . '<td style="width:33%;border:none;padding:0 4px;">' . $P::line($city, $role, $fg, $lockRecords, true) . '</td>'
        . '<td style="width:34%;border:none;padding:0 0 0 4px;">' . $P::line($prov, $role, $fg, $lockRecords, true) . '</td>'
        . '</tr><tr>'
        . '<td style="border:none;"><span class="pf-sublabel">(BARANGAY / TOWN)</span></td>'
        . '<td style="border:none;"><span class="pf-sublabel">(MUNICIPALITY / CITY)</span></td>'
        . '<td style="border:none;"><span class="pf-sublabel">(PROVINCE)</span></td>'
        . '</tr></table>';
};
?>
<div class="paper-sheet paper-sheet--pcsf">
<div class="paper-sheet-inner">
    <table class="pf-table pf-pcsf-header" style="border:none;margin-bottom:6px;">
        <tr>
            <td style="border:none;width:28%;vertical-align:middle;">
                <strong style="font-size:11pt;color:#0066b3;">PhilHealth</strong>
            </td>
            <td style="border:none;text-align:center;vertical-align:middle;">
                <div class="pf-title-main pf-pcsf-title"><?= FormTemplate::html('page.pcsf.title_main', 'Primary Care Selection Form (PCSF)') ?></div>
            </td>
        </tr>
    </table>

    <div class="pf-pcsf-instructions-wrap">
        <strong style="color:#0066b3;font-size:8.5pt;">INSTRUCTIONS</strong>
        <ol class="pf-instructions pf-pcsf-instructions">
            <li>All information should be written in UPPER CASE/ CAPITAL LETTER.</li>
            <li>Fields may be filled as available.</li>
            <li>If the beneficiary is dependent, use the dependent PIN.</li>
            <li>If the beneficiary is below 21 years old, the signatory should be the parent/guardian.</li>
        </ol>
    </div>

    <?= $P::sectionBar('TO BE FILLED-OUT BY THE BENEFICIARY') ?>

    <div class="pf-field-row pf-inline pf-pcsf-member-row">
        <?= $P::radio('philhealth_status_type', 'Member', 'MEMBER', $role, $fg, $lockRecords) ?>
        <?= $P::radio('philhealth_status_type', 'Dependent', 'DEPENDENT', $role, $fg, $lockRecords) ?>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field" style="flex:2;">
            <?= $P::label('PIN:', 'philhealth_no') ?>
            <?= $P::line('philhealth_no', $role, $fg, $lockRecords) ?>
        </div>
        <div class="pf-field" style="flex:1;">
            <?= $P::label('DATE:', 'pcsf_date') ?>
            <?= $P::line('pcsf_date', $role, $fg, $lockRecords, true, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
    </div>

    <div class="pf-field-row">
        <?= $P::label('FULL NAME:', 'last_name') ?>
        <?= $nameRow('last_name', 'first_name', 'middle_name') ?>
    </div>

    <div class="pf-field-row">
        <?= $P::label('ADDRESS:', 'address_barangay') ?>
        <?= $P::magallanesAddress($role, $fg, $lockRecords, 'pcsf') ?>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field">
            <?= $P::label('DATE OF BIRTH:', 'birth_date') ?>
            <?= $P::line('birth_date', $role, $fg, $lockRecords, false, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
        <div class="pf-field" style="flex:2;">
            <?= $P::label('CONTACT NUMBER:', 'contact_number') ?>
            <?= $P::line('contact_number', $role, $fg, $lockRecords) ?>
        </div>
    </div>

    <div class="pf-field-row">
        <?= $P::label('EMAIL ADDRESS:', 'pcsf_email') ?>
        <?= $P::line('pcsf_email', $role, $fg, $lockRecords, true) ?>
    </div>

    <div class="pf-field-row">
        <?= $P::checkbox('pcsf_register_pcc', '1', 'REGISTER TO A PRIMARY CARE CLINIC (PCC)', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <?= $P::checkbox('pcsf_register_dependents', '1', 'REGISTER ALL MY DECLARED MINOR DEPENDENTS (PLEASE USE ADDITIONAL FORM IF NECESSARY)', $role, $fg, $lockRecords, true) ?>
    </div>

    <div class="pf-field-row">
        <?= $P::label('FULL NAME:', 'pcsf_signatory_last', false) ?>
        <?= $nameRow('pcsf_signatory_last', 'pcsf_signatory_first', 'pcsf_signatory_middle', true) ?>
    </div>

    <div class="pf-field-row">
        <?= $P::label('1ST CHOICE PCC:', 'pcsf_pcc_1st', false) ?>
        <?= $P::line('pcsf_pcc_1st', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <?= $P::label('ADDRESS:', 'pcsf_pcc1_barangay', false) ?>
        <?= $addrRow('pcsf_pcc1_barangay', 'pcsf_pcc1_city', 'pcsf_pcc1_province') ?>
    </div>

    <div class="pf-field-row">
        <?= $P::label('2ND CHOICE PCC:', 'pcsf_pcc_2nd', false) ?>
        <?= $P::line('pcsf_pcc_2nd', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <?= $P::label('ADDRESS:', 'pcsf_pcc2_barangay', false) ?>
        <?= $addrRow('pcsf_pcc2_barangay', 'pcsf_pcc2_city', 'pcsf_pcc2_province') ?>
    </div>

    <div class="pf-bordered-box pf-pcsf-transfer-box">
        <div class="pf-field-row">
            <?= $P::checkbox('pcsf_transfer', '1', 'TRANSFER', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field-row">
            <?= $P::label('PREVIOUS PCC:', 'pcsf_previous_pcc', false) ?>
            <?= $P::line('pcsf_previous_pcc', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field-row">
            <?= $P::label('1ST CHOICE PCC:', 'pcsf_transfer_pcc_1st', false) ?>
            <?= $P::line('pcsf_transfer_pcc_1st', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field-row">
            <?= $P::label('ADDRESS:', 'pcsf_transfer_pcc1_barangay', false) ?>
            <?= $addrRow('pcsf_transfer_pcc1_barangay', 'pcsf_transfer_pcc1_city', 'pcsf_transfer_pcc1_province') ?>
        </div>
        <div class="pf-field-row">
            <?= $P::label('2ND CHOICE PCC:', 'pcsf_transfer_pcc_2nd', false) ?>
            <?= $P::line('pcsf_transfer_pcc_2nd', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field-row">
            <?= $P::label('ADDRESS:', 'pcsf_transfer_pcc2_barangay', false) ?>
            <?= $addrRow('pcsf_transfer_pcc2_barangay', 'pcsf_transfer_pcc2_city', 'pcsf_transfer_pcc2_province') ?>
        </div>
        <p class="pf-pcsf-certify">
            I HEREBY CERTIFY THAT I DID NOT AVAIL OF FIRST PATIENT ENCOUNTER (FPE) IN MY PREVIOUS PC.
        </p>
    </div>

    <div class="pf-pcsf-privacy">
        <strong>PRIVACY NOTICE:</strong>
        Pursuant to Republic Act No. 10173 (Data Privacy Act of 2012), the Philippine Health Insurance Corporation (PhilHealth)
        collects personal data from its members and beneficiaries for processing claims and other transactions. PhilHealth may
        share said information with relevant government agencies as required by law. For concerns, contact the PhilHealth Data
        Protection Officer at <strong>dpo@philhealth.gov.ph</strong> or visit <strong>philhealth.gov.ph/privacy</strong>.
    </div>

    <div class="pf-sig-line pf-pcsf-sig"><?= $P::line('pcsf_signature', $role, $fg, $lockRecords, true) ?></div>
    <div class="pf-sig-label">(SIGNATURE OVER PRINTED NAME)</div>
    <div class="pf-pcsf-copy-label">PHILHEALTH COPY</div>

    <div class="pf-tear-line pf-pcsf-tear"></div>

    <?= $P::sectionBar('TO BE FILLED-OUT BY PHILHEALTH PRIMARY CARE PERSONNEL') ?>
    <div class="pf-title-main pf-pcsf-slip-title">PHILHEALTH PRIMARY CARE SELECTION CONFIRMATION SLIP</div>

    <div class="pf-field-row pf-inline" style="margin-top:6px;">
        <div class="pf-field" style="flex:2;">
            <?= $P::label('SELECTION NO.:', 'pcsf_selection_no', false) ?>
            <?= $P::line('pcsf_selection_no', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field">
            <?= $P::label('DATE:', 'pcsf_confirm_date', false) ?>
            <?= $P::line('pcsf_confirm_date', $role, $fg, $lockRecords, true, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
    </div>

    <div class="pf-field-row">
        <?= $P::label('FULL NAME:', 'pcsf_confirm_last', false) ?>
        <?= $nameRow('pcsf_confirm_last', 'pcsf_confirm_first', 'pcsf_confirm_middle', true) ?>
    </div>

    <div class="pf-field-row pf-inline">
        <div class="pf-field" style="flex:2;">
            <?= $P::label('PIN:', 'pcsf_confirm_pin', false) ?>
            <?= $P::line('pcsf_confirm_pin', $role, $fg, $lockRecords, true) ?>
        </div>
        <div class="pf-field">
            <?= $P::label('DATE OF BIRTH:', 'pcsf_confirm_birth_date', false) ?>
            <?= $P::line('pcsf_confirm_birth_date', $role, $fg, $lockRecords, true, 'date') ?>
            <span class="pf-sublabel">(MM/DD/YYYY)</span>
        </div>
    </div>

    <div class="pf-field-row">
        <?= $P::label('PC:', 'pcsf_primary_care_clinic', false) ?>
        <?= $P::line('pcsf_primary_care_clinic', $role, $fg, $lockRecords, true) ?>
    </div>
    <div class="pf-field-row">
        <?= $P::label('ADDRESS:', 'pcsf_confirm_barangay', false) ?>
        <?= $addrRow('pcsf_confirm_barangay', 'pcsf_confirm_city', 'pcsf_confirm_province') ?>
    </div>

    <div class="pf-sig-line pf-pcsf-sig"><?= $P::line('pcsf_personnel_signature', $role, $fg, $lockRecords, true) ?></div>
    <div class="pf-sig-label">(SIGNATURE OVER PRINTED NAME)</div>
    <div class="pf-pcsf-copy-label">BENEFICIARY'S COPY</div>
</div>
</div>
