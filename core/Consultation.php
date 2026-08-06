<?php
/**
 * Consultation records — multiple treatment_records per patient (no schema change).
 *
 * Profile forms live on the first treatment record (lowest id).
 * Each consultation is a separate treatment_records row with consultation-specific data.
 */

require_once __DIR__ . '/ExtendedFormData.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/../config/app.php';

class Consultation
{
  public static function getProfileRecordId(mysqli $conn, int $patientId): ?int
  {
    $stmt = $conn->prepare('SELECT id FROM treatment_records WHERE patient_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : null;
  }

  public static function parseItData(?array $treatment): array
  {
    if (empty($treatment['it_data'])) {
      return [
        'consultation_status' => null,
        'visit_number'        => null,
        'doctor_name'         => null,
        'print_history'       => [],
      ];
    }

    $raw = $treatment['it_data'];
    $data = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($data)) {
      $data = [];
    }

    return [
      'consultation_status' => $data['consultation_status'] ?? null,
      'visit_number'        => $data['visit_number'] ?? null,
      'doctor_name'         => $data['doctor_name'] ?? null,
      'print_history'       => is_array($data['print_history'] ?? null) ? $data['print_history'] : [],
    ];
  }

  public static function encodeItData(array $existing, array $patch): string
  {
    $merged = array_merge($existing, $patch);
    return json_encode($merged, JSON_UNESCAPED_UNICODE);
  }

  public static function getItDataArray(?array $treatment): array
  {
    if (!$treatment || empty($treatment['it_data'])) {
      return [];
    }
    $raw = $treatment['it_data'];
    $data = is_string($raw) ? json_decode($raw, true) : $raw;
    return is_array($data) ? $data : [];
  }

  public static function resolveStatus(array $treatment, string $patientStatus): string
  {
    $it = self::parseItData($treatment);
    if (!empty($it['consultation_status'])) {
      return $it['consultation_status'];
    }

    return $patientStatus;
  }

  public static function formatConsultationRow(array $treatment, int $visitNumber, string $patientStatus): array
  {
    if (!empty($treatment['consultation_types']) && is_string($treatment['consultation_types'])) {
      $treatment['consultation_types'] = json_decode($treatment['consultation_types'], true) ?? [];
    }

    $it = self::parseItData($treatment);
    $status = self::resolveStatus($treatment, $patientStatus);
    $doctor = $treatment['healthcare_provider']
      ?: $treatment['attending_provider']
      ?: ($it['doctor_name'] ?: '—');

    $consultDate = !empty($treatment['date_of_consultation'])
      ? date('M d, Y', strtotime($treatment['date_of_consultation']))
      : (!empty($treatment['created_at']) ? date('M d, Y', strtotime($treatment['created_at'])) : '—');

    return [
      'id'                   => (int) $treatment['id'],
      'visit_number'         => $it['visit_number'] ?: $visitNumber,
      'date_of_consultation' => $treatment['date_of_consultation'] ?? null,
      'consult_date_display' => $consultDate,
      'doctor'               => $doctor,
      'status'               => $status,
      'status_class'         => WorkflowStatus::badgeClass($status),
      'chief_complaint'      => $treatment['chief_complaints'] ?: null,
      'diagnosis'            => $treatment['diagnosis'] ?: null,
      'is_locked'            => WorkflowStatus::isLocked($status),
      'forms'                => ['itr', 'ekas', 'epress'],
      'print_history'        => $it['print_history'],
    ];
  }

  /**
   * @return array<int, array>
   */
  public static function listForPatient(int $patientId, string $patientStatus): array
  {
    require_once __DIR__ . '/ClinicalMaster.php';
    $conn = getDBConnection();
    ClinicalMaster::ensureSchema($conn);
    $stmt = $conn->prepare(
      'SELECT * FROM treatment_records WHERE patient_id = ? ORDER BY COALESCE(date_of_consultation, created_at) DESC, id DESC'
    );
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $total = $result->num_rows;
    $index = 0;
    while ($row = $result->fetch_assoc()) {
      $hydrated = ClinicalMaster::hydrateTreatmentClinical($conn, $row);
      $visitNumber = $total - $index;
      $rows[] = self::formatConsultationRow($hydrated['treatment'], $visitNumber, $patientStatus);
      $index++;
    }

    $stmt->close();
    $conn->close();

    return $rows;
  }

  public static function load(int $treatmentId, int $patientId): ?array
  {
    require_once __DIR__ . '/ClinicalMaster.php';
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE id = ? AND patient_id = ?');
    $stmt->bind_param('ii', $treatmentId, $patientId);
    $stmt->execute();
    $treatment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$treatment) {
      $conn->close();
      return null;
    }

    ClinicalMaster::ensureSchema($conn);
    $hydrated = ClinicalMaster::hydrateTreatmentClinical($conn, $treatment);
    $treatment = $hydrated['treatment'];
    $conn->close();

    if (!empty($treatment['consultation_types']) && is_string($treatment['consultation_types'])) {
      $treatment['consultation_types'] = json_decode($treatment['consultation_types'], true) ?? [];
    }

    return $treatment;
  }

  public static function loadProfileExtended(int $patientId): array
  {
    $conn = getDBConnection();
    $profileId = self::getProfileRecordId($conn, $patientId);

    if (!$profileId) {
      $conn->close();
      return [];
    }

    $stmt = $conn->prepare('SELECT receptionist_data FROM treatment_records WHERE id = ?');
    $stmt->bind_param('i', $profileId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    $block = ExtendedFormData::fromTreatment($row ?: null);
    return $block['fields'];
  }

  /**
   * Create a brand-new consultation visit for a patient.
   * Does NOT copy clinical data from previous visits.
   * Patient identity remains on the patients table / profile record.
   */
  public static function create(int $patientId, int $userId): int
  {
    $conn = getDBConnection();

    $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM treatment_records WHERE patient_id = ?');
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $countRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $visitNumber = ((int) ($countRow['cnt'] ?? 0)) + 1;

    // Visit metadata only — no clinical fields, no prior-visit receptionist_data.
    $itData = json_encode([
      'consultation_status' => WORKFLOW_DRAFT,
      'visit_number'        => $visitNumber,
      'created_by'          => $userId,
      'created_at'          => date('Y-m-d H:i:s'),
      'print_history'       => [],
    ], JSON_UNESCAPED_UNICODE);

    $today = date('Y-m-d');
    $stmt = $conn->prepare(
      'INSERT INTO treatment_records (
          patient_id,
          date_of_consultation,
          it_data,
          receptionist_data,
          chief_complaints,
          diagnosis,
          medication_treatment,
          blood_pressure,
          temperature,
          height_cm,
          weight_kg,
          laboratory_findings,
          performed_lab_test,
          referred_to,
          referral_reasons,
          referred_from,
          referred_by,
          nature_of_visit,
          consultation_types,
          healthcare_provider,
          attending_provider
        ) VALUES (?, ?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)'
    );
    $stmt->bind_param('iss', $patientId, $today, $itData);
    $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare('UPDATE patients SET status = ? WHERE id = ?');
    $draft = WORKFLOW_DRAFT;
    $stmt->bind_param('si', $draft, $patientId);
    $stmt->execute();
    $stmt->close();

    $conn->close();

    return $newId;
  }

  /**
   * @return array<int, array>
   */
  public static function aggregatePrintHistory(int $patientId, string $patientStatus): array
  {
    $consultations = self::listForPatient($patientId, $patientStatus);
    $history = [];

    foreach ($consultations as $consultation) {
      foreach ($consultation['print_history'] as $entry) {
        $history[] = array_merge($entry, [
          'consultation_id'   => $consultation['id'],
          'visit_number'      => $consultation['visit_number'],
          'consultation_date' => $entry['consultation_date'] ?? $consultation['consult_date_display'],
        ]);
      }
    }

    usort($history, static function ($a, $b) {
      return strcmp($b['printed_at'] ?? '', $a['printed_at'] ?? '');
    });

    return $history;
  }

  public static function appendPrintHistory(int $treatmentId, array $entry): void
  {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT it_data FROM treatment_records WHERE id = ?');
    $stmt->bind_param('i', $treatmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $it = self::getItDataArray($row);
    if (!isset($it['print_history']) || !is_array($it['print_history'])) {
      $it['print_history'] = [];
    }
    $it['print_history'][] = $entry;

    $json = json_encode($it, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare('UPDATE treatment_records SET it_data = ? WHERE id = ?');
    $stmt->bind_param('si', $json, $treatmentId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
  }

  public static function updateConsultationStatus(int $treatmentId, int $patientId, string $status, ?string $doctorName = null): void
  {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT it_data FROM treatment_records WHERE id = ? AND patient_id = ?');
    $stmt->bind_param('ii', $treatmentId, $patientId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $it = self::getItDataArray($row);
    $it['consultation_status'] = $status;
    if ($doctorName) {
      $it['doctor_name'] = $doctorName;
    }

    $json = json_encode($it, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare('UPDATE treatment_records SET it_data = ? WHERE id = ?');
    $stmt->bind_param('si', $json, $treatmentId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE patients SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $patientId);
    $stmt->execute();
    $stmt->close();

    $conn->close();
  }
}
