<header class="topbar">
  <button class="btn btn-link sidebar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list"></i>
  </button>

  <div class="topbar-title">
    <h1 class="page-heading"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
    <?php if (!empty($pageSubtitle)): ?>
      <p class="page-subheading"><?= htmlspecialchars($pageSubtitle) ?></p>
    <?php endif; ?>
  </div>

  <?php $patientSearchVariant = (($activePage ?? '') === 'records') ? 'records-filter' : 'navigate'; ?>
  <div class="topbar-search" data-patient-search data-patient-search-variant="<?= htmlspecialchars($patientSearchVariant) ?>">
    <div class="patient-search-widget">
      <div class="patient-search-input-wrap">
        <i class="bi bi-search"></i>
        <input type="search"
               class="patient-search-input"
               placeholder="Search Patient ID, Name, PhilHealth No., Family Serial…"
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

  <div class="topbar-actions">
    <div class="topbar-user">
      <div class="user-avatar">
        <i class="bi bi-person-fill"></i>
      </div>
      <div class="user-info d-none d-md-block">
        <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>
        <span class="user-role"><?= htmlspecialchars($user['role_name'] ?? '') ?></span>
      </div>
    </div>
  </div>
</header>
