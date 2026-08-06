<?php
/**
 * Import official ICD-10 from master_icd_x.sql → staging `icds` → diagnosis_master
 * Mapping: code→icd_code, name_en→diagnosis_name (exact), name_id ignored.
 *
 * Run: c:\xampp\php\php.exe database/imports/run_icd10_import.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../core/ClinicalMaster.php';

$sqlDump = __DIR__ . '/icd10/icd-10-master/master_icd_x.sql';

$report = ['errors' => [], 'steps' => []];

$conn = getDBConnection();
$report['steps'][] = 'Connected to database: ' . DB_NAME;

ClinicalMaster::ensureSchema($conn);
$report['steps'][] = 'Ensured clinical master schema';

$before = (int) ($conn->query('SELECT COUNT(*) AS c FROM diagnosis_master')->fetch_assoc()['c'] ?? 0);
$report['steps'][] = "diagnosis_master rows before: {$before}";

if (!is_file($sqlDump)) {
    $report['errors'][] = 'SQL dump not found: ' . $sqlDump;
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

// Create temporary staging table matching the official dump
$conn->query('DROP TABLE IF EXISTS icds');
if (!$conn->query(
    "CREATE TABLE icds (
        code VARCHAR(255) NOT NULL,
        name_en TEXT NOT NULL,
        name_id TEXT NOT NULL,
        PRIMARY KEY (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
)) {
    $report['errors'][] = 'Create icds failed: ' . $conn->error;
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
$report['steps'][] = 'Created temporary table icds (from master_icd_x.sql structure)';

/**
 * Parse one SQL string literal ending at unescaped quote.
 * @return array{0:string,1:int}|null [value, nextOffset]
 */
function parseSqlString(string $s, int $offset): ?array
{
    if (!isset($s[$offset]) || $s[$offset] !== "'") {
        return null;
    }
    $i = $offset + 1;
    $len = strlen($s);
    $out = '';
    while ($i < $len) {
        $ch = $s[$i];
        if ($ch === '\\' && $i + 1 < $len) {
            $out .= $s[$i + 1];
            $i += 2;
            continue;
        }
        if ($ch === "'") {
            // SQL escaped quote '' 
            if ($i + 1 < $len && $s[$i + 1] === "'") {
                $out .= "'";
                $i += 2;
                continue;
            }
            return [$out, $i + 1];
        }
        $out .= $ch;
        $i++;
    }
    return null;
}

/** @return list<array{code:string,name_en:string,name_id:string}> */
function parseIcdTuplesFromLine(string $line): array
{
    $rows = [];
    $len = strlen($line);
    $i = 0;
    while ($i < $len) {
        $pos = strpos($line, '(', $i);
        if ($pos === false) {
            break;
        }
        // Expect ('code', 'name_en', 'name_id')
        if (!isset($line[$pos + 1]) || $line[$pos + 1] !== "'") {
            $i = $pos + 1;
            continue;
        }
        $a = parseSqlString($line, $pos + 1);
        if ($a === null) {
            $i = $pos + 1;
            continue;
        }
        [$code, $p] = $a;
        while ($p < $len && ($line[$p] === ' ' || $line[$p] === ',')) {
            $p++;
        }
        $b = parseSqlString($line, $p);
        if ($b === null) {
            $i = $pos + 1;
            continue;
        }
        [$nameEn, $p] = $b;
        while ($p < $len && ($line[$p] === ' ' || $line[$p] === ',')) {
            $p++;
        }
        $c = parseSqlString($line, $p);
        if ($c === null) {
            $i = $pos + 1;
            continue;
        }
        [$nameId, $p] = $c;
        $rows[] = [
            'code' => $code,
            'name_en' => $nameEn,
            'name_id' => $nameId,
        ];
        $i = $p;
    }
    return $rows;
}

$fh = fopen($sqlDump, 'r');
if (!$fh) {
    $report['errors'][] = 'Cannot open SQL dump';
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

$ins = $conn->prepare(
    'INSERT INTO icds (code, name_en, name_id) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE name_en = VALUES(name_en), name_id = VALUES(name_id)'
);
$staged = 0;
$skipped = 0;
$inData = false;

$conn->begin_transaction();
try {
    while (($line = fgets($fh)) !== false) {
        if (stripos($line, 'INSERT INTO `icds`') !== false || stripos($line, 'INSERT INTO icds') !== false) {
            $inData = true;
        }
        if (!$inData) {
            continue;
        }

        foreach (parseIcdTuplesFromLine($line) as $row) {
            $code = trim($row['code']);
            $nameEn = $row['name_en']; // exact official English — do not modify
            $nameId = $row['name_id'];
            if ($code === '' || trim($nameEn) === '') {
                $skipped++;
                continue;
            }
            $ins->bind_param('sss', $code, $nameEn, $nameId);
            if (!$ins->execute()) {
                throw new Exception('icds insert failed for ' . $code . ': ' . $ins->error);
            }
            $staged++;
        }

        if (str_contains($line, ';') && $inData && stripos($line, 'INSERT INTO') === false) {
            // end of INSERT block likely
            if (preg_match('/\);\s*$/', trim($line))) {
                $inData = false;
            }
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fclose($fh);
    $report['errors'][] = $e->getMessage();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
fclose($fh);
$ins->close();

$icdsCount = (int) ($conn->query('SELECT COUNT(*) AS c FROM icds')->fetch_assoc()['c'] ?? 0);
$report['steps'][] = "Imported master_icd_x.sql into icds: staged={$staged}, skipped_empty={$skipped}, COUNT(*)={$icdsCount}";

if ($icdsCount < 10000) {
    $report['errors'][] = "Unexpectedly low icds count ({$icdsCount}). Expected ~10469 official ICD-10 rows.";
}

// Unique key for upsert (007 script)
$uq = $conn->query(
    "SELECT COUNT(1) AS c FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'diagnosis_master'
       AND index_name = 'uq_dx_icd_code'"
);
$hasUnique = $uq && ((int) $uq->fetch_assoc()['c'] > 0);
if (!$hasUnique) {
    if ($conn->query('ALTER TABLE diagnosis_master ADD UNIQUE KEY uq_dx_icd_code (icd_code)')) {
        $hasUnique = true;
        $report['steps'][] = 'Added UNIQUE KEY uq_dx_icd_code';
    } else {
        $report['errors'][] = 'Could not add uq_dx_icd_code: ' . $conn->error;
    }
} else {
    $report['steps'][] = 'UNIQUE KEY uq_dx_icd_code already present';
}

// Execute 007 mapping logic
$conn->begin_transaction();
try {
    if ($hasUnique) {
        $ok = $conn->query(
            "INSERT INTO diagnosis_master (icd_code, diagnosis_name, status, created_at, updated_at)
             SELECT TRIM(code), name_en, 'active', NOW(), NOW()
             FROM icds
             WHERE TRIM(IFNULL(code, '')) <> ''
               AND name_en IS NOT NULL
               AND TRIM(name_en) <> ''
             ON DUPLICATE KEY UPDATE
               diagnosis_name = VALUES(diagnosis_name),
               status = 'active',
               updated_at = NOW()"
        );
        if (!$ok) {
            throw new Exception('007 upsert failed: ' . $conn->error);
        }
        $report['steps'][] = 'Executed 007_import_icd10_to_diagnosis_master mapping (upsert by icd_code)';
    } else {
        $ok = $conn->query(
            "UPDATE diagnosis_master dm
             INNER JOIN icds i ON dm.icd_code = TRIM(i.code)
             SET dm.diagnosis_name = i.name_en, dm.status = 'active', dm.updated_at = NOW()"
        );
        if (!$ok) {
            throw new Exception('007 update failed: ' . $conn->error);
        }
        $ok = $conn->query(
            "INSERT INTO diagnosis_master (icd_code, diagnosis_name, status, created_at, updated_at)
             SELECT TRIM(i.code), i.name_en, 'active', NOW(), NOW()
             FROM icds i
             WHERE TRIM(IFNULL(i.code,'')) <> ''
               AND i.name_en IS NOT NULL AND TRIM(i.name_en) <> ''
               AND NOT EXISTS (SELECT 1 FROM diagnosis_master dm WHERE dm.icd_code = TRIM(i.code))"
        );
        if (!$ok) {
            throw new Exception('007 insert failed: ' . $conn->error);
        }
        $report['steps'][] = 'Executed 007 mapping (UPDATE + INSERT WHERE NOT EXISTS)';
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $report['errors'][] = $e->getMessage();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

$total = (int) ($conn->query('SELECT COUNT(*) AS total_diagnoses FROM diagnosis_master')->fetch_assoc()['total_diagnoses'] ?? 0);
$active = (int) ($conn->query("SELECT COUNT(*) AS c FROM diagnosis_master WHERE status = 'active'")->fetch_assoc()['c'] ?? 0);

$nameMismatch = (int) ($conn->query(
    "SELECT COUNT(*) AS c FROM diagnosis_master dm
     INNER JOIN icds i ON dm.icd_code = i.code
     WHERE BINARY dm.diagnosis_name <> BINARY i.name_en"
)->fetch_assoc()['c'] ?? 0);

$samples = [];
$r = $conn->query(
    "SELECT icd_code, diagnosis_name, status FROM diagnosis_master
     WHERE icd_code IN ('I10','E11','A90','A00.0','J06.9') ORDER BY icd_code"
);
while ($r && ($row = $r->fetch_assoc())) {
    $samples[] = $row;
}

$src = file_get_contents(__DIR__ . '/../../core/ClinicalMaster.php');
$searchesNameOnly = (bool) preg_match('/AND\s+diagnosis_name\s+LIKE\s+\?/i', $src)
    && !preg_match('/diagnosis_name\s+LIKE\s+\?\s+OR\s+icd_code\s+LIKE\s+\?/i', $src);

$hyp = ClinicalMaster::searchDiagnosis($conn, 'hyp', 5);
$byCode = ClinicalMaster::searchDiagnosis($conn, 'I10', 5);

$report['verification'] = [
    'SELECT COUNT(*) AS total_diagnoses' => $total,
    'active_diagnoses' => $active,
    'icds_source_rows' => $icdsCount,
    'all_official_imported' => ($icdsCount > 0 && $active >= $icdsCount),
    'name_mismatches_vs_icds' => $nameMismatch,
    'sample_rows' => $samples,
    'autocomplete_searches_diagnosis_name_only' => $searchesNameOnly,
    'search_hyp_top' => array_column($hyp['results'] ?? [], 'label'),
    'search_I10_as_code_hits' => count($byCode['results'] ?? []),
    'ready_for_testing' => empty($report['errors'])
        && $icdsCount >= 10000
        && $active >= $icdsCount
        && $nameMismatch === 0
        && $searchesNameOnly,
];

$conn->close();
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(empty($report['errors']) ? 0 : 1);
