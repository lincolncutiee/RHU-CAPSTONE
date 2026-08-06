<?php
/**
 * Electronic Konsulta Availment Slip (EKAS) — PhilHealth original layout.
 *
 * @var string $role
 * @var bool $lockRecords
 */
$fg = 'doctor';
$P = PaperFormRenderer::class;

$ekasServices = [
    'consultation' => 'Consultation',
    'cbc'          => 'Complete Blood Count (CBC) w/ platelet count',
    'lipid'        => 'Lipid profile (Total Cholesterol, Triglycerides, HDL Cholesterol, LDL Cholesterol)',
    'fbs'          => 'Fasting Blood Sugar (FBS)',
    'ogtt'         => 'Oral Glucose Tolerance Test',
    'hba1c'        => 'Glycosylated Hemoglobin (HbA1c)',
    'creatinine'   => 'Creatinine',
    'chest_xray'   => 'Chest X-Ray',
    'sputum'       => 'Sputum Microscopy',
    'ecg'          => 'Electrocardiogram (ECG)',
    'urinalysis'   => 'Urinalysis',
    'pap_smear'    => 'Pap smear',
    'fecalysis'    => 'Fecalysis',
    'fobt'         => 'Fecal Occult Blood Test',
];
?>
<div class="paper-sheet paper-sheet--ekas" style="max-width:148mm;">
<div class="paper-sheet-inner">
    <table class="pf-table" style="border:none;margin-bottom:4px;">
        <tr>
            <td style="border:none;width:28%;vertical-align:middle;">
                <strong style="font-size:10pt;color:#0066b3;">PhilHealth</strong>
                <div style="font-size:6pt;color:#64748b;line-height:1.2;">Your Partner in Health</div>
            </td>
            <td style="border:none;text-align:center;vertical-align:middle;">
                <div class="pf-title-main" style="font-size:9.5pt;margin:0;">ELECTRONIC KONSULTA AVAILMENT SLIP (EKAS)</div>
            </td>
        </tr>
    </table>

    <?= $P::sectionBar('To be filled-out by the facility (Pupunan ng Pasilidad)') ?>

    <table class="pf-table" style="font-size:7.5pt;">
        <tr>
            <td style="width:38%;">
                <span class="pf-label">HCI NAME</span>
                <?= $P::line('ekas_hci_name', $role, $fg, $lockRecords, true) ?>
            </td>
            <td style="width:18%;">
                <span class="pf-label">Case No.</span>
                <?= $P::line('ekas_case_no', $role, $fg, $lockRecords, true) ?>
            </td>
            <td style="width:22%;">
                <span class="pf-label">HCI Accreditation No.</span>
                <?= $P::line('ekas_accreditation_no', $role, $fg, $lockRecords, true) ?>
            </td>
            <td style="width:22%;">
                <span class="pf-label">Transaction No.</span>
                <?= $P::line('ekas_transaction_no', $role, $fg, $lockRecords, true) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">PIN (PhilHealth Identification Number)</span>
                <?= $P::line('philhealth_no', $role, $fg, $lockRecords) ?>
            </td>
            <td colspan="2">
                <span class="pf-label">Membership Category (Kategorya ng pagiging Miyembro)</span>
                <?= $P::line('philhealth_category', $role, $fg, $lockRecords) ?>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="pf-label">Patient Name (Pangalan ng Pasyente)</span>
                <?= $P::line('ekas_patient_name', $role, $fg, $lockRecords, true) ?>
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
        <tr>
            <td colspan="2">
                <span class="pf-label">Membership Type</span>
                <div style="margin-top:2px;">
                    <?= $P::radio('philhealth_status_type', 'Member', 'MEMBER', $role, $fg, $lockRecords) ?>
                    <?= $P::radio('philhealth_status_type', 'Dependent', 'DEPENDENT', $role, $fg, $lockRecords) ?>
                </div>
            </td>
            <td colspan="2">
                <span class="pf-label">Authorization Transaction Code (ATC)</span>
                <?= $P::line('ekas_atc', $role, $fg, $lockRecords, true) ?>
            </td>
        </tr>
    </table>

    <table class="pf-table ekas-services-table" style="margin-top:6px;font-size:7pt;">
        <thead>
            <tr>
                <th style="width:46%;text-align:left;">Konsulta Services</th>
                <th style="width:14%;text-align:center;">Performed<br><span style="font-weight:400;">(✓ / ✗)</span></th>
                <th style="width:18%;text-align:center;">Date Performed</th>
                <th style="width:22%;text-align:left;">Performed by</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ekasServices as $key => $label): ?>
            <tr>
                <td><?= htmlspecialchars($label) ?></td>
                <td style="text-align:center;vertical-align:middle;">
                    <?= $P::checkbox('ekas_svc_' . $key . '_done', '1', '', $role, $fg, $lockRecords, true, 'ekas_svc_' . $key . '_done') ?>
                </td>
                <td>
                    <?= $P::line('ekas_svc_' . $key . '_date', $role, $fg, $lockRecords, true, 'date') ?>
                </td>
                <td>
                    <?= $P::line('ekas_svc_' . $key . '_by', $role, $fg, $lockRecords, true) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:6.5pt;margin:4px 0 8px;font-style:italic;">Note: This form shall be provided to the Konsulta beneficiary.</p>

    <div class="pf-tear-line"></div>

    <?= $P::sectionBar('To be filled-out by the patient (Pupunan ng Pasyente)') ?>

    <div style="font-size:7.5pt;margin-top:6px;">
        <p style="margin:0 0 4px;">
            <strong>1.</strong> Have you received the above-mentioned essential services?
            <span style="font-style:italic;">(Natanggap mo ba ang mga pangunahing serbisyo na nabanggit?)</span>
        </p>
        <div style="margin:0 0 8px 12px;">
            <?= $P::radio('ekas_feedback_received', 'Yes', 'Yes (Oo)', $role, $fg, $lockRecords, true) ?>
            <?= $P::radio('ekas_feedback_received', 'No', 'No (Hindi)', $role, $fg, $lockRecords, true) ?>
        </div>

        <p style="margin:0 0 4px;">
            <strong>2.</strong> How satisfied are you with the services provided?
            <span style="font-style:italic;">(Gaano ka nasiyahan sa natanggap na serbisyo?)</span>
        </p>
        <div style="margin:0 0 8px 12px;display:flex;flex-wrap:wrap;gap:8px 16px;">
            <?= $P::radio('ekas_satisfaction', 'Happy', '😊 HAPPY', $role, $fg, $lockRecords, true) ?>
            <?= $P::radio('ekas_satisfaction', 'Neutral', '😐 NEUTRAL', $role, $fg, $lockRecords, true) ?>
            <?= $P::radio('ekas_satisfaction', 'Bad', '☹️ BAD', $role, $fg, $lockRecords, true) ?>
        </div>

        <p style="margin:0 0 4px;">
            For your comment, suggestion or complaint:
            <span style="font-style:italic;">(Para sa iyong komento, mungkahi o reklamo):</span>
        </p>
        <?= $P::area('ekas_feedback_comments', $role, $fg, $lockRecords, true, 2) ?>
    </div>

    <p style="font-size:6.5pt;margin:10px 0 6px;">
        Accomplished form shall be submitted to Konsulta Provider
        <span style="font-style:italic;">(Ang kumpletong form ay dapat isumite sa tagapagbigay ng Konsulta).</span>
    </p>

    <div class="pf-field-row" style="margin-top:8px;">
        <span class="pf-label">PhilHealth Identification Number of Patient:</span>
        <?= $P::line('ekas_feedback_pin', $role, $fg, $lockRecords, true) ?>
    </div>
</div>
</div>
