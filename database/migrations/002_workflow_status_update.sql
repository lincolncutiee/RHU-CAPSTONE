-- Workflow status update — run once on existing rhu_management database
USE rhu_management;

ALTER TABLE patients
    MODIFY status ENUM(
        'Draft',
        'Pending IT Validation',
        'Pending Receptionist',
        'Pending Doctor',
        'Pending Print',
        'Validated',
        'Rejected',
        'Pending IT Review',
        'Ready for Printing',
        'Printed',
        'Archived'
    ) NOT NULL DEFAULT 'Draft';

UPDATE patients SET status = 'Pending Receptionist' WHERE status = 'Pending IT Validation';
UPDATE patients SET status = 'Pending IT Review' WHERE status = 'Pending Print';
UPDATE patients SET status = 'Printed' WHERE status = 'Validated';

ALTER TABLE patients
    MODIFY status ENUM(
        'Draft',
        'Pending Receptionist',
        'Pending Doctor',
        'Pending IT Review',
        'Ready for Printing',
        'Printed',
        'Archived',
        'Rejected'
    ) NOT NULL DEFAULT 'Draft';
