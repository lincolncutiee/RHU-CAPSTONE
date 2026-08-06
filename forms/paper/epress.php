<?php
/**
 * Electronic Prescription Slip (EPRESS) — PhilHealth original layout.
 *
 * @var string $role
 * @var bool $lockRecords
 */
$fg = 'doctor';
$P = PaperFormRenderer::class;
?>
<div class="paper-sheet paper-sheet--epress" style="max-width:148mm;">
<div class="paper-sheet-inner">
    <table class="pf-table" style="border:none;margin-bottom:4px;">
        <tr>
            <td style="border:none;width:28%;vertical-align:middle;">
                <strong style="font-size:10pt;color:#0066b3;">PhilHealth</strong>
                <div style="font-size:6pt;color:#64748b;line-height:1.2;">Your Partner in Health</div>
            </td>
            <td style="border:none;text-align:center;vertical-align:middle;">
                <div class="pf-title-main" style="font-size:9.5pt;margin:0;">ELECTRONIC PRESCRIPTION SLIP (EPRESS)</div>
            </td>
        </tr>
    </table>

    <?= $P::sectionBar('To be filled-out by the facility (Pupunan ng Pasilidad)') ?>

    <table class="pf-table" style="font-size:7.5pt;">
        <tr>
            <td style="width:38%;">
                <span class="pf-label">HCI NAME</span>
                <?= $P::line('epress_hci_name', $role, $fg, $lockRecords, true) ?>
            </td>
            <td style="width:18%;">
                <span class="pf-label">Case No.</span>
                <?= $P::line('epress_case_no', $role, $fg, $lockRecords, true) ?>
            </td>
            <td style="width:22%;">
                <span class="pf-label">HCI Accreditation No.</span>
                <?= $P::line('epress_accreditation_no', $role, $fg, $lockRecords, true) ?>
            </td>
            <td style="width:22%;">
                <span class="pf-label">Transaction No.</span>
                <?= $P::line('epress_transaction_no', $role, $fg, $lockRecords, true) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">PIN (PhilHealth Identification Number)</span>
                <?= $P::line('philhealth_no', $role, $fg, $lockRecords) ?>
            </td>
            <td>
                <span class="pf-label">Membership Category</span>
                <?= $P::line('philhealth_category', $role, $fg, $lockRecords) ?>
            </td>
            <td>
                <span class="pf-label">Membership Type</span>
                <div style="margin-top:2px;">
                    <?= $P::radio('philhealth_status_type', 'Member', 'MEMBER', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('philhealth_status_type', 'Dependent', 'DEPENDENT', $role, $fg, $lockRecords) ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">Patient Name (Pangalan ng Pasyente)</span>
                <?= $P::line('epress_patient_name', $role, $fg, $lockRecords, true) ?>
            </td>
            <td>
                <span class="pf-label">Age (Edad)</span>
                <?= $P::line('itr_age', $role, $fg, $lockRecords, true) ?>
            </td>
            <td>
                <span class="pf-label">Contact No. (Numero ng Telepono)</span>
                <?= $P::line('contact_number', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
    </table>

    <div class="epress-rx-box">
        <div class="epress-rx-head">
            <span class="epress-rx-symbol" aria-hidden="true">℞</span>
            <strong class="epress-rx-title">USE GENERIC NAME</strong>
        </div>
        <div class="epress-rx-body">
            <?= $P::area('medication_treatment', $role, $fg, $lockRecords, false, 8) ?>
        </div>
        <div class="epress-rx-foot">
            <div class="epress-rx-foot-left">
                <span class="pf-label">Next Dispensing Date:</span>
                <?= $P::line('epress_next_dispensing_date', $role, $fg, $lockRecords, true, 'date') ?>
                <span style="font-size:6.5pt;font-style:italic;">(Petsa ng susunod na bigay ng gamot)</span>
            </div>
            <div class="epress-rx-foot-right">
                <?= $P::line('epress_physician_name', $role, $fg, $lockRecords, true) ?>
                <div class="pf-sig-label" style="margin-top:2px;">Physician: __________________</div>
                <div style="margin-top:4px;font-size:7pt;">
                    <span class="pf-label" style="display:inline;">PRC LIC No.:</span>
                    <?= $P::line('epress_prc_lic', $role, $fg, $lockRecords, true) ?>
                </div>
                <div style="margin-top:2px;font-size:7pt;">
                    <span class="pf-label" style="display:inline;">PTR No.:</span>
                    <?= $P::line('epress_ptr_no', $role, $fg, $lockRecords, true) ?>
                </div>
                <div style="margin-top:2px;font-size:7pt;">
                    <span class="pf-label" style="display:inline;">S2 No.:</span>
                    <?= $P::line('epress_s2_no', $role, $fg, $lockRecords, true) ?>
                </div>
            </div>
        </div>
    </div>

    <p style="font-size:6.5pt;margin:4px 0 8px;"><strong>Note:</strong></p>
    <div class="pf-tear-line"></div>

    <?= $P::sectionBar('To be filled-out by the patient (Pupunan ng Pasyente)') ?>

    <div style="font-size:7.5pt;margin-top:6px;">
        <p style="margin:0 0 4px;">
            <strong>1.</strong> Did you receive the above-mentioned medicines?
            <span style="font-style:italic;">(Natanggap mo ba ang mga gamot na nabanggit?)</span>
        </p>
        <div style="margin:0 0 8px 12px;">
            <?= $P::radio('epress_feedback_received', 'Yes', 'Yes (Oo)', $role, $fg, $lockRecords, true) ?>
            <?= $P::radio('epress_feedback_received', 'No', 'No (Hindi)', $role, $fg, $lockRecords, true) ?>
        </div>

        <p style="margin:0 0 4px;">
            <strong>2.</strong> Are you satisfied with the medicines you received?
            <span style="font-style:italic;">(Nasiyahan ka ba sa mga gamot na natanggap mo?)</span>
        </p>
        <div style="margin:0 0 8px 12px;display:flex;flex-wrap:wrap;gap:8px 16px;">
            <?= $P::radio('epress_satisfaction', 'Happy', '😊 HAPPY', $role, $fg, $lockRecords, true) ?>
            <?= $P::radio('epress_satisfaction', 'Neutral', '😐 NEUTRAL', $role, $fg, $lockRecords, true) ?>
            <?= $P::radio('epress_satisfaction', 'Bad', '☹️ BAD', $role, $fg, $lockRecords, true) ?>
        </div>

        <p style="margin:0 0 4px;">
            For your comment, suggestion or complaint:
            <span style="font-style:italic;">(Para sa iyong komento, mungkahi o reklamo):</span>
        </p>
        <?= $P::area('epress_feedback_comments', $role, $fg, $lockRecords, true, 2) ?>
    </div>

    <p style="font-size:6.5pt;margin:10px 0 6px;">
        Note: Accomplished form shall be submitted to Konsulta Provider
        <span style="font-style:italic;">(Ang kumpletong form ay dapat isumite sa tagapagbigay ng Konsulta).</span>
    </p>

    <div class="pf-field-row" style="margin-top:8px;">
        <span class="pf-label">PhilHealth Identification Number of Patient:</span>
        <?= $P::line('epress_feedback_pin', $role, $fg, $lockRecords, true) ?>
    </div>
</div>
</div>
