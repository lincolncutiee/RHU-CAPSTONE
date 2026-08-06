<?php
/**
 * IT print configuration — official RHU Magallanes / PhilHealth layouts.
 *
 * Print fidelity over responsive web layout.
 * Set layout_test_mode to false after the official RHU print layout is approved.
 */
return [
    'layout_test_mode' => true,

    /**
     * Canonical form order for every consultation print job.
     * Never reorder. Empty forms still print blank.
     */
    'form_print_order' => [
        'pkrf',
        'itr',
        'enrollment',
        'history',
        'personal',
        'ekas',
        'epress',
    ],

    /**
     * Official Print-module documents — ONE form (or half-half pair) per print dialog.
     * Never pack multiple A4 sheets into one preview: Chrome can crash (STATUS_BREAKPOINT).
     */
    'official_documents' => [
        'pkrf' => [
            'label'       => '1. PhilHealth Konsulta Registration Form (PKRF)',
            'hint'        => 'Legal Portrait · 1 sheet',
            'forms'       => ['pkrf'],
            'layout'      => 'legal-portrait',
            'page_size'   => 'legal',
            'orientation' => 'portrait',
            'duplex'      => false,
            'duplex_hint' => 'Select Legal paper. Turn off Fit to page.',
        ],
        'itr' => [
            'label'       => '2. Individual Treatment Record',
            'hint'        => 'A4 Portrait · 1 sheet',
            'forms'       => ['itr'],
            'layout'      => 'a4-portrait',
            'page_size'   => 'A4',
            'orientation' => 'portrait',
            'duplex'      => false,
            'duplex_hint' => 'Turn off Fit to page.',
        ],
        'enrollment' => [
            'label'       => '3. Patient Enrollment Record',
            'hint'        => 'A4 Portrait · 1 sheet',
            'forms'       => ['enrollment'],
            'layout'      => 'a4-portrait',
            'page_size'   => 'A4',
            'orientation' => 'portrait',
            'duplex'      => false,
            'duplex_hint' => 'Turn off Fit to page.',
        ],
        'history' => [
            'label'       => '4. Past Medical / Surgical / Family History',
            'hint'        => 'A4 Portrait · 1 sheet (prints blank if empty)',
            'forms'       => ['history'],
            'layout'      => 'a4-portrait',
            'page_size'   => 'A4',
            'orientation' => 'portrait',
            'duplex'      => false,
            'duplex_hint' => 'Turn off Fit to page.',
        ],
        'personal' => [
            'label'       => '5. Personal / Social History, NCD & Photo Consent',
            'hint'        => 'A4 Portrait · 1 sheet (prints blank if empty)',
            'forms'       => ['personal'],
            'layout'      => 'a4-portrait',
            'page_size'   => 'A4',
            'orientation' => 'portrait',
            'duplex'      => false,
            'duplex_hint' => 'Turn off Fit to page.',
        ],
        'ekas_epress' => [
            'label'       => '6–7. EKAS + EPRESS',
            'hint'        => 'A4 Landscape · half–half (cut after print)',
            'forms'       => ['ekas', 'epress'],
            'layout'      => 'a4-landscape-half',
            'page_size'   => 'A4',
            'orientation' => 'landscape',
            'duplex'      => false,
            'duplex_hint' => 'One landscape sheet. Turn off Fit to page. Cut in half after printing.',
        ],
    ],

    /**
     * Per-form default layout (used when inferring from ?forms=).
     */
    'form_layouts' => [
        'pkrf'        => 'legal-portrait',
        'itr'         => 'a4-portrait',
        'enrollment'  => 'a4-portrait',
        'history'     => 'a4-portrait',
        'personal'    => 'a4-portrait',
        'ekas'        => 'a4-landscape-half',
        'epress'      => 'a4-landscape-half',
    ],

    /** Legacy single-form / combo bundles (consultation.php quick buttons). */
    'bundles' => [
        'pkrf' => [
            'label'  => 'Print PKRF',
            'forms'  => ['pkrf'],
            'title'  => 'PhilHealth Konsulta Registration Form (PKRF)',
            'layout' => 'legal-portrait',
        ],
        'itr' => [
            'label'  => 'Print Individual Treatment Record',
            'forms'  => ['itr'],
            'title'  => 'Individual Treatment Record',
            'layout' => 'a4-portrait',
        ],
        'enrollment' => [
            'label'  => 'Print Patient Enrollment Record',
            'forms'  => ['enrollment'],
            'title'  => 'Patient Enrollment Record',
            'layout' => 'a4-portrait',
        ],
        'history' => [
            'label'  => 'Print Medical History Forms',
            'forms'  => ['history'],
            'title'  => 'Past Medical / Surgical / Family History',
            'layout' => 'a4-portrait',
        ],
        'personal' => [
            'label'  => 'Print Personal / NCD / Photo Consent',
            'forms'  => ['personal'],
            'title'  => 'Personal / Social History, NCD & Photo Consent',
            'layout' => 'a4-portrait',
        ],
        'ekas' => [
            'label'  => 'Print EKAS',
            'forms'  => ['ekas'],
            'title'  => 'Electronic Konsulta Availment Slip (EKAS)',
            'layout' => 'a4-landscape-half',
        ],
        'epress' => [
            'label'  => 'Print EPRESS',
            'forms'  => ['epress'],
            'title'  => 'Electronic Prescription Slip (EPRESS)',
            'layout' => 'a4-landscape-half',
        ],
        'enrollment_itr' => [
            'label'  => 'Print ITR + Enrollment (A4 Portrait)',
            'forms'  => ['itr', 'enrollment'],
            'title'  => 'Individual Treatment Record + Patient Enrollment Record',
            'layout' => 'a4-portrait',
        ],
        'ekas_epress' => [
            'label'  => 'Print EKAS + EPRESS (Half-Half)',
            'forms'  => ['ekas', 'epress'],
            'title'  => 'EKAS + EPRESS (A4 Landscape)',
            'layout' => 'a4-landscape-half',
        ],
        'consultation' => [
            'label'  => 'Print Consultation Forms',
            'forms'  => ['itr', 'ekas', 'epress'],
            'title'  => 'Consultation Forms (ITR, EKAS, EPRESS)',
            'layout' => 'a4-portrait',
        ],
        /**
         * Full set — preview stacks A4 forms; PKRF/Legal and landscape
         * half-half should be printed via official_documents jobs instead.
         * Prefer Print module “Select all” for correct paper per form.
         */
        'all' => [
            'label'  => 'Print All Forms',
            'forms'  => ['pkrf', 'itr', 'enrollment', 'history', 'personal', 'ekas', 'epress'],
            'title'  => 'Complete RHU Form Set',
            'layout' => 'a4-portrait',
        ],
    ],
];
