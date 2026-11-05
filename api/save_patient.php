<?php
/**
 * Save patient + treatment record with role-based field permissions
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/PatientProfile.php';
require_once __DIR__ . '/../core/ExtendedFormData.php';
require_once __DIR__ . '/../core/WorkflowStatus.php';
require_once __DIR__ . '/../core/Consultation.php';
requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');
$patientDbId = (int) ($_POST['patient_db_id'] ?? 0);
$consultationId = (int) ($_POST['consultation_id'] ?? 0);
$submitValidation = ($_POST['submit_validation'] ?? '0') === '1';
$newConsultation = ($_POST['new_consultation'] ?? '0') === '1';

$conn = getDBConnection();

$post = $_POST;
mergeProfileJsonIntoPost($post);

try {
    $conn->begin_transaction();

    if ($patientDbId <= 0 && !Permissions::canEdit($role, 'records_enrollment')) {
        throw new Exception('Please open an existing patient record.');
    }

    $canCreateProfile = PatientProfile::hasMinimumForCreate($post);

    if (!empty($post['profile_json'])) {
        $decoded = json_decode($post['profile_json'], true);
        if (is_array($decoded['patient'] ?? null)) {
            $canCreateProfile = PatientProfile::hasMinimumForCreate($decoded['patient']);
        }
    }

    $existing = null;
    if ($patientDbId > 0) {
        $stmt = $conn->prepare('SELECT id, status FROM patients WHERE id = ?');
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            throw new Exception('Patient not found.');
        }
    }

    // Records Office may edit only while encoder-owned (Draft / Returned / Rejected)
    $recordsCanWrite = Permissions::canEdit($role, 'records_enrollment')
        || Permissions::canEdit($role, 'records_consent')
        || Permissions::canEdit($role, 'records_treatment');
    $status = WorkflowStatus::normalize($existing['status'] ?? WORKFLOW_DRAFT);
    $encoderMayEdit = !$existing || WorkflowStatus::canEncoderEdit($status);
    $allowRecordsWrite = $recordsCanWrite && ($role !== ROLE_RECORDS || $encoderMayEdit || $newConsultation);

    // IT cannot edit after the record is printed/archived (hard lock)
    if (
        $role === ROLE_IT
        && $existing
        && WorkflowStatus::isLocked($status)
        && !$newConsultation
    ) {
        throw new Exception('This patient record is locked and cannot be modified.');
    }

    // New consultation: create a blank visit first (does not modify prior visits).
    if ($newConsultation && $patientDbId > 0 && $consultationId <= 0) {
        require_once __DIR__ . '/../core/PatientFolder.php';
        if (!PatientFolder::canCreateConsultation($role) || $role === ROLE_DOCTOR) {
            throw new Exception('You do not have permission to create consultations.');
        }
        $consultationId = Consultation::create($patientDbId, (int) $user['id']);
        $status = WORKFLOW_DRAFT;
        $encoderMayEdit = true;
        $allowRecordsWrite = $recordsCanWrite || $role === ROLE_IT;
    }

    if ($existing && WorkflowStatus::isLocked($existing['status']) && !$newConsultation) {
        throw new Exception('This patient record is locked and cannot be modified.');
    }

    // --- Save patient profile (shared across all forms) ---
    // New consultation keeps demographics locked — do not rewrite the patient profile.
    if (!$newConsultation && $allowRecordsWrite && (Permissions::canEdit($role, 'records_enrollment') || Permissions::canEdit($role, 'records_consent'))) {
        if ($patientDbId <= 0) {
            if (!$canCreateProfile) {
                $conn->rollback();
                $conn->close();
                $profilePayload = json_decode($post['profile_json'] ?? '{}', true);
                if (!is_array($profilePayload)) {
                    $profilePayload = [];
                }
                $checkSource = is_array($profilePayload['patient'] ?? null)
                    ? $profilePayload['patient']
                    : $post;
                $missing = [];
                $labels = [
                    'last_name'  => 'Last Name',
                    'first_name' => 'First Name',
                    'sex'        => 'Sex',
                    'birth_date' => 'Birth Date',
                ];
                foreach (PatientProfile::requiredForCreate() as $field) {
                    if (trim((string) ($checkSource[$field] ?? '')) === '') {
                        $missing[] = $labels[$field] ?? $field;
                    }
                }
                echo json_encode([
                    'success'         => true,
                    'local_only'      => true,
                    'message'         => 'Profile saved locally. Complete Last Name, First Name, Sex, and Birth Date to create the patient record.',
                    'patient_db_id'   => 0,
                    'form_progress'   => (int) ($post['form_progress'] ?? 0),
                    'missing_fields'  => $missing,
                    'profile'         => $profilePayload,
                ]);
                exit;
            }
        }

        $patientData = collectPatientFields($post, $role);

        if ($patientDbId > 0) {
            updatePatient($conn, $patientDbId, $patientData, $user['id']);
        } else {
            $patientData['patient_id'] = generatePatientId($conn);
            $patientData['encoded_by'] = $user['id'];
            $patientDbId = insertPatient($conn, $patientData);
            // Queue ticket is created later via Send to Doctor / reception forward — not on draft save.
        }
    }

    if (!$newConsultation && $patientDbId > 0 && $allowRecordsWrite && (Permissions::canEdit($role, 'records_enrollment') || Permissions::canEdit($role, 'records_consent'))) {
        saveExtendedEnrollment($conn, $patientDbId, $post);
    } elseif (!$newConsultation && $patientDbId > 0) {
        saveFormProgressOnly($conn, $patientDbId, (int) ($post['form_progress'] ?? 0));
    }

    // --- Save treatment (Form 2) records office fields ---
    $canSaveTreatment = $patientDbId > 0
        && (
            ($allowRecordsWrite && Permissions::canEdit($role, 'records_treatment'))
            || ($newConsultation && in_array($role, [ROLE_RECORDS, ROLE_IT], true))
        );
    if ($canSaveTreatment) {
        $treatmentData = collectTreatmentFields($post, 'records_treatment');
        saveTreatmentRecord($conn, $patientDbId, $treatmentData, $consultationId);
    }

    // --- Save doctor fields ---
    if ($patientDbId > 0 && Permissions::canEdit($role, 'doctor')) {
        require_once __DIR__ . '/../core/ClinicalMaster.php';
        ClinicalMaster::ensureSchema($conn);

        $diagnosisIds = ClinicalMaster::resolveDiagnosisIdsFromPost($conn, $post);
        $medicationIds = ClinicalMaster::resolveMedicationIdsFromPost($conn, $post);

        // Reject free-typed diagnosis lines that are not in diagnosis_master
        $postedDxLines = ClinicalMaster::splitLines($post['diagnosis'] ?? '');
        $postedMedLines = ClinicalMaster::splitLines($post['medication_treatment'] ?? '');
        if (array_key_exists('diagnosis_ids', $post) || array_key_exists('diagnosis', $post)) {
            $badDx = ClinicalMaster::unresolvedDiagnosisLines($conn, $post);
            if ($badDx !== []) {
                throw new Exception(
                    'Each diagnosis line must match an official diagnosis name from the ICD-10 list. Invalid: '
                    . implode('; ', array_slice($badDx, 0, 3))
                );
            }
            if ($postedDxLines !== [] && $diagnosisIds === []) {
                throw new Exception(
                    'Diagnosis must match the diagnosis master list.'
                );
            }
            if (!ClinicalMaster::validateDiagnosisIds($conn, $diagnosisIds)) {
                throw new Exception('One or more diagnoses are invalid. Please choose from the suggestion list.');
            }
        }
        if (
            array_key_exists('medication_ids', $post) || array_key_exists('medication_treatment', $post)
        ) {
            $badMed = ClinicalMaster::unresolvedMedicationLines($conn, $post);
            if ($badMed !== []) {
                throw new Exception(
                    'Each medication line must match an official medication from the master list. Invalid: '
                    . implode('; ', array_slice($badMed, 0, 3))
                );
            }
            if ($postedMedLines !== [] && $medicationIds === []) {
                throw new Exception(
                    'Medication must match the medication master list.'
                );
            }
            if (!ClinicalMaster::validateMedicationIds($conn, $medicationIds)) {
                throw new Exception('One or more medications are invalid. Please choose from the suggestion list.');
            }
        }

        $doctorData = collectTreatmentFields($post, 'doctor');
        // Normalized storage: do not keep newline-separated names on treatment_records
        unset($doctorData['diagnosis'], $doctorData['medication_treatment']);
        $doctorData['diagnosis'] = null;
        $doctorData['medication_treatment'] = null;

        saveTreatmentRecord($conn, $patientDbId, $doctorData, $consultationId);
        saveDoctorKonsultaExtended($conn, $patientDbId, $post, $consultationId);

        // Resolve consultation id after save (new visit edge case)
        if ($consultationId <= 0) {
            $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->bind_param('i', $patientDbId);
            $stmt->execute();
            $crow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $consultationId = $crow ? (int) $crow['id'] : 0;
        }

        if ($consultationId > 0) {
            ClinicalMaster::syncConsultationDiagnoses($conn, $consultationId, $diagnosisIds);
            ClinicalMaster::syncConsultationMedications($conn, $consultationId, $medicationIds);
        }

        require_once __DIR__ . '/../core/FollowUpReminder.php';
        FollowUpReminder::ensureSchema($conn);
        if ($consultationId > 0) {
            FollowUpReminder::syncFromConsultationPost(
                $conn,
                $patientDbId,
                $consultationId,
                $post,
                (int) ($user['id'] ?? 0)
            );
        }
    }

    // --- Save IT-owned PCSF extended fields ---
    if (!$newConsultation && $patientDbId > 0 && Permissions::canEdit($role, 'it')) {
        saveItPcsfExtended($conn, $patientDbId, $post);
    }

    // --- Workflow: submit for consultation ---
    if ($submitValidation && $role === ROLE_RECORDS) {
        $fromStatus = $existing['status'] ?? WORKFLOW_DRAFT;
        $toStatus = WORKFLOW_PENDING_RECEPTIONIST;
        $stmt = $conn->prepare('UPDATE patients SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $toStatus, $patientDbId);
        $stmt->execute();
        $stmt->close();
        logWorkflow($conn, $patientDbId, $user['id'], 'Submitted for consultation', $fromStatus, $toStatus);
    }

    $conn->commit();

    $stmt = $conn->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->bind_param('i', $patientDbId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($consultationId > 0) {
        $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('ii', $consultationId, $patientDbId);
    } else {
        $stmt = $conn->prepare('SELECT receptionist_data FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $patientDbId);
    }
    $stmt->execute();
    $treatmentRow = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $profileExtended = Consultation::loadProfileExtended($patientDbId);
    $consultExtended = ExtendedFormData::fromTreatment($treatmentRow);
    $mergedExtended = PatientProfile::mergeExtendedForConsultation(
        $profileExtended,
        $consultExtended['fields']
    );
    $extendedFields = ExtendedFormData::hydrateAddressFields(
        $mergedExtended,
        $patient['residential_address'] ?? null
    );

    $conn->close();

    echo json_encode([
        'success'          => true,
        'message'          => $newConsultation
            ? 'New consultation visit saved.'
            : ($submitValidation ? 'Record submitted for consultation.' : 'Draft saved successfully.'),
        'patient_db_id'    => $patientDbId,
        'consultation_id'  => $consultationId > 0 ? $consultationId : (!empty($treatmentRow['id']) ? (int) $treatmentRow['id'] : null),
        'new_consultation' => false,
        'patient'          => $patient,
        'profile'          => PatientProfile::buildPayload($patient ?: [], $extendedFields),
        'extended'         => $extendedFields,
        'form_progress'    => (int) ($post['form_progress'] ?? 0),
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(400);
    $message = $e->getMessage();
    if ($e instanceof mysqli_sql_exception) {
        $message = 'Database/SQL error: ' . $message;
    }
    echo json_encode(['success' => false, 'message' => $message]);
}

/**
 * Canonical patient profile from client — avoids duplicate empty fields on multi-page form.
 */
function mergeProfileJsonIntoPost(array &$post): void
{
    if (empty($post['profile_json'])) {
        return;
    }

    $decoded = json_decode($post['profile_json'], true);
    if (!is_array($decoded)) {
        return;
    }

    foreach ($decoded['patient'] ?? [] as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $post[$key] = is_string($value) ? trim($value) : $value;
    }

    if (!isset($post['extended']) || !is_array($post['extended'])) {
        $post['extended'] = [];
    }

    foreach ($decoded['extended'] ?? [] as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $post['extended'][$key] = is_string($value) ? trim($value) : $value;
    }
}

function collectPatientFields(array $post, string $role): array
{
    $fields = [];
    $enrollmentFields = [
        'family_serial_number', 'facility_code', 'last_name', 'first_name', 'middle_name',
        'suffix', 'maiden_name', 'sex', 'birth_date', 'birthplace', 'blood_type',
        'mothers_name', 'residential_address', 'civil_status', 'contact_number',
        'dswd_nhts', 'facility_household_no', 'four_ps_member', 'household_no',
        'philhealth_member', 'philhealth_status_type', 'philhealth_no', 'philhealth_category',
        'educational_attainment', 'employment_status', 'specific_occupation', 'employment_type',
        'spouse_name', 'father_name', 'mother_name', 'son_name', 'daughter_name',
        'other_family_member', 'pcb_member',
    ];
    $consentFields = ['consent_signature_date', 'chu_representative'];

    $profilePatient = [];
    if (!empty($post['profile_json'])) {
        $decoded = json_decode($post['profile_json'], true);
        if (is_array($decoded['patient'] ?? null)) {
            $profilePatient = $decoded['patient'];
        }
    }

    if (Permissions::canEdit($role, 'records_enrollment')) {
        foreach ($enrollmentFields as $f) {
            if (array_key_exists($f, $profilePatient) && $profilePatient[$f] !== null && $profilePatient[$f] !== '') {
                $fields[$f] = trim((string) $profilePatient[$f]) ?: null;
            } elseif (!empty($profilePatient) && array_key_exists($f, $profilePatient)) {
                continue;
            } elseif (isset($post[$f]) && trim((string) $post[$f]) !== '') {
                $fields[$f] = trim((string) $post[$f]);
            }
        }
    }
    if (Permissions::canEdit($role, 'records_consent')) {
        foreach ($consentFields as $f) {
            if (isset($post[$f]) && trim((string) $post[$f]) !== '') {
                $fields[$f] = trim((string) $post[$f]);
            }
        }
    }

    $extended = $post['extended'] ?? [];
    if (!empty($post['profile_json'])) {
        $decoded = json_decode($post['profile_json'], true);
        $profileExt = $decoded['extended'] ?? [];
        if (is_array($profileExt)) {
            $extended = array_merge($extended, array_filter($profileExt, static fn($v) => $v !== null && $v !== ''));
        }
    }

    if (is_array($extended) && Permissions::canEdit($role, 'records_enrollment')) {
        $fields['residential_address'] = ExtendedFormData::buildResidentialAddress(
            $extended,
            $fields['residential_address'] ?? null
        );
    }

    return $fields;
}

function collectTreatmentFields(array $post, string $group): array
{
    $fields = [];

    if ($group === 'records_treatment') {
        $map = [
            'mode_of_transaction', 'date_of_consultation', 'consultation_time', 'consultation_period',
            'blood_pressure', 'temperature', 'height_cm', 'weight_kg', 'attending_provider',
            'nature_of_visit', 'chief_complaints', 'referred_from', 'referred_to',
            'referral_reasons', 'referred_by',
        ];
        foreach ($map as $f) {
            $fields[$f] = trim($post[$f] ?? '') ?: null;
        }
        $types = $post['consultation_types'] ?? '[]';
        $fields['consultation_types'] = $types;
    }

    if ($group === 'doctor') {
        // Doctor Consultation form fields map onto official ITR / EPRESS columns
        foreach ([
            'blood_pressure',
            'height_cm',
            'weight_kg',
            'chief_complaints',
            'diagnosis',
            'medication_treatment',
            'healthcare_provider',
            'laboratory_findings',
            'performed_lab_test',
            'date_of_consultation',
        ] as $f) {
            if (!array_key_exists($f, $post)) {
                continue;
            }
            $fields[$f] = trim((string) ($post[$f] ?? '')) ?: null;
        }
    }

    return $fields;
}

function insertPatient(mysqli $conn, array $data): int
{
    $cols = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $colNames = implode(', ', $cols);
    $types = str_repeat('s', count($cols));
    $values = array_values($data);

    $sql = "INSERT INTO patients ({$colNames}) VALUES ({$placeholders})";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

function updatePatient(mysqli $conn, int $id, array $data, int $userId): void
{
    if (empty($data)) return;

    $sets = [];
    $values = [];
    $types = '';
    foreach ($data as $col => $val) {
        $sets[] = "{$col} = ?";
        $values[] = $val;
        $types .= 's';
    }
    $values[] = $id;
    $types .= 'i';

    $sql = 'UPDATE patients SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();
}

function saveTreatmentRecord(mysqli $conn, int $patientId, array $data, int $consultationId = 0): void
{
    if (empty($data)) return;

    if ($consultationId > 0) {
        $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('ii', $consultationId, $patientId);
    } else {
        $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $patientId);
    }
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $sets = [];
        $values = [];
        $types = '';
        foreach ($data as $col => $val) {
            $sets[] = "{$col} = ?";
            $values[] = $val;
            $types .= 's';
        }
        $values[] = $existing['id'];
        $types .= 'i';
        $sql = 'UPDATE treatment_records SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    } else {
        $data['patient_id'] = $patientId;
        $cols = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colNames = implode(', ', $cols);
        $types = str_repeat('s', count($cols));
        $values = array_values($data);

        $sql = "INSERT INTO treatment_records ({$colNames}) VALUES ({$placeholders})";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }
}

function saveFormProgressOnly(mysqli $conn, int $patientId, int $formProgress): void
{
    if ($formProgress < 0) {
        return;
    }

    $stmt = $conn->prepare('SELECT id, receptionist_data FROM treatment_records WHERE patient_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $existingBlock = ExtendedFormData::fromTreatment($row ?: null);
    $progress = $formProgress > 0 ? $formProgress : $existingBlock['form_progress'];
    $json = ExtendedFormData::toJson(
        $existingBlock['fields'],
        $progress,
        $row['receptionist_data'] ?? null
    );

    if ($row) {
        $stmt = $conn->prepare('UPDATE treatment_records SET receptionist_data = ? WHERE id = ?');
        $stmt->bind_param('si', $json, $row['id']);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare('INSERT INTO treatment_records (patient_id, receptionist_data) VALUES (?, ?)');
    $stmt->bind_param('is', $patientId, $json);
    $stmt->execute();
    $stmt->close();
}

/**
 * Persist doctor visit extended fields (EKAS / EPRESS / doctor notes) on the consultation row.
 */
function saveDoctorKonsultaExtended(mysqli $conn, int $patientId, array $post, int $consultationId = 0): void
{
    $incoming = $post['extended'] ?? [];
    if (!is_array($incoming)) {
        $incoming = [];
    }

    if (!empty($post['profile_json'])) {
        $decoded = json_decode($post['profile_json'], true);
        $profileExt = $decoded['extended'] ?? [];
        if (is_array($profileExt)) {
            foreach ($profileExt as $key => $val) {
                if ($val !== null && $val !== '') {
                    $incoming[$key] = $val;
                }
            }
        }
    }

    $isDoctorVisitKey = static function (string $key): bool {
        return str_starts_with($key, 'ekas_')
            || str_starts_with($key, 'epress_')
            || str_starts_with($key, 'doctor_')
            || $key === 'doctor_notes';
    };

    $allowed = [];
    foreach ($incoming as $key => $val) {
        $key = (string) $key;
        if (!$isDoctorVisitKey($key)) {
            continue;
        }
        if (is_array($val)) {
            continue;
        }
        $allowed[$key] = is_string($val) ? trim($val) : $val;
        if ($allowed[$key] === '') {
            $allowed[$key] = null;
        }
    }

    foreach ($post as $key => $val) {
        if (!is_string($key)) {
            continue;
        }
        if (!$isDoctorVisitKey($key)) {
            continue;
        }
        if (is_array($val)) {
            continue;
        }
        $allowed[$key] = is_string($val) ? trim($val) : $val;
        if ($allowed[$key] === '') {
            $allowed[$key] = null;
        }
    }

    if (empty($allowed)) {
        return;
    }

    $formProgress = (int) ($post['form_progress'] ?? 0);

    if ($consultationId > 0) {
        $stmt = $conn->prepare('SELECT id, receptionist_data FROM treatment_records WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('ii', $consultationId, $patientId);
    } else {
        $stmt = $conn->prepare('SELECT id, receptionist_data FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $patientId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $existingBlock = ExtendedFormData::fromTreatment($row ?: null);
    $merged = array_merge($existingBlock['fields'], $allowed);

    if ($formProgress <= 0 && $existingBlock['form_progress'] > 0) {
        $formProgress = $existingBlock['form_progress'];
    }

    $json = ExtendedFormData::toJson($merged, $formProgress, $row['receptionist_data'] ?? null);

    if ($row) {
        $stmt = $conn->prepare('UPDATE treatment_records SET receptionist_data = ? WHERE id = ?');
        $stmt->bind_param('si', $json, $row['id']);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare('INSERT INTO treatment_records (patient_id, receptionist_data) VALUES (?, ?)');
    $stmt->bind_param('is', $patientId, $json);
    $stmt->execute();
    $stmt->close();
}

/**
 * Persist PhilHealth PCSF extended fields owned by IT only.
 */
function saveItPcsfExtended(mysqli $conn, int $patientId, array $post): void
{
    $incoming = $post['extended'] ?? [];
    if (!is_array($incoming)) {
        $incoming = [];
    }

    if (!empty($post['profile_json'])) {
        $decoded = json_decode($post['profile_json'], true);
        $profileExt = $decoded['extended'] ?? [];
        if (is_array($profileExt)) {
            foreach ($profileExt as $key => $val) {
                if ($val !== null && $val !== '') {
                    $incoming[$key] = $val;
                }
            }
        }
    }

    $allowed = [];
    foreach ($incoming as $key => $val) {
        $key = (string) $key;
        if (str_starts_with($key, 'pcsf_')) {
            $allowed[$key] = is_string($val) ? trim($val) : $val;
            if ($allowed[$key] === '') {
                $allowed[$key] = null;
            }
        }
    }

    foreach ($post as $key => $val) {
        if (!is_string($key) || !str_starts_with($key, 'pcsf_')) {
            continue;
        }
        if (is_array($val)) {
            continue;
        }
        $allowed[$key] = is_string($val) ? trim($val) : $val;
        if ($allowed[$key] === '') {
            $allowed[$key] = null;
        }
    }

    if (empty($allowed)) {
        return;
    }

    $formProgress = (int) ($post['form_progress'] ?? 0);
    $profileId = Consultation::getProfileRecordId($conn, $patientId);
    $row = null;
    if ($profileId) {
        $stmt = $conn->prepare('SELECT id, receptionist_data FROM treatment_records WHERE id = ?');
        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $existingBlock = ExtendedFormData::fromTreatment($row ?: null);
    $merged = array_merge($existingBlock['fields'], $allowed);

    if ($formProgress <= 0 && $existingBlock['form_progress'] > 0) {
        $formProgress = $existingBlock['form_progress'];
    }

    $json = ExtendedFormData::toJson($merged, $formProgress, $row['receptionist_data'] ?? null);

    if ($row) {
        $stmt = $conn->prepare('UPDATE treatment_records SET receptionist_data = ? WHERE id = ?');
        $stmt->bind_param('si', $json, $row['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO treatment_records (patient_id, receptionist_data) VALUES (?, ?)');
        $stmt->bind_param('is', $patientId, $json);
        $stmt->execute();
        $stmt->close();
    }
}

function saveExtendedEnrollment(mysqli $conn, int $patientId, array $post): void
{
    $incoming = $post['extended'] ?? [];
    if (!is_array($incoming)) {
        $incoming = [];
    }

    if (!empty($post['profile_json'])) {
        $decoded = json_decode($post['profile_json'], true);
        $profileExt = $decoded['extended'] ?? [];
        if (is_array($profileExt)) {
            foreach ($profileExt as $key => $val) {
                if ($val !== null && $val !== '') {
                    $incoming[$key] = $val;
                }
            }
        }
    }

    $formProgress = (int) ($post['form_progress'] ?? 0);

    foreach ($incoming as $key => $val) {
        $incoming[$key] = is_string($val) ? trim($val) : $val;
        if ($incoming[$key] === '') {
            $incoming[$key] = null;
        }
    }

    // Doctor-owned Konsulta slips — Records must not overwrite
    foreach (array_keys($incoming) as $key) {
        $key = (string) $key;
        if (str_starts_with($key, 'ekas_') || str_starts_with($key, 'epress_')) {
            unset($incoming[$key]);
        }
    }

    $profileId = Consultation::getProfileRecordId($conn, $patientId);
    $row = null;
    if ($profileId) {
        $stmt = $conn->prepare('SELECT id, receptionist_data FROM treatment_records WHERE id = ?');
        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $existingBlock = ExtendedFormData::fromTreatment($row ?: null);
    $merged = array_merge($existingBlock['fields'], $incoming);

    if ($formProgress <= 0 && $existingBlock['form_progress'] > 0) {
        $formProgress = $existingBlock['form_progress'];
    }

    $json = ExtendedFormData::toJson($merged, $formProgress, $row['receptionist_data'] ?? null);

    if ($row) {
        $stmt = $conn->prepare('UPDATE treatment_records SET receptionist_data = ? WHERE id = ?');
        $stmt->bind_param('si', $json, $row['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO treatment_records (patient_id, receptionist_data) VALUES (?, ?)');
        $stmt->bind_param('is', $patientId, $json);
        $stmt->execute();
        $stmt->close();
    }
}
