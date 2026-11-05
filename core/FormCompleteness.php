<?php
/**
 * Validates required RHU forms before IT marks a record Ready for Printing.
 */

require_once __DIR__ . '/ExtendedFormData.php';
require_once __DIR__ . '/Permissions.php';
require_once __DIR__ . '/../config/app.php';

class FormCompleteness
{
    private const PMH_KEYS = [
        'pmh_allergy', 'pmh_asthma', 'pmh_cancer', 'pmh_cerebrovascular', 'pmh_coronary',
        'pmh_diabetes', 'pmh_emphysema', 'pmh_epilepsy', 'pmh_hepatitis', 'pmh_hyperlipidemia',
        'pmh_hypertension', 'pmh_peptic_ulcer', 'pmh_pneumonia', 'pmh_thyroid',
        'pmh_pulmonary_tb', 'pmh_extrapulmonary_tb', 'pmh_uti', 'pmh_mental_illness',
        'pmh_others', 'pmh_none',
    ];

    private const FH_KEYS = [
        'fh_hypertension', 'fh_diabetes', 'fh_cancer', 'fh_asthma', 'fh_cerebrovascular',
        'fh_coronary', 'fh_emphysema', 'fh_epilepsy', 'fh_hepatitis', 'fh_hyperlipidemia',
        'fh_peptic_ulcer', 'fh_pneumonia', 'fh_thyroid', 'fh_pulmonary_tb',
        'fh_extrapulmonary_tb', 'fh_uti', 'fh_mental_illness', 'fh_others', 'fh_none',
    ];

    /**
     * @return array{complete: bool, issues: list<array{form: string, fields: list<string>}>}
     */
    public static function validate(array $patient, array $treatment = [], array $extended = [], string $viewerRole = ''): array
    {
        $rules = require __DIR__ . '/../config/form-completeness.php';
        $issues = [];
        $viewerRole = Permissions::normalizeRole($viewerRole);

        foreach ($rules as $formKey => $rule) {
            $owner = (string) ($rule['owner'] ?? '');
            // PCSF is completed by IT — skip for everyone in shared completeness UI
            if ($owner === 'it') {
                continue;
            }
            // EKAS / EPRESS are doctor-owned — Records must not be blocked before consultation
            if ($owner === 'doctor' && $viewerRole === ROLE_RECORDS) {
                continue;
            }

            if (!empty($rule['conditional']) && !self::epressRequired($treatment)) {
                continue;
            }

            $missing = [];

            foreach ($rule['patient'] ?? [] as $field) {
                if (!self::hasValue($patient[$field] ?? null)) {
                    $missing[] = self::humanLabel($field);
                }
            }

            foreach ($rule['patient_consent'] ?? [] as $field) {
                if (!self::hasValue($patient[$field] ?? null)) {
                    $missing[] = self::humanLabel($field);
                }
            }

            foreach ($rule['treatment'] ?? [] as $field) {
                if (!self::hasValue($treatment[$field] ?? null)) {
                    $missing[] = self::humanLabel($field);
                }
            }

            foreach ($rule['extended'] ?? [] as $field) {
                if (!self::hasValue($extended[$field] ?? null)) {
                    $missing[] = self::humanLabel($field);
                }
            }

            foreach ($rule['custom'] ?? [] as $custom) {
                $customMissing = self::validateCustom($custom, $patient, $treatment, $extended);
                $missing = array_merge($missing, $customMissing);
            }

            if (!empty($missing)) {
                $issues[] = [
                    'form'   => $rule['label'],
                    'fields' => array_values(array_unique($missing)),
                ];
            }
        }

        return [
            'complete' => empty($issues),
            'issues'   => $issues,
        ];
    }

    public static function formatIssuesMessage(array $issues): string
    {
        if (empty($issues)) {
            return '';
        }

        $lines = ['The following required sections are incomplete:'];
        foreach ($issues as $issue) {
            $lines[] = '• ' . $issue['form'] . ': ' . implode(', ', $issue['fields']);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{complete: bool, issues: list<array{form: string, fields: list<string>}>}
     */
    public static function validatePatientId(mysqli $conn, int $patientDbId, string $viewerRole = ''): array
    {
        $stmt = $conn->prepare('SELECT * FROM patients WHERE id = ?');
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$patient) {
            return [
                'complete' => false,
                'issues'   => [['form' => 'Patient Record', 'fields' => ['Record not found']]],
            ];
        }

        $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $treatment = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        if (!empty($treatment['consultation_types']) && is_string($treatment['consultation_types'])) {
            $treatment['consultation_types'] = json_decode($treatment['consultation_types'], true) ?? [];
        }

        $extendedBlock = ExtendedFormData::fromTreatment($treatment);
        $extended = ExtendedFormData::hydrateAddressFields(
            $extendedBlock['fields'],
            $patient['residential_address'] ?? null
        );

        return self::validate($patient, $treatment, $extended, $viewerRole);
    }

    private static function validateCustom(
        string $rule,
        array $patient,
        array $treatment,
        array $extended
    ): array {
        return match ($rule) {
            'consultation_types' => self::hasConsultationTypes($treatment)
                ? []
                : ['Consultation Type (at least one)'],
            'pmh_completed' => self::hasAnyChecked($extended, self::PMH_KEYS)
                ? []
                : ['At least one Past Medical History item'],
            'psh_surgical_completed' => (
                self::hasValue($extended['surgical_op_1'] ?? null)
                || self::hasValue($extended['surgical_date_1'] ?? null)
                || self::hasValue($extended['surgical_op_2'] ?? null)
                || self::hasValue($extended['surgical_date_2'] ?? null)
            ) ? [] : ['Past Surgical History'],
            'fh_completed' => self::hasAnyChecked($extended, self::FH_KEYS)
                ? []
                : ['At least one Family History item'],
            'philhealth_format' => self::validatePhilhealthFormat($patient['philhealth_no'] ?? null),
            'family_serial_format' => self::validateFamilySerialFormat($patient['family_serial_number'] ?? null),
            'epress_if_applicable' => self::validateEpress($treatment),
            default => [],
        };
    }

    private static function validatePhilhealthFormat(mixed $value): array
    {
        if (!self::hasValue($value)) {
            return [];
        }
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === null || (strlen($digits) !== 12 && strlen($digits) !== 14)) {
            return ['Invalid PhilHealth Number (must be 12 or 14 digits)'];
        }
        return [];
    }

    private static function validateFamilySerialFormat(mixed $value): array
    {
        if (!self::hasValue($value)) {
            return [];
        }
        $raw = trim((string) $value);
        if (!preg_match('/^[A-Za-z0-9\-]{4,20}$/', $raw)) {
            return ['Invalid Family Serial Number'];
        }
        return [];
    }

    private static function validateEpress(array $treatment): array
    {
        $missing = [];
        $hasDx = self::hasValue($treatment['diagnosis'] ?? null) || !empty($treatment['_has_diagnoses']);
        $hasMed = self::hasValue($treatment['medication_treatment'] ?? null) || !empty($treatment['_has_medications']);
        if (!$hasDx) {
            $missing[] = 'Diagnosis';
        }
        if (!$hasMed) {
            $missing[] = 'Medication / Treatment';
        }
        if (!self::hasValue($treatment['healthcare_provider'] ?? null)) {
            $missing[] = 'Healthcare Provider';
        }

        return $missing;
    }

    private static function epressRequired(array $treatment): bool
    {
        return self::hasValue($treatment['medication_treatment'] ?? null)
            || self::hasValue($treatment['diagnosis'] ?? null)
            || !empty($treatment['_has_medications'])
            || !empty($treatment['_has_diagnoses']);
    }

    private static function hasConsultationTypes(array $treatment): bool
    {
        $types = $treatment['consultation_types'] ?? [];
        if (is_string($types)) {
            $types = json_decode($types, true) ?? [];
        }

        return is_array($types) && count(array_filter($types)) > 0;
    }

    private static function hasAnyChecked(array $extended, array $keys): bool
    {
        foreach ($keys as $key) {
            $val = $extended[$key] ?? null;
            if ($val === '1' || $val === 1 || $val === true) {
                return true;
            }
        }

        return false;
    }

    private static function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return count($value) > 0;
        }

        return true;
    }

    private static function humanLabel(string $field): string
    {
        static $map = [
            'philhealth_no'           => 'PhilHealth PIN',
            'family_serial_number'    => 'Family Serial Number',
            'pcsf_date'               => 'PCSF Form Date',
            'date_of_consultation'    => 'Date of Consultation',
            'chief_complaints'        => 'Chief Complaints',
            'mode_of_transaction'     => 'Mode of Transaction',
            'consent_signature_date'  => 'Consent Signature Date',
            'chu_representative'      => 'CHU/RHU Representative',
            'contact_number'          => 'Contact Number',
            'birth_date'              => 'Birth Date',
            'psh_smoking'             => 'Smoking (Personal/Social History)',
            'psh_alcohol'             => 'Alcohol (Personal/Social History)',
            'psh_drugs'               => 'Illicit Drugs (Personal/Social History)',
            'ncd_diabetes'            => 'NCD — Diabetes',
            'ncd_physical'            => 'NCD — Physical Activity',
            'photo_consent_name'      => 'Photo Consent — Beneficiary Name',
            'photo_consent_date'      => 'Photo Consent — Date',
            'ekas_hci_name'           => 'EKAS HCI Name',
            'ekas_accreditation_no'   => 'EKAS HCI Accreditation No.',
            'ekas_transaction_no'     => 'EKAS Transaction No.',
            'ekas_atc'                => 'EKAS Authorization Transaction Code',
            'epress_hci_name'         => 'EPRESS HCI Name',
            'epress_accreditation_no' => 'EPRESS HCI Accreditation No.',
            'epress_physician_name'   => 'EPRESS Physician',
            'medication_treatment'    => 'Prescription (Medication)',
        ];

        if (isset($map[$field])) {
            return $map[$field];
        }

        return ucwords(str_replace('_', ' ', $field));
    }
}
