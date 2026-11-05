<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();

function currentUser(): ?array
{
    return Auth::user();
}

function requireLogin(): void
{
    Auth::requireLogin();
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    $role = Auth::role();
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function requireAdminAccess(): void
{
    require_once __DIR__ . '/../core/AdminAccess.php';
    AdminAccess::requireAccess();
}

function requireAdminPermission(string $permissionKey): void
{
    require_once __DIR__ . '/../core/AdminAccess.php';
    AdminAccess::requirePermission($permissionKey);
}

function generatePatientId(mysqli $conn): string
{
    $year = date('Y');
    $prefix = "RHU-{$year}-";

    $stmt = $conn->prepare(
        "SELECT patient_id FROM patients WHERE patient_id LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $like = $prefix . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next = 1;
    if ($row) {
        $last = (int) substr($row['patient_id'], -4);
        $next = $last + 1;
    }

    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function logWorkflow(mysqli $conn, int $patientId, ?int $userId, string $action, ?string $fromStatus, ?string $toStatus, ?string $remarks = null): void
{
    $stmt = $conn->prepare(
        'INSERT INTO workflow_logs (patient_id, user_id, action, from_status, to_status, remarks)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissss', $patientId, $userId, $action, $fromStatus, $toStatus, $remarks);
    $stmt->execute();
    $stmt->close();
}

function calculateAge(?string $birthDate): ?int
{
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    return (new DateTime())->diff($birth)->y;
}
