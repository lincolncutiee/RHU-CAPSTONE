/**
 * Patient Registry — card / folder presentation layouts.
 * View mode is frontend-only (localStorage); filters/search unchanged.
 */

const PR_VIEW_STORAGE_KEY = 'rhu_patient_records_view';
const PR_VIEW_MODES = ['card', 'folder'];

function patientRecordOpenUrl(patient) {
    return `${BASE_URL}/patient-folder.php?id=${patient.id}`;
}

function patientRecordOpenLabel() {
    return 'Open Folder';
}

window.PatientRecordsLiveResults = (patients, query, err) => {
    if (err) {
        PatientRecordsPage.showError(err.message || 'Unable to load patient records.');
        return;
    }

    const list = patients || [];
    const hasQuery = (query || '').trim() !== '';
    const hasFilters = PatientRecordsPage.hasActiveFilters();

    if (!list.length) {
        PatientRecordsPage.setPatients([]);
        if (hasQuery || hasFilters) {
            PatientRecordsPage.showNoResults();
        } else {
            PatientRecordsPage.showEmptyRegistry();
        }
        return;
    }

    PatientRecordsPage.renderPatients(list);
};

const PatientRecordsPage = (() => {
    let resultsArea = null;
    let filterView = null;
    let filterBarangay = null;
    let filterStatus = null;
    let filterSex = null;
    let filterDateEncoded = null;
    let btnClearFilters = null;
    let currentPatients = [];
    let currentView = 'card';
    const consultationsCache = new Map();
    const visitFormsCache = new Map();

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function hasText(value) {
        if (value == null) return false;
        if (typeof value === 'string') {
            const t = value.trim();
            return t !== '' && t !== '—';
        }
        if (Array.isArray(value)) return value.some(hasText);
        if (typeof value === 'object') return Object.values(value).some(hasText);
        return true;
    }

    function hasPrefixedKey(data, prefix) {
        if (!data || typeof data !== 'object') return false;
        return Object.keys(data).some((key) => {
            if (!key.startsWith(prefix)) return false;
            return hasText(data[key]);
        });
    }

    function loadSavedView() {
        const saved = localStorage.getItem(PR_VIEW_STORAGE_KEY);
        return PR_VIEW_MODES.includes(saved) ? saved : 'card';
    }

    function saveView(view) {
        localStorage.setItem(PR_VIEW_STORAGE_KEY, view);
    }

    function setView(view, { rerender = true } = {}) {
        if (!PR_VIEW_MODES.includes(view)) view = 'card';
        currentView = view;
        saveView(view);
        if (filterView && filterView.value !== view) {
            filterView.value = view;
        }
        document.body.dataset.prView = view;
        if (rerender && currentPatients.length) {
            renderCurrentView();
        }
    }

    function syncViewButtons() {
        if (filterView) {
            filterView.value = currentView;
        }
    }

    function hasActiveFilters() {
        return (filterBarangay?.value || '') !== ''
            || (filterStatus?.value || '') !== ''
            || (filterSex?.value || '') !== ''
            || (filterDateEncoded?.value || '') !== '';
    }

    function getPhysicalRecordedFilter() {
        const raw = filterStatus?.value || '';
        if (raw === '__recorded__') return '1';
        if (raw === '__not_recorded__') return '0';
        return '';
    }

    function getTopbarSearchInput() {
        return document.querySelector('[data-patient-search-variant="records-filter"] .patient-search-input');
    }

    function setPatients(patients) {
        currentPatients = patients || [];
    }

    function renderPatients(patients) {
        currentPatients = patients || [];
        renderCurrentView();
    }

    function renderCurrentView() {
        if (!resultsArea) return;
        if (!currentPatients.length) {
            showEmptyRegistry();
            return;
        }
        if (currentView === 'folder') {
            renderPatientFolders(currentPatients);
        } else {
            renderPatientCards(currentPatients);
        }
    }

    function showLoading() {
        resultsArea.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p class="loading-text">Loading patient registry…</p>
            </div>
        `;
    }

    function showEmptyRegistry() {
        resultsArea.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
                <h3 class="empty-state-title">No patient records in the system</h3>
                <p class="empty-state-text">
                    New enrollments will appear here once they are encoded.
                </p>
            </div>
        `;
    }

    function showNoResults() {
        resultsArea.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-search"></i>
                </div>
                <h3 class="empty-state-title">No matching patient records found</h3>
                <p class="empty-state-text">
                    Try another search keyword or adjust the filters above.
                </p>
            </div>
        `;
    }

    function showError(message) {
        resultsArea.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon" style="background: #fef2f2;">
                    <i class="bi bi-exclamation-circle" style="color: #dc2626;"></i>
                </div>
                <h3 class="empty-state-title">Something went wrong</h3>
                <p class="empty-state-text">${escapeHtml(message)}</p>
            </div>
        `;
    }

    function getStatusBadgeClass(patient) {
        return patient.status_class || 'badge-draft';
    }

    function consultMeta(patient) {
        const consultLabel = patient.consultation_count_label
            || (patient.consultation_count === 1 ? '1 Consultation' : `${patient.consultation_count || 0} Consultations`);
        const lastConsult = patient.last_consultation_display || 'No Consultation Yet';
        const noConsult = lastConsult === 'No Consultation Yet';
        return { consultLabel, lastConsult, noConsult };
    }

    function isItUser() {
        const role = window.PR_USER_ROLE || '';
        return role === 'it_department' || role === 'it';
    }

    function isRecordsUser() {
        const role = window.PR_USER_ROLE || '';
        return role === 'records_office' || role === 'records';
    }

    function canArchivePatient(patient) {
        const status = patient.status;
        if (status === 'Archived') {
            return false;
        }
        if (isItUser()) {
            return true;
        }
        if (isRecordsUser()) {
            return status === 'Draft' || status === 'Rejected';
        }
        return false;
    }

    function getMenuItems(patient) {
        const items = [];
        if (patient.status === 'Archived') {
            return items;
        }

        if (patient.is_locked) {
            if (patient.status === 'Printed' && canArchivePatient(patient)) {
                return [
                    { icon: 'bi-archive', label: 'Archive', action: 'archive', class: 'text-danger' },
                ];
            }
            return items;
        }

        switch (patient.status) {
            case 'Draft':
                items.push(
                    { icon: 'bi-pencil', label: 'Edit Information', action: 'edit', class: '' },
                    { icon: 'bi-send', label: 'Submit for Consultation', action: 'submit', class: '' }
                );
                break;
            case 'Pending Receptionist':
            case 'Waiting for Doctor':
            case 'Pending Doctor':
            case 'Waiting for Pharmacy':
            case 'Dispensing':
            case 'Dispensed':
            case 'Pending IT Review':
            case 'Pending IT Validation':
            case 'Ready for Printing':
                items.push(
                    { icon: 'bi-clock-history', label: 'View Status', action: 'view-status', class: '' }
                );
                break;
            case 'Returned for Correction':
                items.push(
                    { icon: 'bi-pencil-square', label: 'Continue Editing', action: 'edit', class: '' },
                    { icon: 'bi-exclamation-triangle', label: 'View IT Remarks', action: 'rejection', class: '' },
                    { icon: 'bi-send-check', label: 'Resubmit to IT Review', action: 'resubmit', class: '' }
                );
                break;
            case 'Rejected':
                items.push(
                    { icon: 'bi-pencil', label: 'Edit Information', action: 'edit', class: '' },
                    { icon: 'bi-exclamation-triangle', label: 'View Rejection Reason', action: 'rejection', class: '' },
                    { icon: 'bi-arrow-repeat', label: 'Resubmit', action: 'resubmit', class: '' }
                );
                break;
            case 'Printed':
                break;
        }

        if (canArchivePatient(patient) && !items.some((item) => item.action === 'archive')) {
            if (items.length > 0) {
                items.push({ divider: true });
            }
            items.push(
                { icon: 'bi-archive', label: 'Archive', action: 'archive', class: 'text-danger' }
            );
        }

        return items;
    }

    function renderMenuHtml(patient) {
        const menuItems = getMenuItems(patient);
        if (!menuItems.length) return '';

        const menuHtml = menuItems.map((item) => {
            if (item.divider) return '<li><hr class="dropdown-divider"></li>';
            return `
                <li>
                    <button class="dropdown-item ${item.class}"
                            type="button"
                            data-action="${item.action}"
                            data-patient-id="${patient.id}"
                            data-patient-name="${escapeHtml(patient.full_name)}"
                            data-rejection-reason="${escapeHtml(patient.rejection_reason || '')}">
                        <i class="bi ${item.icon}"></i>
                        ${item.label}
                    </button>
                </li>
            `;
        }).join('');

        return `
            <div class="dropdown">
                <button class="btn-card-menu" type="button"
                        data-bs-toggle="dropdown"
                        data-bs-display="dynamic"
                        data-bs-popper-config='{"strategy":"fixed"}'
                        aria-expanded="false"
                        aria-label="More actions">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-card">
                    ${menuHtml}
                </ul>
            </div>
        `;
    }

    function isPhysicalRecorded(patient) {
        return patient.physical_recorded === true
            || patient.physical_recorded === 1
            || patient.physical_recorded === '1';
    }

    function renderPhysicalCheckbox(patient, variant = 'card') {
        const recorded = isPhysicalRecorded(patient);
        const checked = recorded ? ' checked' : '';
        const recordedClass = recorded ? ' is-recorded' : '';
        const title = recorded
            ? 'Physical folder recorded — click to mark as not recorded'
            : 'Physical folder not recorded — click to mark as recorded';
        return `
            <label class="pr-physical-check pr-physical-check--${variant}${recordedClass}"
                   title="${title}"
                   onclick="event.stopPropagation()">
                <input type="checkbox"
                       class="pr-physical-input"
                       data-physical-check
                       data-patient-id="${patient.id}"
                       aria-label="Physical folder recorded"${checked}>
                <span class="pr-physical-box" aria-hidden="true"></span>
            </label>
        `;
    }

    function renderPatientCards(patients) {
        const cardsHtml = patients.map((patient, index) => {
            const statusLabel = patient.display_status || patient.status;
            const { consultLabel, lastConsult, noConsult } = consultMeta(patient);

            return `
                <article class="patient-card patient-card-compact" style="animation-delay: ${index * 0.03}s" data-patient-id="${patient.id}">
                    <div class="patient-card-top">
                        <span class="patient-card-id">${escapeHtml(patient.patient_id)}</span>
                        <span class="badge-status ${getStatusBadgeClass(patient)}" title="${escapeHtml(patient.status)}">
                            ${escapeHtml(statusLabel)}
                        </span>
                    </div>

                    <h3 class="patient-card-name">${escapeHtml(patient.formal_name || patient.full_name)}</h3>

                    <div class="patient-card-meta">
                        <span class="meta-chip"><i class="bi bi-person"></i> ${patient.age ?? '—'} · ${escapeHtml(patient.sex || '—')}</span>
                        <span class="meta-chip"><i class="bi bi-geo-alt"></i> ${escapeHtml(patient.barangay)}</span>
                    </div>

                    <div class="patient-card-consult">
                        <div class="consult-stat">
                            <span class="consult-stat-label">Last Consultation</span>
                            <span class="consult-stat-value${noConsult ? ' is-muted' : ''}">${escapeHtml(lastConsult)}</span>
                        </div>
                        <div class="consult-stat consult-stat-count">
                            <span class="consult-stat-label">Total Visits</span>
                            <span class="consult-stat-value">${escapeHtml(consultLabel)}</span>
                        </div>
                    </div>

                    <div class="patient-card-footer">
                        <a href="${patientRecordOpenUrl(patient)}"
                           class="btn-view-profile btn-open-folder">
                            <i class="bi bi-folder2-open"></i>
                            ${patientRecordOpenLabel()}
                        </a>
                        ${renderMenuHtml(patient)}
                    </div>
                    ${renderPhysicalCheckbox(patient, 'card')}
                </article>
            `;
        }).join('');

        resultsArea.innerHTML = `
            <div class="patient-cards-grid patient-cards-grid-compact">
                ${cardsHtml}
            </div>
        `;

        bindCardActions();
        bindPhysicalChecks();
    }

    function renderPatientFolders(patients) {
        const folders = patients.map((patient) => {
            const statusLabel = patient.display_status || patient.status;
            const { consultLabel, lastConsult, noConsult } = consultMeta(patient);

            return `
                <article class="pr-folder" data-patient-id="${patient.id}">
                    <div class="pr-folder-bar">
                        <button type="button" class="pr-folder-header" aria-expanded="false"
                                data-folder-toggle="${patient.id}">
                            <span class="pr-folder-chevron" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                            <span class="pr-folder-icon" aria-hidden="true"><i class="bi bi-folder2"></i></span>
                            <span class="pr-folder-main">
                                <span class="pr-folder-name">${escapeHtml(patient.formal_name || patient.full_name)}</span>
                                <span class="pr-folder-meta">
                                    <span class="${noConsult ? 'pr-list-muted' : ''}">
                                        <i class="bi bi-calendar3"></i> ${escapeHtml(lastConsult)}
                                    </span>
                                    <span><i class="bi bi-journal-medical"></i> ${escapeHtml(consultLabel)}</span>
                                </span>
                            </span>
                            <span class="badge-status ${getStatusBadgeClass(patient)}">${escapeHtml(statusLabel)}</span>
                        </button>
                        <a href="${patientRecordOpenUrl(patient)}"
                           class="btn btn-sm btn-outline-primary pr-folder-open"
                           title="Open full patient folder">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <div class="pr-folder-actions">
                            ${renderPhysicalCheckbox(patient, 'folder')}
                            ${renderMenuHtml(patient)}
                        </div>
                    </div>
                    <div class="pr-folder-body" hidden data-folder-body="${patient.id}">
                        <div class="pr-folder-loading">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                            Loading visits…
                        </div>
                    </div>
                </article>
            `;
        }).join('');

        resultsArea.innerHTML = `<div class="pr-folder-list">${folders}</div>`;
        bindFolderToggles();
        bindCardActions();
        bindPhysicalChecks();
    }

    function bindFolderToggles() {
        resultsArea.querySelectorAll('[data-folder-toggle]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const patientId = Number(btn.dataset.folderToggle);
                const body = resultsArea.querySelector(`[data-folder-body="${patientId}"]`);
                const folder = btn.closest('.pr-folder');
                if (!body || !folder) return;

                const isOpen = btn.getAttribute('aria-expanded') === 'true';
                const icon = folder.querySelector('.pr-folder-icon i');
                if (isOpen) {
                    btn.setAttribute('aria-expanded', 'false');
                    body.hidden = true;
                    folder.classList.remove('is-open');
                    if (icon) icon.className = 'bi bi-folder2';
                    return;
                }

                btn.setAttribute('aria-expanded', 'true');
                body.hidden = false;
                folder.classList.add('is-open');
                if (icon) icon.className = 'bi bi-folder2-open';

                if (!body.dataset.loaded) {
                    await loadFolderVisits(patientId, body);
                    body.dataset.loaded = '1';
                }
            });
        });
    }

    async function loadFolderVisits(patientId, bodyEl) {
        bodyEl.innerHTML = `
            <div class="pr-folder-loading">
                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                Loading visits…
            </div>
        `;

        try {
            let consultations = consultationsCache.get(patientId);
            if (!consultations) {
                const res = await fetch(`${BASE_URL}/api/consultations.php?patient_id=${patientId}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed to load visits.');
                consultations = data.consultations || [];
                consultationsCache.set(patientId, consultations);
            }

            if (!consultations.length) {
                bodyEl.innerHTML = `
                    <div class="pr-folder-empty">
                        No consultation visits yet for this patient.
                    </div>
                `;
                return;
            }

            bodyEl.innerHTML = `
                <div class="pr-visit-list">
                    ${consultations.map((c) => renderVisitRow(patientId, c)).join('')}
                </div>
            `;
            bindVisitToggles(bodyEl, patientId);
        } catch (err) {
            bodyEl.innerHTML = `
                <div class="pr-folder-empty pr-folder-error">
                    ${escapeHtml(err.message || 'Unable to load visits.')}
                </div>
            `;
        }
    }

    function renderVisitRow(patientId, consultation) {
        const cid = consultation.id;
        const statusClass = consultation.status_class || 'badge-draft';
        return `
            <div class="pr-visit" data-visit-id="${cid}">
                <button type="button" class="pr-visit-header" aria-expanded="false"
                        data-visit-toggle="${cid}" data-patient-id="${patientId}">
                    <span class="pr-visit-chevron"><i class="bi bi-chevron-right"></i></span>
                    <span class="pr-visit-main">
                        <span class="pr-visit-label">Visit #${escapeHtml(consultation.visit_number)}</span>
                        <span class="pr-visit-date">${escapeHtml(consultation.consult_date_display || '—')}</span>
                        <span class="pr-visit-doctor"><i class="bi bi-person-badge"></i> ${escapeHtml(consultation.doctor || '—')}</span>
                    </span>
                    <span class="badge-status ${escapeHtml(statusClass)}">${escapeHtml(consultation.status || '—')}</span>
                </button>
                <div class="pr-visit-body" hidden data-visit-body="${cid}"></div>
            </div>
        `;
    }

    function bindVisitToggles(scope, patientId) {
        scope.querySelectorAll('[data-visit-toggle]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const visitId = Number(btn.dataset.visitToggle);
                const body = scope.querySelector(`[data-visit-body="${visitId}"]`);
                const visit = btn.closest('.pr-visit');
                if (!body || !visit) return;

                const isOpen = btn.getAttribute('aria-expanded') === 'true';
                if (isOpen) {
                    btn.setAttribute('aria-expanded', 'false');
                    body.hidden = true;
                    visit.classList.remove('is-open');
                    return;
                }

                btn.setAttribute('aria-expanded', 'true');
                body.hidden = false;
                visit.classList.add('is-open');

                if (!body.dataset.loaded) {
                    await loadVisitForms(patientId, visitId, body);
                    body.dataset.loaded = '1';
                }
            });
        });
    }

    function detectCompletedForms(patientId, consultationId, payload) {
        const treatment = payload.treatment || {};
        const extended = payload.extended || {};
        const medications = payload.medications || [];
        const patient = payload.patient || {};
        const forms = [];

        const open = (page) => {
            let url = `${BASE_URL}/forms/doh-form.php?id=${patientId}&page=${encodeURIComponent(page)}`;
            if (consultationId > 0) url += `&consultation_id=${consultationId}`;
            return url;
        };

        if (hasText(patient.first_name) || hasText(patient.last_name) || hasText(patient.philhealth_no)) {
            forms.push({
                id: 'patient-info',
                label: 'Patient Information',
                url: `${BASE_URL}/patient-folder.php?id=${patientId}&tab=summary`,
            });
        }

        if (hasText(treatment.blood_pressure)
            || hasText(treatment.height_cm)
            || hasText(treatment.weight_kg)
            || hasText(treatment.temperature)
            || hasText(treatment.pulse_rate)
            || hasText(treatment.respiratory_rate)
        ) {
            forms.push({ id: 'vitals', label: 'Vital Signs', url: open('itr') });
        }

        if (hasText(treatment.chief_complaints)
            || hasText(treatment.diagnosis)
            || hasText(treatment.healthcare_provider)
            || hasText(treatment.attending_provider)
            || hasText(treatment.date_of_consultation)
            || (payload.diagnoses && payload.diagnoses.length)
        ) {
            forms.push({ id: 'doctor', label: 'Doctor Consultation', url: open('itr') });
        }

        if (medications.length
            || hasText(treatment.medication_treatment)
            || hasPrefixedKey(extended, 'epress_')
        ) {
            forms.push({ id: 'rx', label: 'Prescription', url: open('epress') });
        }

        if (hasPrefixedKey(extended, 'lab_')
            || hasPrefixedKey(extended, 'laboratory_')
            || hasText(treatment.laboratory_findings)
            || hasText(treatment.lab_request)
        ) {
            forms.push({ id: 'lab', label: 'Laboratory Request', url: open('itr') });
        }

        if (hasPrefixedKey(extended, 'medcert_')
            || hasPrefixedKey(extended, 'medical_certificate')
            || hasPrefixedKey(extended, 'certificate_')
        ) {
            forms.push({ id: 'medcert', label: 'Medical Certificate', url: open('itr') });
        }

        if (hasText(treatment.referred_to) || hasText(treatment.referral_reasons)) {
            forms.push({ id: 'referral', label: 'Referral', url: open('itr') });
        }

        return forms;
    }

    async function loadVisitForms(patientId, consultationId, bodyEl) {
        bodyEl.innerHTML = `
            <div class="pr-folder-loading">
                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                Loading forms…
            </div>
        `;

        const cacheKey = `${patientId}:${consultationId}`;
        try {
            let forms = visitFormsCache.get(cacheKey);
            if (!forms) {
                const res = await fetch(
                    `${BASE_URL}/api/get_patient.php?id=${patientId}&consultation_id=${consultationId}`
                );
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed to load forms.');
                forms = detectCompletedForms(patientId, consultationId, data);
                visitFormsCache.set(cacheKey, forms);
            }

            if (!forms.length) {
                bodyEl.innerHTML = `<div class="pr-folder-empty">No completed forms for this visit yet.</div>`;
                return;
            }

            bodyEl.innerHTML = `
                <div class="pr-form-list">
                    ${forms.map((form) => `
                        <a class="pr-form-item" href="${escapeHtml(form.url)}">
                            <span class="pr-form-icon"><i class="bi bi-file-earmark-medical"></i></span>
                            <span class="pr-form-label">${escapeHtml(form.label)}</span>
                            <span class="pr-form-action">View <i class="bi bi-chevron-right"></i></span>
                        </a>
                    `).join('')}
                </div>
            `;
        } catch (err) {
            bodyEl.innerHTML = `
                <div class="pr-folder-empty pr-folder-error">
                    ${escapeHtml(err.message || 'Unable to load forms.')}
                </div>
            `;
        }
    }

    function bindPhysicalChecks() {
        resultsArea.querySelectorAll('[data-physical-check]').forEach((input) => {
            input.addEventListener('click', (e) => e.stopPropagation());
            input.addEventListener('change', async () => {
                const patientId = Number(input.dataset.patientId);
                const recorded = input.checked ? 1 : 0;
                const label = input.closest('.pr-physical-check');
                if (label) {
                    label.classList.toggle('is-recorded', recorded === 1);
                }
                input.disabled = true;

                try {
                    const res = await fetch(`${BASE_URL}/api/physical_folder.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            patient_id: patientId,
                            physical_recorded: recorded,
                        }),
                    });
                    const data = await res.json();
                    if (!data.success) {
                        throw new Error(data.message || 'Save failed');
                    }

                    const saved = !!data.physical_recorded;
                    input.checked = saved;
                    const idx = currentPatients.findIndex((p) => Number(p.id) === patientId);
                    if (idx >= 0) {
                        currentPatients[idx].physical_recorded = saved;
                    }

                    if (label) {
                        label.classList.toggle('is-recorded', saved);
                        label.title = saved
                            ? 'Physical folder recorded — click to mark as not recorded'
                            : 'Physical folder not recorded — click to mark as recorded';
                    }

                    // If Recorded / Not Recorded status filter is active and patient no longer matches, refresh list
                    const filterVal = getPhysicalRecordedFilter();
                    if (filterVal !== '') {
                        const wantRecorded = filterVal === '1';
                        if (saved !== wantRecorded) {
                            refreshList();
                        }
                    }
                } catch (err) {
                    input.checked = !input.checked;
                    if (label) {
                        label.classList.toggle('is-recorded', input.checked);
                    }
                    alert(err.message || 'Unable to save physical folder status.');
                } finally {
                    input.disabled = false;
                }
            });
        });
    }

    function bindCardActions() {
        resultsArea.querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const patientId = btn.dataset.patientId;
                const patientName = btn.dataset.patientName;

                switch (action) {
                    case 'edit':
                        window.location.href = `${BASE_URL}/forms/doh-form.php?id=${patientId}`;
                        break;
                    case 'submit':
                        if (confirm(`Submit "${patientName}" for validation?`)) {
                            alert('Record submitted for validation. (Connect to backend API)');
                        }
                        break;
                    case 'archive':
                        archivePatient(patientId, patientName);
                        break;
                    case 'view-status':
                        alert(`Validation status for "${patientName}": Pending review by IT Department.`);
                        break;
                    case 'rejection':
                        showRejectionModal(patientName, btn.dataset.rejectionReason);
                        break;
                    case 'resubmit':
                        window.location.href = `${BASE_URL}/forms/doh-form.php?id=${patientId}`;
                        break;
                }
            });
        });
    }

    function showRejectionModal(patientName, reason) {
        document.getElementById('rejectionPatientName').textContent = patientName;
        document.getElementById('rejectionReasonText').textContent = reason || 'No reason provided.';
        const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
        modal.show();
    }

    async function archivePatient(patientId, patientName) {
        if (!confirm(`Archive "${patientName}"? This record will be hidden from the patient list.`)) {
            return;
        }

        try {
            const res = await fetch(`${BASE_URL}/api/archive_patient.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ patient_id: Number(patientId) }),
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'Archive failed.');
            }
            refreshList();
        } catch (err) {
            alert(err.message || 'Unable to archive record.');
        }
    }

    function refreshList() {
        consultationsCache.clear();
        visitFormsCache.clear();
        showLoading();
        PatientLiveSearch.triggerRecordsSearch();
    }

    function init() {
        resultsArea = document.getElementById('resultsArea');
        filterView = document.getElementById('filterView');
        filterBarangay = document.getElementById('filterBarangay');
        filterStatus = document.getElementById('filterStatus');
        filterSex = document.getElementById('filterSex');
        filterDateEncoded = document.getElementById('filterDateEncoded');
        btnClearFilters = document.getElementById('btnClearFilters');

        if (!resultsArea) return;

        currentView = loadSavedView();
        syncViewButtons();
        document.body.dataset.prView = currentView;

        filterView?.addEventListener('change', () => {
            setView(filterView.value, { rerender: true });
        });

        [filterBarangay, filterStatus, filterSex, filterDateEncoded].forEach((el) => {
            el?.addEventListener('change', refreshList);
        });

        btnClearFilters?.addEventListener('click', () => {
            if (filterBarangay) filterBarangay.value = '';
            if (filterStatus) filterStatus.value = '';
            if (filterSex) filterSex.value = '';
            if (filterDateEncoded) filterDateEncoded.value = '';

            const searchInput = getTopbarSearchInput();
            if (searchInput) {
                searchInput.value = '';
                const clearBtn = searchInput.closest('.patient-search-widget')?.querySelector('.patient-search-clear');
                clearBtn?.classList.add('d-none');
            }

            refreshList();
        });

        refreshList();
    }

    return {
        init,
        hasActiveFilters,
        showError,
        showNoResults,
        showEmptyRegistry,
        renderPatientCards,
        renderPatients,
        setPatients,
    };
})();

document.addEventListener('DOMContentLoaded', () => PatientRecordsPage.init());
