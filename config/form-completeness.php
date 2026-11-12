<?php
/**
 * Required fields per official RHU form for IT readiness validation.
 * Currently empty: no fields are mandatory (demo / free fill-up).
 */
return [
  'pcsf' => [
    'label' => 'PhilHealth Primary Care Selection Form (PCSF)',
    'owner' => 'it',
    'patient' => [],
    'extended' => [],
    'custom' => [],
  ],
  'itr' => [
    'label' => 'Individual Treatment Record',
    'treatment' => [],
    'custom' => [],
  ],
  'enrollment' => [
    'label' => 'Patient Enrollment Record',
    'patient' => [],
    'patient_consent' => [],
    'custom' => [],
  ],
  'past_medical_history' => [
    'label' => 'Past Medical History',
    'custom' => [],
  ],
  'past_surgical_history' => [
    'label' => 'Past Surgical History',
    'custom' => [],
  ],
  'family_history' => [
    'label' => 'Family History',
    'custom' => [],
  ],
  'personal_social_history' => [
    'label' => 'Personal / Social History',
    'extended' => [],
  ],
  'ncd_assessment' => [
    'label' => 'NCD High-Risk Assessment',
    'extended' => [],
  ],
  'photo_consent' => [
    'label' => 'Photo Consent Form',
    'extended' => [],
  ],
  'ekas' => [
    'label' => 'Electronic Konsulta Availment Slip (EKAS)',
    'owner' => 'doctor',
    'patient' => [],
    'extended' => [],
  ],
  'epress' => [
    'label' => 'Electronic Prescription Slip (EPRESS)',
    'owner' => 'doctor',
    'conditional' => true,
    'custom' => [],
    'patient' => [],
    'extended' => [],
  ],
];
