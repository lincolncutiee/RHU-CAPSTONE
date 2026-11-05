<?php
/**
 * Patient Forms Module — official RHU paper form sequence.
 *
 * Paper relationships (for future printing):
 *   Page 1 PCSF  — independent (Legal portrait)
 *   Page 2 ITR   — front of consultation paper
 *   Page 3 PER   — back of consultation paper
 *   Page 4 History — front of history paper
 *   Page 5 Personal/NCD/Photo — back of history paper
 *   Page 6 EKAS  — independent (half-page, crosswise)
 *   Page 7 EPRESS — independent (half-page, crosswise)
 *   Page 8 Review & Submit — system page (not a paper form)
 *
 * Field names map to patients / treatment_records columns.
 * Extended fields map to treatment_records.receptionist_data JSON.
 */

$yesNo = ['' => '—', 'Yes' => 'Yes', 'No' => 'No'];

return [
    'pages' => [

        // ── Page 1: PCSF (independent form) ─────────────────────
        [
            'id'          => 'pcsf',
            'title'       => 'PhilHealth Primary Care Selection Form (PCSF)',
            'short_title' => 'PhilHealth Primary Care Selection Form (PCSF)',
            'subtitle'    => 'Independent form — Legal portrait',
            'field_group' => 'it',
            'paper'       => ['type' => 'independent', 'size' => 'legal-portrait'],
            'sections'    => [
                [
                    'title'  => 'Beneficiary Information',
                    'fields' => [
                        ['name' => 'philhealth_no', 'label' => 'PIN', 'type' => 'text'],
                        ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text'],
                        ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text'],
                        ['name' => 'pcsf_pcc_1st', 'label' => '1st Choice PCC', 'type' => 'text', 'extended' => true],
                        ['name' => 'pcsf_date', 'label' => 'Form Date', 'type' => 'date', 'extended' => true],
                        ['name' => 'pcsf_email', 'label' => 'Email Address', 'type' => 'text', 'extended' => true],
                    ],
                ],
            ],
        ],

        // ── Page 2: ITR (front of consultation paper) ───────────
        [
            'id'          => 'itr',
            'title'       => 'Individual Treatment Record',
            'short_title' => 'Individual Treatment Record',
            'subtitle'    => 'Front side of the official RHU consultation form (DOH Form 2)',
            'field_group' => 'records_treatment',
            'paper'       => ['type' => 'front', 'pair' => 'enrollment'],
            'sections'    => [
                [
                    'title'  => 'Patient & Consultation',
                    'fields' => [
                        ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text', 'field_group' => 'records_enrollment'],
                        ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'field_group' => 'records_enrollment'],
                        ['name' => 'mode_of_transaction', 'label' => 'Mode of Transaction', 'type' => 'text'],
                        ['name' => 'date_of_consultation', 'label' => 'Date of Consultation', 'type' => 'date'],
                        ['name' => 'chief_complaints', 'label' => 'Chief Complaints', 'type' => 'textarea'],
                        ['name' => 'consultation_types', 'label' => 'Consultation Types', 'type' => 'text'],
                    ],
                ],
                [
                    'title'  => 'Doctor Assessment',
                    'fields' => [
                        ['name' => 'diagnosis', 'label' => 'Diagnosis', 'type' => 'textarea', 'field_group' => 'doctor'],
                        ['name' => 'medication_treatment', 'label' => 'Medication / Treatment', 'type' => 'textarea', 'field_group' => 'doctor'],
                    ],
                ],
            ],
        ],

        // ── Page 3: Patient Enrollment (back of consultation paper)
        [
            'id'          => 'enrollment',
            'title'       => 'Patient Enrollment Record',
            'short_title' => 'Patient Enrollment Record',
            'subtitle'    => 'Back side of the Individual Treatment Record paper (DOH Form 1)',
            'field_group' => 'records_enrollment',
            'paper'       => ['type' => 'back', 'pair' => 'itr'],
            'sections'    => [
                [
                    'title'  => 'Patient Information',
                    'fields' => [
                        ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text'],
                        ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text'],
                        ['name' => 'sex', 'label' => 'Sex', 'type' => 'text'],
                        ['name' => 'birth_date', 'label' => 'Birth Date', 'type' => 'date'],
                        ['name' => 'contact_number', 'label' => 'Contact Number', 'type' => 'text'],
                        ['name' => 'philhealth_no', 'label' => 'PhilHealth No.', 'type' => 'text'],
                    ],
                ],
                [
                    'title'       => "Patient's Consent",
                    'field_group' => 'records_consent',
                    'fields'      => [
                        ['name' => 'consent_signature_date', 'label' => 'Consent Date', 'type' => 'date', 'field_group' => 'records_consent'],
                        ['name' => 'chu_representative', 'label' => 'CHU/RHU Representative', 'type' => 'text', 'field_group' => 'records_consent'],
                    ],
                ],
            ],
        ],

        // ── Page 4: History (front of history paper) ────────────
        [
            'id'          => 'history',
            'title'       => 'Past Medical History',
            'short_title' => 'Past Medical / Surgical / Family History',
            'subtitle'    => 'Front side — Past Medical, Surgical, and Family History',
            'field_group' => 'records_enrollment',
            'paper'       => ['type' => 'front', 'pair' => 'personal'],
            'sections'    => [
                [
                    'title'  => 'Past Medical History',
                    'fields' => [
                        ['name' => 'pmh_hypertension', 'label' => 'Hypertension', 'type' => 'checkbox', 'extended' => true],
                        ['name' => 'pmh_diabetes', 'label' => 'Diabetes Mellitus', 'type' => 'checkbox', 'extended' => true],
                        ['name' => 'pmh_allergy_specify', 'label' => 'Allergy (specify)', 'type' => 'text', 'extended' => true],
                    ],
                ],
                [
                    'title'  => 'Past Surgical History',
                    'fields' => [
                        ['name' => 'surgical_op_1', 'label' => 'Operation 1', 'type' => 'text', 'extended' => true],
                        ['name' => 'surgical_date_1', 'label' => 'Date 1', 'type' => 'date', 'extended' => true],
                    ],
                ],
                [
                    'title'  => 'Family History',
                    'fields' => [
                        ['name' => 'fh_hypertension', 'label' => 'Hypertension', 'type' => 'checkbox', 'extended' => true],
                        ['name' => 'fh_diabetes', 'label' => 'Diabetes Mellitus', 'type' => 'checkbox', 'extended' => true],
                    ],
                ],
            ],
        ],

        // ── Page 5: Personal / NCD / Photo (back of history paper)
        [
            'id'          => 'personal',
            'title'       => 'Personal / Social History',
            'short_title' => 'Personal / Social History, NCD & Photo Consent',
            'subtitle'    => 'Back side — Personal / Social History, NCD Assessment, and Photo Consent',
            'field_group' => 'records_enrollment',
            'paper'       => ['type' => 'back', 'pair' => 'history'],
            'sections'    => [
                [
                    'title'  => 'Personal / Social History',
                    'fields' => [
                        ['name' => 'psh_smoking', 'label' => 'Smoking', 'type' => 'radio', 'extended' => true],
                        ['name' => 'psh_alcohol', 'label' => 'Alcohol', 'type' => 'radio', 'extended' => true],
                        ['name' => 'psh_drugs', 'label' => 'Illicit Drugs', 'type' => 'radio', 'extended' => true],
                    ],
                ],
                [
                    'title'  => 'NCD High Risk Assessment',
                    'fields' => [
                        ['name' => 'ncd_diabetes', 'label' => 'Diabetes Diagnosed', 'type' => 'radio', 'extended' => true],
                        ['name' => 'ncd_physical', 'label' => 'Physical Activity', 'type' => 'radio', 'extended' => true],
                    ],
                ],
                [
                    'title'  => 'Photo Consent',
                    'fields' => [
                        ['name' => 'photo_consent_name', 'label' => 'Beneficiary Name', 'type' => 'text', 'extended' => true],
                        ['name' => 'photo_consent_date', 'label' => 'Consent Date', 'type' => 'date', 'extended' => true],
                    ],
                ],
            ],
        ],

        // ── Page 6: EKAS (independent form) ─────────────────────
        [
            'id'          => 'ekas',
            'title'       => 'Electronic Konsulta Availment Slip (EKAS)',
            'short_title' => 'Electronic Konsulta Availment Slip (EKAS)',
            'subtitle'    => 'Independent form — half-page (crosswise)',
            'field_group' => 'doctor',
            'paper'       => ['type' => 'independent', 'size' => 'half-crosswise'],
            'sections'    => [
                [
                    'title'  => 'Facility / Beneficiary / Services',
                    'fields' => [
                        ['name' => 'philhealth_no', 'label' => 'PIN', 'type' => 'text'],
                        ['name' => 'ekas_hci_name', 'label' => 'HCI Name', 'type' => 'text', 'extended' => true],
                        ['name' => 'ekas_accreditation_no', 'label' => 'HCI Accreditation No.', 'type' => 'text', 'extended' => true],
                        ['name' => 'ekas_transaction_no', 'label' => 'Transaction No.', 'type' => 'text', 'extended' => true],
                        ['name' => 'ekas_atc', 'label' => 'ATC', 'type' => 'text', 'extended' => true],
                    ],
                ],
            ],
        ],

        // ── Page 7: EPRESS (independent form) ───────────────────
        [
            'id'          => 'epress',
            'title'       => 'Electronic Prescription Slip (EPRESS)',
            'short_title' => 'Electronic Prescription Slip (EPRESS)',
            'subtitle'    => 'Independent form — half-page (crosswise)',
            'field_group' => 'doctor',
            'paper'       => ['type' => 'independent', 'size' => 'half-crosswise'],
            'sections'    => [
                [
                    'title'  => 'Facility / Prescription',
                    'fields' => [
                        ['name' => 'philhealth_no', 'label' => 'PIN', 'type' => 'text'],
                        ['name' => 'epress_hci_name', 'label' => 'HCI Name', 'type' => 'text', 'extended' => true],
                        ['name' => 'epress_accreditation_no', 'label' => 'HCI Accreditation No.', 'type' => 'text', 'extended' => true],
                        ['name' => 'epress_physician_name', 'label' => 'Physician', 'type' => 'text', 'extended' => true],
                        ['name' => 'medication_treatment', 'label' => 'Prescription (Generic Name)', 'type' => 'textarea'],
                    ],
                ],
            ],
        ],

        // ── Page 8: Review & Submit (system page) ───────────────
        [
            'id'          => 'review-submit',
            'title'       => 'Review & Submit',
            'short_title' => 'Review & Submit',
            'subtitle'    => 'Document Review Center — validate and preview all RHU forms before IT submission',
            'field_group' => 'records_consent',
            'paper'       => ['type' => 'system'],
            'is_review'   => true,
            'sections'    => [],
        ],
    ],

    /** Last official RHU paper form (before Review & Submit) */
    'last_rhu_form_page' => 'epress',

    /** Review page — Submit to IT Review */
    'records_submit_page' => 'review-submit',

    /** Doctor primary page (ITR) */
    'doctor_primary_page' => 'itr',
];

