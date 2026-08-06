<?php
/**
 * In-app notifications for RHU workflow events.
 */

require_once __DIR__ . '/../config/app.php';

class NotificationService
{
    public static function ensureTable(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS notifications (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function create(
        mysqli $conn,
        int $userId,
        string $title,
        string $message,
        ?int $patientId = null,
        ?string $link = null
    ): void {
        if ($userId <= 0) {
            return;
        }

        self::ensureTable($conn);

        $stmt = $conn->prepare(
            'INSERT INTO notifications (user_id, patient_id, title, message, link) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iisss', $userId, $patientId, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
    }

    public static function notifyWorkflow(
        mysqli $conn,
        array $patient,
        string $event,
        array $actor,
        string $remarks = ''
    ): void {
        $encoderId = (int) ($patient['encoded_by'] ?? 0);
        if ($encoderId <= 0) {
            return;
        }

        $patientDbId = (int) ($patient['id'] ?? 0);
        $patientCode = $patient['patient_id'] ?? '';
        $actorName = $actor['full_name'] ?? 'IT Staff';
        $link = BASE_URL . '/forms/doh-form.php?id=' . $patientDbId;

        switch ($event) {
            case 'returned_for_correction':
                self::create(
                    $conn,
                    $encoderId,
                    'Returned by IT',
                    'Consultation ' . $patientCode . ' was returned for correction by ' . $actorName . '.'
                        . ($remarks !== '' ? "\nReason: " . $remarks : ''),
                    $patientDbId,
                    $link
                );
                break;

            case 'approved':
            case 'ready_for_printing':
                self::create(
                    $conn,
                    $encoderId,
                    'Consultation Approved',
                    'Consultation ' . $patientCode . ' was approved by ' . $actorName . ' and is Ready for Printing.',
                    $patientDbId,
                    BASE_URL . '/patient-folder.php?id=' . $patientDbId
                );
                break;

            case 'submitted_to_it':
                // Optional: notify IT users — skip for now to avoid fan-out without IT user list
                break;
        }
    }

    public static function unreadCount(mysqli $conn, int $userId): int
    {
        self::ensureTable($conn);
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    public static function listForUser(mysqli $conn, int $userId, int $limit = 50): array
    {
        self::ensureTable($conn);
        $limit = max(1, min(100, $limit));
        $stmt = $conn->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public static function markRead(mysqli $conn, int $userId, ?int $notificationId = null): void
    {
        self::ensureTable($conn);
        if ($notificationId) {
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $notificationId, $userId);
        } else {
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
        }
        $stmt->execute();
        $stmt->close();
    }
}
