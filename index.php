<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/vendor-assets.php';
require_once __DIR__ . '/core/Permissions.php';

$enterUrl = BASE_URL . '/login.php';
$enterLabel = 'Log In';
if (Auth::check()) {
    $user = currentUser();
    $role = Permissions::normalizeRole($user['role'] ?? '');
    if ($role === ROLE_DOCTOR) {
        $enterUrl = BASE_URL . '/workflow/queue.php';
    } elseif ($role === ROLE_SYSTEM_ADMIN) {
        $enterUrl = BASE_URL . '/admin/users.php';
    } else {
        $enterUrl = BASE_URL . '/dashboard.php';
    }
    $enterLabel = 'Enter';
}

$cssPath = __DIR__ . '/assets/css/home.css';
$homeCss = BASE_URL . '/assets/css/home.css?v=' . ((int) @filemtime($cssPath) ?: 1);
$buildingPhoto = BASE_URL . '/assets/img/rhu-magallanes-building.jpg?v=' . ((int) @filemtime(__DIR__ . '/assets/img/rhu-magallanes-building.jpg') ?: 1);
$facilityPlace = 'Magallanes, Agusan del Norte';
// Sample placeholders. Replace with the RHU's official phone and email when available.
$facilityPhone = '(085) 222-1234';
$facilityEmail = 'mru@magallanes.ph';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> | RHU Magallanes</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png">
    <link href="<?= htmlspecialchars(rhu_asset_url('bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(rhu_asset_url('bootstrap_icons_css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars($homeCss) ?>" rel="stylesheet">
</head>
<body class="hp">
    <a class="hp-skip" href="#services">Skip to services</a>
    <header class="hp-wrap">
        <nav class="hp-nav" aria-label="Homepage">
            <a class="hp-brand" href="<?= BASE_URL ?>/index.php">
                <img src="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png" alt="" width="48" height="48">
                <div>
                    <strong>MAGALLANES<br>RURAL HEALTH UNIT</strong>
                    <small>Magallanes, Agusan del Norte</small>
                </div>
            </a>
            <button class="hp-menu" type="button" aria-expanded="false" aria-controls="homeLinks">
                <i class="bi bi-list" aria-hidden="true"></i>
                <span class="visually-hidden">Menu</span>
            </button>
            <ul class="hp-links" id="homeLinks">
                <li><a class="is-current" href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-fill" aria-hidden="true"></i> Home</a></li>
                <li><a href="#services"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Services</a></li>
                <li><a href="#about"><i class="bi bi-info-circle" aria-hidden="true"></i> About Us</a></li>
                <li><a href="#contact"><i class="bi bi-envelope" aria-hidden="true"></i> Contact Us</a></li>
            </ul>
            <a class="hp-login" href="<?= htmlspecialchars($enterUrl) ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= htmlspecialchars($enterLabel) ?></a>
        </nav>
    </header>

    <main>
        <section class="hp-hero">
            <div class="hp-hero-grid">
                <div class="hp-hero-copy">
                    <div class="hp-hero-head">
                        <div class="hp-marks" aria-hidden="true">
                            <svg class="hp-heart" viewBox="0 0 120 108" aria-hidden="true">
                                <defs>
                                    <linearGradient id="hpHeart" x1="20%" y1="0%" x2="80%" y2="100%">
                                        <stop offset="0%" stop-color="#38bdf8"/>
                                        <stop offset="100%" stop-color="#1a6fc4"/>
                                    </linearGradient>
                                </defs>
                                <path fill="url(#hpHeart)" d="M60 98C60 98 12 66 12 40 12 22 26 12 42 12c10 0 16 5 18 12 2-7 8-12 18-12 16 0 30 10 30 28C108 66 60 98 60 98z"/>
                                <path fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" d="M24 54h18l8-15 13 30 10-18 7 7h18"/>
                            </svg>
                            <svg class="hp-plus hp-plus--lg" viewBox="0 0 64 64" aria-hidden="true">
                                <path fill="#93c5fd" d="M26 6h12v20h20v12H38v20H26V38H6V26h20z"/>
                            </svg>
                            <svg class="hp-plus hp-plus--sm" viewBox="0 0 64 64" aria-hidden="true">
                                <path fill="#93c5fd" d="M26 6h12v20h20v12H38v20H26V38H6V26h20z"/>
                            </svg>
                        </div>
                        <div>
                            <h1><span class="hp-line-dark">Magallanes</span><span class="hp-line-pink">Rural Health Unit</span></h1>
                            <p class="hp-tagline">Healthy Community, Healthier Tomorrow</p>
                        </div>
                    </div>
                    <p class="hp-lead">Quality and accessible healthcare services for a healthier Magallanes.</p>
                    <div class="hp-actions">
                        <a class="hp-btn hp-btn--solid" href="#services"><i class="bi bi-calendar3" aria-hidden="true"></i> Our Services <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        <a class="hp-btn hp-btn--line" href="#about"><i class="bi bi-info-circle" aria-hidden="true"></i> Learn More <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
                <figure class="hp-hero-photo">
                    <img src="<?= htmlspecialchars($buildingPhoto) ?>" alt="Front of the Magallanes Rural Health Unit building">
                </figure>
            </div>
            <svg class="hp-wave" viewBox="0 0 1440 210" preserveAspectRatio="none" aria-hidden="true">
                <path fill="#bfdbfe" d="M0,118 C220,78 380,168 640,132 C920,92 1080,36 1280,78 C1360,96 1410,112 1440,104 L1440,210 L0,210 Z"></path>
                <path fill="#1a6fc4" d="M0,132 C240,86 400,178 660,140 C940,98 1100,42 1300,86 C1380,104 1420,118 1440,112 L1440,196 C1220,228 980,160 700,176 C400,194 180,150 0,176 Z"></path>
                <path fill="#ffffff" d="M0,162 C250,128 430,196 690,166 C960,134 1120,108 1320,142 C1380,154 1420,164 1440,156 L1440,210 L0,210 Z"></path>
            </svg>
        </section>

        <section class="hp-services" id="services">
            <div class="hp-wrap hp-grid">
                <article class="hp-card">
                    <i class="bi bi-heart-pulse" aria-hidden="true"></i>
                    <h2>Consultation<br>Services</h2>
                </article>
                <article class="hp-card">
                    <i class="bi bi-folder2-open" aria-hidden="true"></i>
                    <h2>Patient<br>Records</h2>
                </article>
                <article class="hp-card">
                    <i class="bi bi-capsule" aria-hidden="true"></i>
                    <h2>Pharmacy<br>Services</h2>
                </article>
                <article class="hp-card">
                    <i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i>
                    <h2>Health<br>Reports</h2>
                </article>
                <article class="hp-card">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                    <h2>Disease<br>Heat Map</h2>
                </article>
                <article class="hp-card">
                    <i class="bi bi-printer-fill" aria-hidden="true"></i>
                    <h2>Form<br>Print</h2>
                </article>
            </div>
        </section>

        <section class="hp-wrap hp-lower">
            <img class="hp-lower-photo" src="<?= htmlspecialchars($buildingPhoto) ?>" alt="">
            <div class="hp-about" id="about">
                <h2><i class="bi bi-heart-fill" aria-hidden="true"></i> About Us</h2>
                <p>The Magallanes Rural Health Unit is dedicated to provide accessible, quality, and compassionate healthcare services to the people of Magallanes, Agusan del Norte.</p>
                <a class="hp-btn hp-btn--solid" href="#contact">Read More <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </section>
    </main>

    <footer class="hp-footer" id="contact">
        <div class="hp-wrap hp-footer-row">
            <div class="hp-footer-bits">
                <span><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Magallanes Rural Health Unit<br><?= htmlspecialchars($facilityPlace) ?></span>
                <?php if ($facilityPhone !== ''): ?><span><i class="bi bi-telephone-fill" aria-hidden="true"></i> <?= htmlspecialchars($facilityPhone) ?></span><?php endif; ?>
                <?php if ($facilityEmail !== ''): ?><a href="mailto:<?= htmlspecialchars($facilityEmail) ?>"><i class="bi bi-envelope-fill" aria-hidden="true"></i> <?= htmlspecialchars($facilityEmail) ?></a><?php endif; ?>
                <span aria-hidden="true"><i class="bi bi-facebook"></i> <i class="bi bi-youtube"></i></span>
            </div>
            <div class="hp-quote">“Healthy People. Healthy Community.” <i class="bi bi-heart-fill" aria-hidden="true"></i></div>
        </div>
    </footer>
    <script>
        const menu = document.querySelector('.hp-menu');
        const nav = document.querySelector('.hp-nav');
        if (menu && nav) {
            menu.addEventListener('click', () => {
                const open = nav.classList.toggle('is-open');
                menu.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
    </script>
</body>
</html>
