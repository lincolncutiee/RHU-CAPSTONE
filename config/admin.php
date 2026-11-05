<?php
/**
 * Administration module — assignable roles and field groups (no receptionist).
 */

return [
    'assignable_roles' => [
        ROLE_RECORDS  => 'Records Office',
        ROLE_DOCTOR   => 'Doctor',
        ROLE_PHARMACY => 'Pharmacy',
        ROLE_IT       => 'IT Department',
    ],
    'privilege_levels' => [
        'inherit' => 'Default (from role)',
        'none'    => 'No access',
        'view'    => 'View only',
        'edit'    => 'Can edit',
    ],
    'assignable_field_groups' => [
        'records_enrollment' => 'Patient Enrollment — Records Office',
        'records_consent'    => 'Patient Consent — Records Office',
        'records_treatment'  => 'Treatment / RHU fields — Records Office',
        'doctor'             => 'Clinical / EKAS / EPRESS — Doctor',
        'it'                 => 'IT encoding & PCSF',
        'pharmacy'           => 'Pharmacy',
    ],
    'logo' => [
        'max_bytes'      => 2 * 1024 * 1024,
        'allowed_types'  => ['image/png', 'image/jpeg', 'image/webp'],
        'upload_subdir'  => 'uploads/branding',
    ],
];
