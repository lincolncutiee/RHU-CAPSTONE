-- Physical paper folder tracking (patient-level only).
-- 0 = Not Recorded, 1 = Recorded

ALTER TABLE patients
    ADD COLUMN physical_recorded TINYINT(1) NOT NULL DEFAULT 0
    AFTER status;
