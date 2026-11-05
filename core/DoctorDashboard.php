<?php
/**
 * Doctor role dashboard — same-day queue, consult stats, weekly trend, surveillance preview.
 */

require_once __DIR__ . '/ReceptionQueue.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/PatientSearch.php';
require_once __DIR__ . '/HeatMap.php';
require_once __DIR__ . '/FollowUpReminder.php';

class DoctorDashboard
{
    private static function today(): string
    {
        return ReceptionQueue::today();
    }

    private static function doctorConsultedClause(): string
    {
        return "(
            TRIM(COALESCE(tr.healthcare_provider, '')) <> ''
            OR TRIM(COALESCE(tr.diagnosis, '')) <> ''
            OR TRIM(COALESCE(tr.medication_treatment, '')) <> ''
        )";
    }

    /** @return array<string, mixed> */
    public static function load(mysqli $conn): array
    {
        ReceptionQueue::ensureSchema($conn);
        WorkflowStatus::ensurePatientsStatusEnum($conn);

        $today = self::today();
        $stats = self::stats($conn, $today);
        $waiting = self::waitingPreview($conn, $today, 5);
        $recent = self::recentConsultations($conn, $today, 8);
        $weekly = self::weeklyConsultCounts($conn);
        $surveillance = self::miniSurveillance($conn);
        FollowUpReminder::ensureSchema($conn);
        $weekEnd = date('Y-m-d', strtotime('+7 days', strtotime($today)));
        $followUps = FollowUpReminder::duePreview($conn, $today, $weekEnd, 6);

        $firstWaiting = $waiting[0] ?? null;
        $primaryAction = [
            'label' => $firstWaiting ? 'Start Next Consultation' : 'Open Doctor Consultation',
            'url'   => $firstWaiting ? self::consultUrl($firstWaiting) : BASE_URL . '/workflow/queue.php',
            'icon'  => $firstWaiting ? 'bi-play-fill' : 'bi-clipboard2-pulse',
        ];

        return [
            'today'         => $today,
            'stats'         => $stats,
            'waiting'       => $waiting,
            'recent'        => $recent,
            'weekly'        => $weekly,
            'surveillance'  => $surveillance,
            'follow_ups'    => $followUps,
            'primaryAction' => $primaryAction,
        ];
    }

    /** @return array{todays_consults:int,waiting_in_queue:int,sent_to_pharmacy:int,completed_today:int} */
    private static function stats(mysqli $conn, string $today): array
    {
        $stats = [
            'todays_consults'   => 0,
            'waiting_in_queue'  => 0,
            'sent_to_pharmacy'  => 0,
            'completed_today'   => 0,
        ];

        $consulted = self::doctorConsultedClause();
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM treatment_records tr
             INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
             WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) = ?
               AND {$consulted}"
        );
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $stats['todays_consults'] = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        $statuses = WorkflowStatus::doctorQueueStatuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = 's' . str_repeat('s', count($statuses));
        $params = array_merge([$today], $statuses);

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt
             FROM patients p
             INNER JOIN reception_queue rq
                     ON rq.patient_id = p.id
                    AND rq.queue_date = ?
                    AND rq.desk_status NOT IN ('completed', 'cancelled')
             WHERE p.is_archived = 0 AND p.status IN ({$placeholders})"
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stats['waiting_in_queue'] = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        $pharmacyStatuses = [WORKFLOW_WAITING_FOR_PHARMACY, WORKFLOW_DISPENSING];
        $phPlaceholders = implode(',', array_fill(0, count($pharmacyStatuses), '?'));
        $phTypes = str_repeat('s', count($pharmacyStatuses)) . 's';
        $phParams = array_merge($pharmacyStatuses, [$today]);

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM patients p
             WHERE p.is_archived = 0
               AND p.status IN ({$phPlaceholders})
               AND DATE(p.updated_at) = ?"
        );
        $stmt->bind_param($phTypes, ...$phParams);
        $stmt->execute();
        $stats['sent_to_pharmacy'] = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM reception_queue
             WHERE queue_date = ? AND desk_status = 'completed'"
        );
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $stats['completed_today'] = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        $stmt->close();

        return $stats;
    }

    /** @return list<array<string, mixed>> */
    private static function waitingPreview(mysqli $conn, string $today, int $limit): array
    {
        $statuses = WorkflowStatus::doctorQueueStatuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = 's' . str_repeat('s', count($statuses)) . 'i';
        $params = array_merge([$today], $statuses, [$limit]);

        $sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                       p.sex, p.birth_date, p.residential_address, p.status,
                       rq.queue_number, rq.created_at AS arrival_at, rq.sent_at,
                       rq.consultation_id AS queue_consultation_id,
                       t.id AS treatment_id, t.consultation_types
                FROM patients p
                INNER JOIN reception_queue rq
                        ON rq.patient_id = p.id
                       AND rq.queue_date = ?
                       AND rq.desk_status NOT IN ('completed', 'cancelled')
                LEFT JOIN treatment_records t ON t.id = COALESCE(
                    NULLIF(rq.consultation_id, 0),
                    (SELECT id FROM treatment_records WHERE patient_id = p.id ORDER BY id DESC LIMIT 1)
                )
                WHERE p.is_archived = 0
                  AND p.status IN ({$placeholders})
                ORDER BY
                    p.date_encoded ASC,
                    CASE WHEN rq.queue_number IS NULL OR rq.queue_number = '' THEN 1 ELSE 0 END,
                    rq.queue_number ASC,
                    rq.created_at ASC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return array_map([self::class, 'formatWaitingRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    private static function recentConsultations(mysqli $conn, string $today, int $limit): array
    {
        $stmt = $conn->prepare(
            "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                    p.status, p.residential_address,
                    rq.queue_number, rq.sent_at, rq.created_at AS arrival_at, rq.desk_status,
                    tr.id AS treatment_id, tr.consultation_types, tr.date_of_consultation, tr.updated_at
             FROM reception_queue rq
             INNER JOIN patients p ON p.id = rq.patient_id AND p.is_archived = 0
             LEFT JOIN treatment_records tr ON tr.id = COALESCE(
                 NULLIF(rq.consultation_id, 0),
                 (SELECT id FROM treatment_records WHERE patient_id = p.id ORDER BY id DESC LIMIT 1)
             )
             WHERE rq.queue_date = ?
             ORDER BY rq.updated_at DESC, rq.created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('si', $today, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return array_map([self::class, 'formatRecentRow'], $rows);
    }

    /** @return list<array{label:string,short:string,count:int,is_today:bool}> */
    private static function weeklyConsultCounts(mysqli $conn): array
    {
        $consulted = self::doctorConsultedClause();
        $from = date('Y-m-d', strtotime('-6 days'));

        $stmt = $conn->prepare(
            "SELECT DATE(COALESCE(tr.date_of_consultation, tr.created_at)) AS day, COUNT(*) AS cnt
             FROM treatment_records tr
             INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
             WHERE DATE(COALESCE(tr.date_of_consultation, tr.created_at)) >= ?
               AND {$consulted}
             GROUP BY day"
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
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = [
                'label'    => date('D', strtotime($day)),
                'short'    => date('M j', strtotime($day)),
                'count'    => $byDay[$day] ?? 0,
                'is_today' => $day === $today,
            ];
        }

        return $out;
    }

    /** @return array{total_cases:int,affected_barangays:int,top_barangays:list<array{name:string,count:int,color:string}>} */
    private static function miniSurveillance(mysqli $conn): array
    {
        $to = self::today();
        $from = date('Y-m-d', strtotime('-6 days', strtotime($to)));
        $payload = HeatMap::build($conn, $from, $to);

        $barangays = $payload['barangays'] ?? [];
        usort($barangays, static fn ($a, $b) => ($b['total_cases'] ?? 0) <=> ($a['total_cases'] ?? 0));

        $top = [];
        foreach (array_slice($barangays, 0, 5) as $row) {
            $count = (int) ($row['total_cases'] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $top[] = [
                'name'  => (string) ($row['name'] ?? ''),
                'count' => $count,
                'color' => (string) ($row['color'] ?? '#1a6fc4'),
            ];
        }

        return [
            'total_cases'         => (int) ($payload['summary']['total_active_cases'] ?? 0),
            'affected_barangays'  => (int) ($payload['summary']['affected_barangays'] ?? 0),
            'top_barangays'       => $top,
            'date_from'           => $from,
            'date_to'             => $to,
        ];
    }

    /** @param array<string, mixed> $row */
    private static function formatWaitingRow(array $row): array
    {
        return [
            'patient_db_id'      => (int) $row['id'],
            'patient_id'         => (string) ($row['patient_id'] ?? ''),
            'patient_name'       => self::formatName($row),
            'queue_number'       => trim((string) ($row['queue_number'] ?? '')) ?: '—',
            'barangay'           => self::barangay($row),
            'arrival_label'      => self::timeLabel($row['sent_at'] ?? $row['arrival_at'] ?? null),
            'consultation_type'  => self::consultationType($row),
            'status'             => WorkflowStatus::normalize($row['status'] ?? ''),
            'consult_url'        => self::consultUrl($row),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function formatRecentRow(array $row): array
    {
        $status = WorkflowStatus::normalize($row['status'] ?? '');
        $desk = (string) ($row['desk_status'] ?? '');

        return [
            'patient_db_id'     => (int) $row['id'],
            'patient_id'        => (string) ($row['patient_id'] ?? ''),
            'patient_name'      => self::formatName($row),
            'queue_number'      => trim((string) ($row['queue_number'] ?? '')) ?: '—',
            'consult_date'      => self::dateLabel($row['date_of_consultation'] ?? null),
            'time_label'        => self::timeLabel($row['sent_at'] ?? $row['arrival_at'] ?? null),
            'status'            => $status,
            'status_badge'      => WorkflowStatus::badgeHtml($status),
            'consultation_type' => self::consultationType($row),
            'folder_url'        => BASE_URL . '/patient-folder.php?id=' . (int) $row['id'],
            'desk_status'       => $desk,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function consultUrl(array $row): string
    {
        $patientId = (int) ($row['patient_db_id'] ?? $row['id'] ?? 0);
        $cid = (int) ($row['queue_consultation_id'] ?? $row['treatment_id'] ?? 0);
        $url = BASE_URL . '/doctor/consultation.php?id=' . $patientId;
        if ($cid > 0) {
            $url .= '&consultation_id=' . $cid;
        }
        return $url;
    }

    /** @param array<string, mixed> $row */
    private static function formatName(array $row): string
    {
        $middle = trim((string) ($row['middle_name'] ?? ''));
        $suffix = trim((string) ($row['suffix'] ?? ''));
        $name = trim(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '')
            . ($middle !== '' ? ' ' . $middle : ''));
        if ($suffix !== '') {
            $name .= ' ' . $suffix;
        }
        return $name !== ',' ? $name : '—';
    }

    /** @param array<string, mixed> $row */
    private static function barangay(array $row): string
    {
        $brgy = PatientSearch::extractBarangay($row['residential_address'] ?? null);
        if ($brgy !== '' && $brgy !== '—') {
            return $brgy;
        }
        $addr = trim((string) ($row['residential_address'] ?? ''));
        return $addr !== '' ? $addr : '—';
    }

    /** @param array<string, mixed> $row */
    private static function consultationType(array $row): string
    {
        $raw = $row['consultation_types'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded) {
                return implode(', ', array_map('strval', $decoded));
            }
            return $raw;
        }
        if (is_array($raw) && $raw) {
            return implode(', ', array_map('strval', $raw));
        }
        return 'General Consultation';
    }

    private static function timeLabel(?string $ts): string
    {
        if (!$ts) {
            return '—';
        }
        return date('g:i A', strtotime($ts));
    }

    private static function dateLabel(?string $date): string
    {
        if (!$date) {
            return '—';
        }
        return date('M d, Y', strtotime($date));
    }
}
