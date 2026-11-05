-- Follow-up check-up reminders (SMS / email)

ALTER TABLE patients
    ADD COLUMN IF NOT EXISTS patient_email VARCHAR(255) DEFAULT NULL AFTER contact_number,
    ADD COLUMN IF NOT EXISTS reminder_sms_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER patient_email,
    ADD COLUMN IF NOT EXISTS reminder_email_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER reminder_sms_opt_in;

CREATE TABLE IF NOT EXISTS reminder_settings (
    id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    days_before INT NOT NULL DEFAULT 1,
    facility_phone VARCHAR(40) DEFAULT NULL,
    sms_mode ENUM('log_only','semaphore','generic_http') NOT NULL DEFAULT 'log_only',
    sms_api_url VARCHAR(500) DEFAULT NULL,
    sms_api_key VARCHAR(255) DEFAULT NULL,
    sms_sender_name VARCHAR(20) DEFAULT NULL,
    email_enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(200) DEFAULT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_user VARCHAR(200) DEFAULT NULL,
    smtp_pass VARCHAR(255) DEFAULT NULL,
    smtp_from_email VARCHAR(200) DEFAULT NULL,
    smtp_from_name VARCHAR(150) DEFAULT NULL,
    sms_template TEXT NOT NULL,
    email_subject_template VARCHAR(255) NOT NULL,
    email_body_template TEXT NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    CONSTRAINT fk_reminder_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO reminder_settings (id, sms_template, email_subject_template, email_body_template)
VALUES (
    1,
    'RHU Magallanes: Reminder for {patient_name}. Follow-up check-up on {follow_up_date}. {instructions} Questions? Call {facility_phone}.',
    'Follow-up reminder — {follow_up_date}',
    'Dear {patient_name},\n\nThis is a reminder from {facility_name} for your follow-up check-up on {follow_up_date}.\n\n{instructions}\n\nFor inquiries: {facility_phone}\n\nThank you.'
);

CREATE TABLE IF NOT EXISTS patient_follow_ups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_id INT DEFAULT NULL,
    follow_up_date DATE NOT NULL,
    instructions TEXT DEFAULT NULL,
    channel ENUM('sms','email','both') NOT NULL DEFAULT 'sms',
    send_reminder TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('scheduled','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_follow_up_consultation (consultation_id),
    INDEX idx_follow_up_patient (patient_id),
    INDEX idx_follow_up_date (follow_up_date, status),
    CONSTRAINT fk_follow_up_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_up_consultation FOREIGN KEY (consultation_id) REFERENCES treatment_records(id) ON DELETE SET NULL,
    CONSTRAINT fk_follow_up_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_reminder_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    follow_up_id INT NOT NULL,
    patient_id INT NOT NULL,
    channel ENUM('sms','email') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT NOT NULL,
    status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    scheduled_for DATETIME NOT NULL,
    sent_at DATETIME DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reminder_pending (status, scheduled_for),
    INDEX idx_reminder_patient (patient_id),
    CONSTRAINT fk_reminder_follow_up FOREIGN KEY (follow_up_id) REFERENCES patient_follow_ups(id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;
