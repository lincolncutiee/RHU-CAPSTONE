<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/Session.php';

class Permissions
{
    private static ?array $matrix = null;
    private static ?array $rolePermissionConfig = null;

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
            'system_administrator' => ROLE_SYSTEM_ADMIN,
            'system administrator' => ROLE_SYSTEM_ADMIN,
            'receptionist' => ROLE_RECEPTIONIST,
            'pharmacy' => ROLE_PHARMACY,
            'pharmacist' => ROLE_PHARMACY,
        ];

        return $aliases[$role] ?? $role;
    }

    /** @return 'none'|'view'|'edit' */
    public static function resolveAccessLevel(string $role, string $fieldGroup, ?int $userId = null): string
    {
        $role = self::normalizeRole($role);
        $group = self::normalizeGroup($fieldGroup);
        $override = self::userPrivilegeOverride($group, $userId);

        if ($override === 'none') {
            return 'none';
        }
        if ($override === 'view') {
            return self::capFieldGroupLevel($group, 'view');
        }
        if ($override === 'edit') {
            return self::capFieldGroupLevel($group, 'edit');
        }

        $config = self::matrix()[$role] ?? null;
        if (!$config) {
            return 'none';
        }
        if (in_array($group, $config['editable'], true)) {
            return self::capFieldGroupLevel($group, 'edit');
        }
        if (in_array($group, $config['readonly'], true)) {
            return self::capFieldGroupLevel($group, 'view');
        }
        return 'none';
    }

    /** @return 'none'|'view'|'edit' */
    private static function capFieldGroupLevel(string $group, string $level): string
    {
        $caps = self::rolePermissionFieldCaps()[$group] ?? null;
        if (!$caps) {
            return $level;
        }
        if ($level === 'edit') {
            $editKey = $caps['edit'] ?? null;
            if ($editKey && self::can($editKey)) {
                return 'edit';
            }
            $viewKey = $caps['view'] ?? null;
            if ($viewKey && self::can($viewKey)) {
                return 'view';
            }
            return 'none';
        }
        $viewKey = $caps['view'] ?? ($caps['edit'] ?? null);
        if ($viewKey && self::can($viewKey)) {
            return 'view';
        }
        return 'none';
    }

    /** @return array<string, array{edit?: string, view?: string}> */
    private static function rolePermissionFieldCaps(): array
    {
        if (self::$rolePermissionConfig === null) {
            self::$rolePermissionConfig = require __DIR__ . '/../config/role-permissions.php';
        }
        return self::$rolePermissionConfig['field_group_caps'] ?? [];
    }

    public static function can(string $permissionKey): bool
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            return false;
        }

        $role = self::normalizeRole((string) Session::get('role'));
        if ($role === ROLE_SYSTEM_ADMIN && str_starts_with($permissionKey, 'admin.')) {
            return true;
        }

        $sessionPerms = Session::get('role_permissions');
        if (is_array($sessionPerms) && array_key_exists($permissionKey, $sessionPerms)) {
            return !empty($sessionPerms[$permissionKey]);
        }

        if (self::$rolePermissionConfig === null) {
            self::$rolePermissionConfig = require __DIR__ . '/../config/role-permissions.php';
        }
        $roleDefaults = self::$rolePermissionConfig['defaults'][$role] ?? [];
        return !empty($roleDefaults[$permissionKey]);
    }

    public static function requireCan(string $permissionKey): void
    {
        if (!self::can($permissionKey)) {
            http_response_code(403);
            die('Access denied. You do not have permission for this action.');
        }
    }

    /** @param list<string> $permissionKeys */
    public static function requireCanAny(array $permissionKeys): void
    {
        foreach ($permissionKeys as $key) {
            if (self::can($key)) {
                return;
            }
        }
        http_response_code(403);
        die('Access denied. You do not have permission for this action.');
    }

    public static function refreshRolePermissionsInSession(mysqli $conn, ?string $roleSlug = null): void
    {
        require_once __DIR__ . '/RolePermissions.php';
        $role = Permissions::normalizeRole($roleSlug ?? (string) Session::get('role'));
        Session::set('role_permissions', RolePermissions::loadForSession($conn, $role));
    }

    private static function userPrivilegeOverride(string $group, ?int $userId): ?string
    {
        $privileges = Session::get('field_privileges');
        if (!is_array($privileges) || empty($privileges)) {
            return null;
        }
        $level = $privileges[$group] ?? 'inherit';
        if ($level === 'inherit') {
            return null;
        }
        if (in_array($level, ['none', 'view', 'edit'], true)) {
            return $level;
        }
        return null;
    }

    public static function canEdit(string $role, string $fieldGroup, ?int $userId = null): bool
    {
        return self::resolveAccessLevel($role, $fieldGroup, $userId) === 'edit';
    }

    public static function canView(string $role, string $fieldGroup, ?int $userId = null): bool
    {
        $level = self::resolveAccessLevel($role, $fieldGroup, $userId);
        return $level === 'edit' || $level === 'view';
    }

    public static function isReadonly(string $role, string $fieldGroup, ?int $userId = null): bool
    {
        return !self::canEdit($role, $fieldGroup, $userId);
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
            'it'                   => 'IT Department — Patient Encoding & PCSF',
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
