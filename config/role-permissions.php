<?php
/**
 * Configurable role permissions — catalog, defaults, and field-group caps.
 *
 * Stored overrides: role_permissions (role_slug, permission_key, allowed).
 * Missing rows fall back to defaults below.
 */

$patient = [
    'label' => 'Patient & registration',
    'permissions' => [
        'patient.create'              => 'Create new patient / start encoding',
        'patient.view'                => 'View patient records & search',
        'patient.edit_demographics'   => 'Edit enrollment & personal information',
        'patient.edit_consent'        => 'Edit consent section',
        'patient.edit_treatment_records' => 'Edit treatment / RHU records section',
        'patient.archive'             => 'Archive patient record',
        'patient.folder'              => 'Open patient folder',
    ],
];

$clinical = [
    'label' => 'Clinical (Doctor)',
    'permissions' => [
        'clinical.view'       => 'View clinical section',
        'clinical.edit'       => 'Edit clinical findings, EKAS & EPRESS',
        'clinical.queue'      => 'Access doctor consultation queue',
        'clinical.diagnosis'  => 'Save diagnosis & consultation notes',
    ],
];

$pharmacy = [
    'label' => 'Pharmacy',
    'permissions' => [
        'pharmacy.queue'              => 'Access pharmacy queue',
        'pharmacy.dispense'           => 'Record dispensing',
        'pharmacy.view_prescription'  => 'View prescription details',
    ],
];

$it = [
    'label' => 'IT & printing',
    'permissions' => [
        'it.encode'             => 'IT encoding & PCSF fields',
        'it.validation_queue'   => 'Access IT validation queue',
        'it.validate'           => 'Approve or return for correction',
        'it.print_queue'        => 'Access print queue',
        'it.print'              => 'Mark forms as printed',
    ],
];

$workflow = [
    'label' => 'Workflow',
    'permissions' => [
        'workflow.view_queue'        => 'View department queue',
        'workflow.advance'           => 'Send patient to next workflow step',
        'workflow.return_correction' => 'Return record for correction',
    ],
];

$surveillance = [
    'label' => 'Surveillance & documents',
    'permissions' => [
        'heatmap.view'        => 'View disease heat map',
        'historical.view'     => 'View historical / scanned records',
        'historical.upload'   => 'Upload historical records',
        'reports.view'        => 'View reports & dashboard overview',
    ],
];

$admin = [
    'label' => 'Administration',
    'permissions' => [
        'admin.access'   => 'Access administration module',
        'admin.users'    => 'Manage staff accounts',
        'admin.roles'    => 'Configure role permissions',
        'admin.templates'=> 'Edit form templates & branding',
        'admin.audit'    => 'View audit log',
        'admin.messaging'=> 'Configure follow-up SMS/email reminders',
    ],
];

return [
    'groups' => [
        'patient'       => $patient,
        'clinical'      => $clinical,
        'pharmacy'      => $pharmacy,
        'it'            => $it,
        'workflow'      => $workflow,
        'surveillance'  => $surveillance,
        'admin'         => $admin,
    ],

    /** Roles shown on Admin → Roles & permissions (fixed slugs). */
    'configurable_roles' => [
        ROLE_RECORDS       => 'Records Office',
        ROLE_DOCTOR        => 'Doctor',
        ROLE_PHARMACY      => 'Pharmacy',
        ROLE_IT            => 'IT Department',
        ROLE_SYSTEM_ADMIN  => 'System Administrator',
    ],

    /** Permission groups shown in Admin when editing each role (others stay hidden but remain in DB). */
    'role_visible_groups' => [
        ROLE_RECORDS       => ['patient', 'workflow', 'surveillance'],
        ROLE_DOCTOR        => ['patient', 'clinical', 'workflow', 'surveillance'],
        ROLE_PHARMACY      => ['patient', 'pharmacy', 'workflow', 'surveillance'],
        ROLE_IT            => ['patient', 'clinical', 'it', 'workflow', 'surveillance', 'admin'],
        ROLE_SYSTEM_ADMIN  => ['patient', 'surveillance', 'admin'],
    ],

    /**
     * When a permission is denied, cap field-group access (edit/view).
     * @var array<string, array{edit?: string, view?: string}>
     */
    'field_group_caps' => [
        'records_enrollment' => ['edit' => 'patient.edit_demographics', 'view' => 'patient.view'],
        'records_consent'    => ['edit' => 'patient.edit_consent', 'view' => 'patient.view'],
        'records_treatment'  => ['edit' => 'patient.edit_treatment_records', 'view' => 'patient.view'],
        'doctor'             => ['edit' => 'clinical.edit', 'view' => 'clinical.view'],
        'it'                 => ['edit' => 'it.encode', 'view' => 'patient.view'],
        'pharmacy'           => ['edit' => 'pharmacy.dispense', 'view' => 'pharmacy.view_prescription'],
        'receptionist'       => ['view' => 'patient.view'],
    ],

    'defaults' => [
        ROLE_RECORDS => [
            'patient.create' => 1, 'patient.view' => 1, 'patient.edit_demographics' => 1,
            'patient.edit_consent' => 1, 'patient.edit_treatment_records' => 1,
            'patient.archive' => 1, 'patient.folder' => 1,
            'clinical.view' => 1,
            'pharmacy.view_prescription' => 1,
            'workflow.view_queue' => 1, 'workflow.advance' => 1,
            'historical.view' => 1, 'historical.upload' => 1,
        ],
        ROLE_DOCTOR => [
            'patient.view' => 1, 'patient.folder' => 1,
            'clinical.view' => 1, 'clinical.edit' => 1, 'clinical.queue' => 1, 'clinical.diagnosis' => 1,
            'pharmacy.view_prescription' => 1,
            'workflow.view_queue' => 1, 'workflow.advance' => 1,
            'heatmap.view' => 1, 'historical.view' => 1, 'reports.view' => 1,
        ],
        ROLE_PHARMACY => [
            'patient.view' => 1,
            'pharmacy.queue' => 1, 'pharmacy.dispense' => 1, 'pharmacy.view_prescription' => 1,
            'workflow.view_queue' => 1, 'workflow.advance' => 1,
        ],
        ROLE_IT => [
            'patient.create' => 1, 'patient.view' => 1, 'patient.edit_demographics' => 1,
            'patient.edit_consent' => 1, 'patient.edit_treatment_records' => 1, 'patient.folder' => 1,
            'clinical.view' => 1,
            'it.encode' => 1, 'it.validation_queue' => 1, 'it.validate' => 1,
            'it.print_queue' => 1, 'it.print' => 1,
            'workflow.view_queue' => 1, 'workflow.advance' => 1, 'workflow.return_correction' => 1,
            'heatmap.view' => 1, 'historical.view' => 1, 'historical.upload' => 1, 'reports.view' => 1,
            'admin.messaging' => 1,
        ],
        ROLE_RECEPTIONIST => [
            'patient.view' => 1, 'patient.folder' => 1,
            'workflow.view_queue' => 1,
        ],
        ROLE_SYSTEM_ADMIN => [
            'patient.view' => 1, 'patient.folder' => 1,
            'admin.access' => 1, 'admin.users' => 1, 'admin.roles' => 1,
            'admin.templates' => 1, 'admin.audit' => 1, 'admin.messaging' => 1,
            'historical.view' => 1,
        ],
    ],
];
