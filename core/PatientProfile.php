<?php
/**
 * Patient profile helpers — single source of truth for shared identity fields.
 */

class PatientProfile
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/patient-profile.php';
        }
        return self::$config;
    }

    public static function patientColumns(): array
    {
        return self::config()['patient_columns'];
    }

    public static function extendedFields(): array
    {
        return self::config()['extended_fields'];
    }

    public static function requiredForCreate(): array
    {
        return self::config()['required_for_create'];
    }

    public static function hasMinimumForCreate(array $post): bool
    {
        foreach (self::requiredForCreate() as $field) {
            if (trim((string) ($post[$field] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    public static function isProfileColumn(string $field): bool
    {
        return in_array($field, self::patientColumns(), true);
    }

    public static function isProfileExtended(string $field): bool
    {
        return in_array($field, self::config()['extended_fields'], true)
            || in_array($field, self::config()['computed_extended'] ?? [], true);
    }

    /** Visit-only extended keys (must never bleed from prior consultations). */
    public static function isVisitSpecificExtended(string $field): bool
    {
        return str_starts_with($field, 'ekas_')
            || str_starts_with($field, 'epress_')
            || str_starts_with($field, 'doctor_')
            || $field === 'doctor_notes';
    }

    /**
     * Permanent patient-profile extended fields (identity / address / enrollment history).
     * Excludes consultation-visit slips (EKAS / EPRESS).
     *
     * @param array<string, mixed> $extended
     * @return array<string, mixed>
     */
    public static function permanentExtendedOnly(array $extended): array
    {
        $out = [];
        foreach ($extended as $key => $val) {
            $key = (string) $key;
            if (self::isVisitSpecificExtended($key)) {
                continue;
            }
            $out[$key] = $val;
        }
        return $out;
    }

    /**
     * Consultation-visit extended fields only.
     *
     * @param array<string, mixed> $extended
     * @return array<string, mixed>
     */
    public static function visitExtendedOnly(array $extended): array
    {
        $out = [];
        foreach ($extended as $key => $val) {
            $key = (string) $key;
            if (self::isVisitSpecificExtended($key)) {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    /**
     * Merge permanent profile extended + this visit's extended (no prior-visit clinical bleed).
     *
     * @param array<string, mixed> $profileExtended
     * @param array<string, mixed> $consultationExtended
     * @return array<string, mixed>
     */
    public static function mergeExtendedForConsultation(array $profileExtended, array $consultationExtended): array
    {
        return array_merge(
            self::permanentExtendedOnly($profileExtended),
            self::visitExtendedOnly($consultationExtended)
        );
    }

    /** Build normalized profile payload for API responses */
    public static function buildPayload(array $patient, array $extended = []): array
    {
        $profilePatient = [];
        foreach (self::patientColumns() as $col) {
            if (isset($patient[$col]) && $patient[$col] !== null && $patient[$col] !== '') {
                $profilePatient[$col] = $patient[$col];
            }
        }

        $profileExtended = [];
        foreach (array_merge(self::extendedFields(), self::config()['computed_extended'] ?? []) as $col) {
            if (isset($extended[$col]) && $extended[$col] !== null && $extended[$col] !== '') {
                $profileExtended[$col] = $extended[$col];
            }
        }

        return [
            'patient'  => $profilePatient,
            'extended' => $profileExtended,
        ];
    }

    /** Filter extended JSON to only non-profile keys (form-specific data) */
    public static function filterFormSpecificExtended(array $extended): array
    {
        $profileKeys = array_merge(
            self::extendedFields(),
            self::config()['computed_extended'] ?? []
        );
        return array_diff_key($extended, array_flip($profileKeys));
    }

    /** Extract profile-related extended fields from incoming POST */
    public static function extractExtendedProfile(array $extended): array
    {
        $out = [];
        foreach (self::extendedFields() as $key) {
            if (array_key_exists($key, $extended)) {
                $out[$key] = $extended[$key];
            }
        }
        return $out;
    }
}
