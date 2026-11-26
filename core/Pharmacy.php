<?php
/**
 * Pharmacy queue & dispensing — uses consultation_medications (no duplicate Rx rows).
 * Dispense metadata is stored on treatment_records.it_data.pharmacy.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/WorkflowStatus.php';
require_once __DIR__ . '/ClinicalMaster.php';
require_once __DIR__ . '/Consultation.php';
require_once __DIR__ . '/ReceptionQueue.php';

class Pharmacy
{
    public static function ensureSchema(mysqli $conn): void
    {
        WorkflowStatus::ensurePatientsStatusEnum($conn);
        ClinicalMaster::ensureSchema($conn);

        // Role + demo pharmacist (password — same as other demo accounts)
        $conn->query(
            "INSERT INTO roles (slug, name, description)
             SELECT 'pharmacy', 'Pharmacy', 'Dispenses medicines from doctor prescriptions'
             WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'pharmacy')"
        );
        $conn->query(
            "INSERT INTO users (role_id, username, password_hash, full_name, email)
             SELECT r.id, 'pharmacy',
                    '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                    'RHU Pharmacist', 'pharmacy@rhu.local'
             FROM roles r
             WHERE r.slug = 'pharmacy'
               AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'pharmacy')"
        );

        // Repair corrupted demo hash (e.g. shell $ expansion) without changing custom passwords.
        $demoHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
        $uname = 'pharmacy';
        $stmt->bind_param('s', $uname);
        $stmt->execute();
        $pharm = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($pharm && !password_verify('password', (string) $pharm['password_hash'])) {
            $id = (int) $pharm['id'];
            $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND username = ?');
            $stmt->bind_param('sis', $demoHash, $id, $uname);
            $stmt->execute();
            $stmt->close();
        }
    }

    /** @return list<string> */
    public static function queueStatuses(): array
    {
        return [
            WORKFLOW_WAITING_FOR_PHARMACY,
            WORKFLOW_DISPENSING,
        ];
    }

    /**
     * Patients still with the pharmacist (Waiting for Pharmacy / Dispensing).
     *
     * @param array{status?:string,search?:string,sort?:string}|string|null $filters
     * @return list<array<string, mixed>>
     */
    public static function listQueue(mysqli $conn, $filters = null): array
    {
        self::ensureSchema($conn);
        ReceptionQueue::ensureSchema($conn);

        // Backward compatible: listQueue($conn, 'Waiting for Pharmacy')
        if (is_string($filters) || $filters === null) {
            $filters = ['status' => (string) ($filters ?? '')];
        }

        $statusFilter = trim((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = trim((string) ($filters['sort'] ?? 'newest'));

        $statuses = self::queueStatuses();
        if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
            $statuses = [$statusFilter];
        } else {
            $statuses = [WORKFLOW_WAITING_FOR_PHARMACY];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = str_repeat('s', count($statuses));
        $params = $statuses;

        $sql = "SELECT p.id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.sex,
                       p.birth_date, p.status, p.contact_number, p.residential_address,
                       tr.id AS consultation_id,
                       tr.date_of_consultation, tr.consultation_time, tr.consultation_period,
                       tr.healthcare_provider, tr.attending_provider, tr.it_data, tr.created_at AS consult_created_at,
                       rq.queue_number,
                       (
                           SELECT COUNT(*) FROM consultation_medications cm
                           WHERE cm.consultation_id = tr.id
                       ) AS medication_count
                FROM patients p
                INNER JOIN treatment_records tr
                    ON tr.id = (
                        SELECT tr2.id FROM treatment_records tr2
                        WHERE tr2.patient_id = p.id
                        ORDER BY COALESCE(tr2.date_of_consultation, DATE(tr2.created_at)) DESC, tr2.id DESC
                        LIMIT 1
                    )
                LEFT JOIN reception_queue rq
                    ON rq.patient_id = p.id
                   AND rq.queue_date = CURDATE()
                WHERE p.is_archived = 0
                  AND p.status IN ({$placeholders})";

        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (
                p.patient_id LIKE ?
                OR p.first_name LIKE ?
                OR p.last_name LIKE ?
                OR p.middle_name LIKE ?
                OR CONCAT(p.last_name, ', ', p.first_name) LIKE ?
                OR CONCAT(p.first_name, ' ', p.last_name) LIKE ?
                OR CAST(rq.queue_number AS CHAR) LIKE ?
                OR CONCAT('P-', LPAD(tr.id, 4, '0')) LIKE ?
            )";
            $types .= str_repeat('s', 8);
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $orderSql = match ($sort) {
            'oldest' => "COALESCE(tr.date_of_consultation, DATE(tr.created_at)) ASC, tr.id ASC",
            default => "COALESCE(tr.date_of_consultation, DATE(tr.created_at)) DESC, tr.id DESC",
        };
        $sql .= " ORDER BY {$orderSql}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = self::formatQueueRow($row);
        }
        $stmt->close();
        return $rows;
    }

    /** @param array<string, mixed> $row */
    public static function formatQueueRow(array $row): array
    {
        $middle = trim((string) ($row['middle_name'] ?? ''));
        $formal = trim(
            ($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '')
            . ($middle !== '' ? ' ' . $middle : '')
        );
        $pharmacy = self::parsePharmacyMeta($row['it_data'] ?? null);
        $doctor = trim((string) ($row['healthcare_provider'] ?? ''))
            ?: trim((string) ($row['attending_provider'] ?? ''))
            ?: '—';

        $consultationId = (int) ($row['consultation_id'] ?? 0);
        $dateRaw = $row['date_of_consultation'] ?? null;
        $timeRaw = $row['consultation_time'] ?? null;
        $period = trim((string) ($row['consultation_period'] ?? ''));

        $consultDate = '—';
        $consultTime = '';
        $consultDisplay = '—';
        $consultSortTs = 0;
        if ($dateRaw) {
            $consultDate = date('M d, Y', strtotime((string) $dateRaw));
            $consultSortTs = strtotime((string) $dateRaw . ' ' . ($timeRaw ?: '00:00:00')) ?: 0;
            if ($timeRaw) {
                $consultTime = date('g:i A', strtotime((string) $timeRaw));
                $consultDisplay = $consultDate . ' · ' . $consultTime;
            } elseif ($period !== '') {
                $consultTime = $period;
                $consultDisplay = $consultDate . ' · ' . $period;
            } else {
                $consultDisplay = $consultDate;
            }
        }

        $status = WorkflowStatus::normalize($row['status'] ?? WORKFLOW_WAITING_FOR_PHARMACY);
        $statusTone = match ($status) {
            WORKFLOW_DISPENSING => 'dispensing',
            WORKFLOW_DISPENSED => 'dispensed',
            default => 'waiting',
        };

        $sinceRaw = null;
        if ($status === WORKFLOW_DISPENSED && !empty($pharmacy['dispensed_at'])) {
            $sinceRaw = $pharmacy['dispensed_at'];
        } elseif ($status === WORKFLOW_DISPENSING && !empty($pharmacy['started_at'])) {
            $sinceRaw = $pharmacy['started_at'];
        } elseif (!empty($pharmacy['sent_at'])) {
            $sinceRaw = $pharmacy['sent_at'];
        } elseif ($dateRaw) {
            $sinceRaw = (string) $dateRaw . ($timeRaw ? ' ' . $timeRaw : '');
        } elseif (!empty($row['consult_created_at'])) {
            $sinceRaw = $row['consult_created_at'];
        }

        $sinceLabel = '';
        if ($sinceRaw) {
            $ts = strtotime((string) $sinceRaw);
            if ($ts) {
                $prefix = $status === WORKFLOW_DISPENSED ? 'Completed' : 'Since';
                $sinceLabel = $prefix . ' ' . date('M j, g:i A', $ts);
            }
        }

        $pharmacyQueueNo = $consultationId > 0
            ? 'P-' . str_pad((string) $consultationId, 4, '0', STR_PAD_LEFT)
            : '—';
        $receptionQueue = trim((string) ($row['queue_number'] ?? ''));
        $medCount = (int) ($row['medication_count'] ?? 0);

        return [
            'patient_db_id'      => (int) $row['id'],
            'patient_id'         => $row['patient_id'],
            'formal_name'        => $formal,
            'full_name'          => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'sex'                => $row['sex'] ?? '—',
            'age'                => calculateAge($row['birth_date'] ?? null),
            'contact_number'     => $row['contact_number'] ?: '—',
            'address'            => $row['residential_address'] ?: '—',
            'status'             => $status,
            'status_class'       => WorkflowStatus::badgeClass($status),
            'status_tone'        => $statusTone,
            'status_since'       => $sinceLabel,
            'queue_number'       => $receptionQueue !== '' ? $receptionQueue : '—',
            'pharmacy_queue_no'  => $pharmacyQueueNo,
            'consultation_id'    => $consultationId,
            'consult_date'       => $consultDate,
            'consult_time'       => $consultTime,
            'consult_display'    => $consultDisplay,
            'consult_sort_ts'    => $consultSortTs,
            'doctor'             => $doctor,
            'medication_count'   => $medCount,
            'medication_label'   => $medCount === 1 ? '1 Medicine' : $medCount . ' Medicines',
            'pharmacy'           => $pharmacy,
            'dispense_url'       => BASE_URL . '/pharmacy/dispense.php?id=' . (int) $row['id']
                . '&consultation_id=' . $consultationId,
            'is_done'            => $status === WORKFLOW_DISPENSED,
            'action_label'       => $status === WORKFLOW_WAITING_FOR_PHARMACY ? 'Open' : 'View',
            'action_icon'        => $status === WORKFLOW_WAITING_FOR_PHARMACY ? 'bi-folder2-open' : 'bi-eye',
        ];
    }

    /** @return array<string, mixed> */
    public static function parsePharmacyMeta(mixed $itData): array
    {
        $data = [];
        if (is_string($itData) && $itData !== '') {
            $decoded = json_decode($itData, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        } elseif (is_array($itData)) {
            $data = $itData;
        }
        $ph = $data['pharmacy'] ?? [];
        return is_array($ph) ? $ph : [];
    }

    /**
     * @return array{patient:array,consultation:array,medications:list<array>,pharmacy:array}|null
     */
    public static function loadDispenseCase(mysqli $conn, int $patientId, int $consultationId = 0): ?array
    {
        self::ensureSchema($conn);

        $stmt = $conn->prepare('SELECT * FROM patients WHERE id = ? AND is_archived = 0');
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$patient) {
            return null;
        }

        if ($consultationId > 0) {
            $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE id = ? AND patient_id = ?');
            $stmt->bind_param('ii', $consultationId, $patientId);
        } else {
            $stmt = $conn->prepare(
                'SELECT * FROM treatment_records WHERE patient_id = ?
                 ORDER BY COALESCE(date_of_consultation, created_at) DESC, id DESC LIMIT 1'
            );
            $stmt->bind_param('i', $patientId);
        }
        $stmt->execute();
        $treatment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$treatment) {
            return null;
        }

        $consultationId = (int) $treatment['id'];
        $medications = ClinicalMaster::getConsultationMedications($conn, $consultationId);
        $pharmacy = self::parsePharmacyMeta($treatment['it_data'] ?? null);

        $doctor = trim((string) ($treatment['healthcare_provider'] ?? ''))
            ?: trim((string) ($treatment['attending_provider'] ?? ''))
            ?: '—';

        return [
            'patient' => $patient,
            'consultation' => $treatment,
            'consultation_id' => $consultationId,
            'medications' => $medications,
            'pharmacy' => $pharmacy,
            'doctor' => $doctor,
            'status' => WorkflowStatus::normalize($patient['status'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $patch */
    public static function mergePharmacyMeta(mysqli $conn, int $consultationId, int $patientId, array $patch): void
    {
        $stmt = $conn->prepare('SELECT it_data FROM treatment_records WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('ii', $consultationId, $patientId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('Consultation not found.');
        }

        $it = [];
        if (!empty($row['it_data'])) {
            $decoded = json_decode((string) $row['it_data'], true);
            if (is_array($decoded)) {
                $it = $decoded;
            }
        }
        $pharmacy = is_array($it['pharmacy'] ?? null) ? $it['pharmacy'] : [];
        $it['pharmacy'] = array_merge($pharmacy, $patch);
        $json = json_encode($it, JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare('UPDATE treatment_records SET it_data = ? WHERE id = ? AND patient_id = ?');
        $stmt->bind_param('sii', $json, $consultationId, $patientId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Flat log rows — one row per prescribed medicine on dispensed consultations.
     *
     * @param array{mine_only?:bool,pharmacist_id?:int,search?:string,date_from?:string,date_to?:string} $filters
     * @return list<array<string, mixed>>
     */
    public static function listDispensedMedicationLog(mysqli $conn, array $filters = []): array
    {
        self::ensureSchema($conn);

        $mineOnly = !empty($filters['mine_only']);
        $pharmacistId = (int) ($filters['pharmacist_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $sql = "SELECT tr.id AS consultation_id,
                       tr.patient_id AS patient_db_id,
                       tr.date_of_consultation,
                       tr.it_data,
                       tr.medication_treatment,
                       p.patient_id AS pin,
                       p.first_name,
                       p.middle_name,
                       p.last_name,
                       mm.generic_name,
                       mm.brand_name,
                       mm.strength,
                       mm.dosage_form,
                       cm.id AS line_id
                FROM consultation_medications cm
                INNER JOIN treatment_records tr ON tr.id = cm.consultation_id
                INNER JOIN patients p ON p.id = tr.patient_id
                INNER JOIN medication_master mm ON mm.id = cm.medication_id
                WHERE p.is_archived = 0";

        $types = '';
        $params = [];
        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= ' AND (
                p.patient_id LIKE ?
                OR p.first_name LIKE ?
                OR p.last_name LIKE ?
                OR mm.generic_name LIKE ?
                OR mm.brand_name LIKE ?
                OR tr.medication_treatment LIKE ?
            )';
            $types .= 'ssssss';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY tr.id DESC, cm.id ASC LIMIT 800';

        $stmt = $conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        /** @var array<int, array<string, mixed>> $byConsult */
        $byConsult = [];
        foreach ($raw as $row) {
            $pharmacy = self::parsePharmacyMeta($row['it_data'] ?? null);
            $dispensedAt = trim((string) ($pharmacy['dispensed_at'] ?? ''));
            if ($dispensedAt === '') {
                continue;
            }

            $dispensedBy = (int) ($pharmacy['dispensed_by'] ?? 0);
            if ($mineOnly && $pharmacistId > 0 && $dispensedBy !== $pharmacistId) {
                continue;
            }

            $cid = (int) $row['consultation_id'];
            $ts = strtotime($dispensedAt);
            $dispensedDisplay = $ts ? date('M d, Y g:i A', $ts) : $dispensedAt;
            $sortTs = $ts ?: 0;

            $middle = trim((string) ($row['middle_name'] ?? ''));
            $formal = trim(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? ''))
                . ($middle !== '' ? ' ' . $middle : '');

            $prescription = trim((string) ($row['medication_treatment'] ?? ''));
            $medicine = trim((string) ($row['generic_name'] ?? ''));
            $brand = trim((string) ($row['brand_name'] ?? ''));
            $form = trim((string) ($row['dosage_form'] ?? '')) ?: '—';

            if (!isset($byConsult[$cid])) {
                $byConsult[$cid] = [
                    'consultation_id'   => $cid,
                    'patient_db_id'     => (int) $row['patient_db_id'],
                    'pin'               => $row['pin'] ?? '—',
                    'patient_name'      => $formal !== ',' ? $formal : '—',
                    'medicine'          => $medicine,
                    'brand'             => $brand,
                    'form'              => $form,
                    'medicines'         => [],
                    'prescription'      => $prescription !== '' ? $prescription : '—',
                    'dispensed_at'      => $dispensedDisplay,
                    'dispensed_sort_ts' => $sortTs,
                    'dispensed_by'      => trim((string) ($pharmacy['dispensed_by_name'] ?? '')) ?: '—',
                    'view_url'          => BASE_URL . '/pharmacy/dispense.php?id=' . (int) $row['patient_db_id']
                        . '&consultation_id=' . $cid,
                ];
            }

            if ($medicine !== '') {
                $byConsult[$cid]['medicines'][$medicine] = true;
            }
            if ($sortTs >= (int) ($byConsult[$cid]['dispensed_sort_ts'] ?? 0)) {
                $byConsult[$cid]['dispensed_sort_ts'] = $sortTs;
                $byConsult[$cid]['dispensed_at'] = $dispensedDisplay;
            }
        }

        $rows = array_values($byConsult);
        usort($rows, static fn($a, $b) => ($b['dispensed_sort_ts'] ?? 0) <=> ($a['dispensed_sort_ts'] ?? 0));

        return $rows;
    }
}
