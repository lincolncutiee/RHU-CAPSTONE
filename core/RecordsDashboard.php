<?php

/**
 * Records Office dashboard — encoding workload (drafts, returned, output).
 */

require_once __DIR__ . '/ReceptionQueue.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/ItPrintForms.php';
require_once __DIR__ . '/PatientSearch.php';

class RecordsDashboard
{
    private static function today(): string
    {
        return ReceptionQueue::today();
    }

    /** @return array<string, mixed> */
    public static function load(mysqli $conn): array
    {
        WorkflowStatus::ensurePatientsStatusEnum($conn);
        $today = self::today();
        $stats = self::stats($conn, $today);
        $workQueue = self::workQueuePreview($conn, 12);
        $recentEncoded = self::recentEncoded($conn, 8);
        $weekly = self::weeklyEncodingCounts($conn);

        $firstAction = self::firstActionable($workQueue);
        $primaryAction = [
            'label' => $firstAction['label'] ?? 'New Patient Encoding',
            'url'   => $firstAction['url'] ?? (BASE_URL . '/forms/doh-form.php'),
            'icon'  => $firstAction['icon'] ?? 'bi-person-plus',
        ];

        return [
            'today'          => $today,
            'stats'          => $stats,
            'work_queue'     => $workQueue,
            'recent_encoded' => $recentEncoded,
            'weekly'         => $weekly,
            'primaryAction'  => $primaryAction,
        ];
    }

    /** @return array{draft:int,returned:int,rejected:int,encoded_today:int,encoded_week:int,waiting_for_doctor:int} */
    private static function stats(mysqli $conn, string $today): array
    {
        $stats = [
            'draft'               => 0,
            'returned'            => 0,
            'rejected'            => 0,
            'encoded_today'       => 0,
            'encoded_week'        => 0,
            'waiting_for_doctor'  => 0,
        ];

        $draft = WORKFLOW_DRAFT;
        $returned = WORKFLOW_RETURNED_FOR_CORRECTION;
        $rejected = WORKFLOW_REJECTED;
        $waiting = WORKFLOW_WAITING_FOR_DOCTOR;

        $st = $conn->prepare(
            'SELECT
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft_cnt,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS returned_cnt,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS rejected_cnt,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS waiting_cnt
             FROM patients WHERE is_archived = 0'
        );
        $st->bind_param('ssss', $draft, $returned, $rejected, $waiting);
        $st->execute();
        $row = $st->get_result()->fetch_assoc() ?: [];
        $st->close();

        $stats['draft'] = (int) ($row['draft_cnt'] ?? 0);
        $stats['returned'] = (int) ($row['returned_cnt'] ?? 0);
        $stats['rejected'] = (int) ($row['rejected_cnt'] ?? 0);
        $stats['waiting_for_doctor'] = (int) ($row['waiting_cnt'] ?? 0);

        $st = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND DATE(date_encoded) = ?'
        );
        $st->bind_param('s', $today);
        $st->execute();
        $stats['encoded_today'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        $weekFrom = date('Y-m-d', strtotime('-6 days', strtotime($today)));
        $st = $conn->prepare(
            'SELECT COUNT(*) AS c FROM patients WHERE is_archived = 0 AND DATE(date_encoded) >= ?'
        );
        $st->bind_param('s', $weekFrom);
        $st->execute();
        $stats['encoded_week'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();

        return $stats;
    }

    /** @return list<array<string, mixed>> */
    private static function workQueuePreview(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(30, $limit));
        $statuses = WorkflowStatus::encoderEditableStatuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = str_repeat('s', count($statuses));

        $returnedEsc = $conn->real_escape_string(WORKFLOW_RETURNED_FOR_CORRECTION);
        $draftEsc = $conn->real_escape_string(WORKFLOW_DRAFT);
        $rejectedEsc = $conn->real_escape_string(WORKFLOW_REJECTED);

        $sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                       p.status, p.date_encoded, p.updated_at, p.residential_address
                FROM patients p
                WHERE p.is_archived = 0 AND p.status IN ($placeholders)
                ORDER BY
                    CASE
                        WHEN p.status = '{$returnedEsc}' THEN 0
                        WHEN p.status = '{$draftEsc}' THEN 1
                        WHEN p.status = '{$rejectedEsc}' THEN 2
                        ELSE 3
                    END,
                    COALESCE(p.updated_at, p.date_encoded) DESC
                LIMIT {$limit}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$statuses);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $today = self::today();
        return array_map(static fn (array $r) => self::mapWorkRow($r, $today), $rows);
    }

    /** @return list<array<string, mixed>> */
    private static function recentEncoded(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(20, $limit));
        $weekFrom = date('Y-m-d', strtotime('-7 days'));

        $stmt = $conn->prepare(
            "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                    p.status, p.date_encoded, p.residential_address
             FROM patients p
             WHERE p.is_archived = 0 AND DATE(p.date_encoded) >= ?
             ORDER BY p.date_encoded DESC, p.id DESC
             LIMIT {$limit}"
        );
        $stmt->bind_param('s', $weekFrom);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $labels = WorkflowStatus::filterOptions();
        $out = [];
        foreach ($rows as $r) {
            $status = WorkflowStatus::normalize((string) ($r['status'] ?? ''));
            $encoded = $r['date_encoded'] ?? null;
            $out[] = [
                'patient_id'   => (string) ($r['patient_id'] ?? ''),
                'patient_name' => ItPrintForms::formatPatientName($r),
                'status_label' => $labels[$status] ?? $status,
                'badge_class'  => WorkflowStatus::badgeClass($status),
                'encoded_label'=> $encoded ? date('M j, Y', strtotime((string) $encoded)) : '—',
                'folder_url'   => BASE_URL . '/patient-folder.php?id=' . (int) $r['id'],
                'encode_url'   => BASE_URL . '/forms/doh-form.php?id=' . (int) $r['id'],
            ];
        }
        return $out;
    }

    /** @return list<array{label:string,short:string,count:int,is_today:bool}> */
    private static function weeklyEncodingCounts(mysqli $conn): array
    {
        $from = date('Y-m-d', strtotime('-6 days'));
        $stmt = $conn->prepare(
            'SELECT DATE(date_encoded) AS day, COUNT(*) AS cnt
             FROM patients
             WHERE is_archived = 0 AND date_encoded IS NOT NULL AND DATE(date_encoded) >= ?
             GROUP BY day'
        );
        $stmt->bind_param('s', $from);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['cnt'];
        }

        $today = self::today();
        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days", strtotime($today)));
            $out[] = [
                'label'    => date('D', strtotime($d)),
                'short'    => date('M j', strtotime($d)),
                'count'    => $byDay[$d] ?? 0,
                'is_today' => $d === $today,
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $r */
    private static function mapWorkRow(array $r, string $today): array
    {
        $status = WorkflowStatus::normalize((string) ($r['status'] ?? ''));
        $labels = WorkflowStatus::filterOptions();
        $touchRaw = $r['updated_at'] ?? $r['date_encoded'] ?? null;
        $touchDate = $touchRaw ? date('Y-m-d', strtotime((string) $touchRaw)) : '';
        $isToday = $touchDate === $today;
        $patientId = (int) ($r['id'] ?? 0);

        $badgeClass = match ($status) {
            WORKFLOW_RETURNED_FOR_CORRECTION => 'returned',
            WORKFLOW_DRAFT                   => 'draft',
            WORKFLOW_REJECTED                => 'rejected',
            default                          => 'muted',
        };

        return [
            'patient_id'     => (string) ($r['patient_id'] ?? ''),
            'patient_name'   => ItPrintForms::formatPatientName($r),
            'barangay'       => PatientSearch::extractBarangay((string) ($r['residential_address'] ?? '')),
            'status'         => $status,
            'badge_label'    => $labels[$status] ?? $status,
            'badge_class'    => $badgeClass,
            'updated_label'  => $touchRaw ? date('M j, g:i A', strtotime((string) $touchRaw)) : '—',
            'is_today'       => $isToday,
            'encode_url'     => BASE_URL . '/forms/doh-form.php?id=' . $patientId,
            'folder_url'     => BASE_URL . '/patient-folder.php?id=' . $patientId,
            'action'         => 'encode',
        ];
    }

    /** @param list<array<string, mixed>> $queue @return array{label:string,url:string,icon:string}|null */
    private static function firstActionable(array $queue): ?array
    {
        foreach ($queue as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === WORKFLOW_RETURNED_FOR_CORRECTION) {
                return [
                    'label' => 'Fix Returned Record',
                    'url'   => (string) $row['encode_url'],
                    'icon'  => 'bi-arrow-return-left',
                ];
            }
        }
        foreach ($queue as $row) {
            if (($row['status'] ?? '') === WORKFLOW_DRAFT) {
                return [
                    'label' => 'Continue Draft Encoding',
                    'url'   => (string) $row['encode_url'],
                    'icon'  => 'bi-pencil-square',
                ];
            }
        }
        return null;
    }
}
