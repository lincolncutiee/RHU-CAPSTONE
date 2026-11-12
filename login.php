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



$postedUser = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');

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

    <link href="<?= BASE_URL ?>/assets/css/login.css?v=2" rel="stylesheet">

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

                <span class="login-tagline"><i class="bi bi-shield-lock"></i> Authorized staff only</span>

            </div>



            <?php if ($error): ?>

                <div class="login-alert" role="alert" aria-live="polite">

                    <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>

                    <span><?= htmlspecialchars($error) ?></span>

                </div>

            <?php endif; ?>



            <form method="POST" class="login-form" id="loginForm" novalidate>

                <div class="mb-3">

                    <label for="username" class="form-label">Username</label>

                    <div class="input-group">

                        <span class="input-group-text" aria-hidden="true"><i class="bi bi-person"></i></span>

                        <input type="text" class="form-control" id="username" name="username"

                               value="<?= $postedUser ?>"

                               placeholder="Enter username"

                               autocomplete="username" required autofocus>

                    </div>

                </div>

                <div class="mb-4">

                    <label for="password" class="form-label">Password</label>

                    <div class="input-group login-password-group">

                        <span class="input-group-text" aria-hidden="true"><i class="bi bi-lock"></i></span>

                        <input type="password" class="form-control" id="password" name="password"

                               placeholder="Enter your password" required autocomplete="current-password">

                        <button type="button" class="btn login-password-toggle" id="togglePassword"

                                aria-label="Show password" title="Show password">

                            <i class="bi bi-eye" aria-hidden="true"></i>

                        </button>

                    </div>

                </div>

                <button type="submit" class="btn btn-primary w-100 btn-login" id="btnLogin">

                    <span class="btn-login-label"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In</span>

                </button>

            </form>

        </div>

    </div>

    <script>

    (function () {

        var form = document.getElementById('loginForm');

        var userInput = document.getElementById('username');

        var passInput = document.getElementById('password');

        var loginBtn = document.getElementById('btnLogin');

        var toggleBtn = document.getElementById('togglePassword');



        if (toggleBtn && passInput) {

            toggleBtn.addEventListener('click', function () {

                var icon = toggleBtn.querySelector('i');

                var show = passInput.type === 'password';

                passInput.type = show ? 'text' : 'password';

                if (icon) {

                    icon.classList.toggle('bi-eye', !show);

                    icon.classList.toggle('bi-eye-slash', show);

                }

                var label = show ? 'Hide password' : 'Show password';

                toggleBtn.setAttribute('aria-label', label);

                toggleBtn.title = label;

            });

        }

        if (form && loginBtn) {

            form.addEventListener('submit', function () {

                loginBtn.classList.add('is-loading');

            });

        }

    })();

    </script>

</body>

</html>

