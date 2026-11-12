<?php
/**
 * Historical patient records — photographed / OCR-digitized old forms.
 */

require_once __DIR__ . '/Permissions.php';
require_once __DIR__ . '/PatientSearch.php';
require_once __DIR__ . '/ExtendedFormData.php';
require_once __DIR__ . '/MagallanesAddress.php';

class HistoricalRecord
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/historical-records.php';
        }
        return self::$config;
    }

    public static function ensureSchema(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS patient_historical_records (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                patient_id          INT          NOT NULL,
                record_type         VARCHAR(80)  NOT NULL,
                record_date         DATE         DEFAULT NULL,
                remarks             TEXT         DEFAULT NULL,
                storage_mode        ENUM('photo_only', 'ocr_reviewed') NOT NULL DEFAULT 'photo_only',
                original_photo_path VARCHAR(500) NOT NULL,
                ocr_raw_text        TEXT         DEFAULT NULL,
                reviewed_data       JSON         DEFAULT NULL,
                soft_copy_path      VARCHAR(500) DEFAULT NULL,
                uploaded_by         INT          DEFAULT NULL,
                created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_patient (patient_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB"
        );
    }

    public static function canAdd(string $role): bool
    {
        $role = Permissions::normalizeRole($role);
        return in_array($role, [ROLE_IT, ROLE_RECORDS], true);
    }

    public static function canView(string $role): bool
    {
        return Permissions::can('historical.view');
    }

    /** @return list<array{id: string, label: string}> */
    public static function officialFormPages(): array
    {
        static $pages = null;
        if ($pages !== null) {
            return $pages;
        }

        $wizard = require __DIR__ . '/../config/form-wizard.php';
        $pages = [];
        foreach ($wizard['pages'] as $page) {
            if (!empty($page['is_review'])) {
                continue;
            }
            $pages[] = [
                'id'       => (string) $page['id'],
                'label'    => (string) ($page['short_title'] ?? $page['title']),
                'subtitle' => (string) ($page['subtitle'] ?? ''),
            ];
            if (count($pages) >= 7) {
                break;
            }
        }

        return $pages;
    }

    /** @return array<string, string> */
    public static function recordTypeOptions(): array
    {
        $opts = [];
        foreach (self::officialFormPages() as $page) {
            $opts[$page['id']] = $page['label'];
        }

        return $opts;
    }

    /** Official wizard form keys + legacy keys accepted when saving. */
    public static function validRecordTypes(): array
    {
        return array_merge(
            self::recordTypeOptions(),
            self::config()['legacy_record_types'] ?? []
        );
    }

    /** Group saved forms into photographed batches (same upload session + form date). */
    public static function groupRecordsForDisplay(array $records): array
    {
        if ($records === []) {
            return [];
        }

        $officialOrder = array_keys(self::recordTypeOptions());
        $orderRank = array_flip($officialOrder);
        $groups = [];

        foreach ($records as $record) {
            $createdAt = (string) ($record['created_at'] ?? '');
            $minuteBucket = $createdAt !== '' ? substr($createdAt, 0, 16) : 'unknown';
            $recordDate = (string) ($record['record_date'] ?? '');
            $uploader = (string) ($record['uploaded_by_name'] ?? '—');
            $key = $recordDate . "\0" . $uploader . "\0" . $minuteBucket;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'record_date_display' => $record['record_date_display'] ?? '—',
                    'staff_display'       => self::formatUploaderDisplay(
                        (string) ($record['uploaded_by_name'] ?? ''),
                        (string) ($record['uploaded_by_role'] ?? '')
                    ),
                    'created_at_display'  => $record['created_at_display'] ?? '—',
                    'sort_ts'             => $createdAt !== '' ? (int) strtotime($createdAt) : 0,
                    'forms'               => [],
                ];
            }

            $groups[$key]['forms'][] = [
                'id'        => (int) $record['id'],
                'type'      => (string) ($record['record_type'] ?? ''),
                'label'     => (string) $record['record_type_label'],
                'view_url'  => (string) $record['view_url'],
                'photo_url' => (string) $record['photo_url'],
                'has_ocr'   => !empty($record['has_ocr']),
                'subtitle'  => !empty($record['has_ocr'])
                    ? 'OCR reviewed copy'
                    : 'Photographed original form',
            ];
        }

        $list = array_values($groups);
        usort($list, static fn(array $a, array $b): int => ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0));

        foreach ($list as &$group) {
            usort($group['forms'], static function (array $a, array $b) use ($orderRank): int {
                $ra = $orderRank[$a['type'] ?? ''] ?? 999;
                $rb = $orderRank[$b['type'] ?? ''] ?? 999;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }

                return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
            });
            $group['form_count'] = count($group['forms']);
        }
        unset($group);

        return $list;
    }

    private static function formatUploaderDisplay(string $name, string $role): string
    {
        $name = trim($name);
        if ($name === '') {
            return '—';
        }

        $role = Permissions::normalizeRole($role);
        if ($role === ROLE_DOCTOR && !preg_match('/^dr\.?\s/i', $name)) {
            return 'Dr. ' . $name;
        }

        return $name;
    }

    public static function recordTypeLabel(string $key): string
    {
        $opts = self::recordTypeOptions();
        if (isset($opts[$key])) {
            return $opts[$key];
        }

        $legacy = self::config()['legacy_record_types'] ?? [];
        return $legacy[$key] ?? $key;
    }

    public static function uploadRoot(): string
    {
        return BASE_PATH . '/' . trim(self::config()['upload_dir'], '/');
    }

    /** @return list<array<string, mixed>> */
    public static function listForPatient(mysqli $conn, int $patientId): array
    {
        self::ensureSchema($conn);

        $stmt = $conn->prepare(
            'SELECT r.*, u.full_name AS uploaded_by_name, rl.slug AS uploaded_by_role
             FROM patient_historical_records r
             LEFT JOIN users u ON u.id = r.uploaded_by
             LEFT JOIN roles rl ON rl.id = u.role_id
             WHERE r.patient_id = ?
             ORDER BY COALESCE(r.record_date, r.created_at) DESC, r.id DESC'
        );
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return array_map([self::class, 'formatRow'], $rows);
    }

    /** @return array<string, mixed>|null */
    public static function find(mysqli $conn, int $recordId): ?array
    {
        self::ensureSchema($conn);

        $stmt = $conn->prepare(
            'SELECT r.*, u.full_name AS uploaded_by_name, rl.slug AS uploaded_by_role,
                    p.patient_id AS patient_code, p.first_name, p.last_name, p.middle_name
             FROM patient_historical_records r
             INNER JOIN patients p ON p.id = r.patient_id
             LEFT JOIN users u ON u.id = r.uploaded_by
             LEFT JOIN roles rl ON rl.id = u.role_id
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? self::formatRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    public static function formatRow(array $row): array
    {
        $reviewed = [];
        if (!empty($row['reviewed_data'])) {
            $decoded = is_array($row['reviewed_data'])
                ? $row['reviewed_data']
                : json_decode((string) $row['reviewed_data'], true);
            $reviewed = is_array($decoded) ? $decoded : [];
        }

        $recordDate = $row['record_date'] ?? null;
        $dateDisplay = '—';
        if ($recordDate) {
            $ts = strtotime((string) $recordDate);
            $dateDisplay = $ts ? date('Y', $ts) : (string) $recordDate;
            if ($ts && date('m-d', $ts) !== '01-01') {
                $dateDisplay = date('M j, Y', $ts);
            }
        }

        $mode = (string) ($row['storage_mode'] ?? 'photo_only');

        return [
            'id'                   => (int) $row['id'],
            'patient_id'           => (int) $row['patient_id'],
            'patient_code'         => $row['patient_code'] ?? null,
            'record_type'          => (string) $row['record_type'],
            'record_type_label'    => self::recordTypeLabel((string) $row['record_type']),
            'record_date'          => $recordDate,
            'record_date_display'  => $dateDisplay,
            'remarks'              => $row['remarks'] ?? null,
            'storage_mode'         => $mode,
            'storage_mode_label'   => $mode === 'ocr_reviewed' ? 'OCR Reviewed' : 'Photographed Record',
            'has_ocr'              => $mode === 'ocr_reviewed',
            'ocr_raw_text'         => $row['ocr_raw_text'] ?? null,
            'reviewed_data'        => $reviewed,
            'uploaded_by_name'     => $row['uploaded_by_name'] ?? '—',
            'uploaded_by_role'     => $row['uploaded_by_role'] ?? '',
            'uploaded_by_display'  => self::formatUploaderDisplay(
                (string) ($row['uploaded_by_name'] ?? ''),
                (string) ($row['uploaded_by_role'] ?? '')
            ),
            'created_at'           => $row['created_at'] ?? null,
            'created_at_display'   => !empty($row['created_at'])
                ? date('M j, Y g:i A', strtotime((string) $row['created_at']))
                : '—',
            'original_photo_path'  => $row['original_photo_path'] ?? null,
            'soft_copy_path'       => $row['soft_copy_path'] ?? null,
            'view_url'             => BASE_URL . '/historical-record.php?id=' . (int) $row['id'],
            'photo_url'            => BASE_URL . '/api/historical_record_file.php?id=' . (int) $row['id'] . '&type=original',
            'soft_copy_url'        => !empty($row['soft_copy_path'])
                ? BASE_URL . '/api/historical_record_file.php?id=' . (int) $row['id'] . '&type=soft'
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function savePhotoRecord(
        mysqli $conn,
        int $patientId,
        array $meta,
        string $tempPhotoPath,
        string $originalFilename,
        int $uploadedBy
    ): array {
        self::ensureSchema($conn);

        if (!is_readable($tempPhotoPath)) {
            throw new RuntimeException('Photo upload failed.');
        }

        $cfg = self::config();
        if (filesize($tempPhotoPath) > (int) $cfg['max_photo_bytes']) {
            throw new RuntimeException('Photo is too large. Maximum size is 8 MB.');
        }

        $recordType = trim((string) ($meta['record_type'] ?? ''));
        if ($recordType === '' || !isset(self::validRecordTypes()[$recordType])) {
            throw new RuntimeException('Invalid record type.');
        }

        $recordDate = self::normalizeRecordDate($meta['record_date'] ?? null);
        $remarks = trim((string) ($meta['remarks'] ?? ''));
        $remarks = $remarks !== '' ? $remarks : null;

        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $dir = self::uploadRoot() . '/' . $patientId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $relative = trim($cfg['upload_dir'], '/') . '/' . $patientId . '/' . $filename;
        $absolute = BASE_PATH . '/' . $relative;

        if (!rename($tempPhotoPath, $absolute)) {
            if (!copy($tempPhotoPath, $absolute)) {
                throw new RuntimeException('Unable to store photograph.');
            }
            @unlink($tempPhotoPath);
        }

        $storageMode = 'photo_only';
        $ocrRaw = null;
        $reviewedJson = null;
        $softCopyPath = null;

        $ocrRawInput = trim((string) ($meta['ocr_raw_text'] ?? ''));
        $reviewedInput = $meta['reviewed_data'] ?? null;
        $hasReviewed = is_array($reviewedInput) && array_filter($reviewedInput, static fn ($v) => trim((string) $v) !== '') !== [];

        if ($ocrRawInput !== '' || $hasReviewed) {
            $storageMode = 'ocr_reviewed';
            $ocrRaw = $ocrRawInput !== '' ? $ocrRawInput : null;
            $reviewedJson = json_encode(self::normalizeReviewedData($reviewedInput), JSON_UNESCAPED_UNICODE);
            $softCopyPath = self::writeSoftCopy($patientId, $filename, $reviewedInput, $recordType, $recordDate);
        }

        $stmt = $conn->prepare(
            'INSERT INTO patient_historical_records (
                patient_id, record_type, record_date, remarks, storage_mode,
                original_photo_path, ocr_raw_text, reviewed_data, soft_copy_path, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'issssssssi',
            $patientId,
            $recordType,
            $recordDate,
            $remarks,
            $storageMode,
            $relative,
            $ocrRaw,
            $reviewedJson,
            $softCopyPath,
            $uploadedBy
        );
        $stmt->execute();
        if ($stmt->errno) {
            throw new RuntimeException('Unable to save historical record.');
        }
        $recordId = (int) $conn->insert_id;
        $stmt->close();

        return self::find($conn, $recordId) ?? [];
    }

    /** @param mixed $input */
    private static function normalizeRecordDate($input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/^\d{4}$/', $input)) {
            return $input . '-01-01';
        }
        $ts = strtotime($input);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /** @param mixed $input @return array<string, string> */
    private static function normalizeReviewedData($input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $out = [];
        foreach (['patient_name', 'address', 'diagnosis', 'record_date', 'notes'] as $key) {
            $val = trim((string) ($input[$key] ?? ''));
            if ($val !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    /** @param mixed $reviewed */
    private static function writeSoftCopy(
        int $patientId,
        string $photoFilename,
        $reviewed,
        string $recordType,
        ?string $recordDate
    ): string {
        $reviewed = self::normalizeReviewedData($reviewed);
        $base = pathinfo($photoFilename, PATHINFO_FILENAME);
        $relative = trim(self::config()['upload_dir'], '/')
            . '/' . $patientId . '/' . $base . '-soft.json';
        $absolute = BASE_PATH . '/' . $relative;

        $payload = [
            'generated_at' => date('c'),
            'record_type'  => $recordType,
            'record_type_label' => self::recordTypeLabel($recordType),
            'record_date'  => $recordDate,
            'fields'       => $reviewed,
        ];

        file_put_contents($absolute, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $relative;
    }

    public static function absolutePath(string $relativePath): string
    {
        $relativePath = str_replace(['\\', '..'], ['/', ''], $relativePath);
        return BASE_PATH . '/' . ltrim($relativePath, '/');
    }

    /** Fields IT must verify before creating a patient from an old form. */
    public static function requiredPatientVerifyFields(): array
    {
        return [];
    }

    /** @param array<string, mixed> $data */
    public static function validatePatientVerifyData(array $data): array
    {
        $missing = [];
        foreach (self::requiredPatientVerifyFields() as $field => $label) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        $sex = trim((string) ($data['sex'] ?? ''));
        if ($sex !== '' && !in_array($sex, ['Male', 'Female'], true)) {
            throw new InvalidArgumentException('Sex must be Male or Female.');
        }

        $birthDate = trim((string) ($data['birth_date'] ?? ''));
        if ($birthDate !== '' && !strtotime($birthDate)) {
            throw new InvalidArgumentException('Invalid date of birth.');
        }

        $barangayRaw = trim((string) ($data['address_barangay'] ?? ''));
        if ($barangayRaw !== '' && MagallanesAddress::normalizeBarangay($barangayRaw) === '') {
            throw new InvalidArgumentException('Please select a valid Magallanes barangay.');
        }

        return $missing;
    }

    /**
     * Find possible duplicate patients using existing search patterns.
     *
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    public static function findPossibleDuplicates(mysqli $conn, array $data): array
    {
        PatientSearch::ensurePhysicalRecordedColumn($conn);

        $seen = [];
        $matches = [];

        $philhealth = preg_replace('/\s+/', '', trim((string) ($data['philhealth_no'] ?? '')));
        if ($philhealth !== '') {
            $stmt = $conn->prepare(
                'SELECT p.id, p.patient_id, p.last_name, p.first_name, p.middle_name,
                        p.birth_date, p.sex, p.philhealth_no, p.status, p.residential_address
                 FROM patients p
                 WHERE REPLACE(p.philhealth_no, \' \', \'\') = ? AND p.is_archived = 0
                 LIMIT 5'
            );
            $stmt->bind_param('s', $philhealth);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $seen[(int) $row['id']] = true;
                $matches[] = self::formatDuplicateMatch($row, 'PhilHealth number match');
            }
            $stmt->close();
        }

        $lastName = trim((string) ($data['last_name'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $birthDate = trim((string) ($data['birth_date'] ?? ''));

        if ($lastName !== '' || $firstName !== '') {
            $term = trim($lastName . ' ' . $firstName);
            $where = PatientSearch::buildWhereClause($term, []);
            $sql = 'SELECT p.id, p.patient_id, p.last_name, p.first_name, p.middle_name,
                           p.birth_date, p.sex, p.philhealth_no, p.status, p.residential_address
                    FROM patients p' . $where['sql'] . ' AND p.is_archived = 0
                    ORDER BY p.date_encoded DESC LIMIT 8';

            $stmt = $conn->prepare($sql);
            if ($where['types'] !== '') {
                $stmt->bind_param($where['types'], ...$where['params']);
            }
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $id = (int) $row['id'];
                if (isset($seen[$id])) {
                    continue;
                }
                if ($birthDate !== '' && !empty($row['birth_date']) && $row['birth_date'] !== $birthDate) {
                    continue;
                }
                $seen[$id] = true;
                $reason = $birthDate !== '' && ($row['birth_date'] ?? '') === $birthDate
                    ? 'Name and birth date match'
                    : 'Similar name found';
                $matches[] = self::formatDuplicateMatch($row, $reason);
            }
            $stmt->close();
        }

        return $matches;
    }

    /** @param array<string, mixed> $row */
    private static function formatDuplicateMatch(array $row, string $reason): array
    {
        $middle = trim((string) ($row['middle_name'] ?? ''));
        $fullName = trim(
            ($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '')
            . ($middle !== '' ? ' ' . $middle : '')
        );
        $birth = !empty($row['birth_date'])
            ? date('M j, Y', strtotime((string) $row['birth_date']))
            : '—';

        return [
            'id'           => (int) $row['id'],
            'patient_id'   => (string) ($row['patient_id'] ?? ''),
            'full_name'    => $fullName,
            'birth_date'   => $row['birth_date'] ?? null,
            'birth_display'=> $birth,
            'sex'          => $row['sex'] ?? '—',
            'philhealth_no'=> $row['philhealth_no'] ?? '—',
            'status'       => $row['status'] ?? '—',
            'barangay'     => PatientSearch::extractBarangay($row['residential_address'] ?? null) ?: '—',
            'match_reason' => $reason,
            'folder_url'   => BASE_URL . '/patient-folder.php?id=' . (int) $row['id'],
        ];
    }

    /**
     * Create a normal patient record and attach the photographed old form.
     *
     * @param array<string, mixed> $patientData
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function createPatientFromForm(
        mysqli $conn,
        array $patientData,
        array $meta,
        string $tempPhotoPath,
        string $originalFilename,
        int $uploadedBy
    ): array {
        $missing = self::validatePatientVerifyData($patientData);
        if ($missing !== []) {
            throw new InvalidArgumentException('Complete required fields: ' . implode(', ', $missing));
        }

        $duplicates = self::findPossibleDuplicates($conn, $patientData);
        $forceCreate = !empty($meta['force_create']);
        if ($duplicates !== [] && !$forceCreate) {
            throw new RuntimeException('Possible duplicate patient found. Review matches before creating a new record.');
        }

        $barangay = MagallanesAddress::normalizeBarangay($patientData['address_barangay'] ?? '');
        $extended = MagallanesAddress::applyToExtended(['address_barangay' => $barangay]);
        $residential = ExtendedFormData::buildResidentialAddress($extended, null);

        $conn->begin_transaction();

        try {
            $insert = [
                'patient_id'          => generatePatientId($conn),
                'last_name'           => trim((string) $patientData['last_name']),
                'first_name'          => trim((string) $patientData['first_name']),
                'middle_name'         => trim((string) ($patientData['middle_name'] ?? '')) ?: null,
                'sex'                 => trim((string) $patientData['sex']),
                'birth_date'          => date('Y-m-d', strtotime((string) $patientData['birth_date'])),
                'civil_status'        => trim((string) $patientData['civil_status']),
                'contact_number'      => trim((string) $patientData['contact_number']),
                'philhealth_no'       => trim((string) $patientData['philhealth_no']),
                'philhealth_member'   => 'Yes',
                'residential_address' => $residential,
                'encoded_by'          => (string) $uploadedBy,
                'status'              => WORKFLOW_DRAFT,
            ];

            $cols = array_keys($insert);
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $types = str_repeat('s', count($cols));
            $stmt = $conn->prepare(
                'INSERT INTO patients (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')'
            );
            $stmt->bind_param($types, ...array_values($insert));
            $stmt->execute();
            if ($stmt->errno) {
                throw new RuntimeException('Unable to create patient record.');
            }
            $patientDbId = (int) $conn->insert_id;
            $stmt->close();

            require_once __DIR__ . '/Consultation.php';
            $json = ExtendedFormData::toJson($extended, 0, null);
            $stmt = $conn->prepare('INSERT INTO treatment_records (patient_id, receptionist_data) VALUES (?, ?)');
            $stmt->bind_param('is', $patientDbId, $json);
            $stmt->execute();
            $stmt->close();

            $record = self::savePhotoRecord(
                $conn,
                $patientDbId,
                $meta,
                $tempPhotoPath,
                $originalFilename,
                $uploadedBy
            );

            $conn->commit();

            return [
                'patient_id'     => $patientDbId,
                'patient_code'   => $insert['patient_id'],
                'patient_name'   => trim($insert['last_name'] . ', ' . $insert['first_name']),
                'folder_url'     => BASE_URL . '/patient-folder.php?id=' . $patientDbId . '&tab=historical',
                'historical_record' => $record,
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
