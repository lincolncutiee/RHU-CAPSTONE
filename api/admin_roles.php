<?php
/**
 * Administration — role permission matrix.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/AdminAccess.php';
require_once __DIR__ . '/../core/RolePermissions.php';
require_once __DIR__ . '/../core/AdminAudit.php';

AdminAccess::requirePermission('admin.roles');

$actor = currentUser();
$actorId = (int) ($actor['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function adminRolesRequirePassword(string $password): void
{
    if (!AdminAccess::verifyActorPassword($password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Incorrect password. Action cancelled.']);
        exit;
    }
}

try {
    $conn = getDBConnection();
    RolePermissions::ensureSchema($conn);

    if ($method === 'GET') {
        $cfg = RolePermissions::config();
        $defaults = [];
        foreach (RolePermissions::configurableRoles() as $slug => $_label) {
            $defaults[$slug] = RolePermissions::defaultForRole($slug);
        }
        echo json_encode([
            'success'            => true,
            'roles'              => RolePermissions::configurableRoles(),
            'groups'             => $cfg['groups'] ?? [],
            'role_visible_groups'=> $cfg['role_visible_groups'] ?? [],
            'matrix'             => RolePermissions::matrixForAdmin($conn),
            'defaults'           => $defaults,
            'locked_role'        => ROLE_SYSTEM_ADMIN,
        ]);
        $conn->close();
        exit;
    }

    if ($method !== 'POST') {
        throw new RuntimeException('Method not allowed.');
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON.');
    }

    $password = (string) ($payload['password'] ?? '');
    adminRolesRequirePassword($password);

    $roleSlug = Permissions::normalizeRole((string) ($payload['role_slug'] ?? ''));
    $permissions = $payload['permissions'] ?? [];
    if (!is_array($permissions)) {
        throw new RuntimeException('Invalid permissions.');
    }

    RolePermissions::saveForRole($conn, $roleSlug, $permissions);

    AdminAudit::log(
        $conn,
        $actorId,
        'role_permissions.update',
        'role',
        $roleSlug,
        ['permission_count' => count($permissions)]
    );

    if (Permissions::normalizeRole((string) Session::get('role')) === $roleSlug) {
        Permissions::refreshRolePermissionsInSession($conn, $roleSlug);
    }

    echo json_encode([
        'success' => true,
        'matrix'  => RolePermissions::matrixForAdmin($conn),
    ]);
    $conn->close();
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
