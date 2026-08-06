-- =============================================================================
-- Import official ICD-10 (master_icd_x / table `icds`) → diagnosis_master
-- Compatible with: MySQL / MariaDB / XAMPP / phpMyAdmin
-- =============================================================================
--
-- HOW TO RUN (phpMyAdmin)
-- -----------------------------------------------------------------------------
-- 1. Open phpMyAdmin → select database: rhu_management
-- 2. Import dump first (creates staging table `icds`):
--      database/imports/icd10/icd-10-master/master_icd_x.sql
-- 3. Then Import / run THIS script:
--      database/migrations/007_import_icd10_to_diagnosis_master.sql
-- 4. Verify counts (see SELECT at bottom).
-- 5. Optional cleanup: DROP TABLE IF EXISTS icds;
--
-- Mapping
-- -----------------------------------------------------------------------------
--   icds.code    → diagnosis_master.icd_code
--   icds.name_en → diagnosis_master.diagnosis_name  (exact, unmodified)
--   icds.name_id → IGNORED
--   status       = 'active'
--   created_at   = NOW()   (on insert)
--   updated_at   = NOW()
--
-- Notes
-- -----------------------------------------------------------------------------
-- • Preserves all official ICD-10 rows from `icds` (~10,000+).
-- • Does not invent / fake diagnoses.
-- • Upserts by icd_code so existing consultation_diagnoses links stay valid
--   when the same ICD code already exists (starter seed rows are updated to
--   the official English name).
-- =============================================================================

SET NAMES utf8mb4;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Ensure target table exists (safe if already created by the app)
CREATE TABLE IF NOT EXISTS diagnosis_master (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Require staging table from master_icd_x.sql
-- (If this fails, import master_icd_x.sql into rhu_management first.)
SELECT COUNT(*) AS icds_source_rows FROM icds;

-- Unique ICD code enables safe upsert (ignore error if already present)
SET @uq_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'diagnosis_master'
      AND index_name = 'uq_dx_icd_code'
);
SET @sql := IF(
    @uq_exists = 0,
    'ALTER TABLE diagnosis_master ADD UNIQUE KEY uq_dx_icd_code (icd_code)',
    'SELECT ''uq_dx_icd_code already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

START TRANSACTION;

-- Insert every official ICD-10 diagnosis; update name if code already exists
INSERT INTO diagnosis_master (icd_code, diagnosis_name, status, created_at, updated_at)
SELECT
    TRIM(code) AS icd_code,
    name_en AS diagnosis_name,
    'active' AS status,
    NOW() AS created_at,
    NOW() AS updated_at
FROM icds
WHERE TRIM(IFNULL(code, '')) <> ''
  AND name_en IS NOT NULL
  AND TRIM(name_en) <> ''
ON DUPLICATE KEY UPDATE
    diagnosis_name = VALUES(diagnosis_name),
    status = 'active',
    updated_at = NOW();

COMMIT;

-- Verification
SELECT
    (SELECT COUNT(*) FROM icds) AS source_icds_rows,
    (SELECT COUNT(*) FROM diagnosis_master WHERE status = 'active') AS active_diagnosis_master_rows,
    (SELECT COUNT(*) FROM diagnosis_master WHERE icd_code IS NOT NULL AND icd_code <> '') AS rows_with_icd_code;

-- Spot-check examples
SELECT id, icd_code, diagnosis_name, status
FROM diagnosis_master
WHERE icd_code IN ('I10', 'E11', 'A90', 'J06.9')
ORDER BY icd_code;

-- Optional: remove staging table after a successful import
-- DROP TABLE IF EXISTS icds;
