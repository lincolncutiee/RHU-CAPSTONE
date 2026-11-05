<?php
/**
 * Role-based field permission configuration.
 * Field ownership is by field_group — never by placeholder text.
 *
 * Field groups:
 * - records_enrollment / records_consent / records_treatment → Records Office
 * - doctor → Doctor (clinical findings + EKAS + EPRESS)
 * - it → IT Department (patient encoding + PhilHealth PCSF)
 * - receptionist → Receptionist (reserved)
 */

return [
    ROLE_SYSTEM_ADMIN => [
        'label'    => 'System Administrator',
        'editable' => [],
        'readonly' => ['records_enrollment', 'records_consent', 'records_treatment', 'doctor', 'it', 'receptionist', 'pharmacy'],
    ],
    ROLE_RECORDS => [
        'label'    => 'Records Office Staff',
        'editable' => ['records_enrollment', 'records_consent', 'records_treatment'],
        'readonly' => ['doctor', 'it', 'receptionist'],
    ],
    ROLE_DOCTOR => [
        'label'    => 'Doctor',
        'editable' => ['doctor'],
        'readonly' => ['records_enrollment', 'records_consent', 'records_treatment', 'it', 'receptionist'],
    ],
    ROLE_IT => [
        'label'    => 'IT Department',
        // IT can encode / complete Records Office fields + PCSF; doctor clinical stays readonly
        'editable' => ['it', 'records_enrollment', 'records_consent', 'records_treatment'],
        'readonly' => ['doctor', 'receptionist'],
    ],
    ROLE_PHARMACY => [
        'label'    => 'Pharmacy',
        'editable' => ['pharmacy'],
        'readonly' => ['records_enrollment', 'records_consent', 'records_treatment', 'doctor', 'it', 'receptionist'],
    ],
    ROLE_RECEPTIONIST => [
        'label'    => 'Receptionist',
        'editable' => ['receptionist'],
        'readonly' => ['records_enrollment', 'records_consent', 'records_treatment', 'doctor', 'it'],
    ],
];
