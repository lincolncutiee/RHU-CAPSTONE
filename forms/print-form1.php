<?php
/**
 * Official DOH Form 1 — Patient Enrollment Record (Print)
 * Layout matches the official paper form.
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
$conn->close();

if (!$p) die('Patient not found.');

function v(array $p, string $key): string {
    return htmlspecialchars($p[$key] ?? '');
}
function chk(array $p, string $key, string $val): string {
    return ($p[$key] ?? '') === $val ? '☑' : '☐';
}
function serialBoxes(?string $serial): string {
    $digits = str_pad(preg_replace('/\D/', '', $serial ?? ''), 7, ' ', STR_PAD_RIGHT);
    $html = '';
    for ($i = 0; $i < 7; $i++) {
        $html .= '<span class="serial-box">' . htmlspecialchars($digits[$i] === ' ' ? '' : $digits[$i]) . '</span>';
    }
    return $html;
}
function fmtDate(?string $d): array {
    if (!$d) return ['','',''];
    $dt = new DateTime($d);
    return [$dt->format('m'), $dt->format('d'), $dt->format('Y')];
}
[$bm, $bd, $by] = fmtDate($p['birth_date'] ?? null);
[$cm, $cd, $cy] = fmtDate($p['consent_signature_date'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form 1 — Patient Enrollment Record</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print-form1.css">
</head>
<body>
<div class="print-controls no-print">
    <button onclick="window.print()">🖨 Print Form 1</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="form-page">
    <!-- Header -->
    <table class="header-table" width="100%">
        <tr>
            <td width="55%" class="header-left">
                <div class="doh-logo-area">
                    <div class="logo-placeholder">DOH</div>
                    <div class="doh-text">
                        <div>Republic of the Philippines</div>
                        <div class="bold">DEPARTMENT OF HEALTH</div>
                        <div class="italic">Kagawaran ng Kalusugan</div>
                    </div>
                </div>
            </td>
            <td width="45%" class="header-right">
                <table class="serial-table">
                    <tr><td class="label-cell">Family Serial Number</td></tr>
                    <tr><td class="serial-row"><?= serialBoxes($p['family_serial_number']) ?></td></tr>
                    <tr><td class="label-cell">Facility Code</td></tr>
                    <tr><td class="facility-box"><?= v($p, 'facility_code') ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="form-title">
        <div class="subtitle">Integrated Clinic Information System (iCLINICSYS)</div>
        <div class="main-title">PATIENT ENROLLMENT RECORD</div>
        <div class="instructions">Instructions: For new patient only. Please print legibly and mark appropriate boxes with "X".</div>
        <div class="instructions italic">Tagubilin: Para sa bagong pasyente lamang. Mangyaring sulatan ng mabasa at lagyan ng tsek (X) ang naaangkop na kahon.</div>
    </div>

    <!-- Section I -->
    <table class="section-header" width="100%"><tr><td>I. PATIENT INFORMATION (IMPORMASYON NG PASYENTE)</td></tr></table>

    <table class="form-grid" width="100%">
        <tr>
            <td colspan="3" class="field-cell">
                <div class="field-label">Last Name (Apelyido)</div>
                <div class="field-value"><?= v($p, 'last_name') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">Suffix (e.g. Sr., Jr., I, III)</div>
                <div class="field-value"><?= v($p, 'suffix') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">Maiden Name (for married women)</div>
                <div class="field-value"><?= v($p, 'maiden_name') ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="field-cell">
                <div class="field-label">First Name (Pangalan)</div>
                <div class="field-value"><?= v($p, 'first_name') ?></div>
            </td>
            <td colspan="2" rowspan="2" class="field-cell">
                <div class="field-label">Residential Address (Tirahan)</div>
                <div class="field-value tall"><?= v($p, 'residential_address') ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="field-cell">
                <div class="field-label">Middle Name (Gitnang Pangalan)</div>
                <div class="field-value"><?= v($p, 'middle_name') ?></div>
            </td>
        </tr>
        <tr>
            <td class="field-cell">
                <div class="field-label">Sex (Kasarian)</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'sex','Female') ?> Female (Babae)</span>
                    <span><?= chk($p,'sex','Male') ?> Male (Lalaki)</span>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">Birth Date (Kaarawan)</div>
                <div class="date-row"><?= $bm ?> / <?= $bd ?> / <?= $by ?> <span class="small">(mm/dd/yyyy)</span></div>
            </td>
            <td class="field-cell">
                <div class="field-label">Birthplace (Lugar ng Kapanganakan)</div>
                <div class="field-value"><?= v($p, 'birthplace') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">Blood Type</div>
                <div class="field-value"><?= v($p, 'blood_type') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">Mother's Name (Pangalan ng Ina)</div>
                <div class="field-value"><?= v($p, 'mothers_name') ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="field-cell">
                <div class="field-label">Civil Status (Katayuang Sibil)</div>
                <div class="checkbox-grid-sm">
                    <?php foreach (['Single','Married','Widower','Widow','Separated','Annulled','Co-Habitation'] as $cs): ?>
                        <span><?= chk($p,'civil_status',$cs) ?> <?= $cs ?></span>
                    <?php endforeach; ?>
                </div>
            </td>
            <td colspan="3" class="field-cell">
                <div class="field-label">Spouse's Name (Asawa)</div>
                <div class="field-value"><?= v($p, 'spouse_name') ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="field-cell">
                <div class="field-label">Educational Attainment</div>
                <div class="checkbox-grid-sm">
                    <?php foreach (['No Formal Education','Elementary','Highschool','High School','Vocational','Post Graduate'] as $ea): ?>
                        <span><?= chk($p,'educational_attainment',$ea) ?> <?= $ea ?></span>
                    <?php endforeach; ?>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">Contact Number</div>
                <div class="field-value"><?= v($p, 'contact_number') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">DSWD NHTS?</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'dswd_nhts','Yes') ?> Yes</span>
                    <span><?= chk($p,'dswd_nhts','No') ?> No</span>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">Facility Household No.</div>
                <div class="field-value"><?= v($p, 'facility_household_no') ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="field-cell">
                <div class="field-label">Employment Status</div>
                <div class="checkbox-grid-sm">
                    <?php foreach (['Student','Employed','Unemployed','Unknown','Retired','Others'] as $es): ?>
                        <span><?= chk($p,'employment_status',$es) ?> <?= $es ?></span>
                    <?php endforeach; ?>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">4Ps Member?</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'four_ps_member','Yes') ?> Yes</span>
                    <span><?= chk($p,'four_ps_member','No') ?> No</span>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">Household No.</div>
                <div class="field-value"><?= v($p, 'household_no') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">PhilHealth Member?</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'philhealth_member','Yes') ?> Yes</span>
                    <span><?= chk($p,'philhealth_member','No') ?> No</span>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="field-cell">
                <div class="field-label">If Employed, Specific Occupation:</div>
                <div class="field-value"><?= v($p, 'specific_occupation') ?></div>
                <div class="checkbox-row mt">
                    <span><?= chk($p,'employment_type','CONTRACTUAL') ?> CONTRACTUAL</span>
                    <span><?= chk($p,'employment_type','CASUAL') ?> CASUAL</span>
                    <span><?= chk($p,'employment_type','PERMANENT') ?> PERMANENT</span>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">Status Type</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'philhealth_status_type','Member') ?> Member</span>
                    <span><?= chk($p,'philhealth_status_type','Dependent') ?> Dependent</span>
                </div>
            </td>
            <td class="field-cell">
                <div class="field-label">PhilHealth No.</div>
                <div class="field-value"><?= v($p, 'philhealth_no') ?></div>
            </td>
            <td class="field-cell">
                <div class="field-label">PhilHealth Category</div>
                <div class="small">IF Member, please</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'philhealth_category','FE-Private') ?> FE – Private</span>
                    <span><?= chk($p,'philhealth_category','FE-Government') ?> FE – Government</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="field-cell"><div class="field-label">Father (Ama)</div><div class="field-value"><?= v($p,'father_name') ?></div></td>
            <td class="field-cell"><div class="field-label">Mother (Ina)</div><div class="field-value"><?= v($p,'mother_name') ?></div></td>
            <td class="field-cell"><div class="field-label">Son (Anak na lalaki)</div><div class="field-value"><?= v($p,'son_name') ?></div></td>
            <td class="field-cell"><div class="field-label">Daughter (Anak na babae)</div><div class="field-value"><?= v($p,'daughter_name') ?></div></td>
            <td class="field-cell">
                <div class="field-label">Others</div>
                <div class="field-value"><?= v($p,'other_family_member') ?></div>
            </td>
        </tr>
        <tr>
            <td colspan="4"></td>
            <td class="field-cell">
                <div class="field-label">Primary Care Benefit (PCB) Member?</div>
                <div class="checkbox-row">
                    <span><?= chk($p,'pcb_member','Yes') ?> Yes</span>
                    <span><?= chk($p,'pcb_member','No') ?> No</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Section II -->
    <table class="section-header" width="100%"><tr><td>II. PATIENT'S CONSENT (PAHINTULOT NG PASYENTE)</td></tr></table>
    <table class="consent-table" width="100%">
        <tr>
            <td width="50%" class="consent-cell">
                <p><strong>IN ENGLISH:</strong> I hereby declare that the information I have given is true and correct. I understand that the CHU/RHU will encode my data into the iClinicSys. I give my permission for the use of my medical results for treatment and public health programs. I understand that I may refuse to give my consent or withdraw it at any time.</p>
            </td>
            <td width="50%" class="consent-cell">
                <p><strong>SA FILIPINO:</strong> Ako ay nagpapatunay na ang impormasyong aking ibinigay ay totoo at tumpak. Nauunawaan ko na ang CHU/RHU ay mag-e-encode ng aking datos sa iClinicSys. Binibigyan ko ng pahintulot ang paggamit ng aking mga resulta ng medikal para sa paggamot at mga programa sa kalusugan ng publiko. Nauunawaan ko na maaari akong tumanggi na magbigay ng pahintulot o bawiin ito anumang oras.</p>
            </td>
        </tr>
        <tr>
            <td class="sig-cell">
                <div class="sig-line"></div>
                <div class="sig-label">SIGNATURE OF PATIENT / DATE</div>
                <div class="sig-date"><?= $cm ?>/<?= $cd ?>/<?= $cy ?></div>
            </td>
            <td class="sig-cell">
                <div class="sig-line"></div>
                <div class="sig-label">NAME OF CHU/RHU REPRESENTATIVE</div>
                <div class="sig-date"><?= v($p, 'chu_representative') ?></div>
            </td>
        </tr>
    </table>

    <div class="form-footer">Clinic Information System | <strong>FORM 1</strong></div>
</div>
</body>
</html>
