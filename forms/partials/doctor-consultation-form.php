<?php
/**
 * Doctor Consultation Workspace UI — clinical findings only.
 * Demographics + vitals auto-load from the SAME treatment_records row (IT encoding).
 * Writes doctor findings back into that visit — does not create duplicates.
 *
 * @var array|null $patientRow
 * @var string $patientStatus
 * @var int $patientId
 * @var int $consultationId
 * @var string $summaryName
 * @var string $summarySex
 * @var string $summaryAge
 * @var string $summaryAddress
 * @var string $summaryContact
 * @var array $user
 * @var string|null $queueNumber
 * @var string $intakeChiefComplaint
 */

$normalizedStatus = WorkflowStatus::normalize($patientStatus);
$canSubmitConsultation = in_array($normalizedStatus, [WORKFLOW_WAITING_FOR_DOCTOR, WORKFLOW_PENDING_DOCTOR], true);
$ageYears = $patientRow ? calculateAge($patientRow['birth_date'] ?? null) : null;
if (!isset($summaryAge) || $summaryAge === '') {
    $summaryAge = $ageYears !== null ? $ageYears . ' years old' : '—';
}
$summaryAddress = $summaryAddress ?? (trim((string) ($patientRow['residential_address'] ?? '')) ?: '—');
$summaryContact = $summaryContact ?? ($patientRow['contact_number'] ?: '—');
$doctorName = trim((string) ($user['full_name'] ?? ''));
$qDisplay = trim((string) ($queueNumber ?? ''));
$ro = $canSubmitConsultation ? '' : 'readonly';
?>
<div class="dc-page" id="doctorConsultationPage">
  <header class="dc-header">
    <a href="<?= BASE_URL ?>/workflow/queue.php" class="dc-back">
      <i class="bi bi-arrow-left"></i> Doctor Consultation Queue
    </a>
    <div class="dc-header-main">
      <div>
        <h1 class="dc-title">Doctor Consultation</h1>
        <?php if ($qDisplay !== ''): ?>
        <div class="dc-qnum-line">Queue <strong><?= htmlspecialchars($qDisplay) ?></strong></div>
        <?php endif; ?>
        <p class="dc-subtitle">Working interface only — findings save into the original RHU consultation record.</p>
      </div>
      <div class="dc-header-status"><?= WorkflowStatus::badgeHtml($normalizedStatus) ?></div>
    </div>
  </header>

  <?php if (!$canSubmitConsultation): ?>
  <div class="dc-alert" role="status">
    <i class="bi bi-lock"></i>
    This visit is read-only. Use the Doctor Consultation Queue for patients Waiting for Doctor.
    View original RHU forms from Patient Records → Consultation Records → View Form.
  </div>
  <?php endif; ?>

  <form id="doctorConsultationForm" class="dc-form" autocomplete="off" novalidate>
    <input type="hidden" name="patient_db_id" id="patientDbId" value="<?= (int) $patientId ?>">
    <input type="hidden" name="consultation_id" id="consultationId" value="<?= (int) $consultationId ?>">
    <input type="hidden" name="healthcare_provider" id="healthcareProvider" value="<?= htmlspecialchars($doctorName) ?>">
    <input type="hidden" name="date_of_consultation" id="dateOfConsultation" value="<?= htmlspecialchars(date('Y-m-d')) ?>">

    <section class="dc-card dc-card-profile">
      <h2 class="dc-section-title">Patient Information</h2>
      <p class="dc-section-hint">Auto-loaded from the consultation record (read only)</p>
      <div class="dc-grid dc-grid-4">
        <div class="dc-field dc-field-span-2">
          <label>Patient Name</label>
          <div class="dc-readonly" id="dcPatientName"><?= htmlspecialchars($summaryName) ?></div>
        </div>
        <div class="dc-field">
          <label>Age</label>
          <div class="dc-readonly" id="dcPatientAge"><?= htmlspecialchars($summaryAge) ?></div>
        </div>
        <div class="dc-field">
          <label>Sex</label>
          <div class="dc-readonly" id="dcPatientSex"><?= htmlspecialchars($summarySex) ?></div>
        </div>
        <div class="dc-field">
          <label>Contact Number</label>
          <div class="dc-readonly" id="dcPatientContact"><?= htmlspecialchars($summaryContact) ?></div>
        </div>
        <div class="dc-field dc-field-span-3">
          <label>Address</label>
          <div class="dc-readonly" id="dcPatientAddress"><?= htmlspecialchars($summaryAddress) ?></div>
        </div>
      </div>
    </section>

    <section class="dc-card">
      <h2 class="dc-section-title">Vital Signs</h2>
      <p class="dc-section-hint">From IT encoding on this visit — BMI auto-computes from height &amp; weight</p>
      <div class="dc-grid dc-grid-4">
        <div class="dc-field">
          <label>Blood Pressure</label>
          <div class="dc-readonly" id="dc_blood_pressure">—</div>
        </div>
        <div class="dc-field">
          <label>Height (cm)</label>
          <div class="dc-readonly" id="dc_height_cm">—</div>
        </div>
        <div class="dc-field">
          <label>Weight (kg)</label>
          <div class="dc-readonly" id="dc_weight_kg">—</div>
        </div>
        <div class="dc-field">
          <label>BMI</label>
          <div class="dc-readonly dc-bmi" id="bmi_display">—</div>
        </div>
      </div>
    </section>

    <section class="dc-card">
      <h2 class="dc-section-title">Medical Findings</h2>
      <p class="dc-section-hint">Complete these fields — they write into the original ITR / EPRESS for this visit</p>
      <div class="dc-field">
        <label for="chief_complaints">Chief Complaint</label>
        <textarea class="form-control" id="chief_complaints" name="chief_complaints" rows="3" <?= $ro ?>><?= htmlspecialchars($intakeChiefComplaint ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="dc-field mt-3">
        <label for="diagnosis">Diagnosis</label>
        <div class="dc-dx-notes" id="diagnosisNotesRoot">
          <textarea class="form-control"
                    id="diagnosis"
                    name="diagnosis"
                    rows="3"
                    autocomplete="off"
                    spellcheck="true"
                    <?= $ro ?>></textarea>
          <ul class="dc-ac-suggestions dc-dx-suggestions" id="diagnosisSuggestions" hidden role="listbox"></ul>
          <p class="dc-ac-dataset-msg" id="diagnosisDatasetMsg" hidden></p>
          <input type="hidden" name="diagnosis_ids" id="diagnosis_ids" value="[]">
        </div>
      </div>
      <div class="dc-field mt-3">
        <label for="laboratory_findings">Assessment</label>
        <textarea class="form-control" id="laboratory_findings" name="laboratory_findings" rows="3" <?= $ro ?>></textarea>
      </div>
      <div class="dc-field mt-3">
        <label for="medication_treatment">Medication / Prescription</label>
        <div class="dc-dx-notes" id="medicationNotesRoot">
          <textarea class="form-control"
                    id="medication_treatment"
                    name="medication_treatment"
                    rows="3"
                    autocomplete="off"
                    spellcheck="true"
                    <?= $ro ?>></textarea>
          <ul class="dc-ac-suggestions dc-dx-suggestions" id="medicationSuggestions" hidden role="listbox"></ul>
          <p class="dc-ac-dataset-msg" id="medicationDatasetMsg" hidden></p>
          <input type="hidden" name="medication_ids" id="medication_ids" value="[]">
        </div>
      </div>
      <div class="dc-field mt-3">
        <label for="doctor_treatment_plan">Treatment Plan</label>
        <textarea class="form-control" id="doctor_treatment_plan" name="extended[doctor_treatment_plan]" rows="3" <?= $ro ?>></textarea>
      </div>
      <div class="dc-field mt-3">
        <label for="doctor_follow_up">Follow-up Instructions</label>
        <textarea class="form-control" id="doctor_follow_up" name="extended[doctor_follow_up]" rows="3" <?= $ro ?>></textarea>
      </div>

      <div class="dc-followup-card mt-3">
        <div class="dc-followup-card__head">
          <h3 class="dc-followup-card__title"><i class="bi bi-calendar2-check"></i> Follow-up check-up</h3>
          <p class="dc-followup-card__sub">Schedule a return visit. Reminders use <strong>email</strong> (no SMS load) when enabled in Admin.</p>
        </div>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label" for="follow_up_date">Follow-up date</label>
            <input type="date" class="form-control" id="follow_up_date" name="follow_up_date" <?= $ro ?>>
          </div>
          <div class="col-md-5">
            <label class="form-label" for="patient_email">Patient email</label>
            <input type="email" class="form-control" id="patient_email" name="patient_email" placeholder="name@gmail.com" <?= $ro ?>>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check dc-followup-remind">
              <input class="form-check-input" type="checkbox" value="1" id="follow_up_remind" name="follow_up_remind" <?= $canSubmitConsultation ? '' : 'disabled' ?>>
              <label class="form-check-label" for="follow_up_remind">Send email reminder</label>
            </div>
          </div>
        </div>
        <div class="row g-2 mt-1 align-items-center">
          <div class="col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="reminder_email_opt_in" name="reminder_email_opt_in" <?= $canSubmitConsultation ? '' : 'disabled' ?>>
              <label class="form-check-label" for="reminder_email_opt_in">Patient consents to email reminders</label>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label visually-hidden" for="follow_up_channel">Channel</label>
            <input type="hidden" id="follow_up_channel" name="follow_up_channel" value="email">
            <details class="dc-followup-sms-details">
              <summary class="small text-muted">Optional: paid SMS (mobile <?= htmlspecialchars($summaryContact) ?>)</summary>
              <div class="mt-2">
                <select class="form-select form-select-sm" id="follow_up_channel_sms_extra" <?= $canSubmitConsultation ? '' : 'disabled' ?>>
                  <option value="email">Email only (recommended)</option>
                  <option value="both">Email + SMS</option>
                  <option value="sms">SMS only (has cost)</option>
                </select>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" value="1" id="reminder_sms_opt_in" name="reminder_sms_opt_in" <?= $canSubmitConsultation ? '' : 'disabled' ?>>
                  <label class="form-check-label" for="reminder_sms_opt_in">Patient consents to SMS reminders</label>
                </div>
              </div>
            </details>
          </div>
        </div>
        <p class="dc-followup-hint mb-0"><i class="bi bi-envelope"></i> Email is sent by the daily job after <strong>Admin → Messaging</strong> SMTP is set up. SMS needs a paid gateway.</p>
      </div>
    </section>

    <div class="dc-actions">
      <span class="dc-save-status" id="dcSaveStatus" aria-live="polite"></span>
      <div class="dc-actions-btns">
        <a href="<?= BASE_URL ?>/patient-folder.php?id=<?= (int) $patientId ?>&tab=consultations"
           class="btn btn-outline-secondary">
          <i class="bi bi-folder2-open"></i> Patient Records
        </a>
        <button type="button" class="btn btn-outline-primary" id="btnDcSaveDraft" <?= $canSubmitConsultation ? '' : 'disabled' ?>>
          <i class="bi bi-save"></i> Save Consultation
        </button>
        <button type="button" class="btn btn-success" id="btnDcSubmit" <?= $canSubmitConsultation ? '' : 'disabled' ?>>
          <i class="bi bi-capsule"></i> Send to Pharmacy
        </button>
      </div>
    </div>
  </form>
</div>
