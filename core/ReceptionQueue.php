<?php
/**
 * Reception desk queue — Receptionist role only.
 * Flow: IT approve → Waiting → Serving (→ Doctor) → Completed / Skip / Cancelled
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/PatientSearch.php';

class ReceptionQueue
{
    public const STATUS_WAITING   = 'waiting';
    public const STATUS_SERVING   = 'serving';
    public const STATUS_SKIPPED   = 'skipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALL_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_SERVING,
        self::STATUS_SKIPPED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** Ensure table + migrate legacy columns (safe for local XAMPP). */
    public static function ensureSchema(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS reception_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                queue_date DATE NOT NULL,
                queue_number VARCHAR(16) DEFAULT NULL,
                desk_status VARCHAR(32) NOT NULL DEFAULT 'waiting',
                called_at DATETIME DEFAULT NULL,
                serving_at DATETIME DEFAULT NULL,
                skipped_at DATETIME DEFAULT NULL,
                done_at DATETIME DEFAULT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                sent_at DATETIME DEFAULT NULL,
                notes VARCHAR(255) DEFAULT NULL,
                assigned_doctor_id INT DEFAULT NULL,
                lane_type VARCHAR(32) NOT NULL DEFAULT 'regular',
                created_by INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_reception_patient_day (patient_id, queue_date),
                INDEX idx_reception_day_status (queue_date, desk_status),
                INDEX idx_reception_day_number (queue_date, queue_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::ensureColumn($conn, 'serving_at', 'DATETIME NULL AFTER called_at');
        self::ensureColumn($conn, 'cancelled_at', 'DATETIME NULL AFTER done_at');
        self::ensureColumn($conn, 'assigned_doctor_id', 'INT NULL AFTER notes');
        self::ensureColumn($conn, 'lane_type', "VARCHAR(32) NOT NULL DEFAULT 'regular' AFTER assigned_doctor_id");
        self::ensureColumn($conn, 'consultation_id', 'INT NULL AFTER patient_id');

        @$conn->query("ALTER TABLE reception_queue MODIFY COLUMN queue_number VARCHAR(16) NULL");
        @$conn->query("ALTER TABLE reception_queue MODIFY COLUMN desk_status VARCHAR(32) NOT NULL DEFAULT 'waiting'");

        @$conn->query("UPDATE reception_queue SET desk_status = 'serving' WHERE desk_status = 'called'");
        @$conn->query("UPDATE reception_queue SET desk_status = 'completed' WHERE desk_status IN ('done','sent_to_doctor')");
        @$conn->query(
            "UPDATE reception_queue
             SET queue_number = CONCAT('A', LPAD(queue_number, 3, '0'))
             WHERE queue_number REGEXP '^[0-9]+$'"
        );
    }

    private static function ensureColumn(mysqli $conn, string $column, string $definition): void
    {
        $db = '';
        $r = $conn->query('SELECT DATABASE() AS db');
        if ($r && ($row = $r->fetch_assoc())) {
            $db = (string) $row['db'];
        }
        if ($db === '') {
            return;
        }
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'reception_queue\' AND COLUMN_NAME = ?'
        );
        $stmt->bind_param('ss', $db, $column);
        $stmt->execute();
        $exists = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        if ($exists === 0) {
            @$conn->query("ALTER TABLE reception_queue ADD COLUMN {$column} {$definition}");
        }
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }

    /**
     * Whether the latest treatment already has doctor clinical content
     * (IT approve → print after consult; → reception before consult).
     */
    public static function hasCompletedClinicalVisit(mysqli $conn, int $patientDbId): bool
    {
        $stmt = $conn->prepare(
            'SELECT diagnosis, medication_treatment, healthcare_provider
             FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        foreach (['diagnosis', 'medication_treatment', 'healthcare_provider'] as $col) {
            if (trim((string) ($row[$col] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** Create/refresh today's waiting ticket when patient becomes Pending Receptionist. */
    public static function ensureWaitingEntry(mysqli $conn, int $patientDbId, ?int $userId = null): void
    {
        self::ensureSchema($conn);
        $day = self::today();

        $stmt = $conn->prepare(
            'SELECT id, desk_status, queue_number FROM reception_queue WHERE patient_id = ? AND queue_date = ? LIMIT 1'
        );
        $stmt->bind_param('is', $patientDbId, $day);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            if (in_array($existing['desk_status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
                $id = (int) $existing['id'];
                $waiting = self::STATUS_WAITING;
                $upd = $conn->prepare(
                    'UPDATE reception_queue
                     SET desk_status = ?, skipped_at = NULL, done_at = NULL, cancelled_at = NULL,
                         sent_at = NULL, serving_at = NULL, called_at = NULL
                     WHERE id = ?'
                );
                $upd->bind_param('si', $waiting, $id);
                $upd->execute();
                $upd->close();
                if (empty($existing['queue_number'])) {
                    self::assignNumber($conn, $id);
                }
            } elseif ($existing['desk_status'] === self::STATUS_SKIPPED) {
                $id = (int) $existing['id'];
                $waiting = self::STATUS_WAITING;
                $upd = $conn->prepare(
                    'UPDATE reception_queue SET desk_status = ?, skipped_at = NULL WHERE id = ?'
                );
                $upd->bind_param('si', $waiting, $id);
                $upd->execute();
                $upd->close();
            } elseif (empty($existing['queue_number'])) {
                self::assignNumber($conn, (int) $existing['id']);
            }
            return;
        }

        $waiting = self::STATUS_WAITING;
        $lane = 'regular';
        if ($userId) {
            $ins = $conn->prepare(
                'INSERT INTO reception_queue (patient_id, queue_date, desk_status, lane_type, created_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->bind_param('isssi', $patientDbId, $day, $waiting, $lane, $userId);
        } else {
            $ins = $conn->prepare(
                'INSERT INTO reception_queue (patient_id, queue_date, desk_status, lane_type)
                 VALUES (?, ?, ?, ?)'
            );
            $ins->bind_param('isss', $patientDbId, $day, $waiting, $lane);
        }
        $ins->execute();
        $newId = (int) $conn->insert_id;
        $ins->close();

        if ($newId > 0) {
            self::assignNumber($conn, $newId);
        }
    }

    /** Link today's queue ticket to the consultation the doctor should open. */
    public static function linkConsultation(mysqli $conn, int $patientDbId, int $consultationId): void
    {
        if ($consultationId <= 0) {
            return;
        }
        self::ensureSchema($conn);
        $day = self::today();
        $stmt = $conn->prepare(
            'UPDATE reception_queue SET consultation_id = ? WHERE patient_id = ? AND queue_date = ?'
        );
        $stmt->bind_param('iis', $consultationId, $patientDbId, $day);
        $stmt->execute();
        $stmt->close();
    }

    public static function latestConsultationId(mysqli $conn, int $patientDbId): int
    {
        $stmt = $conn->prepare(
            'SELECT id FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['id'] : 0;
    }

    /** Kept for Workflow::forward_to_doctor compatibility — keeps desk as serving. */
    public static function markSentToDoctor(mysqli $conn, int $patientDbId): void
    {
        self::ensureSchema($conn);
        $day = self::today();
        $serving = self::STATUS_SERVING;
        $stmt = $conn->prepare(
            'UPDATE reception_queue
             SET desk_status = IF(desk_status IN (\'completed\',\'cancelled\'), desk_status, ?),
                 sent_at = NOW(),
                 serving_at = COALESCE(serving_at, NOW()),
                 called_at = COALESCE(called_at, NOW())
             WHERE patient_id = ? AND queue_date = ?'
        );
        $stmt->bind_param('sis', $serving, $patientDbId, $day);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Doctor submitted consultation — leave the temporary daily queue.
     * Does NOT delete the patient or treatment record (Patient Folder remains).
     */
    public static function markConsultationDone(mysqli $conn, int $patientDbId): void
    {
        self::ensureSchema($conn);
        $day = self::today();
        $done = self::STATUS_COMPLETED;
        $stmt = $conn->prepare(
            'UPDATE reception_queue
             SET desk_status = ?, done_at = NOW(), sent_at = COALESCE(sent_at, NOW())
             WHERE patient_id = ? AND queue_date = ?
               AND desk_status NOT IN (\'cancelled\')'
        );
        $stmt->bind_param('sis', $done, $patientDbId, $day);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * List queue rows for a date with optional filters.
     *
     * @param array{
     *   date?:string,status?:string,barangay?:string,
     *   patient_id?:string,name?:string,active_only?:bool
     * } $filters
     */
    public static function listQueue(mysqli $conn, array $filters = []): array
    {
        self::ensureSchema($conn);
        $day = $filters['date'] ?? self::today();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = self::today();
        }

        self::syncPendingTickets($conn, $day);

        $sql = "SELECT rq.id AS queue_id, rq.queue_number, rq.desk_status, rq.called_at, rq.serving_at,
                       rq.skipped_at, rq.done_at, rq.cancelled_at, rq.created_at AS arrival_at, rq.updated_at,
                       rq.assigned_doctor_id, rq.lane_type, rq.queue_date,
                       p.id AS patient_db_id, p.patient_id, p.first_name, p.middle_name, p.last_name,
                       p.sex, p.birth_date, p.residential_address, p.status AS patient_status,
                       u.full_name AS assigned_doctor_name
                FROM reception_queue rq
                INNER JOIN patients p ON p.id = rq.patient_id
                LEFT JOIN users u ON u.id = rq.assigned_doctor_id
                WHERE rq.queue_date = ?
                  AND p.is_archived = 0";

        $types = 's';
        $params = [$day];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, self::ALL_STATUSES, true)) {
            $sql .= ' AND rq.desk_status = ?';
            $types .= 's';
            $params[] = $status;
        } elseif (!empty($filters['active_only'])) {
            $sql .= " AND rq.desk_status IN ('waiting','serving','skipped')";
        }

        $patientId = trim((string) ($filters['patient_id'] ?? ''));
        if ($patientId !== '') {
            $sql .= ' AND p.patient_id LIKE ?';
            $types .= 's';
            $params[] = '%' . $patientId . '%';
        }

        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $like = '%' . $name . '%';
            $sql .= ' AND (p.first_name LIKE ? OR p.middle_name LIKE ? OR p.last_name LIKE ?
                      OR CONCAT(p.last_name, \', \', p.first_name) LIKE ?
                      OR CONCAT(p.first_name, \' \', p.last_name) LIKE ?)';
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $barangay = trim((string) ($filters['barangay'] ?? ''));
        if ($barangay !== '') {
            $sql .= ' AND p.residential_address LIKE ?';
            $types .= 's';
            $params[] = '%' . $barangay . '%';
        }

        $sql .= " ORDER BY
                    CASE rq.desk_status
                        WHEN 'serving' THEN 0
                        WHEN 'waiting' THEN 1
                        WHEN 'skipped' THEN 2
                        WHEN 'completed' THEN 3
                        WHEN 'cancelled' THEN 4
                        ELSE 5
                    END,
                    CASE WHEN rq.queue_number IS NULL OR rq.queue_number = '' THEN 1 ELSE 0 END,
                    rq.queue_number ASC,
                    rq.created_at ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return array_map([self::class, 'formatRow'], $rows);
    }

    /** @deprecated Use listQueue() */
    public static function listToday(mysqli $conn): array
    {
        return self::listQueue($conn, ['date' => self::today()]);
    }

    private static function syncPendingTickets(mysqli $conn, string $day): void
    {
        $pending = WORKFLOW_PENDING_RECEPTIONIST;
        $sync = $conn->prepare(
            "SELECT p.id FROM patients p
             LEFT JOIN reception_queue rq ON rq.patient_id = p.id AND rq.queue_date = ?
             WHERE p.is_archived = 0 AND p.status = ? AND rq.id IS NULL"
        );
        $sync->bind_param('ss', $day, $pending);
        $sync->execute();
        $missing = $sync->get_result()->fetch_all(MYSQLI_ASSOC);
        $sync->close();
        foreach ($missing as $row) {
            self::ensureWaitingEntry($conn, (int) $row['id'], null);
        }
    }

    public static function dashboardStats(mysqli $conn, ?string $day = null): array
    {
        self::ensureSchema($conn);
        $day = $day ?: self::today();
        self::syncPendingTickets($conn, $day);

        $stats = [
            'waiting'          => 0,
            'serving'          => 0,
            'completed'        => 0,
            'skipped'          => 0,
            'cancelled'        => 0,
            'avg_wait_minutes' => null,
            'avg_wait_label'   => '—',
        ];

        $stmt = $conn->prepare(
            'SELECT desk_status, COUNT(*) AS cnt
             FROM reception_queue WHERE queue_date = ? GROUP BY desk_status'
        );
        $stmt->bind_param('s', $day);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $row) {
            $key = $row['desk_status'];
            if (isset($stats[$key])) {
                $stats[$key] = (int) $row['cnt'];
            }
        }

        $avgStmt = $conn->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(serving_at, called_at, done_at))) AS avg_mins
             FROM reception_queue
             WHERE queue_date = ?
               AND desk_status IN ('serving','completed','skipped')
               AND COALESCE(serving_at, called_at, done_at) IS NOT NULL"
        );
        $avgStmt->bind_param('s', $day);
        $avgStmt->execute();
        $avg = $avgStmt->get_result()->fetch_assoc();
        $avgStmt->close();

        if ($avg && $avg['avg_mins'] !== null) {
            $mins = (int) round((float) $avg['avg_mins']);
            $stats['avg_wait_minutes'] = $mins;
            if ($mins < 60) {
                $stats['avg_wait_label'] = $mins . ' min';
            } else {
                $h = intdiv($mins, 60);
                $m = $mins % 60;
                $stats['avg_wait_label'] = $h . 'h ' . $m . 'm';
            }
        }

        return $stats;
    }

    public static function formatRow(array $row): array
    {
        $middle = trim((string) ($row['middle_name'] ?? ''));
        $formal = trim($row['last_name'] . ', ' . trim($row['first_name'] . ($middle !== '' ? ' ' . $middle : '')));
        $age = self::ageYears($row['birth_date'] ?? null);
        $status = $row['desk_status'];

        return [
            'queue_id'           => (int) $row['queue_id'],
            'queue_number'       => $row['queue_number'] ?: null,
            'desk_status'        => $status,
            'desk_status_label'  => self::deskStatusLabel($status),
            'patient_db_id'      => (int) $row['patient_db_id'],
            'patient_id'         => $row['patient_id'],
            'patient_name'       => $formal,
            'sex'                => $row['sex'] ?: '—',
            'age'                => $age !== null ? (string) $age : '—',
            'barangay'           => PatientSearch::extractBarangay($row['residential_address'] ?? null),
            'arrival_at'         => $row['arrival_at'] ?? null,
            'queued_at'          => $row['arrival_at'] ?? null,
            'called_at'          => $row['called_at'] ?? null,
            'serving_at'         => $row['serving_at'] ?? null,
            'patient_status'     => $row['patient_status'] ?? '',
            'assigned_doctor_id' => isset($row['assigned_doctor_id']) ? (int) $row['assigned_doctor_id'] : null,
            'assigned_doctor'    => trim((string) ($row['assigned_doctor_name'] ?? '')) ?: '—',
            'lane_type'          => $row['lane_type'] ?? 'regular',
            'queue_date'         => $row['queue_date'] ?? null,
            'view_url'           => BASE_URL . '/patient-folder.php?id=' . (int) $row['patient_db_id'],
        ];
    }

    public static function deskStatusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_WAITING   => 'Waiting',
            self::STATUS_SERVING   => 'Serving',
            self::STATUS_SKIPPED   => 'Skipped',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function ageYears(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }
        try {
            $dob = new DateTimeImmutable($birthDate);
            $today = new DateTimeImmutable('today');
            return (int) $dob->diff($today)->y;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function getEntry(mysqli $conn, int $queueId): ?array
    {
        self::ensureSchema($conn);
        $stmt = $conn->prepare(
            'SELECT rq.*, p.status AS patient_status, p.patient_id, p.first_name, p.last_name
             FROM reception_queue rq
             INNER JOIN patients p ON p.id = rq.patient_id
             WHERE rq.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** Auto-generate daily queue code A001, A002, … (resets each day). */
    public static function assignNumber(mysqli $conn, int $queueId): array
    {
        $entry = self::getEntry($conn, $queueId);
        if (!$entry) {
            throw new RuntimeException('Queue entry not found.');
        }
        if (!empty($entry['queue_number'])) {
            return $entry;
        }

        $day = $entry['queue_date'];
        $stmt = $conn->prepare(
            "SELECT queue_number FROM reception_queue
             WHERE queue_date = ? AND queue_number IS NOT NULL AND queue_number <> ''"
        );
        $stmt->bind_param('s', $day);
        $stmt->execute();
        $nums = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $max = 0;
        foreach ($nums as $n) {
            if (preg_match('/(\d+)$/', (string) $n['queue_number'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        $code = 'A' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);

        $upd = $conn->prepare(
            "UPDATE reception_queue SET queue_number = ? WHERE id = ? AND (queue_number IS NULL OR queue_number = '')"
        );
        $upd->bind_param('si', $code, $queueId);
        $upd->execute();
        $upd->close();

        $entry['queue_number'] = $code;
        return $entry;
    }

    /**
     * Call / mark serving. Forwards to Doctor when still Pending Receptionist.
     *
     * @return array{entry: array, forward: ?array}
     */
    public static function markServing(mysqli $conn, int $queueId, array $user, bool $forwardToDoctor = true): array
    {
        $entry = self::getEntry($conn, $queueId);
        if (!$entry) {
            throw new RuntimeException('Queue entry not found.');
        }
        if (in_array($entry['desk_status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new RuntimeException('This patient is already ' . $entry['desk_status'] . '.');
        }

        if (empty($entry['queue_number'])) {
            $entry = self::assignNumber($conn, $queueId);
        }

        $serving = self::STATUS_SERVING;

        $upd = $conn->prepare(
            'UPDATE reception_queue
             SET desk_status = ?, called_at = COALESCE(called_at, NOW()), serving_at = NOW(), skipped_at = NULL
             WHERE id = ?'
        );
        $upd->bind_param('si', $serving, $queueId);
        $upd->execute();
        $upd->close();

        $result = [
            'entry'   => self::getEntry($conn, $queueId),
            'forward' => null,
        ];

        if (
            $forwardToDoctor
            && ($entry['patient_status'] ?? '') === WORKFLOW_PENDING_RECEPTIONIST
        ) {
            require_once __DIR__ . '/Workflow.php';
            $result['forward'] = Workflow::execute(
                $conn,
                'forward_to_doctor',
                (int) $entry['patient_id'],
                $user
            );
            $keep = $conn->prepare(
                'UPDATE reception_queue SET desk_status = ?, serving_at = COALESCE(serving_at, NOW()) WHERE id = ?'
            );
            $keep->bind_param('si', $serving, $queueId);
            $keep->execute();
            $keep->close();
            $result['entry'] = self::getEntry($conn, $queueId);
        }

        return $result;
    }

    public static function callPatient(mysqli $conn, int $queueId, array $user): array
    {
        return self::markServing($conn, $queueId, $user, true);
    }

    public static function callNext(mysqli $conn, array $user): ?array
    {
        self::ensureSchema($conn);
        $day = self::today();
        $waiting = self::STATUS_WAITING;

        $stmt = $conn->prepare(
            "SELECT id FROM reception_queue
             WHERE queue_date = ? AND desk_status = ?
             ORDER BY
                CASE WHEN queue_number IS NULL OR queue_number = '' THEN 1 ELSE 0 END,
                queue_number ASC,
                created_at ASC
             LIMIT 1"
        );
        $stmt->bind_param('ss', $day, $waiting);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return self::markServing($conn, (int) $row['id'], $user, true);
    }

    public static function markSkipped(mysqli $conn, int $queueId): array
    {
        $entry = self::getEntry($conn, $queueId);
        if (!$entry) {
            throw new RuntimeException('Queue entry not found.');
        }
        $skipped = self::STATUS_SKIPPED;
        $upd = $conn->prepare(
            'UPDATE reception_queue SET desk_status = ?, skipped_at = NOW() WHERE id = ?'
        );
        $upd->bind_param('si', $skipped, $queueId);
        $upd->execute();
        $upd->close();
        $entry['desk_status'] = $skipped;
        return $entry;
    }

    public static function markCompleted(mysqli $conn, int $queueId): array
    {
        $entry = self::getEntry($conn, $queueId);
        if (!$entry) {
            throw new RuntimeException('Queue entry not found.');
        }
        $completed = self::STATUS_COMPLETED;
        $upd = $conn->prepare(
            'UPDATE reception_queue SET desk_status = ?, done_at = NOW() WHERE id = ?'
        );
        $upd->bind_param('si', $completed, $queueId);
        $upd->execute();
        $upd->close();
        $entry['desk_status'] = $completed;
        return $entry;
    }

    public static function markCancelled(mysqli $conn, int $queueId): array
    {
        $entry = self::getEntry($conn, $queueId);
        if (!$entry) {
            throw new RuntimeException('Queue entry not found.');
        }
        $cancelled = self::STATUS_CANCELLED;
        $upd = $conn->prepare(
            'UPDATE reception_queue SET desk_status = ?, cancelled_at = NOW() WHERE id = ?'
        );
        $upd->bind_param('si', $cancelled, $queueId);
        $upd->execute();
        $upd->close();
        $entry['desk_status'] = $cancelled;
        return $entry;
    }

    /** @deprecated */
    public static function markDoneAtReception(mysqli $conn, int $queueId): array
    {
        return self::markCompleted($conn, $queueId);
    }
}
