<?php
/**
 * Get patient + treatment record by ID
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/ExtendedFormData.php';
require_once __DIR__ . '/../core/PatientProfile.php';
require_once __DIR__ . '/../core/Consultation.php';
require_once __DIR__ . '/../core/ClinicalMaster.php';
require_once __DIR__ . '/../core/FollowUpReminder.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$consultationId = (int) ($_GET['consultation_id'] ?? 0);
$newConsultation = isset($_GET['new_consultation']) && (string) $_GET['new_consultation'] === '1';
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare('SELECT * FROM patients WHERE id = ? AND is_archived = 0');
$stmt->bind_param('i', $id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Patient not found.']);
    exit;
}

$profileExtended = Consultation::loadProfileExtended($id);

// New consultation encoding: profile demographics only — no prior visit clinical data.
if ($newConsultation && $consultationId <= 0) {
    $conn->close();
    $extendedFields = ExtendedFormData::hydrateAddressFields(
        PatientProfile::permanentExtendedOnly($profileExtended),
        $patient['residential_address'] ?? null
    );

    echo json_encode([
        'success'          => true,
        'patient'          => $patient,
        'treatment'        => [],
        'consultation_id'  => null,
        'new_consultation' => true,
        'extended'         => $extendedFields,
        'profile'          => PatientProfile::buildPayload($patient, $extendedFields),
        'form_progress'    => 0,
        'profile_required' => PatientProfile::requiredForCreate(),
    ]);
    exit;
}

if ($consultationId > 0) {
    $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE id = ? AND patient_id = ?');
    $stmt->bind_param('ii', $consultationId, $id);
} else {
    $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $id);
}
$stmt->execute();
$treatment = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

$diagnoses = [];
$medications = [];
if (!empty($treatment['id'])) {
    ClinicalMaster::ensureSchema($conn);
    $hydrated = ClinicalMaster::hydrateTreatmentClinical($conn, $treatment);
    $treatment = $hydrated['treatment'];
    $diagnoses = $hydrated['diagnoses'];
    $medications = $hydrated['medications'];
}

$followUp = null;
$reminderPrefs = FollowUpReminder::patientPrefs($conn, $id);
if (!empty($treatment['id'])) {
    $followUp = FollowUpReminder::forConsultation($conn, (int) $treatment['id']);
}
$conn->close();

$consultExtended = ExtendedFormData::fromTreatment($treatment);
$mergedExtended = PatientProfile::mergeExtendedForConsultation(
    $profileExtended,
    $consultExtended['fields']
);

if (!empty($treatment['consultation_types'])) {
    $treatment['consultation_types'] = json_decode($treatment['consultation_types'], true) ?? [];
}

$extendedFields = ExtendedFormData::hydrateAddressFields(
    $mergedExtended,
    $patient['residential_address'] ?? null
);

echo json_encode([
    'success'       => true,
    'patient'       => $patient,
    'treatment'     => $treatment,
    'diagnoses'     => $diagnoses,
    'medications'   => $medications,
    'consultation_id' => !empty($treatment['id']) ? (int) $treatment['id'] : null,
    'extended'      => $extendedFields,
    'profile'       => PatientProfile::buildPayload($patient, $extendedFields),
    'form_progress' => $consultExtended['form_progress'],
    'profile_required' => PatientProfile::requiredForCreate(),
    'follow_up'        => $followUp,
    'reminder_prefs'   => $reminderPrefs,
]);
