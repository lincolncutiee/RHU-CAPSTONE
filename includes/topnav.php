<?php
/**
 * Fixed top navigation — HIS/EMR layout (replaces permanent left sidebar).
 * Links and role visibility match the previous sidebar; no workflow changes.
 *
 * Live patient search appears only on Patient Records (browse/locate folders).
 * Patient Encoding stays focused on one patient at a time.
 */
$userRole = $user['role'] ?? '';
$isIt = $userRole === ROLE_IT;
$isRecords = $userRole === ROLE_RECORDS;
$isReceptionist = $userRole === ROLE_RECEPTIONIST;
$isDoctor = $userRole === ROLE_DOCTOR;
$isPharmacy = $userRole === ROLE_PHARMACY;
$showPatientSearch = (($activePage ?? '') === 'records') && empty($hidePatientSearch);
$topnavClass = $showPatientSearch ? 'app-topnav' : 'app-topnav app-topnav--compact';

$notifyCount = 0;
try {
    require_once __DIR__ . '/../core/NotificationService.php';
    $nconn = getDBConnection();
    $notifyCount = NotificationService::unreadCount($nconn, (int) ($user['id'] ?? 0));
    $nconn->close();
} catch (Throwable $e) {
    $notifyCount = 0;
}
?>
<header class="<?= $topnavClass ?>" id="appTopnav">
  <div class="topnav-row topnav-primary">
    <a href="<?= BASE_URL ?>/dashboard.php" class="topnav-brand">
      <span class="topnav-brand-icon">
        <img src="<?= BASE_URL ?>/assets/img/rhu-magallanes-logo.png"
             alt="RHU Magallanes"
             width="36" height="36">
      </span>
      <span class="topnav-brand-text">
        <span class="topnav-brand-title">RHU Magallanes</span>
        <span class="topnav-brand-sub">Agusan del Norte</span>
      </span>
    </a>

    <button type="button" class="btn btn-link topnav-toggle d-lg-none" id="topnavToggle" aria-label="Toggle navigation" aria-expanded="false">
      <i class="bi bi-list"></i>
    </button>

    <nav class="topnav-menu" id="topnavMenu" aria-label="Main">
      <?php if ($isReceptionist): ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="topnav-link <?= ($activePage ?? '') === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
        <a href="<?= BASE_URL ?>/reception/queue.php" class="topnav-link <?= ($activePage ?? '') === 'workflow' ? 'is-active' : '' ?>">My Queue</a>
        <a href="<?= BASE_URL ?>/patient-records.php" class="topnav-link <?= ($activePage ?? '') === 'records' ? 'is-active' : '' ?>">Patient Records (View Only)</a>
        <div class="dropdown topnav-dropdown">
          <button type="button" class="topnav-link topnav-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            Reports <i class="bi bi-chevron-down"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end topnav-dropdown-menu">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard.php">Dashboard Overview</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/patient-records.php">Patient Registry</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/reception/queue.php">Reception Queue</a></li>
          </ul>
        </div>
      <?php elseif ($isIt): ?>
        <?php
        $printReadyCount = 0;
        try {
            require_once __DIR__ . '/../core/ItPrintForms.php';
            $printReadyCount = ItPrintForms::readyToPrintCount();
        } catch (Throwable $e) {
            $printReadyCount = 0;
        }
        ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="topnav-link <?= ($activePage ?? '') === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
        <a href="<?= BASE_URL ?>/forms/doh-form.php" class="topnav-link <?= ($activePage ?? '') === 'encoding' ? 'is-active' : '' ?>">Patient Encoding</a>
        <a href="<?= BASE_URL ?>/patient-records.php" class="topnav-link <?= ($activePage ?? '') === 'records' ? 'is-active' : '' ?>">Patient Records</a>
        <a href="<?= BASE_URL ?>/it/print-queue.php" class="topnav-link <?= in_array(($activePage ?? ''), ['it-print', 'it-print-queue'], true) ? 'is-active' : '' ?>">
          Print
          <?php if ($printReadyCount > 0): ?>
            <span class="topnav-count-badge"><?= $printReadyCount > 99 ? '99+' : (int) $printReadyCount ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown topnav-dropdown">
          <button type="button" class="topnav-link topnav-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            Reports <i class="bi bi-chevron-down"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end topnav-dropdown-menu">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard.php">Dashboard Overview</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/patient-records.php">Patient Registry</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/it/print-queue.php">Print</a></li>
          </ul>
        </div>
      <?php elseif ($isPharmacy): ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="topnav-link <?= in_array(($activePage ?? ''), ['dashboard', 'pharmacy'], true) ? 'is-active' : '' ?>">Pharmacy Queue</a>
      <?php elseif ($isDoctor): ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="topnav-link <?= ($activePage ?? '') === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
        <a href="<?= BASE_URL ?>/workflow/queue.php" class="topnav-link <?= ($activePage ?? '') === 'workflow' ? 'is-active' : '' ?>">Doctor Consultation</a>
        <a href="<?= BASE_URL ?>/patient-records.php" class="topnav-link <?= ($activePage ?? '') === 'records' ? 'is-active' : '' ?>">Patient Records</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="topnav-link <?= ($activePage ?? '') === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
        <a href="<?= BASE_URL ?>/forms/doh-form.php" class="topnav-link <?= ($activePage ?? '') === 'encoding' ? 'is-active' : '' ?>">Patient Encoding</a>
        <a href="<?= BASE_URL ?>/patient-records.php" class="topnav-link <?= ($activePage ?? '') === 'records' ? 'is-active' : '' ?>">Patient Records</a>
        <?php if (!$isRecords): ?>
          <a href="<?= BASE_URL ?>/workflow/queue.php" class="topnav-link <?= ($activePage ?? '') === 'workflow' ? 'is-active' : '' ?>">My Queue</a>
        <?php endif; ?>
        <div class="dropdown topnav-dropdown">
          <button type="button" class="topnav-link topnav-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            Reports <i class="bi bi-chevron-down"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end topnav-dropdown-menu">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard.php">Dashboard Overview</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/patient-records.php">Patient Registry</a></li>
          </ul>
        </div>
      <?php endif; ?>
    </nav>

    <div class="topnav-end">
      <a href="<?= BASE_URL ?>/notifications.php" class="topnav-icon-btn <?= ($activePage ?? '') === 'notifications' ? 'is-active' : '' ?>" aria-label="Notifications" title="Notifications">
        <i class="bi bi-bell"></i>
        <?php if ($notifyCount > 0): ?>
          <span class="topnav-notify-dot"><?= $notifyCount > 9 ? '9+' : (int) $notifyCount ?></span>
        <?php endif; ?>
      </a>

      <div class="dropdown topnav-user-dropdown">
        <button type="button" class="topnav-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="user-avatar"><i class="bi bi-person-fill"></i></span>
          <span class="user-info d-none d-md-flex">
            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>
            <span class="user-role"><?= htmlspecialchars($user['role_name'] ?? '') ?></span>
          </span>
          <i class="bi bi-chevron-down d-none d-md-inline topnav-user-caret"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>

  <?php if ($showPatientSearch): ?>
  <div class="topnav-row topnav-secondary">
    <div class="topnav-search" data-patient-search data-patient-search-variant="records-filter">
      <div class="patient-search-widget">
        <div class="patient-search-input-wrap">
          <i class="bi bi-search"></i>
          <input type="search"
                 class="patient-search-input"
                 placeholder="Search Patient ID, Name, PhilHealth No., Family Serial No…"
                 autocomplete="off"
                 aria-label="Search patients"
                 aria-autocomplete="list"
                 aria-controls="globalPatientSearchResults">
          <button type="button" class="patient-search-clear d-none" aria-label="Clear search">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="patient-search-dropdown" id="globalPatientSearchResults">
          <div class="patient-search-statusbar" hidden></div>
          <div class="patient-search-loading" hidden>
            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
            Searching…
          </div>
          <ul class="patient-search-results" role="listbox"></ul>
          <div class="patient-search-empty" hidden>Type to search patients…</div>
        </div>
      </div>
    </div>

    <?php if ($isRecords || $isIt): ?>
    <a href="<?= BASE_URL ?>/forms/doh-form.php" class="btn btn-outline-primary btn-new-patient">
      <i class="bi bi-plus-lg"></i> New Patient Record
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</header>
