-- RHU workflow: IT Validation separation + notifications
-- Run once against the RHU database (phpMyAdmin or mysql CLI).

ALTER TABLE patients
  MODIFY COLUMN status ENUM(
    'Draft',
    'Pending Receptionist',
    'Pending Doctor',
    'Pending IT Review',
    'Pending IT Validation',
    'Returned for Correction',
    'Ready for Printing',
    'Printed',
    'Archived',
    'Rejected'
  ) NOT NULL DEFAULT 'Draft';

UPDATE patients
SET status = 'Pending IT Validation'
WHERE status = 'Pending IT Review';

UPDATE patients
SET status = 'Returned for Correction'
WHERE status = 'Rejected' AND rejection_reason IS NOT NULL AND rejection_reason <> '';

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  patient_id INT DEFAULT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
