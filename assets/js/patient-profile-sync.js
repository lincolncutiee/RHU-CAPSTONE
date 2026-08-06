/**
 * Patient Profile — live sync across all RHU paper forms (EMR-style).
 * One profile; encode identity once; mirrors update everywhere instantly.
 */
const PatientProfileSync = (() => {
    const STORAGE_KEY = 'rhu_patient_profile_draft';

    const PATIENT_FIELDS = new Set([
        'patient_id', 'family_serial_number', 'facility_code',
        'last_name', 'first_name', 'middle_name', 'suffix', 'maiden_name',
        'sex', 'birth_date', 'birthplace', 'blood_type', 'mothers_name',
        'civil_status', 'contact_number', 'dswd_nhts', 'facility_household_no',
        'four_ps_member', 'household_no', 'philhealth_member', 'philhealth_status_type',
        'philhealth_no', 'philhealth_category', 'educational_attainment',
        'employment_status', 'specific_occupation', 'employment_type', 'spouse_name',
        'father_name', 'mother_name', 'son_name', 'daughter_name',
        'other_family_member', 'pcb_member',
    ]);

    const EXTENDED_PROFILE_FIELDS = new Set([
        'address_street', 'address_barangay', 'address_municipality', 'address_province',
        'philhealth_fe_private', 'philhealth_fe_government', 'pkrf_registration_date', 'itr_age',
        'pkrf_date_registered',
        'pkrf_signatory_last', 'pkrf_signatory_first', 'pkrf_signatory_middle',
        'pkrf_confirm_full_name', 'pkrf_confirm_pin', 'pkrf_confirm_birth_date', 'pkrf_confirm_address',
        'ekas_patient_name', 'ekas_feedback_pin',
        'epress_patient_name', 'epress_feedback_pin',
    ]);

    const MAGALLANES = window.RHU_FORM?.magallanesAddress || {
        province: 'Agusan del Norte',
        municipality: 'Magallanes',
        barangays: [
            'Buhang', 'Caloc-an', 'Guiasan', 'Marcos',
            'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy',
        ],
    };

    const COMPUTED_EXTENDED = {
        itr_age: (s) => calcAge(s.birth_date),
        photo_consent_name: (s) => formatFullName(s),
        photo_consent_address: (s) => formatAddress(s),
        // PKRF signatory + confirmation slip — mirror beneficiary profile
        pkrf_signatory_last: (s) => (s.last_name || '').trim(),
        pkrf_signatory_first: (s) => (s.first_name || '').trim(),
        pkrf_signatory_middle: (s) => (s.middle_name || '').trim(),
        pkrf_confirm_full_name: (s) => formatFullName(s),
        pkrf_confirm_pin: (s) => (s.philhealth_no || '').trim(),
        pkrf_confirm_birth_date: (s) => (s.birth_date || '').trim(),
        pkrf_confirm_address: (s) => formatAddress(s),
        // RHU visit / encounter date
        pkrf_registration_date: () => getVisitDate(),
        pkrf_date_registered: () => getVisitDate(),
        ekas_patient_name: (s) => formatFullName(s),
        ekas_feedback_pin: (s) => (s.philhealth_no || '').trim(),
        epress_patient_name: (s) => formatFullName(s),
        epress_feedback_pin: (s) => (s.philhealth_no || '').trim(),
    };

    function getVisitDate() {
        const fromCfg = (window.RHU_FORM?.visitDate || '').trim();
        if (fromCfg) return fromCfg;
        const d = new Date();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${d.getFullYear()}-${mm}-${dd}`;
    }

    function getVisitTime() {
        const fromCfg = (window.RHU_FORM?.visitTime || '').trim();
        if (fromCfg) return fromCfg.length === 5 ? fromCfg : fromCfg.slice(0, 5);
        const d = new Date();
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    function getVisitPeriod(timeHHmm) {
        const hour = parseInt(String(timeHHmm || getVisitTime()).split(':')[0], 10);
        if (Number.isNaN(hour)) return '';
        return hour >= 12 ? 'PM' : 'AM';
    }

    let form = null;
    let profile = {};
    let syncing = false;
    let onChangeCallback = null;

    function init(formEl, options = {}) {
        form = formEl;
        onChangeCallback = options.onChange || null;

        form.querySelectorAll('[data-profile-field]').forEach(markProfileElement);

        form.addEventListener('input', handleFieldEvent);
        form.addEventListener('change', handleFieldEvent);

        restoreLocalDraft();
        tagProfileElements();
        applyAll();
        updateComputedFields();
    }

    function markProfileElement(el) {
        el.classList.add('pf-profile-field');
        // Only stamp readonly styling for true system/profile locks — not role-owned
        // fields that may still have temporary disabled/readonly before DOM permission sync.
        if (el.dataset.profileReadonly === '1' || el.name === 'patient_id') {
            el.classList.add('pf-profile-readonly');
        } else if (el.dataset.permEditable === '1') {
            el.classList.remove('pf-profile-readonly');
        } else if (el.dataset.permEditable === '0' || el.readOnly || el.disabled) {
            el.classList.add('pf-profile-readonly');
        }
    }

    function tagProfileElements() {
        form.querySelectorAll('input, textarea, select').forEach((el) => {
            const name = fieldKeyFromElement(el);
            if (!name) return;
            if (!isProfileField(name.key, name.extended)) return;
            el.dataset.profileField = name.key;
            el.dataset.profileExtended = name.extended ? '1' : '0';
            markProfileElement(el);
        });
    }

    function fieldKeyFromElement(el) {
        const fromData = el.dataset.profileField;
        if (fromData) {
            return { key: fromData, extended: el.dataset.profileExtended === '1' };
        }
        const m = el.name && el.name.match(/^extended\[(.+)\]$/);
        if (m) return { key: m[1], extended: true };
        if (el.name && !el.name.endsWith('[]')) return { key: el.name, extended: false };
        return null;
    }

    function isProfileField(key, extended) {
        if (extended) return EXTENDED_PROFILE_FIELDS.has(key) || key in COMPUTED_EXTENDED;
        return PATIENT_FIELDS.has(key);
    }

    function handleFieldEvent(e) {
        const el = e.target;
        const info = fieldKeyFromElement(el);
        if (!info || !isProfileField(info.key, info.extended)) return;

        if (syncing) return;

        // Fixed Magallanes municipality/province/composed street — ignore direct edits
        if (
            info.extended
            && (info.key === 'address_municipality'
                || info.key === 'address_province'
                || info.key === 'address_street')
        ) {
            return;
        }

        const value = readElementValue(el, info.key);
        setProfileValue(info.key, value, { skipElement: el, extended: info.extended });

        if (info.extended && info.key === 'address_barangay') {
            syncMagallanesAddress({ skipElement: el });
        }

        updateComputedFields({ skipElement: el });
        persistLocalDraft();
        onChangeCallback?.();
    }

    function readElementValue(el, key) {
        if (el.type === 'radio') {
            const checked = form.querySelector(
                `input[type="radio"][name="${CSS.escape(el.name)}"]:checked`
            );
            return checked ? checked.value : '';
        }
        if (el.type === 'checkbox') {
            return el.checked ? (el.value || '1') : '';
        }
        return (el.value || '').trim();
    }

    function getElements(key, extended = false) {
        const attr = extended ? '1' : '0';
        return form.querySelectorAll(`[data-profile-field="${CSS.escape(key)}"][data-profile-extended="${attr}"]`);
    }

    function setProfileValue(key, value, options = {}) {
        const extended = !!options.extended;
        profile[storageKey(key, extended)] = value ?? '';

        syncing = true;
        writeToAll(key, value ?? '', extended, options.skipElement || null);
        syncing = false;
    }

    function writeElementValue(el, value) {
        if (el.type === 'radio') {
            // Only touch this input — checking another same-name radio unchecks this one.
            el.checked = value != null && String(el.value) === String(value);
            return;
        }
        if (el.type === 'checkbox') {
            el.checked = value === '1' || value === 1 || value === true || value === el.value;
            return;
        }
        const next = value ?? '';
        el.value = next;
        // Force <select> option match (critical for inactive/hidden pages)
        if (el.tagName === 'SELECT' && next !== '' && el.value !== String(next)) {
            const opt = Array.from(el.options).find(
                (o) => String(o.value).toLowerCase() === String(next).toLowerCase()
            );
            if (opt) el.value = opt.value;
        }
    }

    /**
     * Radios with the same name appear on multiple wizard pages (e.g. sex on Enrollment + PKRF).
     * HTML allows only one checked radio per name in the whole form — checking every match
     * immediately unchecks the one the user clicked. Prefer skipElement, then active page.
     */
    function syncRadioGroupByName(name, value, skipElement = null) {
        const radios = [...form.querySelectorAll(`input[type="radio"][name="${CSS.escape(name)}"]`)];
        if (!radios.length) return false;

        radios.forEach((r) => { r.checked = false; });
        if (value == null || String(value).trim() === '') return true;

        const matches = radios.filter((r) => String(r.value) === String(value));
        if (!matches.length) return true;

        let target = null;
        if (skipElement && matches.includes(skipElement)) {
            target = skipElement;
        } else {
            target = matches.find((r) => r.closest('.wizard-page-pane.active')) || matches[0];
        }
        if (target) target.checked = true;
        return true;
    }

    function writeToAll(key, value, extended, skipElement = null) {
        const name = extended ? `extended[${key}]` : key;

        // Same-name radios across pages must sync as one group
        if (syncRadioGroupByName(name, value, skipElement)) {
            getElements(key, extended).forEach((el) => {
                if (el.type === 'radio') {
                    el.classList.toggle('pf-profile-filled', !!(value && String(value).trim()));
                }
            });
            return;
        }

        const applyTo = (el) => {
            if (skipElement && el === skipElement) return;
            writeElementValue(el, value);
            el.classList.toggle('pf-profile-filled', !!(value && String(value).trim()));
        };

        // Prefer profile-tagged copies, then any same-named field (covers every page)
        const seen = new Set();
        const visit = (el) => {
            if (!el || seen.has(el)) return;
            seen.add(el);
            applyTo(el);
        };

        getElements(key, extended).forEach(visit);

        form.querySelectorAll(
            `input:not([type="radio"]):not([type="checkbox"])[name="${CSS.escape(name)}"], `
            + `textarea[name="${CSS.escape(name)}"], select[name="${CSS.escape(name)}"]`
        ).forEach(visit);
    }

    function syncMagallanesAddress(options = {}) {
        const barangay = normalizeBarangay(getProfileValue('address_barangay', true));
        profile[storageKey('address_barangay', true)] = barangay;
        profile[storageKey('address_municipality', true)] = MAGALLANES.municipality;
        profile[storageKey('address_province', true)] = MAGALLANES.province;
        profile[storageKey('address_street', true)] = composeMagallanesAddress(barangay);

        const wasSyncing = syncing;
        syncing = true;
        writeToAll('address_barangay', barangay, true, options.skipElement || null);
        writeToAll('address_municipality', MAGALLANES.municipality, true, null);
        writeToAll('address_province', MAGALLANES.province, true, null);
        writeToAll('address_street', composeMagallanesAddress(barangay), true, null);
        syncing = wasSyncing;
    }

    function storageKey(key, extended) {
        return extended ? `ext:${key}` : key;
    }

    function getProfileValue(key, extended = false) {
        return profile[storageKey(key, extended)] ?? '';
    }

    /**
     * @param {object} patient
     * @param {object} extended
     * @param {{ merge?: boolean }} [options] merge=true keeps existing non-empty profile keys
     *        when the payload omits them (avoids wiping in-progress drafts).
     */
    function loadFromApi(patient, extended = {}, options = {}) {
        if (!options.merge) {
            profile = {};
        }
        if (patient) {
            Object.entries(patient).forEach(([k, v]) => {
                if (PATIENT_FIELDS.has(k) && v != null && v !== '') {
                    profile[storageKey(k, false)] = String(v);
                }
            });
            // Prefer barangay from stored residential_address when extended lacks it
            if (!extended?.address_barangay && patient.residential_address) {
                const fromAddr = normalizeBarangay(patient.residential_address);
                if (fromAddr) {
                    extended = { ...(extended || {}), address_barangay: fromAddr };
                }
            }
        }
        if (extended && typeof extended === 'object' && !Array.isArray(extended)) {
            Object.entries(extended).forEach(([k, v]) => {
                if ((EXTENDED_PROFILE_FIELDS.has(k) || k in COMPUTED_EXTENDED) && v != null && v !== '') {
                    profile[storageKey(k, true)] = String(v);
                }
            });
        }
        syncMagallanesAddress();
        applyAll();
        updateComputedFields();
        if (!options.merge) {
            clearLocalDraft();
        }
    }

    function applyAll() {
        syncMagallanesAddress();
        syncing = true;

        [...PATIENT_FIELDS].forEach((key) => {
            const val = getProfileValue(key, false);
            writeToAll(key, val, false);
        });

        [...EXTENDED_PROFILE_FIELDS].forEach((key) => {
            const val = getProfileValue(key, true);
            writeToAll(key, val, true);
        });

        syncing = false;
        highlightFilledFields();
        updateComputedFields();
    }

    function onPageChange() {
        // Re-harvest then push profile to every page copy (address included)
        harvestFromForm();
        applyAll();
    }

    function updateComputedFields(options = {}) {
        const state = flattenProfileState();
        syncing = true;

        Object.entries(COMPUTED_EXTENDED).forEach(([key, fn]) => {
            const val = fn(state) || '';
            profile[storageKey(key, true)] = val;
            writeToAll(key, val, true, options.skipElement || null);
            getElements(key, true).forEach((el) => {
                el.classList.add('pf-profile-computed');
                el.readOnly = true;
                el.setAttribute('readonly', 'readonly');
            });
            // Also name-matched copies without data-profile-field yet
            form.querySelectorAll(
                `input[name="extended[${CSS.escape(key)}]"], textarea[name="extended[${CSS.escape(key)}]"]`
            ).forEach((el) => {
                el.classList.add('pf-profile-computed');
                el.readOnly = true;
                el.setAttribute('readonly', 'readonly');
            });
        });

        fillKppDefaultsIfEmpty(state);
        applyVisitDateFields();
        syncing = false;
    }

    /** Prefer RHU Magallanes as 1st KPP when still blank (editable defaults). */
    function fillKppDefaultsIfEmpty(state) {
        const setIfEmpty = (name, value) => {
            if (!value) return;
            const el = form.querySelector(`input[name="extended[${CSS.escape(name)}]"], input[name="${CSS.escape(name)}"]`);
            if (!el) return;
            if ((el.value || '').trim() !== '') return;
            el.value = value;
            el.classList.toggle('pf-profile-filled', true);
        };

        const barangay = normalizeBarangay(state.address_barangay || '') || 'Poblacion';
        setIfEmpty('pkrf_kpp_1st', 'RHU MAGALLANES');
        setIfEmpty('pkrf_kpp1_barangay', barangay);
        setIfEmpty('pkrf_kpp1_city', MAGALLANES.municipality);
        setIfEmpty('pkrf_kpp1_province', MAGALLANES.province);
        setIfEmpty('pkrf_konsulta_package', 'RHU MAGALLANES');
        fillEkasDefaultsIfEmpty();
        fillEpressDefaultsIfEmpty();
    }

    function fillEkasDefaultsIfEmpty() {
        const setIfEmpty = (name, value) => {
            if (!value) return;
            const el = form.querySelector(`input[name="extended[${CSS.escape(name)}]"], input[name="${CSS.escape(name)}"]`);
            if (!el) return;
            if ((el.value || '').trim() !== '') return;
            el.value = value;
            el.classList.toggle('pf-profile-filled', true);
        };

        setIfEmpty('ekas_hci_name', 'MAGALLANES RHU & FAMILY PLANNING CENTER');
        setIfEmpty('ekas_accreditation_no', 'P14035975');
        setIfEmpty('ekas_facility_name', 'MAGALLANES RHU & FAMILY PLANNING CENTER');
    }

    function fillEpressDefaultsIfEmpty() {
        const setIfEmpty = (name, value) => {
            if (!value) return;
            const el = form.querySelector(`input[name="extended[${CSS.escape(name)}]"], input[name="${CSS.escape(name)}"]`);
            if (!el) return;
            if ((el.value || '').trim() !== '') return;
            el.value = value;
            el.classList.toggle('pf-profile-filled', true);
        };

        setIfEmpty('epress_hci_name', 'MAGALLANES RHU & FAMILY PLANNING CENTER');
        setIfEmpty('epress_accreditation_no', 'P14035975');
        setIfEmpty('epress_physician_name', 'DOC. BREECH MARTIN I. PABAS');
        setIfEmpty('epress_prc_lic', '0123909');
    }

    /** Date/Time of Consultation ← day & time patient came to RHU */
    function applyVisitDateFields() {
        const visit = getVisitDate();
        const time = getVisitTime();
        const period = getVisitPeriod(time);
        if (!form) return;

        if (visit) {
            form.querySelectorAll('input[name="date_of_consultation"]').forEach((el) => {
                if ((el.value || '').trim() !== '') return;
                el.value = visit;
                el.classList.add('pf-profile-filled');
            });
        }

        if (time) {
            form.querySelectorAll('input[name="consultation_time"]').forEach((el) => {
                if ((el.value || '').trim() !== '') return;
                el.value = time;
                el.classList.add('pf-profile-filled');
            });
        }

        if (period) {
            form.querySelectorAll('select[name="consultation_period"]').forEach((el) => {
                if ((el.value || '').trim() !== '') return;
                el.value = period;
                el.classList.add('pf-profile-filled');
            });
        }
    }

    function harvestFromForm() {
        PATIENT_FIELDS.forEach((key) => {
            const value = pickBestValue(key, false);
            if (value !== '') {
                profile[storageKey(key, false)] = value;
            }
        });

        EXTENDED_PROFILE_FIELDS.forEach((key) => {
            // Never harvest free-typed mun/province/street — Magallanes controls them
            if (
                key === 'address_municipality'
                || key === 'address_province'
                || key === 'address_street'
            ) {
                return;
            }
            const value = pickBestValue(key, true);
            if (value !== '') {
                profile[storageKey(key, true)] = value;
            }
        });

        syncMagallanesAddress();
        updateComputedFields();
    }

    function buildProfileFromState() {
        syncMagallanesAddress();
        const patient = {};
        const extended = {};

        PATIENT_FIELDS.forEach((k) => {
            const v = getProfileValue(k, false);
            if (v !== '') patient[k] = v;
        });

        EXTENDED_PROFILE_FIELDS.forEach((k) => {
            const v = getProfileValue(k, true);
            if (v !== '') extended[k] = v;
        });

        Object.keys(COMPUTED_EXTENDED).forEach((k) => {
            const v = getProfileValue(k, true);
            if (v !== '') extended[k] = v;
        });

        return { patient, extended };
    }

    /** First non-empty value wins (PKRF / earlier pages appear first in DOM). */
    function pickBestValue(key, extended) {
        const name = extended ? `extended[${key}]` : key;

        const radios = form.querySelectorAll(`input[type="radio"][name="${CSS.escape(name)}"]`);
        if (radios.length) {
            for (const r of radios) {
                if (r.checked && r.value) return r.value;
            }
            // Fall back to in-memory profile (disabled radios may not stay checked across pages)
            return getProfileValue(key, extended) || '';
        }

        const fields = form.querySelectorAll(
            `input:not([type="radio"]):not([type="checkbox"])[name="${CSS.escape(name)}"], `
            + `textarea[name="${CSS.escape(name)}"], select[name="${CSS.escape(name)}"]`
        );
        for (const el of fields) {
            const v = (el.value || '').trim();
            if (v) return v;
        }
        return getProfileValue(key, extended);
    }

    function flattenProfileState() {
        const s = {};
        PATIENT_FIELDS.forEach((k) => { s[k] = getProfileValue(k, false); });
        EXTENDED_PROFILE_FIELDS.forEach((k) => { s[k] = getProfileValue(k, true); });
        return s;
    }

    function highlightFilledFields() {
        form.querySelectorAll('[data-profile-field]').forEach((el) => {
            const info = fieldKeyFromElement(el);
            if (!info) return;
            const val = getProfileValue(info.key, info.extended);
            el.classList.toggle('pf-profile-filled', !!(val && String(val).trim()));
        });
    }

    function calcAge(birthDate) {
        if (!birthDate) return '';
        const dob = new Date(birthDate);
        if (Number.isNaN(dob.getTime())) return '';
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        return age >= 0 ? String(age) : '';
    }

    function formatFullName(s) {
        return [s.last_name, s.first_name, s.middle_name]
            .map((p) => (p || '').trim())
            .filter(Boolean)
            .join(', ')
            .replace(/^, |, $/g, '');
    }

    function formatAddress(s) {
        return composeMagallanesAddress(s.address_barangay || '');
    }

    function normalizeBarangay(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const list = MAGALLANES.barangays || [];
        const exact = list.find((b) => b.toLowerCase() === raw.toLowerCase());
        if (exact) return exact;
        const fromBrgy = raw.match(/Brgy\.\s*([^,]+)/i);
        if (fromBrgy) {
            const nested = list.find((b) => b.toLowerCase() === fromBrgy[1].trim().toLowerCase());
            if (nested) return nested;
        }
        const contained = list.find((b) => raw.toLowerCase().includes(b.toLowerCase()));
        return contained || '';
    }

    function composeMagallanesAddress(barangay) {
        const b = normalizeBarangay(barangay);
        if (!b) return '';
        return `Brgy. ${b}, ${MAGALLANES.municipality}, ${MAGALLANES.province}`;
    }

    function persistLocalDraft() {
        const patientDbId = document.getElementById('patientDbId')?.value;
        if (patientDbId && parseInt(patientDbId, 10) > 0) return;
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(profile));
        } catch (_) { /* ignore */ }
    }

    function restoreLocalDraft() {
        const patientDbId = document.getElementById('patientDbId')?.value;
        if (patientDbId && parseInt(patientDbId, 10) > 0) return;
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;
            profile = JSON.parse(raw) || {};
        } catch (_) {
            profile = {};
        }
    }

    function clearLocalDraft() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (_) { /* ignore */ }
    }

    function hasMinimumForCreate() {
        const required = window.RHU_FORM?.profileRequired || ['last_name', 'first_name', 'sex', 'birth_date'];
        return required.every((k) => !!(getProfileValue(k, false) || '').trim());
    }

    function isPatientColumn(key) {
        return PATIENT_FIELDS.has(key);
    }

    function isExtendedProfileKey(key) {
        return EXTENDED_PROFILE_FIELDS.has(key) || key in COMPUTED_EXTENDED;
    }

    function getPayloadForSave() {
        harvestFromForm();
        return buildProfileFromState();
    }

    return {
        init,
        loadFromApi,
        applyAll,
        onPageChange,
        buildProfileFromState,
        getPayloadForSave,
        harvestFromForm,
        hasMinimumForCreate,
        clearLocalDraft,
        getProfileValue,
        isPatientColumn,
        isExtendedProfileKey,
    };
})();
