-- Receptionist queue v2 — desk statuses, A### codes, future-ready columns.
-- Applied automatically by ReceptionQueue::ensureSchema(); this file is for reference / manual runs.

ALTER TABLE reception_queue
    MODIFY COLUMN queue_number VARCHAR(16) NULL,
    MODIFY COLUMN desk_status VARCHAR(32) NOT NULL DEFAULT 'waiting';

UPDATE reception_queue SET desk_status = 'serving'   WHERE desk_status = 'called';
UPDATE reception_queue SET desk_status = 'completed' WHERE desk_status IN ('done', 'sent_to_doctor');

UPDATE reception_queue
SET queue_number = CONCAT('A', LPAD(queue_number, 3, '0'))
WHERE queue_number REGEXP '^[0-9]+$';

-- Run these only if columns do not already exist:
-- ALTER TABLE reception_queue ADD COLUMN serving_at DATETIME NULL AFTER called_at;
-- ALTER TABLE reception_queue ADD COLUMN cancelled_at DATETIME NULL AFTER done_at;
-- ALTER TABLE reception_queue ADD COLUMN assigned_doctor_id INT NULL AFTER notes;
-- ALTER TABLE reception_queue ADD COLUMN lane_type VARCHAR(32) NOT NULL DEFAULT 'regular' AFTER assigned_doctor_id;
