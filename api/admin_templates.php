<?php
/**
 * Administration — form template strings & branding.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/AdminAccess.php';
require_once __DIR__ . '/../core/FormTemplate.php';
require_once __DIR__ . '/../core/AdminInstall.php';

AdminAccess::requireAccess();

$actor = currentUser();
$actorId = (int) ($actor['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

function adminTplRequirePassword(string $password): void
{
    if (!AdminAccess::verifyActorPassword($password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Incorrect password. Action cancelled.']);
        exit;
    }
}

try {
    $conn = getDBConnection();
    AdminInstall::ensureSchema($conn);
    $adminCfg = require __DIR__ . '/../config/admin.php';

    if ($method === 'GET') {
        $search = trim($_GET['search'] ?? '');
        $prefix = trim($_GET['prefix'] ?? '');
        $formId = trim($_GET['form_id'] ?? '');
        if ($formId !== '' && $prefix === '') {
            $prefix = 'field.' . $formId . '.';
        }
        $forms = FormTemplate::formCatalog();
        $strings = FormTemplate::listStrings($conn, $search !== '' ? $search : null, $prefix !== '' ? $prefix : null);
        if ($prefix === '' && $search === '') {
            $allForGroup = FormTemplate::listStrings($conn, null, null);
        } else {
            $allForGroup = $strings;
        }
        $prefixOptions = [['id' => '', 'label' => 'All forms']];
        foreach ($forms as $f) {
            if (!empty($f['is_system'])) {
                continue;
            }
            $prefixOptions[] = [
                'id'    => 'field.' . $f['id'] . '.',
                'label' => $f['short_title'] ?? $f['title'],
            ];
        }
        $prefixOptions[] = ['id' => 'page.', 'label' => 'Page titles & sections (all)'];
        $prefixOptions[] = ['id' => 'label.', 'label' => 'Shared field labels (global)'];

        echo json_encode([
            'success'         => true,
            'branding'        => FormTemplate::branding(),
            'forms'           => $forms,
            'strings'         => $strings,
            'strings_grouped' => FormTemplate::groupStringsByForm($allForGroup, $forms),
            'prefixes'        => $prefixOptions,
        ]);
        $conn->close();
        exit;
    }

    if ($method !== 'POST') {
        throw new RuntimeException('Method not allowed.');
    }

    $action = $_POST['action'] ?? $action;
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($action === 'save_string') {
        adminTplRequirePassword($adminPassword);
        $key = trim($_POST['template_key'] ?? '');
        $custom = $_POST['custom_text'] ?? null;
        if ($key === '') {
            throw new RuntimeException('Missing template key.');
        }
        FormTemplate::saveString($conn, $actorId, $key, $custom !== null ? (string) $custom : null);
        echo json_encode(['success' => true]);
        $conn->close();
        exit;
    }

    if ($action === 'save_branding') {
        adminTplRequirePassword($adminPassword);
        FormTemplate::saveBranding($conn, $actorId, [
            'site_title'    => $_POST['site_title'] ?? '',
            'site_subtitle' => $_POST['site_subtitle'] ?? '',
            'facility_name' => $_POST['facility_name'] ?? '',
            'logo_path'     => $_POST['logo_path'] ?? FormTemplate::branding()['logo_path'],
        ]);
        echo json_encode(['success' => true, 'branding' => FormTemplate::branding()]);
        $conn->close();
        exit;
    }

    if ($action === 'upload_logo') {
        adminTplRequirePassword($adminPassword);
        if (empty($_FILES['logo']['tmp_name'])) {
            throw new RuntimeException('No file uploaded.');
        }
        $file = $_FILES['logo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }
        if ($file['size'] > ($adminCfg['logo']['max_bytes'] ?? 2097152)) {
            throw new RuntimeException('File too large (max 2 MB).');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = $adminCfg['logo']['allowed_types'] ?? ['image/png', 'image/jpeg', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Invalid image type. Use PNG, JPEG, or WebP.');
        }

        $ext = match ($mime) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };
        $subdir = $adminCfg['logo']['upload_subdir'] ?? 'uploads/branding';
        $dir = dirname(__DIR__) . '/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save uploaded file.');
        }

        $webPath = $subdir . '/' . $name;
        FormTemplate::saveBranding($conn, $actorId, array_merge(FormTemplate::branding(), [
            'logo_path' => $webPath,
        ]));
        echo json_encode(['success' => true, 'logo_url' => FormTemplate::logoUrl(), 'logo_path' => $webPath]);
        $conn->close();
        exit;
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
