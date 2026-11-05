<?php
/**
 * Historical / digitized old form records — configuration.
 */
return [
    /** Old keys kept so saved historical records still show readable labels. */
    'legacy_record_types' => [
        'old_consultation'    => 'Old Consultation Form',
        'old_patient_record'  => 'Old Patient Record',
        'old_medical_history' => 'Old Medical History',
        'old_laboratory'      => 'Old Laboratory Result',
        'other'               => 'Other Historical Record',
    ],

    /** Relative to project root */
    'upload_dir' => 'uploads/historical',

    /** Max photo upload size (bytes) — 8 MB */
    'max_photo_bytes' => 8 * 1024 * 1024,
];
