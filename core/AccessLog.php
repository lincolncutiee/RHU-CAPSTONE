<?php

require_once __DIR__ . '/AdminInstall.php';
require_once __DIR__ . '/AdminAccess.php';

class AccessLog
{
    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return [
            'doctor'               => 'Doctor',
            'it_department'        => 'IT',
            'system_administrator' => 'Admin',
            'records_office'       => 'Records staff',
            'pharmacy'             => 'Pharmacy',
            'receptionist'         => 'Receptionist',
        ];
    }

    /** @return array<string, string> */
    public static function eventOptions(): array
    {
        return [
            'login'  => 'Logged in',
            'failed' => 'Failed login',
            'logout' => 'Logged out',
        ];
    }

    public static function write(
        mysqli $conn,
        string $event,
        ?int $userId,
        string $username,
        ?string $roleSlug,
        ?string $roleName,
        ?string $reason
    ): void {
        if (!isset(self::eventOptions()[$event])) {
            return;
        }

        try {
            AdminInstall::ensureSchema($conn);
            $username = mb_substr(trim($username), 0, 80);
            $roleSlug = $roleSlug !== null && $roleSlug !== '' ? mb_substr($roleSlug, 0, 64) : null;
            $roleName = $roleName !== null && $roleName !== '' ? mb_substr($roleName, 0, 80) : null;
            $reason = $reason !== null && $reason !== '' ? mb_substr($reason, 0, 40) : null;
            $ip = AdminAccess::clientIp();
            $userKey = $userId !== null && $userId > 0 ? (string) $userId : '';
            $stmt = $conn->prepare(
                "INSERT INTO access_logs (event, user_id, username, role_slug, role_name, reason, ip_address)
                 VALUES (?, NULLIF(?, ''), ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('sssssss', $event, $userKey, $username, $roleSlug, $roleName, $reason, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('[RHU AccessLog] ' . $e->getMessage());
        }
    }

    /**
     * @return array{login:int,failed:int,logout:int}
     */
    public static function counts(mysqli $conn, string $role): array
    {
        AdminInstall::ensureSchema($conn);
        $counts = ['login' => 0, 'failed' => 0, 'logout' => 0];
        [$where, $types, $params] = self::filters('', $role);
        $sql = 'SELECT event, COUNT(*) AS c FROM access_logs' . $where . ' GROUP BY event';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $counts;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $event = (string) ($row['event'] ?? '');
            if (isset($counts[$event])) {
                $counts[$event] = (int) $row['c'];
            }
        }
        $stmt->close();
        return $counts;
    }

    /** @return array{total:int,rows:list<array<string,mixed>>} */
    public static function search(mysqli $conn, string $event, string $role, int $limit = 200): array
    {
        AdminInstall::ensureSchema($conn);
        $limit = max(1, min(500, $limit));
        [$where, $types, $params] = self::filters($event, $role);

        $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM access_logs' . $where);
        $total = 0;
        if ($countStmt) {
            if ($types !== '') {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $total = (int) ($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $countStmt->close();
        }

        $listTypes = $types . 'i';
        $listParams = $params;
        $listParams[] = $limit;
        $stmt = $conn->prepare(
            'SELECT id, event, username, role_slug, role_name, reason, ip_address, created_at
             FROM access_logs' . $where . ' ORDER BY id DESC LIMIT ?'
        );
        $rows = [];
        if ($stmt) {
            $stmt->bind_param($listTypes, ...$listParams);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        return ['total' => $total, 'rows' => $rows];
    }

    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'wrong_password'    => 'Wrong password',
            'unknown_username'  => 'Unknown username',
            'inactive'          => 'Account inactive',
            default             => '—',
        };
    }

    /** @return array{0:string,1:string,2:list<string>} */
    private static function filters(string $event, string $role): array
    {
        $sql = '';
        $types = '';
        $params = [];
        $clauses = [];

        if (isset(self::eventOptions()[$event])) {
            $clauses[] = 'event = ?';
            $types .= 's';
            $params[] = $event;
        }
        if (isset(self::roleOptions()[$role])) {
            $clauses[] = 'role_slug = ?';
            $types .= 's';
            $params[] = $role;
        }
        if ($clauses !== []) {
            $sql = ' WHERE ' . implode(' AND ', $clauses);
        }

        return [$sql, $types, $params];
    }
}
