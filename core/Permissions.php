<?php

require_once __DIR__ . '/../config/app.php';

class Permissions
{
    private static ?array $matrix = null;

    /** Temporary audit log — keep false in production. */
    public static bool $debugLogging = false;

    private static function matrix(): array
    {
        if (self::$matrix === null) {
            self::$matrix = require __DIR__ . '/../config/permissions.php';
        }
        return self::$matrix;
    }

    /**
     * Map session / display role names to canonical ROLE_* slugs.
     */
    public static function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));
        if ($role === '') {
            return '';
        }

        $aliases = [
            'records_office' => ROLE_RECORDS,
            'records' => ROLE_RECORDS,
            'records office' => ROLE_RECORDS,
            'records office staff' => ROLE_RECORDS,
            'encoder' => ROLE_RECORDS,
            'encoding' => ROLE_RECORDS,
            'nurse' => ROLE_RECORDS, // nurse/midwife treatment fields owned by Records Office today
            'midwife' => ROLE_RECORDS,
            'doctor' => ROLE_DOCTOR,
            'physician' => ROLE_DOCTOR,
            'attending physician' => ROLE_DOCTOR,
            'it_department' => ROLE_IT,
            'it' => ROLE_IT,
            'it department' => ROLE_IT,
            'it review' => ROLE_IT,
            'admin' => ROLE_IT,
            'receptionist' => ROLE_RECEPTIONIST,
            'pharmacy' => ROLE_PHARMACY,
            'pharmacist' => ROLE_PHARMACY,
        ];

        return $aliases[$role] ?? $role;
    }

    public static function canEdit(string $role, string $fieldGroup): bool
    {
        $role = self::normalizeRole($role);
        $group = self::normalizeGroup($fieldGroup);
        $config = self::matrix()[$role] ?? null;
        if (!$config) {
            if (self::$debugLogging) {
                error_log("[RHU_PERM] canEdit FAIL unknown role='{$role}' group='{$group}'");
            }
            return false;
        }
        return in_array($group, $config['editable'], true);
    }

    public static function isReadonly(string $role, string $fieldGroup): bool
    {
        return !self::canEdit($role, $fieldGroup);
    }

    /** True when the logged-in role owns this field group. */
    public static function ownsField(string $role, string $fieldGroup): bool
    {
        return self::canEdit($role, $fieldGroup);
    }

    public static function getEditableGroups(string $role): array
    {
        $role = self::normalizeRole($role);
        return self::matrix()[$role]['editable'] ?? [];
    }

    public static function getRoleLabel(string $role): string
    {
        $role = self::normalizeRole($role);
        return self::matrix()[$role]['label'] ?? ucfirst(str_replace('_', ' ', $role));
    }

    /**
     * Normalize aliases so field ownership stays consistent.
     * nurse / midwife paper sections map to records_treatment (Records Office).
     */
    public static function normalizeGroup(string $fieldGroup): string
    {
        $aliases = [
            'records' => 'records_enrollment',
            'enrollment' => 'records_enrollment',
            'consent' => 'records_consent',
            'treatment' => 'records_treatment',
            'nurse' => 'records_treatment',
            'midwife' => 'records_treatment',
            'nurse_midwife' => 'records_treatment',
            'physician' => 'doctor',
            'admin' => 'it',
        ];

        return $aliases[$fieldGroup] ?? $fieldGroup;
    }

    public static function allFieldGroups(): array
    {
        return [
            'records_enrollment' => 'Patient Enrollment (Form 1) — Records Office',
            'records_consent'      => 'Patient Consent (Form 1) — Records Office',
            'records_treatment'    => 'Treatment / RHU clinical fields (Form 2) — Records Office',
            'doctor'               => 'Clinical Findings, EKAS & EPRESS — Doctor',
            'it'                   => 'IT Department — Patient Encoding & PKRF',
            'receptionist'         => 'Receptionist (Reserved)',
        ];
    }

    /**
     * Returns HTML attributes for a form field based on role permissions.
     */
    public static function fieldAttrs(string $role, string $fieldGroup): string
    {
        $role = self::normalizeRole($role);
        $group = self::normalizeGroup($fieldGroup);
        $editable = self::canEdit($role, $group);
        $attrs = 'data-field-group="' . htmlspecialchars($group) . '" data-field-owner="' . htmlspecialchars($group) . '"'
            . ' data-perm-role="' . htmlspecialchars($role) . '"'
            . ' data-perm-editable="' . ($editable ? '1' : '0') . '"';

        if (!$editable) {
            $attrs .= ' readonly disabled';
        }

        return $attrs;
    }

    public static function sectionClass(string $role, string $fieldGroup): string
    {
        $group = self::normalizeGroup($fieldGroup);
        $classes = ['form-section', 'section-' . $group];
        if (self::canEdit($role, $group)) {
            $classes[] = 'section-editable';
        } else {
            $classes[] = 'section-readonly';
        }
        return implode(' ', $classes);
    }

    public static function debugLog(string $message): void
    {
        if (self::$debugLogging) {
            error_log('[RHU_PERM] ' . $message);
        }
    }
}
