/**
 * Live patient search with autocomplete — system-wide.
 */
const PatientLiveSearch = (() => {
    const DEBOUNCE_MS = 220;
    const MIN_CHARS = 1;

    let abortController = null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    async function fetchPatients(query, filters = {}, autocomplete = true, options = {}) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        const hasFilters = Object.values(filters).some((v) => v !== '' && v != null);
        const listAll = options.listAll === true;

        const params = new URLSearchParams();
        if (query) params.set('search', query);
        if (listAll) params.set('list', 'all');
        if (autocomplete && !hasFilters && !listAll) params.set('mode', 'autocomplete');
        if (options.sort) params.set('sort', options.sort);
        Object.entries(filters).forEach(([key, val]) => {
            if (val) params.set(key, val);
        });

        const res = await fetch(`${BASE_URL}/api/search_patients.php?${params.toString()}`, {
            signal: abortController.signal,
        });
        return res.json();
    }

    function renderResultItem(patient, active = false) {
        const ph = patient.philhealth_no
            ? `<span><i class="bi bi-credit-card-2-front"></i> ${escapeHtml(patient.philhealth_no)}</span>`
            : '';

        return `
            <li>
                <button type="button"
                        class="patient-search-item${active ? ' is-active' : ''}"
                        data-patient-id="${patient.id}"
                        data-profile-url="${escapeHtml(patient.profile_url)}">
                    <div class="patient-search-item-top">
                        <span class="patient-search-item-name">${escapeHtml(patient.formal_name || patient.full_name)}</span>
                        <span class="ps-status-badge badge-status ${escapeHtml(patient.status_class)}">${escapeHtml(patient.display_status)}</span>
                    </div>
                    <div class="patient-search-item-meta">
                        <span><i class="bi bi-person-badge"></i> ${escapeHtml(patient.patient_id)}</span>
                        ${ph}
                    </div>
                </button>
            </li>
        `;
    }

    function initWidget(root, options = {}) {
        const input = root.querySelector('.patient-search-input');
        const dropdown = root.querySelector('.patient-search-dropdown');
        const resultsList = root.querySelector('.patient-search-results');
        const emptyEl = root.querySelector('.patient-search-empty');
        const loadingEl = root.querySelector('.patient-search-loading');
        const statusBar = root.querySelector('.patient-search-statusbar');
        const clearBtn = root.querySelector('.patient-search-clear');

        if (!input || !dropdown) return;

        const navigateOnSelect = options.navigateOnSelect !== false;
        const filterListOnly = options.filterListOnly === true;
        const onResults = typeof options.onResults === 'function' ? options.onResults : null;
        const getFilters = typeof options.getFilters === 'function' ? options.getFilters : () => ({});

        let debounceTimer = null;
        let activeIndex = -1;
        let lastPatients = [];

        function setLoading(show) {
            if (loadingEl) loadingEl.hidden = !show;
            if (resultsList) resultsList.hidden = show;
            if (emptyEl) emptyEl.hidden = true;
        }

        function closeDropdown() {
            dropdown.classList.remove('is-open');
            activeIndex = -1;
        }

        function openDropdown() {
            if (!filterListOnly) {
                dropdown.classList.add('is-open');
            }
        }

        function updateClearButton() {
            if (!clearBtn) return;
            clearBtn.classList.toggle('d-none', !input.value.trim());
        }

        function selectPatient(patient) {
            if (!patient) return;
            const url = resolvePatientOpenUrl(patient);
            if (url) {
                window.location.href = url;
                return;
            }
            if (options.onSelect) {
                options.onSelect(patient);
            }
        }

        function resolvePatientOpenUrl(patient) {
            if (navigateOnSelect) {
                return `${BASE_URL}/patient-folder.php?id=${patient.id}`;
            }
            return null;
        }

        function renderDropdown(patients, query) {
            lastPatients = patients;
            activeIndex = -1;

            if (filterListOnly) {
                closeDropdown();
                return;
            }

            if (statusBar) {
                if (!query) {
                    statusBar.hidden = true;
                } else {
                    statusBar.hidden = false;
                    statusBar.textContent = patients.length
                        ? `${patients.length} match${patients.length !== 1 ? 'es' : ''} for "${query}"`
                        : `No matches for "${query}"`;
                }
            }

            setLoading(false);

            if (!patients.length) {
                if (resultsList) {
                    resultsList.innerHTML = '';
                    resultsList.hidden = true;
                }
                if (emptyEl) {
                    emptyEl.hidden = false;
                    emptyEl.textContent = query.length >= MIN_CHARS
                        ? 'No matching patient records found.'
                        : 'Type to search patients…';
                }
                openDropdown();
                return;
            }

            if (emptyEl) emptyEl.hidden = true;
            if (resultsList) {
                resultsList.hidden = false;
                resultsList.innerHTML = patients.map((p) => renderResultItem(p)).join('');
                resultsList.querySelectorAll('.patient-search-item').forEach((btn, idx) => {
                    btn.addEventListener('click', () => selectPatient(patients[idx]));
                });
            }
            openDropdown();
        }

        async function runSearch() {
            const query = input.value.trim();
            const filters = getFilters();
            const hasFilters = hasFilterValues(filters);
            updateClearButton();

            if (!filterListOnly && query.length < MIN_CHARS && !hasFilters) {
                closeDropdown();
                if (onResults) onResults([], query);
                return;
            }

            if (!filterListOnly) {
                setLoading(true);
                openDropdown();
            }

            const listAll = filterListOnly && query.length < MIN_CHARS && !hasFilters;

            try {
                const data = await fetchPatients(query, filters, !listAll, { listAll, sort: getRecordsSort() });
                if (!data.success) {
                    throw new Error(data.message || 'Search failed');
                }
                const patients = data.patients || [];
                renderDropdown(patients, query);
                if (onResults) onResults(patients, query);
            } catch (err) {
                if (err.name === 'AbortError') return;
                if (!filterListOnly) {
                    setLoading(false);
                    if (emptyEl) {
                        emptyEl.hidden = false;
                        emptyEl.textContent = 'Unable to search. Please try again.';
                    }
                    if (resultsList) resultsList.hidden = true;
                    openDropdown();
                }
                if (onResults) onResults(null, query, err);
            }
        }

        function hasFilterValues(filters) {
            return Object.values(filters).some((v) => v !== '' && v != null);
        }

        function scheduleSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
        }

        function highlightActive() {
            if (!resultsList) return;
            const items = resultsList.querySelectorAll('.patient-search-item');
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        input.addEventListener('input', scheduleSearch);
        input.addEventListener('focus', () => {
            if (filterListOnly) return;
            if (input.value.trim().length >= MIN_CHARS) {
                scheduleSearch();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (filterListOnly) return;

            if (!dropdown.classList.contains('is-open') || !lastPatients.length) {
                if (e.key === 'Escape') closeDropdown();
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, lastPatients.length - 1);
                highlightActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                highlightActive();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0) {
                    selectPatient(lastPatients[activeIndex]);
                } else if (lastPatients.length === 1) {
                    selectPatient(lastPatients[0]);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        clearBtn?.addEventListener('click', () => {
            input.value = '';
            updateClearButton();
            closeDropdown();
            scheduleSearch();
            input.focus();
        });

        document.addEventListener('click', (e) => {
            if (!root.contains(e.target)) {
                closeDropdown();
            }
        });

        root._patientSearchSchedule = scheduleSearch;
        updateClearButton();
    }

    function getRecordsFilters() {
        const rawStatus = document.getElementById('filterStatus')?.value || '';
        let status = rawStatus;
        let physicalRecorded = '';
        if (rawStatus === '__recorded__') {
            status = '';
            physicalRecorded = '1';
        } else if (rawStatus === '__not_recorded__') {
            status = '';
            physicalRecorded = '0';
        }

        return {
            barangay: document.getElementById('filterBarangay')?.value || '',
            status,
            sex: document.getElementById('filterSex')?.value || '',
            date_encoded: document.getElementById('filterDateEncoded')?.value || '',
            physical_recorded: physicalRecorded,
        };
    }

    function getRecordsSort() {
        return document.getElementById('filterSort')?.value || 'encoded_asc';
    }

    function triggerRecordsSearch() {
        const root = document.querySelector('[data-patient-search-variant="records-filter"]');
        if (root?._patientSearchSchedule) {
            root._patientSearchSchedule();
            return;
        }
        const input = root?.querySelector('.patient-search-input');
        input?.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function initAll() {
        document.querySelectorAll('[data-patient-search]').forEach((root) => {
            if (root.dataset.psInitialized === '1') return;
            root.dataset.psInitialized = '1';

            const variant = root.dataset.patientSearchVariant || 'navigate';

            if (variant === 'records-filter') {
                initWidget(root, {
                    navigateOnSelect: false,
                    filterListOnly: true,
                    getFilters: getRecordsFilters,
                    onResults: (patients, query, err) => {
                        if (typeof window.PatientRecordsLiveResults === 'function') {
                            window.PatientRecordsLiveResults(patients, query, err);
                        }
                    },
                });
            } else {
                initWidget(root, { navigateOnSelect: true });
            }
        });
    }

    return { initAll, fetchPatients, escapeHtml, triggerRecordsSearch, getRecordsFilters, getRecordsSort };
})();

document.addEventListener('DOMContentLoaded', () => PatientLiveSearch.initAll());
