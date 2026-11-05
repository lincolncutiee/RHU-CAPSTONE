<?php
/**
 * Workflow status labels and color-coded badge classes.
 */

require_once __DIR__ . '/../config/app.php';

class WorkflowStatus
{
    /** Legacy aliases still present in some DB rows */
    public static function pendingItAliases(): array
    {
        return [WORKFLOW_PENDING_IT_REVIEW, 'Pending IT Review', 'Pending IT Validation'];
    }

    public static function isPendingItValidation(string $status): bool
    {
        return in_array(self::normalize($status), self::pendingItAliases(), true)
            || in_array($status, self::pendingItAliases(), true);
    }

    /**
     * Normalize blank / null / legacy blanks to Draft so Records can encode.
     */
    public static function normalize(?string $status): string
    {
        $status = trim((string) $status);
        if ($status === '') {
            return WORKFLOW_DRAFT;
        }
        // Legacy → current labels
        if ($status === 'Pending Doctor') {
            return WORKFLOW_WAITING_FOR_DOCTOR;
        }
        return $status;
    }

    /** Statuses that appear in the Pharmacy Queue */
    public static function pharmacyQueueStatuses(): array
    {
        return [
            WORKFLOW_WAITING_FOR_PHARMACY,
            WORKFLOW_DISPENSING,
            WORKFLOW_DISPENSED,
        ];
    }

    /** Statuses that appear in the Doctor Consultation Queue */
    public static function doctorQueueStatuses(): array
    {
        return [WORKFLOW_WAITING_FOR_DOCTOR, 'Pending Doctor'];
    }

    public static function encoderEditableStatuses(): array
    {
        return [WORKFLOW_DRAFT, WORKFLOW_REJECTED, WORKFLOW_RETURNED_FOR_CORRECTION];
    }

    public static function canEncoderEdit(?string $status): bool
    {
        return in_array(self::normalize($status), self::encoderEditableStatuses(), true);
    }

    /**
     * Whether records-owned field groups must be forced read-only for this viewer.
     * Permission ownership still applies separately via Permissions.
     */
    public static function shouldLockRecordsFields(string $role, ?string $status): bool
    {
        $status = self::normalize($status);

        // Hard lock after printing
        if (self::isLocked($status)) {
            return true;
        }

        // Records Office may edit only while the record remains in the encoder queue
        if ($role === ROLE_RECORDS) {
            return !self::canEncoderEdit($status);
        }

        // IT may fill Records / PCSF fields until the record is hard-locked (printed/archived)
        if ($role === ROLE_IT) {
            return false;
        }

        return false;
    }

    /** @return array<string, string> */
    public static function badgeClassMap(): array
    {
        return [
            WORKFLOW_DRAFT                  => 'badge-draft',
            WORKFLOW_PENDING_RECEPTIONIST   => 'badge-clinical',
            WORKFLOW_WAITING_FOR_DOCTOR     => 'badge-clinical',
            WORKFLOW_PENDING_DOCTOR         => 'badge-clinical',
            'Pending Doctor'                => 'badge-clinical',
            WORKFLOW_WAITING_FOR_PHARMACY   => 'badge-clinical',
            WORKFLOW_DISPENSING             => 'badge-it-review',
            WORKFLOW_DISPENSED              => 'badge-printed',
            WORKFLOW_PENDING_IT_REVIEW      => 'badge-it-review',
            'Pending IT Review'             => 'badge-it-review',
            WORKFLOW_RETURNED_FOR_CORRECTION => 'badge-returned',
            WORKFLOW_READY_FOR_PRINT        => 'badge-ready-print',
            WORKFLOW_PRINTED                => 'badge-printed',
            WORKFLOW_ARCHIVED               => 'badge-archived',
            WORKFLOW_REJECTED               => 'badge-rejected',
        ];
    }

    public static function badgeClass(string $status): string
    {
        return self::badgeClassMap()[self::normalize($status)] ?? 'badge-draft';
    }

    public static function badgeHtml(string $status, ?string $label = null): string
    {
        $normalized = self::normalize($status);
        $text = htmlspecialchars($label ?? $normalized);
        $class = htmlspecialchars(self::badgeClass($normalized));

        return '<span class="badge-status ' . $class . '">' . $text . '</span>';
    }

    /** Statuses where medical data must not be edited. */
    public static function isLocked(?string $status): bool
    {
        return in_array(self::normalize($status), [WORKFLOW_PRINTED, WORKFLOW_ARCHIVED], true);
    }

    /** IT-facing post-clinical statuses. */
    public static function itPhaseStatuses(): array
    {
        return array_values(array_unique(array_merge(
            self::pendingItAliases(),
            [
                WORKFLOW_READY_FOR_PRINT,
                WORKFLOW_PRINTED,
                WORKFLOW_ARCHIVED,
            ]
        )));
    }

    /** @return array<string, string> */
    public static function filterOptions(): array
    {
        return [
            WORKFLOW_DRAFT                   => 'Draft',
            WORKFLOW_PENDING_RECEPTIONIST    => 'Pending Receptionist',
            WORKFLOW_WAITING_FOR_DOCTOR      => 'Waiting for Doctor',
            WORKFLOW_WAITING_FOR_PHARMACY    => 'Waiting for Pharmacy',
            WORKFLOW_DISPENSING              => 'Dispensing',
            WORKFLOW_DISPENSED               => 'Dispensed',
            WORKFLOW_PENDING_IT_REVIEW       => 'Pending IT Validation',
            WORKFLOW_RETURNED_FOR_CORRECTION => 'Returned for Correction',
            WORKFLOW_READY_FOR_PRINT         => 'Ready for Printing',
            WORKFLOW_PRINTED                 => 'Printed',
            WORKFLOW_ARCHIVED                => 'Archived',
            WORKFLOW_REJECTED                => 'Rejected',
        ];
    }

    /**
     * Expand patients.status ENUM for new workflow labels (safe on XAMPP / existing DBs).
     */
    public static function ensurePatientsStatusEnum(mysqli $conn): void
    {
        @$conn->query(
            "ALTER TABLE patients MODIFY COLUMN status ENUM(
                'Draft',
                'Pending Receptionist',
                'Pending Doctor',
                'Waiting for Doctor',
                'Waiting for Pharmacy',
                'Dispensing',
                'Dispensed',
                'Pending IT Review',
                'Pending IT Validation',
                'Returned for Correction',
                'Ready for Printing',
                'Printed',
                'Archived',
                'Rejected'
            ) NOT NULL DEFAULT 'Draft'"
        );
        @$conn->query(
            "UPDATE patients SET status = 'Waiting for Doctor' WHERE status = 'Pending Doctor'"
        );
    }
}
