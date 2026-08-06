-- Clinical master datasets + normalized consultation links (offline MySQL).
-- Safe to re-run. Import full ICD-10 / medication CSVs into master tables later.

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

CREATE TABLE IF NOT EXISTS medication_master (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS consultation_diagnoses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    diagnosis_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_consult_diagnosis (consultation_id, diagnosis_id),
    INDEX idx_cd_consultation (consultation_id),
    INDEX idx_cd_diagnosis (diagnosis_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS consultation_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    medication_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_consult_medication (consultation_id, medication_id),
    INDEX idx_cm_consultation (consultation_id),
    INDEX idx_cm_medication (medication_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
