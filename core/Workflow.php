<?php
/**
 * Patient record workflow — status maps to assigned office.
 * Validation and printing are separate stages.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/FormCompleteness.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/ReceptionQueue.php';

class Workflow
{
    /** Status → office slug that currently owns the record */
    private static array $statusOffice = [
        WORKFLOW_DRAFT                   => ROLE_RECORDS,
        WORKFLOW_PENDING_RECEPTIONIST    => ROLE_RECEPTIONIST,
        WORKFLOW_WAITING_FOR_DOCTOR      => ROLE_DOCTOR,
        WORKFLOW_PENDING_DOCTOR          => ROLE_DOCTOR,
        'Pending Doctor'                 => ROLE_DOCTOR,
        WORKFLOW_WAITING_FOR_PHARMACY    => ROLE_PHARMACY,
        WORKFLOW_DISPENSING              => ROLE_PHARMACY,
        WORKFLOW_DISPENSED               => ROLE_PHARMACY,
        WORKFLOW_PENDING_IT_REVIEW       => ROLE_IT,
        'Pending IT Review'              => ROLE_IT,
        WORKFLOW_RETURNED_FOR_CORRECTION => ROLE_RECORDS,
        WORKFLOW_READY_FOR_PRINT         => ROLE_IT,
        WORKFLOW_PRINTED                 => ROLE_IT,
        WORKFLOW_ARCHIVED                => ROLE_IT,
        WORKFLOW_REJECTED                => ROLE_RECORDS,
    ];

    /** Queue statuses visible per role */
    private static array $roleQueues = [
        ROLE_RECORDS      => [WORKFLOW_DRAFT, WORKFLOW_REJECTED, WORKFLOW_RETURNED_FOR_CORRECTION],
        ROLE_RECEPTIONIST => [WORKFLOW_PENDING_RECEPTIONIST],
        ROLE_DOCTOR       => [WORKFLOW_WAITING_FOR_DOCTOR, 'Pending Doctor'],
        ROLE_PHARMACY     => [WORKFLOW_WAITING_FOR_PHARMACY, WORKFLOW_DISPENSING],
        ROLE_IT           => [WORKFLOW_DRAFT, WORKFLOW_PENDING_IT_REVIEW, WORKFLOW_READY_FOR_PRINT, WORKFLOW_PRINTED],
    ];

    /** Allowed transitions: action => config */
    private static array $transitions = [
        'submit_for_consultation' => [
            'role'  => ROLE_RECORDS,
            'from'  => [WORKFLOW_DRAFT, WORKFLOW_REJECTED, WORKFLOW_RETURNED_FOR_CORRECTION],
            'to'    => WORKFLOW_PENDING_RECEPTIONIST,
            'label' => 'Submitted for consultation',
        ],
        'submit_to_it_review' => [
            'role'     => ROLE_RECORDS,
            'from'     => [WORKFLOW_DRAFT, WORKFLOW_REJECTED, WORKFLOW_RETURNED_FOR_CORRECTION],
            'to'       => WORKFLOW_PENDING_IT_REVIEW,
            'label'    => 'Submitted to IT Review',
            'validate' => true,
            'notify'   => 'submitted_to_it',
        ],
        'forward_to_doctor' => [
            'role'  => ROLE_RECEPTIONIST,
            'from'  => [WORKFLOW_PENDING_RECEPTIONIST],
            'to'    => WORKFLOW_WAITING_FOR_DOCTOR,
            'label' => 'Forwarded to Doctor — Waiting for Doctor',
        ],
        /** IT encoding → Doctor Consultation Queue (same consultation record, no duplicate). */
        'send_to_doctor' => [
            'role'     => ROLE_IT,
            'from'     => [WORKFLOW_DRAFT, WORKFLOW_RETURNED_FOR_CORRECTION, WORKFLOW_REJECTED],
            'to'       => WORKFLOW_WAITING_FOR_DOCTOR,
            'label'    => 'Sent to Doctor Consultation Queue — Waiting for Doctor',
            'validate' => false,
        ],
        'complete_consultation' => [
            'role'  => ROLE_DOCTOR,
            'from'  => [WORKFLOW_WAITING_FOR_DOCTOR, WORKFLOW_PENDING_DOCTOR, 'Pending Doctor'],
            'to'    => WORKFLOW_WAITING_FOR_PHARMACY,
            'label' => 'Sent to Pharmacy — Waiting for Pharmacy',
        ],
        'send_to_pharmacy' => [
            'role'  => ROLE_DOCTOR,
            'from'  => [WORKFLOW_WAITING_FOR_DOCTOR, WORKFLOW_PENDING_DOCTOR, 'Pending Doctor'],
            'to'    => WORKFLOW_WAITING_FOR_PHARMACY,
            'label' => 'Sent to Pharmacy — Waiting for Pharmacy',
        ],
        'start_dispensing' => [
            'role'  => ROLE_PHARMACY,
            'from'  => [WORKFLOW_WAITING_FOR_PHARMACY],
            'to'    => WORKFLOW_DISPENSING,
            'label' => 'Pharmacy started dispensing',
        ],
        'mark_dispensed' => [
            'role'  => ROLE_PHARMACY,
            'from'  => [WORKFLOW_WAITING_FOR_PHARMACY, WORKFLOW_DISPENSING],
            'to'    => WORKFLOW_DISPENSED,
            'label' => 'Medicines dispensed',
        ],
        'approve_for_printing' => [
            'role'           => ROLE_IT,
            'from'           => ['Pending IT Review', WORKFLOW_PENDING_IT_REVIEW, 'Pending IT Validation', WORKFLOW_DISPENSED],
            'to'             => WORKFLOW_READY_FOR_PRINT,
            'label'          => 'Approved Consultation — sent to Print Queue',
            'validate'       => false,
            'set_validated'  => true,
            'notify'         => 'approved',
        ],
        'mark_ready_for_print' => [
            'role'           => ROLE_IT,
            'from'           => ['Pending IT Review', WORKFLOW_PENDING_IT_REVIEW, 'Pending IT Validation', WORKFLOW_DISPENSED],
            'to'             => WORKFLOW_READY_FOR_PRINT,
            'label'          => 'Approved — Ready for Printing',
            'validate'       => false,
            'set_validated'  => true,
            'notify'         => 'ready_for_printing',
        ],
        'return_for_correction' => [
            'role'            => ROLE_IT,
            'from'            => ['Pending IT Review', WORKFLOW_PENDING_IT_REVIEW, 'Pending IT Validation'],
            'to'              => WORKFLOW_RETURNED_FOR_CORRECTION,
            'label'           => 'Returned for Correction',
            'requires_reason' => true,
            'notify'          => 'returned_for_correction',
        ],
        /** @deprecated Prefer return_for_correction */
        'reject_to_encoder' => [
            'role'            => ROLE_IT,
            'from'            => ['Pending IT Review', WORKFLOW_PENDING_IT_REVIEW, 'Pending IT Validation'],
            'to'              => WORKFLOW_RETURNED_FOR_CORRECTION,
            'label'           => 'Returned for Correction',
            'requires_reason' => true,
            'notify'          => 'returned_for_correction',
        ],
        'mark_printed' => [
            'role'  => ROLE_IT,
            'from'  => [WORKFLOW_READY_FOR_PRINT],
            'to'    => WORKFLOW_PRINTED,
            'label' => 'Official forms printed',
        ],
        'archive_record' => [
            'role'  => ROLE_IT,
            'from'  => [WORKFLOW_PRINTED],
            'to'    => WORKFLOW_ARCHIVED,
            'label' => 'Record archived',
        ],
    ];

    public static function getAssignedOffice(string $status): ?string
    {
        return self::$statusOffice[$status] ?? null;
    }

    public static function getQueueStatuses(string $role): array
    {
        return self::$roleQueues[$role] ?? [];
    }

    public static function getActionForRoleAndStatus(string $role, string $status): ?array
    {
        foreach (self::$transitions as $action => $config) {
            if ($config['role'] === $role && in_array($status, $config['from'], true)) {
                return [
                    'action' => $action,
                    'to'     => $config['to'],
                    'label'  => self::getActionButtonLabel($action),
                ];
            }
        }

        return null;
    }

    public static function getActionButtonLabel(string $action): string
    {
        return match ($action) {
            'submit_for_consultation' => 'Submit for Consultation',
            'submit_to_it_review'     => 'Submit to IT Review',
            'forward_to_doctor'       => 'Forward to Doctor',
            'send_to_doctor'          => 'Send to Doctor',
            'complete_consultation'   => 'Send to Pharmacy',
            'send_to_pharmacy'        => 'Send to Pharmacy',
            'start_dispensing'        => 'Start Dispensing',
            'mark_dispensed'          => 'Mark as Dispensed',
            'approve_for_printing'    => 'Approve & Send to Print Queue',
            'mark_ready_for_print'    => 'Approve & Send to Print Queue',
            'return_for_correction'   => 'Return for Correction',
            'reject_to_encoder'       => 'Return for Correction',
            'mark_printed'            => 'Mark as Printed',
            'archive_record'          => 'Archive Consultation',
            default                   => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    public static function canPrint(string $role, string $status): bool
    {
        return $role === ROLE_IT
            && in_array($status, [WORKFLOW_READY_FOR_PRINT, WORKFLOW_PRINTED], true);
    }

    public static function isLocked(string $status): bool
    {
        return WorkflowStatus::isLocked($status);
    }

    public static function execute(
        mysqli $conn,
        string $action,
        int $patientId,
        array $user
    ): array {
        if (!isset(self::$transitions[$action])) {
            throw new InvalidArgumentException('Invalid workflow action.');
        }

        $config = self::$transitions[$action];
        $role   = $user['role'];

        if ($config['role'] !== $role) {
            throw new RuntimeException('Your role is not authorized for this action.');
        }

        $stmt = $conn->prepare('SELECT id, status, patient_id, encoded_by FROM patients WHERE id = ?');
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$patient) {
            throw new RuntimeException('Patient record not found.');
        }

        if ($patient['status'] === WORKFLOW_ARCHIVED) {
            throw new RuntimeException('Archived records cannot be modified.');
        }

        $currentStatus = WorkflowStatus::normalize($patient['status'] ?? '');
        $fromOk = false;
        foreach ($config['from'] as $allowed) {
            if (WorkflowStatus::normalize($allowed) === $currentStatus || $patient['status'] === $allowed) {
                $fromOk = true;
                break;
            }
        }
        if (!$fromOk) {
            throw new RuntimeException(
                'This record cannot be processed at its current status (' . $patient['status'] . ').'
            );
        }

        $validation = null;
        if (!empty($config['validate'])) {
            $validation = FormCompleteness::validatePatientId($conn, $patientId);
            if (!$validation['complete']) {
                throw new RuntimeException(FormCompleteness::formatIssuesMessage($validation['issues']));
            }
        }

        $remarks = trim((string) ($user['_rejection_reason'] ?? $user['_remarks'] ?? ''));
        if (!empty($config['requires_reason']) && $remarks === '') {
            throw new RuntimeException('Remarks are required when returning a consultation for correction.');
        }

        WorkflowStatus::ensurePatientsStatusEnum($conn);

        $fromStatus = $patient['status'];
        $toStatus   = $config['to'];
        $uid        = (int) $user['id'];

        // IT approve (encoding only, no clinical yet) → Doctor Consultation Queue
        if (
            in_array($action, ['approve_for_printing', 'mark_ready_for_print'], true)
            && !ReceptionQueue::hasCompletedClinicalVisit($conn, $patientId)
        ) {
            $toStatus = WORKFLOW_WAITING_FOR_DOCTOR;
        }

        $returnToUserId = (int) ($user['_return_to_user_id'] ?? 0);
        $isReturnForCorrection = in_array($action, ['return_for_correction', 'reject_to_encoder'], true);

        if ($isReturnForCorrection) {
            if ($returnToUserId <= 0) {
                $returnToUserId = (int) ($patient['encoded_by'] ?? 0);
            }
            if ($returnToUserId <= 0) {
                throw new RuntimeException('Select a staff member to return this record to.');
            }
            self::assertActiveUser($conn, $returnToUserId);
        }

        if ($toStatus === WORKFLOW_ARCHIVED) {
            $stmt = $conn->prepare('UPDATE patients SET status = ?, is_archived = 1 WHERE id = ?');
            $stmt->bind_param('si', $toStatus, $patientId);
        } elseif ($toStatus === WORKFLOW_RETURNED_FOR_CORRECTION || $toStatus === WORKFLOW_REJECTED) {
            if ($isReturnForCorrection) {
                $stmt = $conn->prepare(
                    'UPDATE patients SET status = ?, rejection_reason = ?, encoded_by = ? WHERE id = ?'
                );
                $stmt->bind_param('ssii', $toStatus, $remarks, $returnToUserId, $patientId);
                $patient['encoded_by'] = $returnToUserId;
            } else {
                $stmt = $conn->prepare('UPDATE patients SET status = ?, rejection_reason = ? WHERE id = ?');
                $stmt->bind_param('ssi', $toStatus, $remarks, $patientId);
            }
        } elseif (!empty($config['set_validated'])) {
            $stmt = $conn->prepare('UPDATE patients SET status = ?, rejection_reason = NULL, validated_by = ? WHERE id = ?');
            $stmt->bind_param('sii', $toStatus, $uid, $patientId);
        } else {
            $stmt = $conn->prepare('UPDATE patients SET status = ?, rejection_reason = NULL WHERE id = ?');
            $stmt->bind_param('si', $toStatus, $patientId);
        }
        $stmt->execute();
        if ($stmt->errno) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('SQL error updating patient status: ' . $err);
        }
        $stmt->close();

        logWorkflow($conn, $patientId, $uid, $config['label'], $fromStatus, $toStatus, $remarks !== '' ? $remarks : null);

        if ($toStatus === WORKFLOW_PENDING_RECEPTIONIST) {
            ReceptionQueue::ensureWaitingEntry($conn, $patientId, $uid);
        }
        if ($toStatus === WORKFLOW_WAITING_FOR_DOCTOR || $toStatus === WORKFLOW_PENDING_DOCTOR) {
            ReceptionQueue::ensureWaitingEntry($conn, $patientId, $uid);
            ReceptionQueue::markSentToDoctor($conn, $patientId);
            $linkConsultId = (int) ($user['_consultation_id'] ?? 0);
            if ($linkConsultId <= 0) {
                $linkConsultId = ReceptionQueue::latestConsultationId($conn, $patientId);
            }
            if ($linkConsultId > 0) {
                ReceptionQueue::linkConsultation($conn, $patientId, $linkConsultId);
            }
        }
        if (in_array($action, ['complete_consultation', 'send_to_pharmacy'], true)) {
            ReceptionQueue::markConsultationDone($conn, $patientId);
        }
        if ($action === 'forward_to_doctor') {
            ReceptionQueue::markSentToDoctor($conn, $patientId);
        }

        if (!empty($config['notify'])) {
            $notifyUserId = $isReturnForCorrection ? $returnToUserId : 0;
            $consultForLink = (int) ($user['_consultation_id'] ?? 0);
            NotificationService::notifyWorkflow(
                $conn,
                $patient,
                $config['notify'],
                $user,
                $remarks,
                $notifyUserId > 0 ? $notifyUserId : null,
                $consultForLink > 0 ? $consultForLink : null
            );
        }

        $message = self::getSuccessMessage($action);
        if (
            in_array($action, ['approve_for_printing', 'mark_ready_for_print'], true)
            && ($toStatus === WORKFLOW_WAITING_FOR_DOCTOR || $toStatus === WORKFLOW_PENDING_DOCTOR)
        ) {
            $message = 'Record approved and sent to the Doctor Consultation Queue (Waiting for Doctor).';
        }

        return [
            'patient_id'      => $patient['patient_id'],
            'from_status'     => $fromStatus,
            'to_status'       => $toStatus,
            'assigned_office' => self::getAssignedOffice($toStatus),
            'message'         => $message,
            'validation'      => $validation,
        ];
    }

    private static function getSuccessMessage(string $action): string
    {
        return match ($action) {
            'submit_for_consultation' => 'Record submitted for consultation.',
            'submit_to_it_review'     => 'Record submitted to IT Department for validation.',
            'forward_to_doctor'       => 'Patient marked Waiting for Doctor.',
            'send_to_doctor'          => 'Patient sent to the Doctor Consultation Queue (Waiting for Doctor). Same consultation record — no duplicate created.',
            'complete_consultation'   => 'Consultation saved and sent to Pharmacy (Waiting for Pharmacy).',
            'send_to_pharmacy'        => 'Consultation saved and sent to Pharmacy (Waiting for Pharmacy).',
            'start_dispensing'        => 'Prescription marked as Dispensing.',
            'mark_dispensed'          => 'Medicines marked as Dispensed.',
            'approve_for_printing'    => 'Consultation approved and moved to the Print Queue.',
            'mark_ready_for_print'    => 'Consultation approved and moved to the Print Queue.',
            'return_for_correction'   => 'Consultation returned to the encoder for correction.',
            'reject_to_encoder'       => 'Consultation returned to the encoder for correction.',
            'mark_printed'            => 'Record marked as printed.',
            'archive_record'          => 'Consultation archived successfully.',
            default                   => 'Workflow updated successfully.',
        };
    }

    public static function getQueueTitle(string $role): string
    {
        return match ($role) {
            ROLE_RECORDS      => 'Records Office — Pending Encoding',
            ROLE_IT           => 'IT Department — Validation & Print',
            ROLE_RECEPTIONIST => 'Receptionist — My Queue',
            ROLE_DOCTOR       => 'Doctor — Consultation Queue',
            ROLE_PHARMACY     => 'Pharmacy — Dispensing Queue',
            default           => 'Workflow Queue',
        };
    }

    public static function getQueueSubtitle(string $role): string
    {
        return match ($role) {
            ROLE_RECORDS      => 'Draft and returned records ready for encoding',
            ROLE_IT           => 'Validate consultations, then print official forms',
            ROLE_RECEPTIONIST => 'IT-approved patients ready for queue assignment',
            ROLE_DOCTOR       => 'FIFO queue — Waiting for Doctor (today only)',
            ROLE_PHARMACY     => 'Patients waiting for the pharmacist',
            default           => 'Patient records assigned to your office',
        };
    }

    private static function assertActiveUser(mysqli $conn, int $userId): void
    {
        $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('The selected user is not an active account.');
        }
    }
}
