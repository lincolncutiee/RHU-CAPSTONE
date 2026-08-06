<?php
/**
 * Official DOH Form 2 — Individual Treatment Record (Print)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) die('Invalid patient ID.');

$conn = getDBConnection();
$stmt = $conn->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('SELECT * FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();
$conn->close();

if (!$p) die('Patient not found.');

function v(array $d, string $key): string { return htmlspecialchars($d[$key] ?? ''); }
function chk(array $d, string $key, string $val): string { return ($d[$key] ?? '') === $val ? '☑' : '☐'; }
function chkArr(array $types, string $val): string {
    return in_array($val, $types, true) ? '☑' : '☐';
}
function fmtDate(?string $d): array {
    if (!$d) return ['','',''];
    $dt = new DateTime($d);
    return [$dt->format('m'), $dt->format('d'), $dt->format('Y')];
}
$age = calculateAge($p['birth_date']);
[$cm, $cd, $cy] = fmtDate($t['date_of_consultation'] ?? null);
$types = [];
if (!empty($t['consultation_types'])) {
    $types = is_string($t['consultation_types']) ? json_decode($t['consultation_types'], true) : $t['consultation_types'];
    $types = $types ?: [];
}
$consultTypes = ['General','Family Planning','Prenatal','Postpartum','Dental Care','Tuberculosis','Child Care','Immunization','Child Nutrition','Sick Children','Injury','Firecracker Injury','Adult Immunization'];
$timeStr = '';
if (!empty($t['consultation_time'])) {
    $timeStr = date('g:i', strtotime($t['consultation_time']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form 2 — Individual Treatment Record</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print-form2.css">
</head>
<body>
<div class="print-controls no-print">
    <button onclick="window.print()">🖨 Print Form 2</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="form-page">
    <div class="form-title">
        <div class="subtitle">Integrated Clinic Information System (iCLINICSYS)</div>
        <div class="main-title">INDIVIDUAL TREATMENT RECORD</div>
        <div class="instructions">Instructions: For old, returning and/or referred patient. Please print legibly and mark appropriate boxes with "X".</div>
        <div class="instructions italic">Tagubilin: Para sa dating, bumabalik at/o nirefer na pasyente. Mangyaring sulatan ng mabasa at lagyan ng tsek (X) ang naaangkop na kahon.</div>
    </div>

    <!-- Section I -->
    <table class="section-header" width="100%"><tr><td>I. PATIENT INFORMATION (IMPORMASYON NG PASYENTE)</td></tr></table>
    <table class="form-grid" width="100%">
        <tr>
            <td width="35%" class="field-cell">
                <div class="field-label">Last Name (Apelyido)</div>
                <div class="field-value"><?= v($p,'last_name') ?></div>
            </td>
            <td width="20%" class="field-cell">
                <div class="field-label">Suffix (e.g. Jr., Sr., II, III)</div>
                <div class="field-value"><?= v($p,'suffix') ?></div>
            </td>
            <td width="15%" class="field-cell">
                <div class="field-label">Age (Edad)</div>
                <div class="field-value"><?= $age ?></div>
            </td>
            <td width="30%" rowspan="2" class="field-cell">
                <div class="field-label">Residential Address (Tirahan)</div>
                <div class="field-value tall"><?= v($p,'residential_address') ?></div>
            </td>
        </tr>
        <tr>
            <td class="field-cell">
                <div class="field-label">First Name (Pangalan)</div>
                <div class="field-value"><?= v($p,'first_name') ?></div>
            </td>
            <td colspan="2" class="field-cell">
                <div class="field-label">Middle Name (Gitnang Pangalan)</div>
                <div class="field-value"><?= v($p,'middle_name') ?></div>
            </td>
        </tr>
    </table>

    <!-- Section II -->
    <table class="section-header" width="100%"><tr><td>II. FOR CHU / RHU PERSONNEL ONLY (PARA SA KINATAWAN NG CHU / RHU LAMANG)</td></tr></table>
    <table class="form-grid" width="100%">
        <tr>
            <td width="50%" class="field-cell" valign="top">
                <div class="field-label">Mode of Transaction</div>
                <div class="checkbox-row">
                    <span><?= chk($t,'mode_of_transaction','Walk-in') ?> Walk-in</span>
                    <span><?= chk($t,'mode_of_transaction','Visited') ?> Visited</span>
                    <span><?= chk($t,'mode_of_transaction','Referral') ?> Referral</span>
                </div>
                <div class="field-label mt">Date of Consultation</div>
                <div class="date-row"><?= $cm ?> / <?= $cd ?> / <?= $cy ?> <span class="small">(mm/dd/yyyy)</span></div>
                <div class="field-label mt">Consultation Time</div>
                <div class="field-value"><?= $timeStr ?> <?= v($t,'consultation_period') ?></div>

                <table class="vitals-grid" width="100%">
                    <tr>
                        <td><div class="field-label">Blood Pressure</div><div class="field-value"><?= v($t,'blood_pressure') ?></div></td>
                        <td><div class="field-label">Temperature</div><div class="field-value"><?= v($t,'temperature') ?></div></td>
                    </tr>
                    <tr>
                        <td><div class="field-label">Height (cm)</div><div class="field-value"><?= v($t,'height_cm') ?></div></td>
                        <td><div class="field-label">Weight (kg)</div><div class="field-value"><?= v($t,'weight_kg') ?></div></td>
                    </tr>
                </table>

                <div class="field-label mt">Name of Attending Provider</div>
                <div class="field-value"><?= v($t,'attending_provider') ?></div>

                <div class="field-label mt">Nature of Visit</div>
                <div class="checkbox-row">
                    <span><?= chk($t,'nature_of_visit','New Consultation/Case') ?> New Consultation/Case</span>
                    <span><?= chk($t,'nature_of_visit','New Admission') ?> New Admission</span>
                    <span><?= chk($t,'nature_of_visit','Follow-up Visit') ?> Follow-up Visit</span>
                </div>
            </td>
            <td width="50%" class="field-cell referral-box" valign="top">
                <div class="referral-title">For REFERRAL Transaction only</div>
                <div class="field-label">REFERRED FROM:</div>
                <div class="field-value"><?= v($t,'referred_from') ?></div>
                <div class="field-label mt">REFERRED TO:</div>
                <div class="field-value"><?= v($t,'referred_to') ?></div>
                <div class="field-label mt">Reason(s) for Referral:</div>
                <div class="field-value tall"><?= v($t,'referral_reasons') ?></div>
                <div class="field-label mt">Referred by:</div>
                <div class="field-value"><?= v($t,'referred_by') ?></div>
            </td>
        </tr>
        <tr>
            <td class="field-cell" valign="top">
                <div class="field-label">Type of Consultation / Purpose of Visit</div>
                <table class="consult-grid" width="100%">
                    <?php for ($i = 0; $i < count($consultTypes); $i += 3): ?>
                    <tr>
                        <?php for ($j = $i; $j < min($i + 3, count($consultTypes)); $j++): ?>
                        <td><span><?= chkArr($types, $consultTypes[$j]) ?> <?= $consultTypes[$j] ?></span></td>
                        <?php endfor; ?>
                    </tr>
                    <?php endfor; ?>
                </table>
            </td>
            <td class="field-cell" valign="top">
                <div class="field-label">Chief Complaints</div>
                <div class="field-value complaints"><?= nl2br(v($t,'chief_complaints')) ?></div>
            </td>
        </tr>
    </table>

    <!-- Clinical sections -->
    <table class="clinical-table" width="100%">
        <tr>
            <td class="clinical-cell">
                <div class="clinical-label">Diagnosis</div>
                <div class="clinical-value"><?= nl2br(v($t,'diagnosis')) ?></div>
            </td>
        </tr>
        <tr>
            <td width="70%" class="clinical-cell">
                <div class="clinical-label">Medication / Treatment</div>
                <div class="clinical-value"><?= nl2br(v($t,'medication_treatment')) ?></div>
            </td>
            <td width="30%" class="clinical-cell">
                <div class="clinical-label">Name of Health Care Provider:</div>
                <div class="clinical-value"><?= v($t,'healthcare_provider') ?></div>
            </td>
        </tr>
        <tr>
            <td class="clinical-cell">
                <div class="clinical-label">Laboratory Findings / Impression</div>
                <div class="clinical-value"><?= nl2br(v($t,'laboratory_findings')) ?></div>
            </td>
            <td class="clinical-cell">
                <div class="clinical-label">Performed Laboratory Test:</div>
                <div class="clinical-value"><?= nl2br(v($t,'performed_lab_test')) ?></div>
            </td>
        </tr>
    </table>

    <div class="form-footer">Clinic Information System | <strong>FORM 2</strong> | Page 1</div>
</div>
</body>
</html>
