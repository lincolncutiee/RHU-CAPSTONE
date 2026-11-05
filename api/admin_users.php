<?php
/**
 * Administration — user accounts & field-group privileges.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/AdminAccess.php';
require_once __DIR__ . '/../core/AdminUsers.php';

AdminAccess::requirePermission('admin.users');

$actor = currentUser();
$actorId = (int) ($actor['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function adminRequirePassword(string $password): void
{
    if (!AdminAccess::verifyActorPassword($password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Incorrect password. Action cancelled.']);
        exit;
    }
}

try {
    $conn = getDBConnection();

    if ($method === 'GET') {
        if ($action === 'get') {
            $id = (int) ($_GET['id'] ?? 0);
            $user = AdminUsers::getUser($conn, $id);
            if (!$user) {
                throw new RuntimeException('User not found.');
            }
            unset($user['password_hash']);
            echo json_encode(['success' => true, 'user' => $user]);
            $conn->close();
            exit;
        }

        $search = trim($_GET['search'] ?? '');
        $users = AdminUsers::listUsers($conn, $search !== '' ? $search : null);
        echo json_encode([
            'success' => true,
            'users'   => $users,
            'roles'   => AdminUsers::assignableRolesList(),
            'groups'  => AdminUsers::assignableFieldGroups(),
            'levels'  => AdminUsers::assignableRoles()['privilege_levels'] ?? [],
        ]);
        $conn->close();
        exit;
    }

    if ($method !== 'POST') {
        throw new RuntimeException('Method not allowed.');
    }

    $payload = $_POST;
    if (empty($payload) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    $action = $payload['action'] ?? $action;
    $adminPassword = (string) ($payload['admin_password'] ?? '');

    switch ($action) {
        case 'verify_password':
            adminRequirePassword($adminPassword);
            echo json_encode(['success' => true]);
            break;

        case 'create':
            adminRequirePassword($adminPassword);
            $result = AdminUsers::createUser($conn, $actorId, [
                'username'          => $payload['username'] ?? '',
                'full_name'         => $payload['full_name'] ?? '',
                'email'             => $payload['email'] ?? '',
                'role_slug'         => $payload['role_slug'] ?? '',
                'password'          => $payload['password'] ?? '',
                'can_access_admin'  => !empty($payload['can_access_admin']),
                'privileges'        => $payload['privileges'] ?? [],
            ]);
            echo json_encode($result);
            break;

        case 'update':
            adminRequirePassword($adminPassword);
            $userId = (int) ($payload['user_id'] ?? 0);
            $result = AdminUsers::updateUser($conn, $actorId, $userId, [
                'full_name'        => $payload['full_name'] ?? null,
                'email'            => $payload['email'] ?? null,
                'role_slug'        => $payload['role_slug'] ?? null,
                'can_access_admin' => array_key_exists('can_access_admin', $payload)
                    ? !empty($payload['can_access_admin'])
                    : null,
                'privileges'       => $payload['privileges'] ?? [],
            ]);
            echo json_encode($result);
            break;

        case 'reset_password':
            adminRequirePassword($adminPassword);
            $userId = (int) ($payload['user_id'] ?? 0);
            $result = AdminUsers::resetPassword($conn, $actorId, $userId, (string) ($payload['new_password'] ?? ''));
            echo json_encode($result);
            break;

        case 'set_active':
            adminRequirePassword($adminPassword);
            $userId = (int) ($payload['user_id'] ?? 0);
            $active = !empty($payload['is_active']);
            $result = AdminUsers::setActive($conn, $actorId, $userId, $active);
            echo json_encode($result);
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }

    $conn->close();
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
