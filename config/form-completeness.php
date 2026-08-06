<?php
/**
 * Required fields per official RHU form for IT readiness validation.
 *
 * Keys:
 *   patient  — patients table column
 *   treatment — treatment_records column
 *   extended — receptionist_data JSON field
 *   custom   — handled in FormCompleteness
 */
return [
  'pkrf' => [
    'label' => 'PhilHealth Konsulta Registration Form (PKRF)',
    'owner' => 'it',
    'patient' => ['philhealth_no', 'last_name', 'first_name', 'sex', 'birth_date'],
    'extended' => ['pkrf_registration_date'],
    'custom' => ['philhealth_format'],
  ],
  'itr' => [
    'label' => 'Individual Treatment Record',
    'treatment' => ['date_of_consultation', 'chief_complaints', 'mode_of_transaction'],
    'custom' => ['consultation_types'],
  ],
  'enrollment' => [
    'label' => 'Patient Enrollment Record',
    'patient' => ['last_name', 'first_name', 'sex', 'birth_date', 'contact_number', 'family_serial_number'],
    'patient_consent' => ['consent_signature_date', 'chu_representative'],
    'custom' => ['family_serial_format'],
  ],
  'past_medical_history' => [
    'label' => 'Past Medical History',
    'custom' => ['pmh_completed'],
  ],
  'past_surgical_history' => [
    'label' => 'Past Surgical History',
    'custom' => ['psh_surgical_completed'],
  ],
  'family_history' => [
    'label' => 'Family History',
    'custom' => ['fh_completed'],
  ],
  'personal_social_history' => [
    'label' => 'Personal / Social History',
    'extended' => ['psh_smoking', 'psh_alcohol', 'psh_drugs'],
  ],
  'ncd_assessment' => [
    'label' => 'NCD High-Risk Assessment',
    'extended' => ['ncd_diabetes', 'ncd_physical'],
  ],
  'photo_consent' => [
    'label' => 'Photo Consent Form',
    'extended' => ['photo_consent_name', 'photo_consent_date'],
  ],
  'ekas' => [
    'label' => 'Electronic Konsulta Availment Slip (EKAS)',
    'owner' => 'doctor',
    'patient' => ['philhealth_no'],
    'extended' => ['ekas_hci_name'],
  ],
  'epress' => [
    'label' => 'Electronic Prescription Slip (EPRESS)',
    'owner' => 'doctor',
    'conditional' => true,
    'custom' => ['epress_if_applicable'],
    'patient' => ['philhealth_no'],
    'extended' => ['epress_hci_name'],
  ],
];
