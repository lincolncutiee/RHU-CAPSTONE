<?php

require_once __DIR__ . '/AdminInstall.php';
require_once __DIR__ . '/AdminAccess.php';

class AdminAudit
{
    public static function log(
        mysqli $conn,
        int $actorUserId,
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $details = null
    ): void {
        AdminInstall::ensureSchema($conn);
        $json = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        $ip = AdminAccess::clientIp();
        $stmt = $conn->prepare(
            'INSERT INTO admin_audit_log (actor_user_id, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssss', $actorUserId, $action, $targetType, $targetId, $json, $ip);
        $stmt->execute();
        $stmt->close();
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(mysqli $conn, int $limit = 200, ?string $search = null): array
    {
        AdminInstall::ensureSchema($conn);
        $limit = max(1, min(500, $limit));
        $search = trim((string) $search);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $stmt = $conn->prepare(
                "SELECT a.*, u.full_name AS actor_name, u.username AS actor_username
                 FROM admin_audit_log a
                 JOIN users u ON u.id = a.actor_user_id
                 WHERE a.action LIKE ? OR a.target_type LIKE ? OR a.target_id LIKE ?
                    OR u.full_name LIKE ? OR u.username LIKE ?
                 ORDER BY a.id DESC
                 LIMIT ?"
            );
            $stmt->bind_param('sssssi', $like, $like, $like, $like, $like, $limit);
        } else {
            $stmt = $conn->prepare(
                "SELECT a.*, u.full_name AS actor_name, u.username AS actor_username
                 FROM admin_audit_log a
                 JOIN users u ON u.id = a.actor_user_id
                 ORDER BY a.id DESC
                 LIMIT ?"
            );
            $stmt->bind_param('i', $limit);
        }

        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as &$row) {
            if (!empty($row['details']) && is_string($row['details'])) {
                $row['details'] = json_decode($row['details'], true);
            }
        }

        return $rows;
    }
}
