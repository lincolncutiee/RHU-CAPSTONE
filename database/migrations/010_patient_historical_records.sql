-- Historical digitized records (photographed old RHU forms)
CREATE TABLE IF NOT EXISTS patient_historical_records (
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
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
