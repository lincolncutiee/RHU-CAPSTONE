<?php
/**
 * IT Department dashboard — validation/print queue and same-day activity.
 */

require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/ItPrintForms.php';
require_once __DIR__ . '/PatientSearch.php';
require_once __DIR__ . '/ReceptionQueue.php';

class ItDashboard
{
    private static function today(): string
    {
        return ReceptionQueue::today();
    }

    /** @return list<string> */
    public static function activeQueueStatuses(): array
    {
        return array_values(array_unique(array_merge(
            WorkflowStatus::pendingItAliases(),
            [
                WORKFLOW_RETURNED_FOR_CORRECTION,
                WORKFLOW_READY_FOR_PRINT,
            ]
        )));
    }

    /** @return array<string, mixed> */
    public static function load(mysqli $conn): array
    {
        $today = self::today();
        $stats = self::stats($conn, $today);
        $allQueue = self::queuePreview($conn, 40);
        $queueToday = [];
        $queueBacklog = [];
        foreach ($allQueue as $row) {
            if (!empty($row['is_today'])) {
                $queueToday[] = $row;
            } else {
                $queueBacklog[] = $row;
            }
        }
        $stats['queue_today'] = count($queueToday);
        $printedToday = self::printedToday($conn, $today, 10);

        $firstPending = self::firstActionableRow(array_merge($queueToday, $queueBacklog));

        $primaryAction = [
            'label' => $firstPending ? 'Open Next in Queue' : 'Open Review Queue',
            'url'   => $firstPending['primary_url'] ?? (BASE_URL . '/validation-status.php'),
            'icon'  => $firstPending ? 'bi-play-fill' : 'bi-shield-check',
        ];

        return [
            'today'          => $today,
            'stats'          => $stats,
            'queue_today'    => $queueToday,
            'queue_backlog'  => $queueBacklog,
            'printed_today'  => $printedToday,
            'primaryAction'  => $primaryAction,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function firstActionableRow(array $rows): ?array
    {
        foreach ($rows as $row) {
            if (($row['action'] ?? '') === 'review') {
                return $row;
            }
        }
        foreach ($rows as $row) {
            if (($row['action'] ?? '') === 'print') {
                return $row;
            }
        }
        return null;
    }

    /** @return array{pending_validation:int,ready_to_print:int,returned:int,validated_today:int,printed_today:int,queue_today:int} */
    private static function stats(mysqli $conn, string $today): array
    {
        $pendingAliases = WorkflowStatus::pendingItAliases();
        $pendingPh = implode(',', array_fill(0, count($pendingAliases), '?'));
        $pendingTypes = str_repeat('s', count($pendingAliases));

        $stats = [
            'pending_validation' => 0,
            'ready_to_print'     => ItPrintForms::readyToPrintCount($conn),
            'returned'           => 0,
            'validated_today'    => 0,
            'printed_today'      => 0,
            'queue_today'        => 0,
        ];

        $ps = $conn->prepare("SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status IN ($pendingPh)");
        $ps->bind_param($pendingTypes, ...$pendingAliases);
        $ps->execute();
        $stats['pending_validation'] = (int) ($ps->get_result()->fetch_assoc()['c'] ?? 0);
        $ps->close();

        $returned = WORKFLOW_RETURNED_FOR_CORRECTION;
        $st = $conn->prepare('SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ?');
        $st->bind_param('s', $returned);
        $st->execute();
        $stats['returned'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        $ready = WORKFLOW_READY_FOR_PRINT;
        $st = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ? AND DATE(updated_at) = ?'
        );
        $st->bind_param('ss', $ready, $today);
        $st->execute();
        $stats['validated_today'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        $printed = WORKFLOW_PRINTED;
        $st = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND status = ? AND DATE(updated_at) = ?'
        );
        $st->bind_param('ss', $printed, $today);
        $st->execute();
        $stats['printed_today'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function queuePreview(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $queueStatuses = self::activeQueueStatuses();
        $pendingAliases = WorkflowStatus::pendingItAliases();

        $placeholders = implode(',', array_fill(0, count($queueStatuses), '?'));
        $types = str_repeat('s', count($queueStatuses));

        $orderPending = implode("','", array_map(static fn ($s) => $conn->real_escape_string($s), $pendingAliases));
        $returnedEsc = $conn->real_escape_string(WORKFLOW_RETURNED_FOR_CORRECTION);
        $readyEsc = $conn->real_escape_string(WORKFLOW_READY_FOR_PRINT);

        $sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                       p.status, p.date_encoded, p.updated_at, p.residential_address,
                       t.id AS consultation_id, t.date_of_consultation
                FROM patients p
                LEFT JOIN treatment_records t ON t.id = (
                    SELECT id FROM treatment_records WHERE patient_id = p.id ORDER BY id DESC LIMIT 1
                )
                WHERE p.is_archived = 0 AND p.status IN ($placeholders)
                ORDER BY
                    CASE
                        WHEN p.status IN ('{$orderPending}') THEN 0
                        WHEN p.status = '{$returnedEsc}' THEN 1
                        WHEN p.status = '{$readyEsc}' THEN 2
                        ELSE 3
                    END,
                    COALESCE(p.updated_at, p.date_encoded) ASC
                LIMIT {$limit}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$queueStatuses);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $today = self::today();
        return array_map(static function (array $r) use ($today) {
            return self::mapQueueRow($r, $today);
        }, $records);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function printedToday(mysqli $conn, string $today, int $limit): array
    {
        $limit = max(1, min(30, $limit));
        $printed = WORKFLOW_PRINTED;
        $stmt = $conn->prepare(
            "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                    p.status, p.updated_at
             FROM patients p
             WHERE p.is_archived = 0 AND p.status = ? AND DATE(p.updated_at) = ?
             ORDER BY p.updated_at DESC
             LIMIT {$limit}"
        );
        $stmt->bind_param('ss', $printed, $today);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'patient_id'   => $r['patient_id'],
                'patient_name' => ItPrintForms::formatPatientName($r),
                'printed_at'   => !empty($r['updated_at'])
                    ? date('g:i A', strtotime($r['updated_at']))
                    : '—',
                'folder_url'   => BASE_URL . '/patient-folder.php?id=' . (int) $r['id'],
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $r */
    private static function mapQueueRow(array $r, string $today): array
    {
        $status = (string) ($r['status'] ?? '');
        $touchRaw = $r['updated_at'] ?? $r['date_encoded'] ?? null;
        $touchDate = $touchRaw ? date('Y-m-d', strtotime((string) $touchRaw)) : '';
        $isToday = $touchDate === $today;
        $isPending = WorkflowStatus::isPendingItValidation($status);
        $isReturned = $status === WORKFLOW_RETURNED_FOR_CORRECTION;
        $isReady = $status === WORKFLOW_READY_FOR_PRINT;

        if ($isPending) {
            $badgeLabel = 'Pending Validation';
            $badgeClass = 'pending';
            $action = 'review';
        } elseif ($isReturned) {
            $badgeLabel = 'Returned for correction';
            $badgeClass = 'returned';
            $action = 'review';
        } elseif ($isReady) {
            $badgeLabel = 'Ready to print';
            $badgeClass = 'ready';
            $action = 'print';
        } else {
            $labels = WorkflowStatus::filterOptions();
            $badgeLabel = $labels[$status] ?? $labels[WorkflowStatus::normalize($status)] ?? $status;
            $badgeClass = 'muted';
            $action = 'view';
        }

        $pid = (int) ($r['id'] ?? 0);
        $cid = (int) ($r['consultation_id'] ?? 0);
        $reviewUrl = BASE_URL . '/it/validation-workspace.php?id=' . $pid
            . ($cid > 0 ? '&consultation=' . $cid : '');
        $printUrl = BASE_URL . '/it/print-queue.php?patient_id=' . rawurlencode((string) ($r['patient_id'] ?? ''));
        $primaryUrl = $isReady ? $printUrl : $reviewUrl;

        $barangay = PatientSearch::extractBarangay($r['residential_address'] ?? null);

        return [
            'patient_id'       => $r['patient_id'] ?? '',
            'patient_name'     => ItPrintForms::formatPatientName($r),
            'barangay'         => $barangay !== '' && $barangay !== '—' ? $barangay : '',
            'consult_date'     => !empty($r['date_of_consultation'])
                ? date('M d, Y', strtotime($r['date_of_consultation']))
                : '—',
            'updated_label'    => !empty($r['updated_at'])
                ? date('M d, g:i A', strtotime($r['updated_at']))
                : '—',
            'is_today'         => $isToday,
            'badge_label'      => $badgeLabel,
            'badge_class'      => $badgeClass,
            'action'           => $action,
            'primary_url'      => $primaryUrl,
            'review_url'       => $reviewUrl,
            'print_url'        => $printUrl,
            'folder_url'       => BASE_URL . '/patient-folder.php?id=' . $pid,
        ];
    }
}
