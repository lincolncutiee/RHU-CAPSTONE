<?php
/**
 * Application configuration
 */

define('APP_NAME', 'RHU Management System');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/RHUWEB');

define('SESSION_LIFETIME', 3600 * 8);

define('ROLE_RECORDS', 'records_office');
define('ROLE_IT', 'it_department');
define('ROLE_RECEPTIONIST', 'receptionist');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_PHARMACY', 'pharmacy');
/** System settings / users / templates — not Records, IT, or clinical workflow. */
define('ROLE_SYSTEM_ADMIN', 'system_administrator');

/** Clinical / encoding phase */
define('WORKFLOW_DRAFT', 'Draft');
define('WORKFLOW_PENDING_RECEPTIONIST', 'Pending Receptionist');
/** Doctor consultation queue (legacy DB may still store "Pending Doctor") */
define('WORKFLOW_WAITING_FOR_DOCTOR', 'Waiting for Doctor');
define('WORKFLOW_PENDING_DOCTOR', 'Waiting for Doctor');
/** After doctor sends prescription — Pharmacy queue */
define('WORKFLOW_WAITING_FOR_PHARMACY', 'Waiting for Pharmacy');
define('WORKFLOW_DISPENSING', 'Dispensing');
define('WORKFLOW_DISPENSED', 'Dispensed');

/**
 * IT validation & printing phase
 * Canonical IT queue status label: Pending IT Validation
 * (legacy DB rows may still store "Pending IT Review")
 */
define('WORKFLOW_PENDING_IT_REVIEW', 'Pending IT Validation');
define('WORKFLOW_RETURNED_FOR_CORRECTION', 'Returned for Correction');
define('WORKFLOW_READY_FOR_PRINT', 'Ready for Printing');
define('WORKFLOW_PRINTED', 'Printed');
define('WORKFLOW_ARCHIVED', 'Archived');

/** Legacy — kept for older records; prefer Returned for Correction */
define('WORKFLOW_REJECTED', 'Rejected');

date_default_timezone_set('Asia/Manila');
