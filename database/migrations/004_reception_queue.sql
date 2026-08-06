-- Reception desk queue — Magallanes RHU (receptionist role only).

CREATE TABLE IF NOT EXISTS reception_queue (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT          NOT NULL,
    queue_date      DATE         NOT NULL,
    queue_number    INT          DEFAULT NULL,
    desk_status     ENUM('waiting','called','skipped','done','sent_to_doctor') NOT NULL DEFAULT 'waiting',
    called_at       DATETIME     DEFAULT NULL,
    skipped_at      DATETIME     DEFAULT NULL,
    done_at         DATETIME     DEFAULT NULL,
    sent_at         DATETIME     DEFAULT NULL,
    notes           VARCHAR(255) DEFAULT NULL,
    created_by      INT          DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_reception_patient_day (patient_id, queue_date),
    INDEX idx_reception_day_status (queue_date, desk_status),
    INDEX idx_reception_day_number (queue_date, queue_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
