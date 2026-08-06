<?php
/**
 * Local clinical master datasets — Diagnosis (ICD) & Medication.
 * Offline MySQL only; import-ready for full ICD-10 / drug lists later.
 */

class ClinicalMaster
{
    public static function ensureSchema(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS diagnosis_master (
                id INT AUTO_INCREMENT PRIMARY KEY,
                icd_code VARCHAR(32) DEFAULT NULL,
                diagnosis_name VARCHAR(255) NOT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_dx_status (status),
                INDEX idx_dx_name (diagnosis_name),
                INDEX idx_dx_icd (icd_code),
                INDEX idx_dx_name_status (status, diagnosis_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS medication_master (
                id INT AUTO_INCREMENT PRIMARY KEY,
                generic_name VARCHAR(255) NOT NULL,
                brand_name VARCHAR(255) DEFAULT NULL,
                strength VARCHAR(64) DEFAULT NULL,
                dosage_form VARCHAR(64) DEFAULT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_med_status (status),
                INDEX idx_med_generic (generic_name),
                INDEX idx_med_brand (brand_name),
                INDEX idx_med_generic_status (status, generic_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS consultation_diagnoses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                consultation_id INT NOT NULL,
                diagnosis_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_consult_diagnosis (consultation_id, diagnosis_id),
                INDEX idx_cd_consultation (consultation_id),
                INDEX idx_cd_diagnosis (diagnosis_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS consultation_medications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                consultation_id INT NOT NULL,
                medication_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_consult_medication (consultation_id, medication_id),
                INDEX idx_cm_consultation (consultation_id),
                INDEX idx_cm_medication (medication_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::seedIfEmpty($conn);
    }

    /** Seed a small RHU starter set so autocomplete works before full import. */
    private static function seedIfEmpty(mysqli $conn): void
    {
        $dxCount = 0;
        $r = $conn->query('SELECT COUNT(*) AS c FROM diagnosis_master');
        if ($r && ($row = $r->fetch_assoc())) {
            $dxCount = (int) $row['c'];
        }
        if ($dxCount === 0) {
            $diagnoses = [
                ['I10', 'Essential Hypertension'],
                ['E11', 'Type 2 Diabetes Mellitus'],
                ['J06.9', 'Acute Upper Respiratory Infection'],
                ['A09', 'Infectious Gastroenteritis and Colitis'],
                ['A90', 'Dengue Fever'],
                ['J45.9', 'Asthma, Unspecified'],
                ['N39.0', 'Urinary Tract Infection'],
                ['E78.5', 'Hyperlipidemia, Unspecified'],
                ['M79.3', 'Panniculitis, Unspecified'],
                ['R50.9', 'Fever, Unspecified'],
                ['R51', 'Headache'],
                ['J02.9', 'Acute Pharyngitis, Unspecified'],
                ['K29.7', 'Gastritis, Unspecified'],
                ['L30.9', 'Dermatitis, Unspecified'],
                ['E03.9', 'Hypothyroidism, Unspecified'],
                ['E16.2', 'Hypoglycemia, Unspecified'],
                ['E05.9', 'Hyperthyroidism, Unspecified'],
                ['I25.9', 'Chronic Ischemic Heart Disease'],
                ['J18.9', 'Pneumonia, Unspecified'],
                ['B34.9', 'Viral Infection, Unspecified'],
            ];
            $stmt = $conn->prepare(
                'INSERT INTO diagnosis_master (icd_code, diagnosis_name, status) VALUES (?, ?, \'active\')'
            );
            foreach ($diagnoses as [$code, $name]) {
                $stmt->bind_param('ss', $code, $name);
                $stmt->execute();
            }
            $stmt->close();
        }

        $medCount = 0;
        $r = $conn->query('SELECT COUNT(*) AS c FROM medication_master');
        if ($r && ($row = $r->fetch_assoc())) {
            $medCount = (int) $row['c'];
        }
        if ($medCount === 0) {
            $meds = [
                ['Paracetamol', 'Biogesic', '500mg', 'Tablet'],
                ['Paracetamol', null, null, 'Syrup'],
                ['Paracetamol', null, null, 'Drops'],
                ['Amoxicillin', null, '500mg', 'Capsule'],
                ['Amoxicillin', null, '250mg/5mL', 'Suspension'],
                ['Cetirizine', null, '10mg', 'Tablet'],
                ['Cetirizine', null, '5mg/5mL', 'Syrup'],
                ['Losartan', null, '50mg', 'Tablet'],
                ['Amlodipine', null, '5mg', 'Tablet'],
                ['Metformin', null, '500mg', 'Tablet'],
                ['Ibuprofen', null, '400mg', 'Tablet'],
                ['Mefenamic Acid', null, '500mg', 'Capsule'],
                ['Omeprazole', null, '20mg', 'Capsule'],
                ['Salbutamol', null, '2mg', 'Tablet'],
                ['Salbutamol', null, null, 'Nebule'],
                ['Cotrimoxazole', null, '400mg/80mg', 'Tablet'],
                ['ORS', 'Hydrite', null, 'Sachet'],
                ['Multivitamins', null, null, 'Capsule'],
                ['Ferrous Sulfate', null, '325mg', 'Tablet'],
                ['Lagundi', 'Ascof', null, 'Syrup'],
            ];
            $stmt = $conn->prepare(
                'INSERT INTO medication_master (generic_name, brand_name, strength, dosage_form, status)
                 VALUES (?, ?, ?, ?, \'active\')'
            );
            foreach ($meds as [$generic, $brand, $strength, $form]) {
                $stmt->bind_param('ssss', $generic, $brand, $strength, $form);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    public static function countActive(mysqli $conn, string $type): int
    {
        $table = $type === 'medication' ? 'medication_master' : 'diagnosis_master';
        $r = $conn->query("SELECT COUNT(*) AS c FROM {$table} WHERE status = 'active'");
        if ($r && ($row = $r->fetch_assoc())) {
            return (int) $row['c'];
        }
        return 0;
    }

    public static function formatDiagnosisLabel(array $row): string
    {
        $name = trim((string) ($row['diagnosis_name'] ?? ''));
        $code = trim((string) ($row['icd_code'] ?? ''));
        if ($code !== '') {
            return $name . ' (' . $code . ')';
        }
        return $name;
    }

    public static function formatMedicationLabel(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['generic_name'] ?? '')),
            trim((string) ($row['strength'] ?? '')),
            trim((string) ($row['dosage_form'] ?? '')),
        ], static fn($p) => $p !== '');
        $label = implode(' ', $parts);
        $brand = trim((string) ($row['brand_name'] ?? ''));
        if ($brand !== '') {
            $label .= ' [' . $brand . ']';
        }
        return $label;
    }

    /**
     * @return array{empty_dataset:bool,message:?string,results:list<array>}
     */
    /**
     * High-frequency outpatient clinical keywords for autocomplete boost.
     * Does not change stored ICD names — ranking only.
     *
     * @return list<string>
     */
    private static function clinicalDiagnosisKeywords(): array
    {
        return [
            'hypertension', 'hypertensive', 'hyperthyroidism', 'hyperglycemia',
            'hyperlipidaemia', 'hyperlipidemia', 'hypercholesterolemia', 'hyperuricemia',
            'hypersensitivity', 'hypothyroidism', 'hypoglycemia', 'hypotension',
            'hypokalemia', 'hyponatremia', 'diabetes', 'diabetic', 'pneumonia', 'asthma',
            'tuberculosis', 'bronchitis', 'gastritis', 'gastroenteritis', 'diarrhea',
            'diarrhoea', 'dysentery', 'anemia', 'anaemia', 'otitis', 'conjunctivitis',
            'dermatitis', 'cellulitis', 'abscess', 'wound', 'fracture', 'sprain',
            'arthritis', 'myalgia', 'headache', 'migraine', 'urinary', 'cystitis',
            'pyelonephritis', 'urethritis', 'pregnancy', 'prenatal', 'postpartum',
            'abortion', 'dengue', 'influenza', 'measles', 'mumps', 'varicella',
            'ischemic', 'ischaemic', 'angina', 'stroke', 'hemorrhage', 'haemorrhage',
            'appendicitis', 'cholecystitis', 'nephritis', 'nephropathy', 'retinopathy',
            'obesity', 'malnutrition', 'dehydration', 'sepsis', 'septicemia',
            'anxiety', 'depression', 'psychosis', 'epilepsy', 'convulsion', 'seizure',
            'cough', 'fever', 'vertigo', 'syncope', 'palpitation',
        ];
    }

    /**
     * Lower tier = higher relevance for doctor autocomplete.
     *
     * @return array{0:int,1:int|float,2:int}
     */
    private static function scoreDiagnosisMatch(string $diagnosisName, string $query): array
    {
        $name = mb_strtolower(trim($diagnosisName));
        $q = mb_strtolower(trim($query));
        $nameLen = mb_strlen($name);

        if ($name === $q) {
            return [0, 0, $nameLen];
        }

        // Tokenize on non-letters so "(primary)" and hyphens split cleanly
        $tokens = preg_split('/[^a-z0-9]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $matchedToken = null;
        $matchedTokenIndex = null;
        foreach ($tokens as $i => $token) {
            if (mb_strpos($token, $q) === 0) {
                $matchedToken = $token;
                $matchedTokenIndex = $i;
                break;
            }
        }

        $hasWordPrefix = $matchedToken !== null;
        $nameStartsWith = mb_strpos($name, $q) === 0;

        // Boost when a clinical keyword that starts with the query appears as a word
        $bestKw = null;
        $bestKwScore = null;
        foreach (self::clinicalDiagnosisKeywords() as $kw) {
            $kw = mb_strtolower($kw);
            if (mb_strpos($kw, $q) !== 0) {
                continue;
            }
            foreach ($tokens as $ti => $token) {
                if ($token !== $kw && mb_strpos($token, $kw) !== 0) {
                    continue;
                }
                // Prefer shorter/exact keywords (hypertension over hypersensitivity)
                $kwScore = mb_strlen($kw) * 100 + $ti;
                if ($bestKwScore === null || $kwScore < $bestKwScore) {
                    $bestKw = $kw;
                    $bestKwScore = $kwScore;
                }
                break;
            }
        }

        if ($bestKw !== null) {
            // Clinical keyword beat alphabetical Hyper* dump
            $secondary = (int) $bestKwScore;

            // Prefer primary outpatient forms (e.g. Essential (primary) hypertension)
            if (preg_match('/\bessential\b/u', $name) && preg_match('/\b' . preg_quote($bestKw, '/') . '\b/u', $name)) {
                $secondary -= 3000;
            } elseif (
                preg_match('/\bprimary\b/u', $name)
                && !preg_match('/\bsecondary\b/u', $name)
                && preg_match('/\b' . preg_quote($bestKw, '/') . '\b/u', $name)
            ) {
                $secondary -= 2000;
            } elseif (preg_match('/\bunspecified\b/u', $name)) {
                $secondary -= 400;
            }

            // Prefer shorter titles within the same keyword family
            $secondary += $nameLen;

            return [1, $secondary, $nameLen];
        }

        if ($hasWordPrefix && !$nameStartsWith) {
            // Word-boundary match later in the name
            $tokenLen = mb_strlen((string) $matchedToken);
            return [2, -$tokenLen * 100 + (int) $matchedTokenIndex, $nameLen];
        }

        if ($nameStartsWith && $hasWordPrefix) {
            // Name begins with query (Hyperaldosteronism…) — after clinical keywords
            $tokenLen = mb_strlen((string) $matchedToken);
            return [3, $tokenLen, $nameLen];
        }

        // Mid-token substring only
        $pos = mb_strpos($name, $q);
        return [4, $pos === false ? 9999 : $pos, $nameLen];
    }

    /**
     * @param array<int, array<string, mixed>> $byId
     */
    private static function collectDiagnosisRows(mysqli $conn, string $likePattern, int $rowLimit, array &$byId): void
    {
        if ($rowLimit < 1) {
            return;
        }
        $sql = "SELECT id, icd_code, diagnosis_name
                FROM diagnosis_master
                WHERE status = 'active'
                  AND diagnosis_name LIKE ?
                LIMIT {$rowLimit}";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $likePattern);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = (int) $row['id'];
            if (!isset($byId[$id])) {
                $byId[$id] = $row;
            }
        }
        $stmt->close();
    }

    public static function searchDiagnosis(mysqli $conn, string $query, int $limit = 20): array
    {
        self::ensureSchema($conn);
        $total = self::countActive($conn, 'diagnosis');
        if ($total === 0) {
            return [
                'empty_dataset' => true,
                'message' => 'No diagnosis dataset found. Please import the ICD-10 diagnosis dataset.',
                'results' => [],
            ];
        }

        $q = trim($query);
        if ($q === '' || strlen($q) < 1) {
            return ['empty_dataset' => false, 'message' => null, 'results' => []];
        }

        $limit = max(1, min(50, $limit));
        $qLower = mb_strtolower($q);
        $byId = [];

        // Always pull rows that contain clinical keywords matching this prefix
        // (so "Essential (primary) hypertension" is not crowded out by Hyper*).
        foreach (self::clinicalDiagnosisKeywords() as $kw) {
            if (mb_strpos(mb_strtolower($kw), $qLower) !== 0) {
                continue;
            }
            $kwLike = '%' . self::escapeLike($kw) . '%';
            self::collectDiagnosisRows($conn, $kwLike, 40, $byId);
        }

        // Broader name contains pool
        $like = '%' . self::escapeLike($q) . '%';
        $candidateLimit = max(80, min(250, $limit * 12));
        self::collectDiagnosisRows($conn, $like, $candidateLimit, $byId);

        $scored = [];
        foreach ($byId as $row) {
            $score = self::scoreDiagnosisMatch((string) $row['diagnosis_name'], $q);
            $scored[] = [
                'row' => $row,
                'tier' => $score[0],
                'secondary' => $score[1],
                'len' => $score[2],
                'name' => (string) $row['diagnosis_name'],
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            return [$a['tier'], $a['secondary'], $a['len'], $a['name']]
                <=> [$b['tier'], $b['secondary'], $b['len'], $b['name']];
        });

        $results = [];
        foreach (array_slice($scored, 0, $limit) as $item) {
            $row = $item['row'];
            $results[] = [
                'id' => (int) $row['id'],
                'icd_code' => $row['icd_code'],
                'diagnosis_name' => $row['diagnosis_name'],
                'label' => self::formatDiagnosisLabel($row),
            ];
        }

        return ['empty_dataset' => false, 'message' => null, 'results' => $results];
    }

    /**
     * @return array{empty_dataset:bool,message:?string,results:list<array>}
     */
    public static function searchMedication(mysqli $conn, string $query, int $limit = 20): array
    {
        self::ensureSchema($conn);
        $total = self::countActive($conn, 'medication');
        if ($total === 0) {
            return [
                'empty_dataset' => true,
                'message' => 'No medication dataset found. Please import the medication dataset.',
                'results' => [],
            ];
        }

        $q = trim($query);
        if ($q === '' || strlen($q) < 1) {
            return ['empty_dataset' => false, 'message' => null, 'results' => []];
        }

        $limit = max(1, min(50, $limit));
        $like = '%' . self::escapeLike($q) . '%';
        $prefix = self::escapeLike($q) . '%';

        $sql = "SELECT id, generic_name, brand_name, strength, dosage_form
                FROM medication_master
                WHERE status = 'active'
                  AND (generic_name LIKE ? OR brand_name LIKE ? OR strength LIKE ? OR dosage_form LIKE ?)
                ORDER BY
                  CASE
                    WHEN generic_name LIKE ? THEN 0
                    WHEN brand_name LIKE ? THEN 1
                    ELSE 2
                  END,
                  generic_name ASC, strength ASC, dosage_form ASC
                LIMIT {$limit}";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssss', $like, $like, $like, $like, $prefix, $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        $results = [];
        while ($row = $res->fetch_assoc()) {
            $results[] = [
                'id' => (int) $row['id'],
                'generic_name' => $row['generic_name'],
                'brand_name' => $row['brand_name'],
                'strength' => $row['strength'],
                'dosage_form' => $row['dosage_form'],
                'label' => self::formatMedicationLabel($row),
            ];
        }
        $stmt->close();

        return ['empty_dataset' => false, 'message' => null, 'results' => $results];
    }

    /** True when every non-empty line matches an active diagnosis master label/name/code. */
    public static function validateDiagnosisValue(mysqli $conn, ?string $value): bool
    {
        self::ensureSchema($conn);
        $lines = self::splitLines($value);
        if ($lines === []) {
            return true;
        }
        foreach ($lines as $line) {
            if (!self::isValidDiagnosisLabel($conn, $line)) {
                return false;
            }
        }
        return true;
    }

    public static function validateMedicationValue(mysqli $conn, ?string $value): bool
    {
        self::ensureSchema($conn);
        $lines = self::splitLines($value);
        if ($lines === []) {
            return true;
        }
        foreach ($lines as $line) {
            if (!self::isValidMedicationLabel($conn, $line)) {
                return false;
            }
        }
        return true;
    }

    public static function isValidDiagnosisLabel(mysqli $conn, string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        // Exact label: "Name (CODE)" or name only
        $stmt = $conn->prepare(
            "SELECT id FROM diagnosis_master
             WHERE status = 'active'
               AND (
                 diagnosis_name = ?
                 OR CONCAT(diagnosis_name, ' (', IFNULL(icd_code, ''), ')') = ?
                 OR icd_code = ?
               )
             LIMIT 1"
        );
        $stmt->bind_param('sss', $label, $label, $label);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    public static function isValidMedicationLabel(mysqli $conn, string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        $stmt = $conn->prepare(
            "SELECT id, generic_name, brand_name, strength, dosage_form
             FROM medication_master WHERE status = 'active'"
        );
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (strcasecmp(self::formatMedicationLabel($row), $label) === 0) {
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
        return false;
    }

    /** @return list<string> */
    public static function splitLines(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        $parts = preg_split('/[\r\n;|]+/', $value) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return list<int> */
    public static function parseIdList(mixed $raw): array
    {
        if (is_array($raw)) {
            $ids = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                $ids = preg_split('/[\s,;]+/', $raw) ?: [];
            }
        } else {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }

    public static function validateDiagnosisIds(mysqli $conn, array $ids): bool
    {
        self::ensureSchema($conn);
        if ($ids === []) {
            return true;
        }
        foreach ($ids as $id) {
            if (!self::diagnosisExists($conn, (int) $id)) {
                return false;
            }
        }
        return true;
    }

    public static function validateMedicationIds(mysqli $conn, array $ids): bool
    {
        self::ensureSchema($conn);
        if ($ids === []) {
            return true;
        }
        foreach ($ids as $id) {
            if (!self::medicationExists($conn, (int) $id)) {
                return false;
            }
        }
        return true;
    }

    public static function diagnosisExists(mysqli $conn, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = $conn->prepare("SELECT id FROM diagnosis_master WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    public static function medicationExists(mysqli $conn, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = $conn->prepare("SELECT id FROM medication_master WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    /** Replace all diagnosis links for a consultation (one row per selected chip). */
    public static function syncConsultationDiagnoses(mysqli $conn, int $consultationId, array $diagnosisIds): void
    {
        self::ensureSchema($conn);
        if ($consultationId <= 0) {
            return;
        }

        $stmt = $conn->prepare('DELETE FROM consultation_diagnoses WHERE consultation_id = ?');
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $stmt->close();

        $ids = self::parseIdList($diagnosisIds);
        if ($ids === []) {
            return;
        }

        $ins = $conn->prepare(
            'INSERT INTO consultation_diagnoses (consultation_id, diagnosis_id) VALUES (?, ?)'
        );
        foreach ($ids as $diagnosisId) {
            if (!self::diagnosisExists($conn, $diagnosisId)) {
                continue;
            }
            $ins->bind_param('ii', $consultationId, $diagnosisId);
            $ins->execute();
        }
        $ins->close();
    }

    /** Replace all medication links for a consultation (one row per selected chip). */
    public static function syncConsultationMedications(mysqli $conn, int $consultationId, array $medicationIds): void
    {
        self::ensureSchema($conn);
        if ($consultationId <= 0) {
            return;
        }

        $stmt = $conn->prepare('DELETE FROM consultation_medications WHERE consultation_id = ?');
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $stmt->close();

        $ids = self::parseIdList($medicationIds);
        if ($ids === []) {
            return;
        }

        $ins = $conn->prepare(
            'INSERT INTO consultation_medications (consultation_id, medication_id) VALUES (?, ?)'
        );
        foreach ($ids as $medicationId) {
            if (!self::medicationExists($conn, $medicationId)) {
                continue;
            }
            $ins->bind_param('ii', $consultationId, $medicationId);
            $ins->execute();
        }
        $ins->close();
    }

    /**
     * @return list<array{id:int,label:string,icd_code:?string,diagnosis_name:string}>
     */
    public static function getConsultationDiagnoses(mysqli $conn, int $consultationId): array
    {
        self::ensureSchema($conn);
        if ($consultationId <= 0) {
            return [];
        }

        $sql = "SELECT dm.id, dm.icd_code, dm.diagnosis_name
                FROM consultation_diagnoses cd
                INNER JOIN diagnosis_master dm ON dm.id = cd.diagnosis_id
                WHERE cd.consultation_id = ?
                ORDER BY cd.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int) $row['id'],
                'icd_code' => $row['icd_code'],
                'diagnosis_name' => $row['diagnosis_name'],
                'label' => self::formatDiagnosisLabel($row),
            ];
        }
        $stmt->close();
        return $out;
    }

    /**
     * @return list<array{id:int,label:string,generic_name:string,brand_name:?string,strength:?string,dosage_form:?string}>
     */
    public static function getConsultationMedications(mysqli $conn, int $consultationId): array
    {
        self::ensureSchema($conn);
        if ($consultationId <= 0) {
            return [];
        }

        $sql = "SELECT mm.id, mm.generic_name, mm.brand_name, mm.strength, mm.dosage_form
                FROM consultation_medications cm
                INNER JOIN medication_master mm ON mm.id = cm.medication_id
                WHERE cm.consultation_id = ?
                ORDER BY cm.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int) $row['id'],
                'generic_name' => $row['generic_name'],
                'brand_name' => $row['brand_name'],
                'strength' => $row['strength'],
                'dosage_form' => $row['dosage_form'],
                'label' => self::formatMedicationLabel($row),
            ];
        }
        $stmt->close();
        return $out;
    }

    public static function diagnosisLabelsText(array $rows): string
    {
        return implode("\n", array_map(static fn($r) => (string) ($r['label'] ?? ''), $rows));
    }

    public static function medicationLabelsText(array $rows): string
    {
        return implode("\n", array_map(static fn($r) => (string) ($r['label'] ?? ''), $rows));
    }

    public static function hasConsultationDiagnoses(mysqli $conn, int $consultationId): bool
    {
        if ($consultationId <= 0) {
            return false;
        }
        self::ensureSchema($conn);
        $stmt = $conn->prepare('SELECT 1 FROM consultation_diagnoses WHERE consultation_id = ? LIMIT 1');
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    public static function hasConsultationMedications(mysqli $conn, int $consultationId): bool
    {
        if ($consultationId <= 0) {
            return false;
        }
        self::ensureSchema($conn);
        $stmt = $conn->prepare('SELECT 1 FROM consultation_medications WHERE consultation_id = ? LIMIT 1');
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    /**
     * Hydrate treatment row for UI/reports: join master labels into display fields.
     * Prefer normalized relations; fall back to legacy text columns.
     *
     * @param array<string, mixed> $treatment
     * @return array{treatment:array,diagnoses:list,medications:list}
     */
    public static function hydrateTreatmentClinical(mysqli $conn, array $treatment): array
    {
        self::ensureSchema($conn);
        $consultationId = (int) ($treatment['id'] ?? 0);
        $diagnoses = $consultationId > 0 ? self::getConsultationDiagnoses($conn, $consultationId) : [];
        $medications = $consultationId > 0 ? self::getConsultationMedications($conn, $consultationId) : [];

        // One-time style fallback: if relations empty but legacy text exists, try resolve for display chips
        if ($diagnoses === [] && !empty($treatment['diagnosis'])) {
            $diagnoses = self::resolveDiagnosesFromLabels($conn, (string) $treatment['diagnosis']);
        }
        if ($medications === [] && !empty($treatment['medication_treatment'])) {
            $medications = self::resolveMedicationsFromLabels($conn, (string) $treatment['medication_treatment']);
        }

        if ($diagnoses !== []) {
            $treatment['diagnosis'] = self::diagnosisLabelsText($diagnoses);
        }
        if ($medications !== []) {
            $treatment['medication_treatment'] = self::medicationLabelsText($medications);
        }

        return [
            'treatment' => $treatment,
            'diagnoses' => $diagnoses,
            'medications' => $medications,
        ];
    }

    /** @return list<array{id:int,label:string}> */
    public static function resolveDiagnosesFromLabels(mysqli $conn, string $text): array
    {
        $out = [];
        foreach (self::splitLines($text) as $label) {
            $id = self::findDiagnosisIdByLabel($conn, $label);
            if ($id > 0) {
                $stmt = $conn->prepare('SELECT id, icd_code, diagnosis_name FROM diagnosis_master WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $out[] = [
                        'id' => (int) $row['id'],
                        'icd_code' => $row['icd_code'],
                        'diagnosis_name' => $row['diagnosis_name'],
                        'label' => self::formatDiagnosisLabel($row),
                    ];
                }
            } else {
                $out[] = ['id' => 0, 'label' => $label, 'icd_code' => null, 'diagnosis_name' => $label];
            }
        }
        return $out;
    }

    /** @return list<array{id:int,label:string}> */
    public static function resolveMedicationsFromLabels(mysqli $conn, string $text): array
    {
        $out = [];
        foreach (self::splitLines($text) as $label) {
            $id = self::findMedicationIdByLabel($conn, $label);
            if ($id > 0) {
                $stmt = $conn->prepare(
                    'SELECT id, generic_name, brand_name, strength, dosage_form FROM medication_master WHERE id = ?'
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $out[] = [
                        'id' => (int) $row['id'],
                        'generic_name' => $row['generic_name'],
                        'brand_name' => $row['brand_name'],
                        'strength' => $row['strength'],
                        'dosage_form' => $row['dosage_form'],
                        'label' => self::formatMedicationLabel($row),
                    ];
                }
            } else {
                $out[] = [
                    'id' => 0,
                    'label' => $label,
                    'generic_name' => $label,
                    'brand_name' => null,
                    'strength' => null,
                    'dosage_form' => null,
                ];
            }
        }
        return $out;
    }

    public static function findDiagnosisIdByLabel(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        $stmt = $conn->prepare(
            "SELECT id FROM diagnosis_master
             WHERE status = 'active'
               AND (
                 diagnosis_name = ?
                 OR CONCAT(diagnosis_name, ' (', IFNULL(icd_code, ''), ')') = ?
                 OR icd_code = ?
               )
             LIMIT 1"
        );
        $stmt->bind_param('sss', $label, $label, $label);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['id'] : 0;
    }

    public static function findMedicationIdByLabel(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        $stmt = $conn->prepare(
            "SELECT id, generic_name, brand_name, strength, dosage_form
             FROM medication_master WHERE status = 'active'"
        );
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (strcasecmp(self::formatMedicationLabel($row), $label) === 0) {
                $stmt->close();
                return (int) $row['id'];
            }
        }
        $stmt->close();
        return 0;
    }

    /**
     * Resolve posted chip IDs; if missing, derive from label text (legacy UI payloads).
     * @return list<int>
     */
    /**
     * Resolve diagnosis IDs from clinical-notes textarea (one official name per line).
     * @return list<int>
     */
    public static function resolveDiagnosisIdsFromPost(mysqli $conn, array $post): array
    {
        $fromLabels = [];
        $unresolved = [];
        foreach (self::splitLines($post['diagnosis'] ?? '') as $label) {
            $id = self::findDiagnosisIdByLabel($conn, $label);
            if ($id > 0) {
                $fromLabels[$id] = $id;
            } else {
                $unresolved[] = $label;
            }
        }
        if ($unresolved !== []) {
            return []; // signal invalid free-typed lines to caller
        }
        if ($fromLabels !== []) {
            return array_values($fromLabels);
        }
        // Fallback when only IDs were posted (legacy chip UI)
        return self::parseIdList($post['diagnosis_ids'] ?? null);
    }

    /**
     * @return list<string> unresolved diagnosis lines (empty if all valid / blank)
     */
    public static function unresolvedDiagnosisLines(mysqli $conn, array $post): array
    {
        $unresolved = [];
        foreach (self::splitLines($post['diagnosis'] ?? '') as $label) {
            if (self::findDiagnosisIdByLabel($conn, $label) <= 0) {
                $unresolved[] = $label;
            }
        }
        return $unresolved;
    }

    /**
     * Resolve medication IDs from clinical-notes textarea (one official label per line).
     * @return list<int>
     */
    public static function resolveMedicationIdsFromPost(mysqli $conn, array $post): array
    {
        $fromLabels = [];
        foreach (self::splitLines($post['medication_treatment'] ?? '') as $label) {
            $id = self::findMedicationIdByLabel($conn, $label);
            if ($id > 0) {
                $fromLabels[$id] = $id;
            }
        }
        if ($fromLabels !== []) {
            return array_values($fromLabels);
        }
        return self::parseIdList($post['medication_ids'] ?? null);
    }

    /**
     * @return list<string> unresolved medication lines
     */
    public static function unresolvedMedicationLines(mysqli $conn, array $post): array
    {
        $unresolved = [];
        foreach (self::splitLines($post['medication_treatment'] ?? '') as $label) {
            if (self::findMedicationIdByLabel($conn, $label) <= 0) {
                $unresolved[] = $label;
            }
        }
        return $unresolved;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
