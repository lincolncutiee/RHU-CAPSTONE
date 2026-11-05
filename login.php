<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/vendor-assets.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/Pharmacy.php';

function rhuHomeUrl(): string
{
    if (!Auth::check()) {
        return BASE_URL . '/login.php';
    }
    $user = currentUser();
    $role = Permissions::normalizeRole($user['role'] ?? '');
    if ($role === ROLE_DOCTOR) {
        return BASE_URL . '/workflow/queue.php';
    }
    if ($role === ROLE_PHARMACY) {
        return BASE_URL . '/dashboard.php';
    }
    if ($role === ROLE_SYSTEM_ADMIN) {
        return BASE_URL . '/admin/users.php';
    }
    return BASE_URL . '/dashboard.php';
}

if (Auth::check()) {
    header('Location: ' . rhuHomeUrl());
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } elseif (Auth::attempt($username, $password)) {
        header('Location: ' . rhuHomeUrl());
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png">
    <link href="<?= htmlspecialchars(rhu_asset_url('bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(rhu_asset_url('bootstrap_icons_css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/main.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/login.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png"
                         alt="Rural Health Unit of Magallanes, Agusan del Norte"
                         width="96" height="96">
                </div>
                <h1>RHU Management System</h1>
                <p>Rural Health Unit of Magallanes — Agusan del Norte</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="Enter username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-login">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <div class="login-demo mt-4">
                <p class="text-muted small mb-2">Demo Accounts (password: <code>password</code>)</p>
                <div class="demo-accounts">
                    <span class="badge bg-light text-dark">records</span>
                    <span class="badge bg-light text-dark">doctor</span>
                    <span class="badge bg-light text-dark">pharmacy</span>
                    <span class="badge bg-light text-dark">it</span>
                    <span class="badge bg-light text-dark">receptionist</span>
                    <span class="badge bg-secondary text-white" title="System settings only — not IT workflow">admin</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
