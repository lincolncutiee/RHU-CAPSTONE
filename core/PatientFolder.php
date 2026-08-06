<?php
/**
 * Patient Folder — EMR summary, permissions, and data loading.
 */

require_once __DIR__ . '/Consultation.php';
require_once __DIR__ . '/ExtendedFormData.php';
require_once __DIR__ . '/PatientProfile.php';
require_once __DIR__ . '/Permissions.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/Workflow.php';
require_once __DIR__ . '/ItPrintForms.php';

class PatientFolder
{
  public static function load(int $patientId): ?array
  {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT p.*, u.full_name AS encoded_by_name FROM patients p LEFT JOIN users u ON u.id = p.encoded_by WHERE p.id = ? AND p.is_archived = 0');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$patient) {
      return null;
    }

    $profileExtended = Consultation::loadProfileExtended($patientId);
    $extended = ExtendedFormData::hydrateAddressFields(
      $profileExtended,
      $patient['residential_address'] ?? null
    );

    $consultations = Consultation::listForPatient($patientId, $patient['status']);
    $consultations = self::attachVisitForms($consultations, $patientId, $patient, $extended);
    $printHistory = Consultation::aggregatePrintHistory($patientId, $patient['status']);
    $latestConsultation = $consultations[0] ?? null;

    return [
      'patient'             => $patient,
      'summary'             => self::buildSummary($patient, $extended),
      'extended'            => $extended,
      'profile'             => PatientProfile::buildPayload($patient, $extended),
      'consultations'       => $consultations,
      'print_history'       => $printHistory,
      'latest_consultation' => $latestConsultation,
    ];
  }

  public static function buildSummary(array $patient, array $extended): array
  {
    $middle = trim($patient['middle_name'] ?? '');
    $fullName = trim(
      ($patient['last_name'] ?? '') . ', '
      . ($patient['first_name'] ?? '')
      . ($middle !== '' ? ' ' . $middle : '')
      . (!empty($patient['suffix']) ? ' ' . $patient['suffix'] : '')
    );

    $birthDate = !empty($patient['birth_date'])
      ? date('M d, Y', strtotime($patient['birth_date']))
      : '—';

    $regDate = !empty($patient['date_encoded'])
      ? date('M d, Y', strtotime($patient['date_encoded']))
      : '—';

    return [
      'patient_id'       => $patient['patient_id'],
      'philhealth_no'    => $patient['philhealth_no'] ?: '—',
      'family_serial'    => $patient['family_serial_number'] ?: '—',
      'full_name'        => $fullName,
      'birth_date'       => $birthDate,
      'age'              => calculateAge($patient['birth_date'] ?? null),
      'sex'              => $patient['sex'] ?: '—',
      'civil_status'     => $patient['civil_status'] ?: '—',
      'address'          => $patient['residential_address'] ?: '—',
      'contact_number'   => $patient['contact_number'] ?: '—',
      'registration_date'=> $regDate,
      'status'           => $patient['status'],
      'status_class'     => WorkflowStatus::badgeClass($patient['status']),
    ];
  }

  public static function formatPatientName(array $patient): string
  {
    return ItPrintForms::formatPatientName($patient);
  }

  public static function canCreateConsultation(string $role): bool
  {
    return in_array($role, [ROLE_RECORDS, ROLE_IT, ROLE_DOCTOR], true);
  }

  public static function canEditProfile(string $role, string $status): bool
  {
    if (WorkflowStatus::isLocked($status)) {
      return false;
    }
    $role = Permissions::normalizeRole($role);
    if ($role === ROLE_IT) {
      return true;
    }
    return Permissions::canEdit($role, 'records_enrollment')
      || Permissions::canEdit($role, 'records_consent');
  }

  public static function canEditConsultation(string $role, string $consultationStatus): bool
  {
    if (WorkflowStatus::isLocked($consultationStatus)) {
      return false;
    }
    return Permissions::canEdit($role, 'doctor')
      || Permissions::canEdit($role, 'records_treatment');
  }

  public static function canPrint(string $role, string $status): bool
  {
    return Workflow::canPrint($role, $status);
  }

  public static function canReprint(string $role): bool
  {
    return $role === ROLE_IT;
  }

  public static function profileForms(): array
  {
    return [
      ['id' => 'pkrf',       'label' => 'PhilHealth Konsulta Registration Form (PKRF)', 'page' => 'pkrf'],
      ['id' => 'enrollment', 'label' => 'Patient Enrollment Record', 'page' => 'enrollment'],
      ['id' => 'history',    'label' => 'Past Medical / Surgical / Family History', 'page' => 'history'],
      ['id' => 'personal',   'label' => 'Personal / Social History, NCD & Photo Consent', 'page' => 'personal'],
    ];
  }

  public static function consultationForms(): array
  {
    return [
      ['id' => 'itr',    'label' => 'Individual Treatment Record (ITR)'],
      ['id' => 'ekas',   'label' => 'Electronic Konsulta Availment Slip (EKAS)'],
      ['id' => 'epress', 'label' => 'Electronic Prescription Slip (EPRESS)'],
    ];
  }

  /**
   * Attach Open Form cards for each visit (UI only — uses existing consultation + profile data).
   *
   * @param list<array> $consultations
   * @return list<array>
   */
  public static function attachVisitForms(array $consultations, int $patientId, array $patient, array $extended): array
  {
    if ($consultations === []) {
      return [];
    }

    $conn = getDBConnection();
    require_once __DIR__ . '/ClinicalMaster.php';
    ClinicalMaster::ensureSchema($conn);
    $byId = [];
    $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE patient_id = ?');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $cid = (int) $row['id'];
      $row['_has_diagnoses'] = ClinicalMaster::hasConsultationDiagnoses($conn, $cid);
      $row['_has_medications'] = ClinicalMaster::hasConsultationMedications($conn, $cid);
      if ($row['_has_diagnoses'] || $row['_has_medications']) {
        $hydrated = ClinicalMaster::hydrateTreatmentClinical($conn, $row);
        $row = array_merge($hydrated['treatment'], [
          '_has_diagnoses' => $row['_has_diagnoses'],
          '_has_medications' => $row['_has_medications'],
        ]);
      }
      $byId[$cid] = $row;
    }
    $stmt->close();
    $conn->close();

    $oldestId = null;
    $oldestTs = null;
    foreach ($consultations as $c) {
      $id = (int) ($c['id'] ?? 0);
      $row = $byId[$id] ?? null;
      $ts = strtotime((string) ($row['date_of_consultation'] ?? $row['created_at'] ?? '')) ?: $id;
      if ($oldestId === null || $ts < $oldestTs) {
        $oldestId = $id;
        $oldestTs = $ts;
      }
    }

    $profileForms = self::detectProfileFormsWithData($patient, $extended, $patientId);

    foreach ($consultations as &$c) {
      $id = (int) ($c['id'] ?? 0);
      $row = $byId[$id] ?? [];
      $forms = self::detectConsultationFormsWithData($row, $patientId, $id);
      if ($id === $oldestId && $profileForms !== []) {
        $forms = array_merge($profileForms, $forms);
      }
      $c['visit_forms'] = $forms;
      $c['sort_ts'] = strtotime((string) ($c['date_of_consultation'] ?? ''))
        ?: strtotime((string) ($row['created_at'] ?? ''))
        ?: $id;
    }
    unset($c);

    return $consultations;
  }

  /** @return list<array{id:string,label:string,url:string,kind:string}> */
  private static function detectProfileFormsWithData(array $patient, array $extended, int $patientId): array
  {
    $base = BASE_URL . '/forms/doh-form.php?id=' . $patientId . '&page=';
    $forms = [];

    $hasPkRf = self::hasText($patient['philhealth_no'] ?? null)
      || self::hasPrefixedKey($extended, 'pkrf_');
    if ($hasPkRf) {
      $forms[] = [
        'id' => 'pkrf',
        'label' => 'PhilHealth Konsulta Registration Form (PKRF)',
        'url' => $base . 'pkrf',
        'kind' => 'profile',
      ];
    }

    $hasEnrollment = self::hasText($patient['last_name'] ?? null)
      && self::hasText($patient['first_name'] ?? null);
    if ($hasEnrollment) {
      $forms[] = [
        'id' => 'enrollment',
        'label' => 'Patient Enrollment Record',
        'url' => $base . 'enrollment',
        'kind' => 'profile',
      ];
    }

    if (
      self::hasPrefixedKey($extended, 'pmh_')
      || self::hasPrefixedKey($extended, 'surgical_')
      || self::hasPrefixedKey($extended, 'fh_')
    ) {
      $forms[] = [
        'id' => 'history',
        'label' => 'Past Medical / Surgical / Family History',
        'url' => $base . 'history',
        'kind' => 'profile',
      ];
    }

    if (
      self::hasPrefixedKey($extended, 'psh_')
      || self::hasPrefixedKey($extended, 'ncd_')
      || self::hasPrefixedKey($extended, 'photo_consent_')
    ) {
      $forms[] = [
        'id' => 'personal',
        'label' => 'Personal / Social History, NCD & Photo Consent',
        'url' => $base . 'personal',
        'kind' => 'profile',
      ];
    }

    return $forms;
  }

  /** @return list<array{id:string,label:string,url:string,kind:string}> */
  private static function detectConsultationFormsWithData(array $treatment, int $patientId, int $consultationId): array
  {
    $ext = ExtendedFormData::fromTreatment($treatment)['fields'];
    return self::buildConsultFormCards($treatment, $ext, $patientId, $consultationId);
  }

  /** @return list<array{id:string,label:string,url:string,kind:string}> */
  private static function buildConsultFormCards(array $treatment, array $ext, int $patientId, int $consultationId): array
  {
    $forms = [];
    $open = static function (string $page) use ($patientId, $consultationId): string {
      $url = BASE_URL . '/forms/doh-form.php?id=' . $patientId . '&page=' . rawurlencode($page);
      if ($consultationId > 0) {
        $url .= '&consultation_id=' . $consultationId;
      }
      return $url;
    };

    $hasVitalsOrClinical = self::hasText($treatment['chief_complaints'] ?? null)
      || self::hasText($treatment['diagnosis'] ?? null)
      || self::hasText($treatment['blood_pressure'] ?? null)
      || self::hasText($treatment['height_cm'] ?? null)
      || self::hasText($treatment['weight_kg'] ?? null)
      || self::hasText($treatment['healthcare_provider'] ?? null)
      || self::hasText($treatment['attending_provider'] ?? null)
      || self::hasText($treatment['date_of_consultation'] ?? null)
      || self::hasText($treatment['medication_treatment'] ?? null)
      || self::hasText($treatment['mode_of_transaction'] ?? null)
      || !empty($treatment['_has_diagnoses'])
      || !empty($treatment['_has_medications']);

    // Always open the original ITR / RHU paper form — never the Doctor Workspace.
    if ($hasVitalsOrClinical) {
      $forms[] = [
        'id' => 'itr',
        'label' => 'Individual Treatment Record (ITR) / Doctor Consultation Form',
        'url' => $open('itr'),
        'kind' => 'consultation',
      ];
    }

    if (self::hasPrefixedKey($ext, 'ekas_')) {
      $forms[] = [
        'id' => 'ekas',
        'label' => 'Electronic Konsulta Availment Slip (EKAS)',
        'url' => $open('ekas'),
        'kind' => 'consultation',
      ];
    }

    if (self::hasText($treatment['medication_treatment'] ?? null)
        || !empty($treatment['_has_medications'])
        || self::hasPrefixedKey($ext, 'epress_')
    ) {
      $forms[] = [
        'id' => 'epress',
        'label' => 'Electronic Prescription Slip (EPRESS)',
        'url' => $open('epress'),
        'kind' => 'consultation',
      ];
    }

    if (self::hasText($treatment['referred_to'] ?? null) || self::hasText($treatment['referral_reasons'] ?? null)) {
      $forms[] = [
        'id' => 'referral',
        'label' => 'Referral Form',
        'url' => $open('itr'),
        'kind' => 'consultation',
      ];
    }

    return $forms;
  }

  private static function hasText(mixed $value): bool
  {
    if ($value === null) {
      return false;
    }
    if (is_string($value)) {
      return trim($value) !== '' && trim($value) !== '—';
    }
    if (is_array($value)) {
      return count(array_filter($value, static fn($v) => self::hasText($v))) > 0;
    }
    return true;
  }

  private static function hasPrefixedKey(array $data, string $prefix): bool
  {
    foreach ($data as $key => $val) {
      if (!is_string($key) || !str_starts_with($key, $prefix)) {
        continue;
      }
      if (self::hasText($val)) {
        return true;
      }
    }
    return false;
  }
}
