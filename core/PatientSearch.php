<?php
/**
 * Patient search — shared query builder and display helpers.
 */

require_once __DIR__ . '/WorkflowStatus.php';

class PatientSearch
{
    public static function ensurePhysicalRecordedColumn(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $check = $conn->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'patients'
               AND COLUMN_NAME = 'physical_recorded'"
        );
        $exists = $check && (int) ($check->fetch_assoc()['c'] ?? 0) > 0;
        if ($exists) {
            return;
        }

        $conn->query(
            'ALTER TABLE patients
             ADD COLUMN physical_recorded TINYINT(1) NOT NULL DEFAULT 0'
        );
    }

    public static function displayStatus(string $workflowStatus): string
    {
        return $workflowStatus;
    }

    public static function displayStatusClass(string $workflowStatus): string
    {
        return WorkflowStatus::badgeClass($workflowStatus);
    }

    /**
     * @return array{sql: string, params: array, types: string}
     */
    public static function buildWhereClause(string $term, array $filters = []): array
    {
        $sql = ' WHERE 1=1';
        $params = [];
        $types = '';

        if ($term !== '') {
            $like = '%' . $term . '%';
            $sql .= ' AND (
                p.patient_id LIKE ?
                OR p.philhealth_no LIKE ?
                OR p.family_serial_number LIKE ?
                OR p.last_name LIKE ?
                OR p.first_name LIKE ?
                OR p.middle_name LIKE ?
                OR CONCAT(p.last_name, \', \', p.first_name, IF(p.middle_name IS NULL OR p.middle_name = \'\', \'\', CONCAT(\' \', p.middle_name))) LIKE ?
                OR CONCAT(p.first_name, \' \', IFNULL(p.middle_name, \'\'), \' \', p.last_name) LIKE ?
                OR CONCAT(IFNULL(p.middle_name, \'\'), \' \', p.last_name, \' \', p.first_name) LIKE ?
            )';
            $params = array_fill(0, 9, $like);
            $types = str_repeat('s', 9);
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = ?';
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['sex'])) {
            $sql .= ' AND p.sex = ?';
            $params[] = $filters['sex'];
            $types .= 's';
        }

        if (!empty($filters['date_encoded'])) {
            $sql .= ' AND DATE(p.date_encoded) = ?';
            $params[] = $filters['date_encoded'];
            $types .= 's';
        }

        if (!empty($filters['barangay'])) {
            $sql .= ' AND p.residential_address LIKE ?';
            $params[] = '%' . $filters['barangay'] . '%';
            $types .= 's';
        }

        if (isset($filters['physical_recorded']) && $filters['physical_recorded'] !== '' && $filters['physical_recorded'] !== null) {
            $sql .= ' AND p.physical_recorded = ?';
            $params[] = (int) $filters['physical_recorded'] ? 1 : 0;
            $types .= 'i';
        }

        return ['sql' => $sql, 'params' => $params, 'types' => $types];
    }

    /**
     * Consultation aggregates for registry list (subquery SELECT fragments).
     */
    public static function consultationSelectSql(): string
    {
        return ',
            (SELECT COUNT(*) FROM treatment_records tr WHERE tr.patient_id = p.id) AS consultation_count,
            (SELECT MAX(COALESCE(tr.date_of_consultation, DATE(tr.created_at)))
             FROM treatment_records tr WHERE tr.patient_id = p.id) AS last_consultation';
    }

    /**
     * @return array{sql: string, params: array, types: string}
     */
    public static function buildOrderClause(string $sort): array
    {
        $order = match ($sort) {
            'encoded_desc' => 'p.date_encoded DESC, p.id DESC',
            'name_asc' => 'p.last_name ASC, p.first_name ASC, p.id ASC',
            'name_desc' => 'p.last_name DESC, p.first_name DESC, p.id DESC',
            'consult_newest' => 'last_consultation DESC, p.id DESC',
            'consult_oldest' => 'last_consultation ASC, p.id ASC',
            'consult_most' => 'consultation_count DESC, p.last_name ASC, p.id DESC',
            'consult_least' => 'consultation_count ASC, p.last_name ASC, p.id ASC',
            default => 'p.date_encoded ASC, p.id ASC',
        };

        return ['sql' => ' ORDER BY ' . $order, 'params' => [], 'types' => ''];
    }

    public static function consultationCountLabel(int $count): string
    {
        if ($count === 1) {
            return '1 Consultation';
        }
        return $count . ' Consultations';
    }

    public static function formatRow(array $row): array
    {
        $middle = trim($row['middle_name'] ?? '');
        $first = trim($row['first_name'] ?? '');
        $last = trim($row['last_name'] ?? '');

        $fullName = trim($first . ($middle !== '' ? ' ' . $middle : '') . ' ' . $last);
        $formalName = trim($last . ', ' . $first . ($middle !== '' ? ' ' . $middle : ''));

        $status = $row['status'] ?? WORKFLOW_DRAFT;
        $displayStatus = self::displayStatus($status);
        $isLocked = WorkflowStatus::isLocked($status);

        $consultCount = (int) ($row['consultation_count'] ?? 0);
        $lastConsultRaw = $row['last_consultation'] ?? null;
        $physicalRecorded = (int) ($row['physical_recorded'] ?? 0) === 1;

        return [
            'id'               => (int) $row['id'],
            'patient_id'       => $row['patient_id'],
            'full_name'        => $fullName,
            'formal_name'      => $formalName,
            'philhealth_no'    => $row['philhealth_no'] ?: null,
            'family_serial_number' => $row['family_serial_number'] ?: null,
            'status'           => $status,
            'display_status'   => $displayStatus,
            'status_class'     => self::displayStatusClass($status),
            'is_locked'        => $isLocked,
            'age'              => calculateAge($row['birth_date'] ?? null),
            'sex'              => $row['sex'] ?? '',
            'barangay'         => self::extractBarangay($row['residential_address'] ?? null),
            'contact_number'   => $row['contact_number'] ?: '—',
            'date_encoded'     => !empty($row['date_encoded'])
                ? date('M d, Y', strtotime($row['date_encoded']))
                : '—',
            'consultation_count' => $consultCount,
            'consultation_count_label' => self::consultationCountLabel($consultCount),
            'last_consultation' => $lastConsultRaw,
            'last_consultation_display' => $consultCount > 0 && $lastConsultRaw
                ? date('F j, Y', strtotime($lastConsultRaw))
                : 'No Consultation Yet',
            'physical_recorded' => $physicalRecorded,
            'encoded_by'       => $row['encoded_by'] ?? '—',
            'rejection_reason' => $row['rejection_reason'] ?? null,
            'profile_url'      => self::profileUrl($row),
        ];
    }

    public static function profileUrl(array $row): string
    {
        return BASE_URL . '/patient-folder.php?id=' . (int) $row['id'];
    }

    public static function extractBarangay(?string $address): string
    {
        if (!$address) {
            return '—';
        }

        require_once __DIR__ . '/MagallanesAddress.php';
        $normalized = MagallanesAddress::normalizeBarangay($address);
        if ($normalized !== '') {
            return $normalized;
        }

        if (preg_match('/Brgy\.\s*([^,]+)/i', $address, $m)) {
            $fromPrefix = MagallanesAddress::normalizeBarangay($m[1]);
            return $fromPrefix !== '' ? $fromPrefix : trim($m[1]);
        }

        $parts = array_map('trim', explode(',', $address));
        if (count($parts) >= 3) {
            // Prefer barangay slot before municipality in Magallanes format
            $candidate = MagallanesAddress::normalizeBarangay($parts[0]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $address;
    }
}
