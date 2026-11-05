<?php

/**
 * Patient follow-up scheduling and SMS/email reminders.
 */
require_once __DIR__ . '/FormTemplate.php';
require_once __DIR__ . '/ItPrintForms.php';

class FollowUpReminder
{
    private static bool $schemaReady = false;

    public static function ensureSchema(mysqli $conn): void
    {
        if (self::$schemaReady) {
            return;
        }

        if (!self::columnExists($conn, 'patients', 'patient_email')) {
            @$conn->query(
                'ALTER TABLE patients ADD COLUMN patient_email VARCHAR(255) DEFAULT NULL AFTER contact_number'
            );
        }
        if (!self::columnExists($conn, 'patients', 'reminder_sms_opt_in')) {
            @$conn->query(
                'ALTER TABLE patients ADD COLUMN reminder_sms_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER patient_email'
            );
        }
        if (!self::columnExists($conn, 'patients', 'reminder_email_opt_in')) {
            @$conn->query(
                'ALTER TABLE patients ADD COLUMN reminder_email_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER reminder_sms_opt_in'
            );
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS reminder_settings (
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
            ) ENGINE=InnoDB"
        );

        $defaults = require __DIR__ . '/../config/follow-up-reminder.php';
        $sms = $conn->real_escape_string($defaults['default_sms']);
        $sub = $conn->real_escape_string($defaults['default_email_subject']);
        $body = $conn->real_escape_string($defaults['default_email_body']);
        $conn->query(
            "INSERT IGNORE INTO reminder_settings (id, sms_template, email_subject_template, email_body_template)
             VALUES (1, '{$sms}', '{$sub}', '{$body}')"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS patient_follow_ups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                consultation_id INT DEFAULT NULL,
                follow_up_date DATE NOT NULL,
                instructions TEXT DEFAULT NULL,
                channel ENUM('sms','email','both') NOT NULL DEFAULT 'email',
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
            ) ENGINE=InnoDB"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS patient_reminder_messages (
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
            ) ENGINE=InnoDB"
        );

        self::$schemaReady = true;
    }

    private static function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $column = preg_replace('/[^a-z0-9_]/i', '', $column);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!$res) {
            return false;
        }
        $ok = $res->num_rows > 0;
        $res->free();
        return $ok;
    }

    /** @return array<string, mixed> */
    public static function settings(mysqli $conn): array
    {
        self::ensureSchema($conn);
        $row = $conn->query('SELECT * FROM reminder_settings WHERE id = 1')->fetch_assoc();
        if (!$row) {
            return self::defaultSettingsPayload();
        }
        unset($row['smtp_pass']);
        $row['has_smtp_pass'] = self::hasSmtpPassword($conn);
        return $row;
    }

    /** @return array<string, mixed> */
    private static function defaultSettingsPayload(): array
    {
        $defaults = require __DIR__ . '/../config/follow-up-reminder.php';
        return [
            'id' => 1,
            'is_enabled' => 0,
            'days_before' => 1,
            'facility_phone' => '',
            'sms_mode' => 'log_only',
            'sms_api_url' => '',
            'sms_api_key' => '',
            'sms_sender_name' => 'RHUMag',
            'email_enabled' => 1,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => 'RHU Magallanes',
            'sms_template' => $defaults['default_sms'],
            'email_subject_template' => $defaults['default_email_subject'],
            'email_body_template' => $defaults['default_email_body'],
            'has_smtp_pass' => false,
        ];
    }

    private static function hasSmtpPassword(mysqli $conn): bool
    {
        $row = $conn->query('SELECT smtp_pass FROM reminder_settings WHERE id = 1')->fetch_assoc();
        return !empty($row['smtp_pass']);
    }

    /** @param array<string, mixed> $input */
    public static function saveSettings(mysqli $conn, array $input, int $actorId): void
    {
        self::ensureSchema($conn);

        $isEnabled = !empty($input['is_enabled']) ? 1 : 0;
        $daysBefore = max(0, min(14, (int) ($input['days_before'] ?? 1)));
        $facilityPhone = trim((string) ($input['facility_phone'] ?? ''));
        $smsMode = (string) ($input['sms_mode'] ?? 'log_only');
        if (!in_array($smsMode, ['log_only', 'semaphore', 'generic_http'], true)) {
            $smsMode = 'log_only';
        }
        $smsApiUrl = trim((string) ($input['sms_api_url'] ?? ''));
        $smsApiKey = trim((string) ($input['sms_api_key'] ?? ''));
        $smsSender = trim((string) ($input['sms_sender_name'] ?? ''));
        $emailEnabled = !empty($input['email_enabled']) ? 1 : 0;
        $smtpHost = trim((string) ($input['smtp_host'] ?? ''));
        $smtpPort = max(1, min(65535, (int) ($input['smtp_port'] ?? 587)));
        $smtpUser = trim((string) ($input['smtp_user'] ?? ''));
        $smtpFromEmail = trim((string) ($input['smtp_from_email'] ?? ''));
        $smtpFromName = trim((string) ($input['smtp_from_name'] ?? ''));
        $smsTemplate = trim((string) ($input['sms_template'] ?? ''));
        $emailSubject = trim((string) ($input['email_subject_template'] ?? ''));
        $emailBody = trim((string) ($input['email_body_template'] ?? ''));

        $defaults = require __DIR__ . '/../config/follow-up-reminder.php';
        if ($smsTemplate === '') {
            $smsTemplate = $defaults['default_sms'];
        }
        if ($emailSubject === '') {
            $emailSubject = $defaults['default_email_subject'];
        }
        if ($emailBody === '') {
            $emailBody = $defaults['default_email_body'];
        }

        $existing = $conn->query('SELECT sms_api_key, smtp_pass FROM reminder_settings WHERE id = 1')->fetch_assoc();
        if ($smsApiKey === '' && !empty($existing['sms_api_key'])) {
            $smsApiKey = (string) $existing['sms_api_key'];
        }
        $smtpPass = trim((string) ($input['smtp_pass'] ?? ''));
        if ($smtpPass === '' && !empty($existing['smtp_pass'])) {
            $smtpPass = (string) $existing['smtp_pass'];
        }

        $stmt = $conn->prepare(
            'UPDATE reminder_settings SET
                is_enabled = ?, days_before = ?, facility_phone = ?,
                sms_mode = ?, sms_api_url = ?, sms_api_key = ?, sms_sender_name = ?,
                email_enabled = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?,
                smtp_from_email = ?, smtp_from_name = ?,
                sms_template = ?, email_subject_template = ?, email_body_template = ?,
                updated_at = NOW(), updated_by = ?
             WHERE id = 1'
        );
        $stmt->bind_param(
            'iisssssisisssssssi',
            $isEnabled,
            $daysBefore,
            $facilityPhone,
            $smsMode,
            $smsApiUrl,
            $smsApiKey,
            $smsSender,
            $emailEnabled,
            $smtpHost,
            $smtpPort,
            $smtpUser,
            $smtpPass,
            $smtpFromEmail,
            $smtpFromName,
            $smsTemplate,
            $emailSubject,
            $emailBody,
            $actorId
        );
        $stmt->execute();
        $stmt->close();

        if ($isEnabled) {
            self::refreshAllReminderQueues($conn);
        }
    }

    public static function refreshAllReminderQueues(mysqli $conn): int
    {
        self::ensureSchema($conn);
        $res = $conn->query(
            "SELECT id FROM patient_follow_ups WHERE status = 'scheduled' AND send_reminder = 1"
        );
        if (!$res) {
            return 0;
        }
        $n = 0;
        while ($row = $res->fetch_assoc()) {
            self::rebuildMessageQueue($conn, (int) $row['id']);
            $n++;
        }
        $res->free();
        return $n;
    }

    /** @param array<string, mixed> $post */
    public static function syncFromConsultationPost(
        mysqli $conn,
        int $patientId,
        int $consultationId,
        array $post,
        int $userId
    ): void {
        self::ensureSchema($conn);

        $dateRaw = trim((string) ($post['follow_up_date'] ?? ''));
        $sendReminder = !empty($post['follow_up_remind']);
        $channel = (string) ($post['follow_up_channel'] ?? 'email');
        if (!in_array($channel, ['sms', 'email', 'both'], true)) {
            $channel = 'email';
        }
        $instructions = trim((string) ($post['extended']['doctor_follow_up'] ?? $post['doctor_follow_up'] ?? ''));
        if ($instructions === '' && isset($post['extended']) && is_array($post['extended'])) {
            $instructions = trim((string) ($post['extended']['doctor_follow_up'] ?? ''));
        }

        self::updatePatientReminderPrefs($conn, $patientId, $post);

        if ($dateRaw === '') {
            self::cancelForConsultation($conn, $consultationId);
            return;
        }

        $ts = strtotime($dateRaw);
        if ($ts === false) {
            return;
        }
        $followUpDate = date('Y-m-d', $ts);

        if (!$sendReminder) {
            self::upsertFollowUp($conn, $patientId, $consultationId, $followUpDate, $instructions, $channel, 0, $userId);
            self::clearPendingMessages($conn, $consultationId);
            return;
        }

        $followUpId = self::upsertFollowUp($conn, $patientId, $consultationId, $followUpDate, $instructions, $channel, 1, $userId);
        self::rebuildMessageQueue($conn, $followUpId);
    }

    /** @param array<string, mixed> $post */
    private static function updatePatientReminderPrefs(mysqli $conn, int $patientId, array $post): void
    {
        if (!array_key_exists('reminder_sms_opt_in', $post)
            && !array_key_exists('reminder_email_opt_in', $post)
            && !array_key_exists('patient_email', $post)) {
            return;
        }

        $smsOpt = !empty($post['reminder_sms_opt_in']) && (string) ($post['reminder_sms_opt_in'] ?? '') !== '0' ? 1 : 0;
        $emailOpt = !empty($post['reminder_email_opt_in']) && (string) ($post['reminder_email_opt_in'] ?? '') !== '0' ? 1 : 0;
        $email = trim((string) ($post['patient_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        if ($email === '') {
            $stmt = $conn->prepare(
                'UPDATE patients SET reminder_sms_opt_in = ?, reminder_email_opt_in = ? WHERE id = ?'
            );
            $stmt->bind_param('iii', $smsOpt, $emailOpt, $patientId);
        } else {
            $stmt = $conn->prepare(
                'UPDATE patients SET reminder_sms_opt_in = ?, reminder_email_opt_in = ?, patient_email = ? WHERE id = ?'
            );
            $stmt->bind_param('iisi', $smsOpt, $emailOpt, $email, $patientId);
        }
        $stmt->execute();
        $stmt->close();
    }

    private static function cancelForConsultation(mysqli $conn, int $consultationId): void
    {
        if ($consultationId <= 0) {
            return;
        }
        $stmt = $conn->prepare(
            "UPDATE patient_follow_ups SET status = 'cancelled', send_reminder = 0 WHERE consultation_id = ?"
        );
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $stmt->close();
        self::clearPendingMessages($conn, $consultationId);
    }

    private static function upsertFollowUp(
        mysqli $conn,
        int $patientId,
        int $consultationId,
        string $followUpDate,
        string $instructions,
        string $channel,
        int $sendReminder,
        int $userId
    ): int {
        $status = 'scheduled';
        $stmt = $conn->prepare(
            'INSERT INTO patient_follow_ups
                (patient_id, consultation_id, follow_up_date, instructions, channel, send_reminder, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                follow_up_date = VALUES(follow_up_date),
                instructions = VALUES(instructions),
                channel = VALUES(channel),
                send_reminder = VALUES(send_reminder),
                status = VALUES(status),
                updated_at = NOW()'
        );
        $stmt->bind_param(
            'iisssisi',
            $patientId,
            $consultationId,
            $followUpDate,
            $instructions,
            $channel,
            $sendReminder,
            $status,
            $userId
        );
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('SELECT id FROM patient_follow_ups WHERE consultation_id = ? LIMIT 1');
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['id'] ?? 0);
    }

    private static function clearPendingMessages(mysqli $conn, int $consultationId): void
    {
        $stmt = $conn->prepare(
            "DELETE m FROM patient_reminder_messages m
             INNER JOIN patient_follow_ups f ON f.id = m.follow_up_id
             WHERE f.consultation_id = ? AND m.status = 'pending'"
        );
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $stmt->close();
    }

    private static function rebuildMessageQueue(mysqli $conn, int $followUpId): void
    {
        if ($followUpId <= 0) {
            return;
        }

        $settings = self::settingsRow($conn);
        if (!(int) ($settings['is_enabled'] ?? 0)) {
            return;
        }

        $stmt = $conn->prepare(
            'SELECT f.*, p.contact_number, p.patient_email, p.reminder_sms_opt_in, p.reminder_email_opt_in,
                    p.first_name, p.middle_name, p.last_name, p.suffix
             FROM patient_follow_ups f
             INNER JOIN patients p ON p.id = f.patient_id
             WHERE f.id = ? AND f.status = \'scheduled\' AND f.send_reminder = 1'
        );
        $stmt->bind_param('i', $followUpId);
        $stmt->execute();
        $follow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$follow) {
            return;
        }

        $conn->query("DELETE FROM patient_reminder_messages WHERE follow_up_id = {$followUpId} AND status = 'pending'");

        $daysBefore = max(0, (int) ($settings['days_before'] ?? 1));
        $scheduledFor = date('Y-m-d 08:00:00', strtotime("-{$daysBefore} days", strtotime($follow['follow_up_date'])));
        if (strtotime($scheduledFor) < time()) {
            $scheduledFor = date('Y-m-d H:i:s');
        }

        $tokens = self::buildTokens($conn, $follow, $settings);
        $channel = (string) $follow['channel'];
        $patientId = (int) $follow['patient_id'];

        if (($channel === 'sms' || $channel === 'both') && (int) $follow['reminder_sms_opt_in'] === 1) {
            $phone = self::normalizePhone((string) ($follow['contact_number'] ?? ''));
            if ($phone !== '') {
                $body = self::applyTemplate((string) $settings['sms_template'], $tokens);
                self::insertMessage($conn, $followUpId, $patientId, 'sms', $phone, null, $body, $scheduledFor);
            } else {
                self::insertMessage($conn, $followUpId, $patientId, 'sms', '—', null, '', $scheduledFor, 'skipped', 'No contact number');
            }
        }

        if (($channel === 'email' || $channel === 'both') && (int) $follow['reminder_email_opt_in'] === 1
            && (int) ($settings['email_enabled'] ?? 0) === 1) {
            $email = trim((string) ($follow['patient_email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = self::applyTemplate((string) $settings['email_subject_template'], $tokens);
                $body = self::applyTemplate((string) $settings['email_body_template'], $tokens);
                self::insertMessage($conn, $followUpId, $patientId, 'email', $email, $subject, $body, $scheduledFor);
            } else {
                self::insertMessage($conn, $followUpId, $patientId, 'email', '—', null, '', $scheduledFor, 'skipped', 'No valid email');
            }
        }
    }

    /** @param array<string, mixed> $follow @param array<string, mixed> $settings @return array<string, string> */
    private static function buildTokens(mysqli $conn, array $follow, array $settings): array
    {
        $branding = FormTemplate::branding();
        $name = ItPrintForms::formatPatientName($follow);
        $dateLabel = date('F j, Y', strtotime((string) $follow['follow_up_date']));
        $instr = trim((string) ($follow['instructions'] ?? ''));
        if ($instr === '') {
            $instr = 'Please visit the RHU on your scheduled date.';
        }

        return [
            '{patient_name}' => $name,
            '{follow_up_date}' => $dateLabel,
            '{instructions}' => $instr,
            '{facility_name}' => (string) ($branding['facility_name'] ?? 'RHU Magallanes'),
            '{facility_phone}' => (string) ($settings['facility_phone'] ?? ''),
        ];
    }

    private static function applyTemplate(string $template, array $tokens): string
    {
        return str_replace(array_keys($tokens), array_values($tokens), $template);
    }

    private static function insertMessage(
        mysqli $conn,
        int $followUpId,
        int $patientId,
        string $channel,
        string $recipient,
        ?string $subject,
        string $body,
        string $scheduledFor,
        string $status = 'pending',
        ?string $error = null
    ): void {
        $stmt = $conn->prepare(
            'INSERT INTO patient_reminder_messages
                (follow_up_id, patient_id, channel, recipient, subject, body, status, scheduled_for, error_message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisssssss',
            $followUpId,
            $patientId,
            $channel,
            $recipient,
            $subject,
            $body,
            $status,
            $scheduledFor,
            $error
        );
        $stmt->execute();
        $stmt->close();
    }

    /** @return array<string, mixed>|null */
    public static function forConsultation(mysqli $conn, int $consultationId): ?array
    {
        self::ensureSchema($conn);
        if ($consultationId <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT f.* FROM patient_follow_ups f WHERE f.consultation_id = ? AND f.status != \'cancelled\' LIMIT 1'
        );
        $stmt->bind_param('i', $consultationId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    public static function patientPrefs(mysqli $conn, int $patientId): array
    {
        self::ensureSchema($conn);
        $stmt = $conn->prepare(
            'SELECT contact_number, patient_email, reminder_sms_opt_in, reminder_email_opt_in FROM patients WHERE id = ?'
        );
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $row;
    }

    /** @return list<array<string, mixed>> */
    public static function listForPatient(mysqli $conn, int $patientId, int $limit = 20): array
    {
        self::ensureSchema($conn);
        $limit = max(1, min(50, $limit));
        $stmt = $conn->prepare(
            "SELECT f.*, u.full_name AS created_by_name
             FROM patient_follow_ups f
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.patient_id = ?
             ORDER BY f.follow_up_date DESC, f.id DESC
             LIMIT {$limit}"
        );
        $stmt->bind_param('i', $patientId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $out = [];
        foreach ($rows as $f) {
            $fid = (int) $f['id'];
            $msgs = self::messagesForFollowUp($conn, $fid);
            $out[] = [
                'follow_up' => $f,
                'messages'  => $msgs,
            ];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function messagesForFollowUp(mysqli $conn, int $followUpId): array
    {
        $stmt = $conn->prepare(
            'SELECT id, channel, recipient, status, scheduled_for, sent_at, error_message, subject
             FROM patient_reminder_messages WHERE follow_up_id = ? ORDER BY id ASC'
        );
        $stmt->bind_param('i', $followUpId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function duePreview(mysqli $conn, string $fromDate, string $toDate, int $limit = 8): array
    {
        self::ensureSchema($conn);
        $limit = max(1, min(30, $limit));
        $stmt = $conn->prepare(
            "SELECT f.id, f.follow_up_date, f.channel, f.status, p.id AS patient_db_id, p.patient_id,
                    p.first_name, p.middle_name, p.last_name, p.suffix
             FROM patient_follow_ups f
             INNER JOIN patients p ON p.id = f.patient_id AND p.is_archived = 0
             WHERE f.status = 'scheduled' AND f.follow_up_date BETWEEN ? AND ?
             ORDER BY f.follow_up_date ASC
             LIMIT {$limit}"
        );
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as &$r) {
            $r['patient_name'] = ItPrintForms::formatPatientName($r);
            $r['folder_url'] = BASE_URL . '/patient-folder.php?id=' . (int) $r['patient_db_id'] . '&tab=follow-ups';
        }
        unset($r);
        return $rows;
    }

    /** @return array{processed:int,sent:int,failed:int,skipped:int} */
    public static function processDueMessages(mysqli $conn, bool $dryRun = false): array
    {
        self::ensureSchema($conn);
        $settings = self::settingsRow($conn);
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        if (!(int) ($settings['is_enabled'] ?? 0)) {
            return $stats;
        }

        $now = date('Y-m-d H:i:s');
        $res = $conn->query(
            "SELECT * FROM patient_reminder_messages
             WHERE status = 'pending' AND scheduled_for <= '{$now}'
             ORDER BY scheduled_for ASC, id ASC
             LIMIT 100"
        );
        if (!$res) {
            return $stats;
        }

        while ($row = $res->fetch_assoc()) {
            $stats['processed']++;
            if ($row['status'] === 'skipped' || $row['body'] === '') {
                $stats['skipped']++;
                continue;
            }
            if ($dryRun) {
                continue;
            }

            $id = (int) $row['id'];
            $channel = (string) $row['channel'];
            try {
                if ($channel === 'sms') {
                    self::sendSms($settings, (string) $row['recipient'], (string) $row['body']);
                } else {
                    self::sendEmail($settings, (string) $row['recipient'], (string) ($row['subject'] ?? ''), (string) $row['body']);
                }
                self::markMessage($conn, $id, 'sent', null);
                $stats['sent']++;
            } catch (Throwable $e) {
                self::markMessage($conn, $id, 'failed', $e->getMessage());
                $stats['failed']++;
            }
        }
        $res->free();

        return $stats;
    }

    /** @return array<string, mixed> */
    private static function settingsRow(mysqli $conn): array
    {
        $row = $conn->query('SELECT * FROM reminder_settings WHERE id = 1')->fetch_assoc();
        return $row ?: [];
    }

    private static function markMessage(mysqli $conn, int $id, string $status, ?string $error): void
    {
        $stmt = $conn->prepare(
            'UPDATE patient_reminder_messages SET status = ?, sent_at = NOW(), error_message = ? WHERE id = ?'
        );
        $err = $error !== null ? substr($error, 0, 500) : null;
        $stmt->bind_param('ssi', $status, $err, $id);
        $stmt->execute();
        $stmt->close();
    }

    /** @param array<string, mixed> $settings */
    private static function sendSms(array $settings, string $phone, string $message): void
    {
        $mode = (string) ($settings['sms_mode'] ?? 'log_only');
        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            throw new RuntimeException('Invalid phone number');
        }

        if ($mode === 'log_only') {
            error_log('[FollowUpReminder SMS] ' . $phone . ' — ' . $message);
            return;
        }

        if ($mode === 'semaphore') {
            $apiKey = (string) ($settings['sms_api_key'] ?? '');
            if ($apiKey === '') {
                throw new RuntimeException('Semaphore API key not configured');
            }
            $sender = (string) ($settings['sms_sender_name'] ?? 'RHUMag');
            $payload = http_build_query([
                'apikey' => $apiKey,
                'number' => $phone,
                'message' => $message,
                'sendername' => $sender,
            ]);
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 30,
                ],
            ]);
            $url = 'https://api.semaphore.co/api/v4/messages';
            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                throw new RuntimeException('SMS gateway request failed');
            }
            return;
        }

        if ($mode === 'generic_http') {
            $url = (string) ($settings['sms_api_url'] ?? '');
            if ($url === '') {
                throw new RuntimeException('SMS API URL not configured');
            }
            $payload = json_encode([
                'api_key' => (string) ($settings['sms_api_key'] ?? ''),
                'to'      => $phone,
                'message' => $message,
                'sender'  => (string) ($settings['sms_sender_name'] ?? ''),
            ]);
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 30,
                ],
            ]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                throw new RuntimeException('Generic SMS HTTP request failed');
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private static function sendEmail(array $settings, string $to, string $subject, string $body): void
    {
        if (!(int) ($settings['email_enabled'] ?? 0)) {
            throw new RuntimeException('Email reminders disabled in settings');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email');
        }

        $fromEmail = (string) ($settings['smtp_from_email'] ?? '');
        $fromName = (string) ($settings['smtp_from_name'] ?? 'RHU');
        $host = trim((string) ($settings['smtp_host'] ?? ''));

        if ($host === '') {
            throw new RuntimeException(
                'SMTP host is not configured. Open Admin → Messaging, set SMTP host (e.g. smtp.gmail.com), save, then retry.'
            );
        }

        self::sendSmtp(
            $host,
            (int) ($settings['smtp_port'] ?? 587),
            (string) ($settings['smtp_user'] ?? ''),
            (string) ($settings['smtp_pass'] ?? ''),
            $fromEmail,
            $fromName,
            $to,
            $subject,
            $body
        );
    }

    private static function encodeAddress(string $name, string $email): string
    {
        $name = str_replace(['"', "\r", "\n"], '', $name);
        return $name !== '' ? "\"{$name}\" <{$email}>" : $email;
    }

    private static function sendSmtp(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body
    ): void {
        $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 20);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: {$errstr}");
        }
        stream_set_timeout($fp, 20);
        self::smtpExpect($fp, [220]);
        self::smtpCmd($fp, 'EHLO localhost', [250]);

        if ($port === 587) {
            self::smtpCmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                throw new RuntimeException('SMTP STARTTLS failed');
            }
            self::smtpCmd($fp, 'EHLO localhost', [250]);
        }

        if ($user !== '') {
            self::smtpCmd($fp, 'AUTH LOGIN', [334]);
            self::smtpCmd($fp, base64_encode($user), [334]);
            self::smtpCmd($fp, base64_encode($pass), [235]);
        }

        $from = $fromEmail !== '' ? $fromEmail : $user;
        self::smtpCmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        self::smtpCmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        self::smtpCmd($fp, 'DATA', [354]);

        $headers = 'From: ' . self::encodeAddress($fromName, $from) . "\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        fwrite($fp, $headers . $body . "\r\n.\r\n");
        self::smtpExpect($fp, [250]);
        self::smtpCmd($fp, 'QUIT', [221]);
        fclose($fp);
    }

    /** @param resource $fp @param list<int> $codes */
    private static function smtpCmd($fp, string $cmd, array $codes): void
    {
        fwrite($fp, $cmd . "\r\n");
        self::smtpExpect($fp, $codes);
    }

    /** @param resource $fp @param list<int> $codes */
    private static function smtpExpect($fp, array $codes): void
    {
        $line = '';
        while ($str = fgets($fp, 515)) {
            $line = $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($line));
        }
    }

    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '63' . substr($digits, 1);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '63' . $digits;
        }
        return $digits;
    }
}
