-- ============================================================
-- RHU Management System — Complete Database Schema
-- Supports: roles, auth, DOH Form 1 & 2, workflow, expansion
-- ============================================================

CREATE DATABASE IF NOT EXISTS rhu_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE rhu_management;

-- Drop old simplified table if migrating
DROP TABLE IF EXISTS workflow_logs;
DROP TABLE IF EXISTS treatment_records;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- ------------------------------------------------------------
-- ROLES & USERS
-- ------------------------------------------------------------
CREATE TABLE roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(50)  NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    description TEXT         DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    role_id       INT          NOT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(150) NOT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_role (role_id),
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PATIENTS — Form 1 (Patient Enrollment Record)
-- Encoded once, reused across all modules
-- ------------------------------------------------------------
CREATE TABLE patients (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    patient_id              VARCHAR(20)  NOT NULL UNIQUE,

    -- Administrative
    family_serial_number    VARCHAR(20)  DEFAULT NULL,
    facility_code           VARCHAR(20)  DEFAULT NULL,

    -- Section I: Patient Information
    last_name               VARCHAR(100) NOT NULL,
    first_name              VARCHAR(100) NOT NULL,
    middle_name             VARCHAR(100) DEFAULT NULL,
    suffix                  VARCHAR(20)  DEFAULT NULL,
    maiden_name             VARCHAR(100) DEFAULT NULL,
    sex                     ENUM('Female', 'Male') NOT NULL,
    birth_date              DATE         NOT NULL,
    birthplace              VARCHAR(200) DEFAULT NULL,
    blood_type              VARCHAR(10)  DEFAULT NULL,
    mothers_name            VARCHAR(200) DEFAULT NULL,
    residential_address     TEXT         DEFAULT NULL,
    civil_status            ENUM('Single','Married','Widower','Widow','Separated','Annulled','Co-Habitation') DEFAULT NULL,
    contact_number          VARCHAR(20)  DEFAULT NULL,

    -- Socio-economic
    dswd_nhts               ENUM('Yes','No') DEFAULT NULL,
    facility_household_no   VARCHAR(50)  DEFAULT NULL,
    four_ps_member          ENUM('Yes','No') DEFAULT NULL,
    household_no            VARCHAR(50)  DEFAULT NULL,
    philhealth_member       ENUM('Yes','No') DEFAULT NULL,
    philhealth_status_type  ENUM('Member','Dependent') DEFAULT NULL,
    philhealth_no           VARCHAR(30)  DEFAULT NULL,
    philhealth_category     ENUM('FE-Private','FE-Government') DEFAULT NULL,
    educational_attainment  ENUM('No Formal Education','Elementary','Highschool','High School','Vocational','Post Graduate') DEFAULT NULL,
    employment_status       ENUM('Student','Employed','Unemployed','Unknown','Retired','Others') DEFAULT NULL,
    specific_occupation     VARCHAR(150) DEFAULT NULL,
    employment_type         ENUM('CONTRACTUAL','CASUAL','PERMANENT') DEFAULT NULL,
    spouse_name             VARCHAR(200) DEFAULT NULL,

    -- Family members
    father_name             VARCHAR(150) DEFAULT NULL,
    mother_name             VARCHAR(150) DEFAULT NULL,
    son_name                VARCHAR(150) DEFAULT NULL,
    daughter_name           VARCHAR(150) DEFAULT NULL,
    other_family_member     VARCHAR(150) DEFAULT NULL,
    other_family_ie         TINYINT(1)   DEFAULT 0,
    pcb_member              ENUM('Yes','No') DEFAULT NULL,

    -- Section II: Patient Consent
    consent_signature_date  DATE         DEFAULT NULL,
    chu_representative      VARCHAR(200) DEFAULT NULL,

    -- Workflow
    status                  ENUM('Draft','Pending Receptionist','Pending Doctor','Pending IT Review','Pending IT Validation','Returned for Correction','Ready for Printing','Printed','Archived','Rejected') NOT NULL DEFAULT 'Draft',
    rejection_reason        TEXT         DEFAULT NULL,

    -- Metadata
    encoded_by              INT          DEFAULT NULL,
    validated_by            INT          DEFAULT NULL,
    date_encoded            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_archived             TINYINT(1)   NOT NULL DEFAULT 0,

    FOREIGN KEY (encoded_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_name (last_name, first_name),
    INDEX idx_contact (contact_number),
    INDEX idx_status (status),
    INDEX idx_date_encoded (date_encoded)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TREATMENT RECORDS — Form 2 (Individual Treatment Record)
-- ------------------------------------------------------------
CREATE TABLE treatment_records (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    patient_id              INT          NOT NULL,

    -- Section I (auto-populated from patient, stored for print)
    -- Section II: Records Office fields
    mode_of_transaction     ENUM('Walk-in','Visited','Referral') DEFAULT NULL,
    date_of_consultation    DATE         DEFAULT NULL,
    consultation_time       TIME         DEFAULT NULL,
    consultation_period     ENUM('AM','PM') DEFAULT NULL,
    blood_pressure          VARCHAR(20)  DEFAULT NULL,
    temperature             VARCHAR(10)  DEFAULT NULL,
    height_cm               DECIMAL(5,1) DEFAULT NULL,
    weight_kg               DECIMAL(5,1) DEFAULT NULL,
    attending_provider      VARCHAR(200) DEFAULT NULL,
    nature_of_visit         ENUM('New Consultation/Case','New Admission','Follow-up Visit') DEFAULT NULL,

    -- Consultation types (stored as JSON array of selected types)
    consultation_types      JSON         DEFAULT NULL,
    chief_complaints        TEXT         DEFAULT NULL,

    -- Referral
    referred_from           VARCHAR(200) DEFAULT NULL,
    referred_to             VARCHAR(200) DEFAULT NULL,
    referral_reasons        TEXT         DEFAULT NULL,
    referred_by             VARCHAR(200) DEFAULT NULL,

    -- Doctor fields
    diagnosis               TEXT         DEFAULT NULL,
    medication_treatment    TEXT         DEFAULT NULL,
    healthcare_provider     VARCHAR(200) DEFAULT NULL,
    laboratory_findings     TEXT         DEFAULT NULL,
    performed_lab_test      TEXT         DEFAULT NULL,

    -- Reserved for future modules (IT, Receptionist)
    it_data                 JSON         DEFAULT NULL,
    receptionist_data       JSON         DEFAULT NULL,

    created_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient (patient_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- WORKFLOW LOG — Audit trail
-- ------------------------------------------------------------
CREATE TABLE workflow_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT          NOT NULL,
    user_id     INT          DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    from_status VARCHAR(50)  DEFAULT NULL,
    to_status   VARCHAR(50)  DEFAULT NULL,
    remarks     TEXT         DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SEED DATA
-- ------------------------------------------------------------
INSERT INTO roles (slug, name, description) VALUES
('records_office', 'Records Office Staff', 'Encodes patient enrollment and treatment record data'),
('it_department',  'IT Department',        'Validates records and prints final DOH forms'),
('receptionist',   'Receptionist',         'Reception and queue management'),
('doctor',         'Doctor',               'Encodes clinical findings and diagnosis');

-- Default password for all demo users: password123
INSERT INTO users (role_id, username, password_hash, full_name, email) VALUES
(1, 'records',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz',  'records@rhu.local'),
(2, 'it',          '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Lopez',     'it@rhu.local'),
(3, 'receptionist','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Santos',      'reception@rhu.local'),
(4, 'doctor',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Pedro Reyes', 'doctor@rhu.local');

-- Sample patients
INSERT INTO patients (patient_id, family_serial_number, facility_code, last_name, first_name, middle_name, suffix, sex, birth_date, birthplace, blood_type, mothers_name, residential_address, civil_status, contact_number, dswd_nhts, four_ps_member, philhealth_member, philhealth_status_type, philhealth_no, educational_attainment, employment_status, spouse_name, father_name, mother_name, pcb_member, consent_signature_date, chu_representative, status, encoded_by, date_encoded) VALUES
('RHU-2026-0001', '1234567', 'RHU-01', 'Cruz', 'Maria', 'Santos', NULL, 'Female', '1985-03-15', 'Poblacion', 'O+', 'Rosa Santos', '123 Rizal St., Poblacion', 'Married', '09171234567', 'No', 'No', 'Yes', 'Member', '12-345678901-2', 'Highschool', 'Employed', 'Jose Cruz', 'Pedro Cruz', 'Rosa Santos', 'Yes', '2026-01-10', 'Juan Dela Cruz', 'Printed', 1, '2026-01-10 09:30:00'),
('RHU-2026-0002', '2345678', 'RHU-01', 'Reyes', 'Pedro', 'Garcia', 'Jr.', 'Male', '1990-07-22', 'San Jose', 'A+', 'Carmen Garcia', '45 Mabini Ave., San Jose', 'Single', '09189876543', 'Yes', 'Yes', 'Yes', 'Member', '12-987654321-0', 'Elementary', 'Unemployed', NULL, 'Antonio Reyes', 'Carmen Garcia', 'No', '2026-01-15', 'Juan Dela Cruz', 'Pending IT Review', 1, '2026-01-15 14:20:00'),
('RHU-2026-0003', '3456789', 'RHU-01', 'Mendoza', 'Rosa', 'Luna', NULL, 'Female', '1978-11-08', 'Poblacion', 'B+', 'Elena Luna', '78 Bonifacio Rd., Poblacion', 'Widow', '09205551234', 'No', 'No', 'No', NULL, NULL, 'Vocational', 'Retired', NULL, 'Miguel Mendoza', 'Elena Luna', 'No', NULL, NULL, 'Draft', 1, '2026-02-01 10:00:00');

INSERT INTO treatment_records (patient_id, mode_of_transaction, date_of_consultation, consultation_time, consultation_period, blood_pressure, temperature, height_cm, weight_kg, attending_provider, nature_of_visit, consultation_types, chief_complaints, diagnosis, medication_treatment, healthcare_provider, laboratory_findings, performed_lab_test) VALUES
(1, 'Walk-in', '2026-01-10', '09:30:00', 'AM', '120/80', '36.5', 160.0, 55.0, 'Dr. Pedro Reyes', 'New Consultation/Case', '["General"]', 'Headache and mild fever for 2 days', 'Acute upper respiratory infection', 'Paracetamol 500mg q6h PRN, increased fluid intake', 'Dr. Pedro Reyes', 'CBC within normal limits', 'Complete Blood Count'),
(2, 'Walk-in', '2026-01-15', '14:00:00', 'PM', '130/85', '37.0', 175.0, 70.0, 'Dr. Pedro Reyes', 'Follow-up Visit', '["General","Immunization"]', 'Follow-up for previous consultation', NULL, NULL, NULL, NULL, NULL),
(3, 'Visited', '2026-02-01', '10:00:00', 'AM', '110/70', '36.8', 155.0, 52.0, 'Midwife Santos', 'New Consultation/Case', '["Prenatal"]', 'Prenatal check-up, 20 weeks gestation', NULL, NULL, NULL, NULL, NULL);
