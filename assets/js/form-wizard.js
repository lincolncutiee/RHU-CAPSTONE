/**
 * Patient Forms Module — Option A encoding workspace
 *
 * Collapsible 220px sidebar + sticky top toolbar + fit-width viewer.
 * Encoding pages → Previous + Save Draft + Next (no Print)
 * Review page    → Print Preview only (final print via IT queue)
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dohForm');
    const cfg = window.RHU_FORM;
    if (!form || !cfg) return;

    let currentPage = cfg.startPage || 0;
    let autoSaveTimer = null;
    let reviewZoom = 1;
    let reviewFitMode = true;
    let reviewActivePageIndex = 0;
    /** @type {Record<number, 'complete'|'incomplete'>} */
    const pageFillStatus = {};

    PatientProfileSync.init(form, {
        onChange: () => scheduleAutoSave(),
    });

    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnGoReview = document.getElementById('btnGoReview');
    const btnSaveDraft = document.getElementById('btnSaveDraft');
    const btnSendToDoctor = document.getElementById('btnSendToDoctor');
    const btnSubmitIT = document.getElementById('btnSubmitIT');
    const btnComplete = document.getElementById('btnCompleteConsultation');
    const btnPreviewFinal = document.getElementById('btnPreviewFinalPrint');
    const saveStatus = document.getElementById('saveStatus');
    const progressText = document.getElementById('wizardProgressText');
    const toolbarPageHint = document.getElementById('toolbarPageHint');
    const formProgressInput = document.getElementById('formProgress');
    const SAVE_DRAFT_LABEL = cfg.role === 'records_office'
        ? '<i class="bi bi-save"></i> Save'
        : '<i class="bi bi-save"></i> Save Draft';
    const isRecordsRole = cfg.role === 'records_office';

    // Keep patient id in the form so Send to Doctor stays clickable
    if (cfg.patientId > 0) {
        const dbEl = document.getElementById('patientDbId');
        if (dbEl && (!dbEl.value || dbEl.value === '0')) {
            dbEl.value = String(cfg.patientId);
        }
    }

    initSidebarCollapse();

    if (cfg.isLocked || cfg.canSaveDraft === false) {
        if (cfg.isLocked) {
            btnSaveDraft?.classList.add('d-none');
            btnSendToDoctor?.classList.add('d-none');
            btnSubmitIT?.classList.add('d-none');
            btnComplete?.classList.add('d-none');
            form.querySelectorAll('input, select, textarea, button').forEach((el) => {
                if (
                    el.closest('.form-workspace-toolbar') ||
                    el.closest('.form-nav-footer') ||
                    el.closest('.wizard-nav') ||
                    el.closest('.wizard-sidebar-header') ||
                    el.closest('.wizard-toolbar')
                ) {
                    return;
                }
                if (el.type === 'hidden') return;
                el.disabled = true;
                el.readOnly = true;
            });
        } else {
            // Records fields locked by workflow, but form may still be viewable
            btnSaveDraft?.classList.add('d-none');
            btnSendToDoctor?.classList.add('d-none');
            btnSubmitIT?.classList.add('d-none');
        }
    }

    // Must run after any hard-lock pass so owned Draft fields stay editable
    enforceRoleFieldAccess();

        btnPrev?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (document.getElementById('wizardMain')?.classList.contains('is-review-mode')) {
            goToPage(cfg.lastRhuFormPage ?? Math.max(0, (cfg.recordsLastPage || 1) - 1));
            return;
        }
        if (currentPage <= 0) return;
        PatientProfileSync.harvestFromForm();
        goToPage(currentPage - 1);
    });

    btnNext?.addEventListener('click', (e) => {
        e.preventDefault();
        advancePage(currentPage + 1);
    });

    btnGoReview?.addEventListener('click', (e) => {
        e.preventDefault();
        advancePage(cfg.recordsLastPage);
    });

    btnSaveDraft?.addEventListener('click', (e) => {
        e.preventDefault();
        saveDraft(false);
    });

    btnSendToDoctor?.addEventListener('click', (e) => {
        e.preventDefault();
        sendToDoctor();
    });

    btnPreviewFinal?.addEventListener('click', (e) => {
        e.preventDefault();
        openFinalPrintPreview();
    });

    btnSubmitIT?.addEventListener('click', (e) => {
        e.preventDefault();
        submitToItReview();
    });

    btnComplete?.addEventListener('click', () => {
        const isDoctor = cfg.role === 'doctor';
        Swal.fire({
            title: isDoctor ? 'Send to Pharmacy?' : 'Send to Pharmacy?',
            text: isDoctor
                ? 'Findings will be saved and the prescription will be sent to the Pharmacy Queue (Waiting for Pharmacy).'
                : 'Doctor findings will be saved and the prescription will be sent to Pharmacy.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Send to Pharmacy',
            confirmButtonColor: '#16a34a',
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            const saved = await saveDraft(true);
            if (!saved) return;
            await runWorkflow('send_to_pharmacy');
        });
    });

    document.querySelectorAll('.wizard-nav-item').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const target = parseInt(btn.dataset.page, 10);
            if (Number.isNaN(target)) return;

            // In review workspace: single-click loads that document in the viewer
            if (document.getElementById('wizardMain')?.classList.contains('is-review-mode')) {
                showReviewDocument(target);
                return;
            }

            if (target === currentPage) return;
            // Soft check: allow next even if incomplete; left page gets ✓ or ✕
            await advancePage(target, false);
        });

        btn.addEventListener('dblclick', (e) => {
            e.preventDefault();
            if (!document.getElementById('wizardMain')?.classList.contains('is-review-mode')) return;
            openFinalPrintPreview();
        });
    });

    form.addEventListener('input', () => {
        scheduleAutoSave();
        refreshMarkedPageFillStatus(currentPage);
    });
    form.addEventListener('change', () => {
        scheduleAutoSave();
        refreshMarkedPageFillStatus(currentPage);
    });

    initPaperZoomControls();
    requestAnimationFrame(() => {
        applyFitWidth();
        enforceRoleFieldAccess();
    });
    window.addEventListener('resize', () => applyFitWidth());

    if (cfg.patientId > 0) {
        loadPatient(cfg.patientId);
    } else {
        PatientProfileSync.applyAll();
        restoreLocalProgress();
        goToPage(currentPage, false);
        enforceRoleFieldAccess();
    }

    function initSidebarCollapse() {
        const layout = document.getElementById('wizardLayout');
        const toggle = document.getElementById('btnToggleSidebar');
        if (!layout || !toggle) return;

        const storageKey = 'rhu_encoding_sidebar_collapsed';
        const apply = (collapsed) => {
            layout.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            toggle.setAttribute('aria-label', toggle.title);
            try {
                localStorage.setItem(storageKey, collapsed ? '1' : '0');
            } catch (e) { /* ignore */ }
            requestAnimationFrame(() => applyFitWidth());
        };

        try {
            apply(localStorage.getItem(storageKey) === '1');
        } catch (e) {
            apply(false);
        }

        toggle.addEventListener('click', () => {
            apply(!layout.classList.contains('is-collapsed'));
        });
    }

    function applyFitWidth() {
        const viewport = document.getElementById('paperViewport');
        const frame = document.getElementById('paperZoomFrame');
        const stage = document.getElementById('paperZoomStage');
        if (!viewport || !stage) return;

        stage.classList.add('paper-fit-width');

        // Review = fluid workspace
        if (document.getElementById('wizardMain')?.classList.contains('is-review-mode')) {
            stage.style.transform = 'none';
            stage.style.zoom = '';
            stage.style.width = '100%';
            stage.style.maxWidth = 'none';
            stage.style.height = '100%';
            stage.style.marginBottom = '';
            if (frame) {
                frame.style.width = '100%';
                frame.style.maxWidth = 'none';
                frame.style.height = '100%';
                frame.style.overflow = 'visible';
            }
            return;
        }

        // Interactive encoding: NEVER scale/zoom/transform the live form.
        // Scaling broke pointer hit-testing so inputs looked filled but could not be edited
        // even when disabled/readonly were correctly removed.
        stage.style.transform = 'none';
        stage.style.zoom = '';
        stage.style.width = '100%';
        stage.style.maxWidth = '100%';
        stage.style.height = 'auto';
        stage.style.marginBottom = '';

        if (frame) {
            frame.style.width = '100%';
            frame.style.maxWidth = '100%';
            frame.style.height = 'auto';
            frame.style.overflow = 'visible';
        }

        const sheet = stage.querySelector('.wizard-page-pane.active .paper-sheet')
            || stage.querySelector('.wizard-page-pane.active .wizard-page-shell')
            || stage.querySelector('.paper-sheet');
        if (sheet) {
            sheet.style.width = '100%';
            sheet.style.maxWidth = '100%';
            sheet.style.marginLeft = '0';
            sheet.style.marginRight = '0';
        }
    }

    /** Alias kept for existing call sites — DOM sync only (no permission recompute). */
    function enforceRoleFieldAccess() {
        return applyPermissionsToDom();
    }

    /**
     * Apply pre-computed data-perm-editable flags to the live DOM.
     * Permission logic stays in PHP; this only syncs readonly/disabled attributes.
     */
    function applyPermissionsToDom() {
        let unlocked = 0;
        let locked = 0;

        form.querySelectorAll('[data-perm-editable]').forEach((input) => {
            if (input.type === 'hidden') return;
            if (
                input.closest('.form-nav-footer')
                || input.closest('.wizard-nav')
                || input.closest('.wizard-sidebar-header')
                || input.closest('.drc-working-page')
                || input.closest('#drcFullscreenPreview')
            ) {
                return;
            }

            if (
                input.dataset.profileReadonly === '1'
                || input.name === 'patient_id'
                || input.dataset.permReason === 'system_readonly'
                || ((cfg.newConsultation || cfg.lockPatientProfile) && (
                    input.dataset.permReason === 'profile_readonly'
                    || input.dataset.fieldGroup === 'records_enrollment'
                    || input.dataset.fieldGroup === 'records_consent'
                    || input.dataset.fieldGroup === 'it'
                    || input.dataset.profileField
                ))
            ) {
                input.setAttribute('readonly', 'readonly');
                input.readOnly = true;
                if (input.tagName === 'SELECT' || input.type === 'radio' || input.type === 'checkbox') {
                    input.setAttribute('disabled', 'disabled');
                    input.disabled = true;
                } else {
                    input.removeAttribute('disabled');
                    input.disabled = false;
                }
                input.classList.add('pf-profile-readonly');
                locked += 1;
                return;
            }

            if (
                (cfg.newConsultation || cfg.lockPatientProfile)
                && (input.dataset.fieldGroup === 'records_treatment' || input.dataset.fieldGroup === 'doctor')
            ) {
                input.dataset.permEditable = '1';
                input.removeAttribute('readonly');
                input.removeAttribute('disabled');
                input.removeAttribute('aria-disabled');
                input.readOnly = false;
                input.disabled = false;
                if (input.tabIndex < 0) input.tabIndex = 0;
                input.classList.remove('pf-profile-readonly');
                unlocked += 1;
                return;
            }

            if (input.dataset.permEditable === '1') {
                input.removeAttribute('readonly');
                input.removeAttribute('disabled');
                input.removeAttribute('aria-disabled');
                input.readOnly = false;
                input.disabled = false;
                if (input.tabIndex < 0) input.tabIndex = 0;
                input.classList.remove('pf-profile-readonly');
                unlocked += 1;
            } else {
                input.setAttribute('readonly', 'readonly');
                input.setAttribute('disabled', 'disabled');
                input.readOnly = true;
                input.disabled = true;
                locked += 1;
            }
        });

        return { unlocked, locked };
    }

    function scheduleAutoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => saveDraft(true), 3000);
    }

    function restoreLocalProgress() {
        const key = localStorageKey();
        const stored = localStorage.getItem(key);
        if (stored !== null && cfg.patientId === 0) {
            const page = parseInt(stored, 10);
            if (!isNaN(page) && page >= 0 && page < cfg.totalPages) {
                currentPage = clampToEditablePage(page);
            }
        }
    }

    function localStorageKey() {
        const id = document.getElementById('patientDbId').value || 'new';
        return `rhu_wizard_page_${id}`;
    }

    /** Review page shows disabled clones — never resume encoding onto it. */
    function isReviewPageIndex(index) {
        return Number(index) === Number(cfg.recordsLastPage);
    }

    function clampToEditablePage(index) {
        const n = parseInt(index, 10);
        if (isNaN(n) || n < 0 || n >= cfg.totalPages) {
            return cfg.startPage || 0;
        }
        if (isReviewPageIndex(n)) {
            return Math.max(0, cfg.lastRhuFormPage ?? (cfg.recordsLastPage - 1));
        }
        return n;
    }

    async function advancePage(targetIndex, animate = true) {
        // Soft validation: do not block Next; mark left page ✓ / ✕ in goToPage
        PatientProfileSync.harvestFromForm();
        goToPage(targetIndex, animate);
        PatientProfileSync.applyAll();
        applyPermissionsToDom();

        const saved = await saveDraft(true);
        if (!saved && !cfg.skipPageValidation) {
            Swal.fire({
                icon: 'warning',
                title: 'Could not save progress',
                text: 'You can keep filling out the form, but please save before leaving this page.',
                timer: 2800,
                showConfirmButton: false,
            });
        }
    }

    function isEncodingPageIndex(index) {
        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${index}"]`);
        return !!(pane && pane.dataset.isReview !== '1');
    }

    /** True when completeness rules for this page are satisfied (no Swal). */
    function isPageFilled(pageIndex) {
        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${pageIndex}"]`);
        if (!pane || pane.dataset.isReview === '1') return true;

        const pageId = pane.dataset.pageId || '';
        const ruleKeys = Object.entries(cfg.completenessPageMap || {})
            .filter(([, pid]) => pid === pageId)
            .map(([key]) => key);

        if (!ruleKeys.length) {
            return isPaneRequiredFilled(pane) && !paneHasEmptyCoreFields(pane);
        }

        const rules = cfg.completenessRules || {};
        let evaluated = 0;
        for (const key of ruleKeys) {
            const rule = rules[key];
            if (!rule) continue;
            // Records cannot complete doctor forms — don't mark those incomplete for them
            if (rule.owner === 'doctor' && cfg.role === 'records_office') continue;
            if (rule.conditional && !isEpressApplicableClient()) continue;
            evaluated += 1;
            if (!doesCompletenessRulePass(rule)) return false;
        }

        // Fail closed: if rules missing/unusable, treat empty panes as incomplete
        if (evaluated === 0) {
            return isPaneRequiredFilled(pane) && !paneHasEmptyCoreFields(pane);
        }

        return true;
    }

    /** Heuristic: empty text/select/textarea/radio groups on the page → incomplete. */
    function paneHasEmptyCoreFields(pane) {
        const controls = pane.querySelectorAll('input, select, textarea');
        const radioGroups = new Set();

        for (const el of controls) {
            if (el.disabled || el.readOnly) continue;
            if (el.type === 'hidden' || el.type === 'button' || el.type === 'submit') continue;
            if (el.type === 'checkbox') continue; // handled by completeness customs
            if (!el.name) continue;

            if (el.type === 'radio') {
                if (radioGroups.has(el.name)) continue;
                radioGroups.add(el.name);
                const checked = pane.querySelector(`input[type="radio"][name="${CSS.escape(el.name)}"]:checked`);
                if (!checked) return true;
                continue;
            }

            if (!(el.value ?? '').toString().trim()) return true;
        }

        return false;
    }

    function isPaneRequiredFilled(pane) {
        const required = pane.querySelectorAll('[required]:not([disabled])');
        const checkedRadioGroups = new Set();

        for (const el of required) {
            if (el.type === 'radio') {
                if (checkedRadioGroups.has(el.name)) continue;
                checkedRadioGroups.add(el.name);
                const checked = pane.querySelector(`input[type="radio"][name="${CSS.escape(el.name)}"]:checked`);
                if (!checked) return false;
                continue;
            }
            if (!(el.value ?? '').toString().trim()) return false;
        }
        return true;
    }

    function readFormFieldValue(name, extended = false) {
        if (!extended && typeof PatientProfileSync !== 'undefined' && PatientProfileSync.isPatientColumn?.(name)) {
            const profileVal = PatientProfileSync.getProfileValue(name, false);
            if ((profileVal ?? '').toString().trim() !== '') return profileVal;
        }
        if (extended && typeof PatientProfileSync !== 'undefined' && PatientProfileSync.isExtendedProfileKey?.(name)) {
            const profileVal = PatientProfileSync.getProfileValue(name, true);
            if ((profileVal ?? '').toString().trim() !== '') return profileVal;
        }

        const fieldName = extended ? `extended[${name}]` : name;
        const radios = form.querySelectorAll(`input[type="radio"][name="${CSS.escape(fieldName)}"]`);
        if (radios.length) {
            const checked = Array.from(radios).find((r) => r.checked);
            return checked ? checked.value : '';
        }

        const boxes = form.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(fieldName)}"]`);
        if (boxes.length) {
            const checked = Array.from(boxes).filter((b) => b.checked).map((b) => b.value);
            if (checked.length === 1 && checked[0] === '1') return '1';
            return checked.length ? checked : '';
        }

        const el = form.querySelector(`[name="${CSS.escape(fieldName)}"]`);
        if (!el) return '';
        return (el.value ?? '').toString().trim();
    }

    function hasClientValue(value) {
        if (value === null || value === undefined) return false;
        if (Array.isArray(value)) return value.some((v) => String(v).trim() !== '');
        return String(value).trim() !== '';
    }

    function isEpressApplicableClient() {
        return hasClientValue(readFormFieldValue('medication_treatment'))
            || hasClientValue(readFormFieldValue('diagnosis'))
            || form.querySelectorAll('.diagnosis-row, [data-diagnosis-id]').length > 0
            || form.querySelectorAll('.medication-row, [data-medication-id]').length > 0;
    }

    const PMH_KEYS = [
        'pmh_allergy', 'pmh_asthma', 'pmh_cancer', 'pmh_cerebrovascular', 'pmh_coronary',
        'pmh_diabetes', 'pmh_emphysema', 'pmh_epilepsy', 'pmh_hepatitis', 'pmh_hyperlipidemia',
        'pmh_hypertension', 'pmh_peptic_ulcer', 'pmh_pneumonia', 'pmh_thyroid',
        'pmh_pulmonary_tb', 'pmh_extrapulmonary_tb', 'pmh_uti', 'pmh_mental_illness',
        'pmh_others', 'pmh_none',
    ];
    const FH_KEYS = [
        'fh_hypertension', 'fh_diabetes', 'fh_cancer', 'fh_asthma', 'fh_cerebrovascular',
        'fh_coronary', 'fh_emphysema', 'fh_epilepsy', 'fh_hepatitis', 'fh_hyperlipidemia',
        'fh_peptic_ulcer', 'fh_pneumonia', 'fh_thyroid', 'fh_pulmonary_tb',
        'fh_extrapulmonary_tb', 'fh_uti', 'fh_mental_illness', 'fh_others', 'fh_none',
    ];

    function hasAnyExtendedChecked(keys) {
        return keys.some((key) => {
            const val = readFormFieldValue(key, true);
            return val === '1' || val === 1 || val === true || val === 'on';
        });
    }

    const FIELD_LABELS = {
        philhealth_no: 'PhilHealth PIN',
        family_serial_number: 'Family Serial Number',
        pkrf_registration_date: 'PKRF Registration Date',
        date_of_consultation: 'Date of Consultation',
        chief_complaints: 'Chief Complaints',
        mode_of_transaction: 'Mode of Transaction',
        consent_signature_date: 'Consent Signature Date',
        chu_representative: 'CHU/RHU Representative',
        contact_number: 'Contact Number',
        birth_date: 'Birth Date',
        last_name: 'Last Name',
        first_name: 'First Name',
        sex: 'Sex',
        psh_smoking: 'Smoking',
        psh_alcohol: 'Alcohol',
        psh_drugs: 'Illicit Drugs',
        ncd_diabetes: 'NCD — Diabetes',
        ncd_physical: 'NCD — Physical Activity',
        photo_consent_name: 'Photo Consent — Name',
        photo_consent_date: 'Photo Consent — Date',
        ekas_hci_name: 'EKAS HCI Name',
        epress_hci_name: 'EPRESS HCI Name',
        medication_treatment: 'Medication / Treatment',
        diagnosis: 'Diagnosis',
        healthcare_provider: 'Healthcare Provider',
    };

    function humanFieldLabel(field) {
        if (FIELD_LABELS[field]) return FIELD_LABELS[field];
        return String(field).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function collectCustomMissing(custom) {
        switch (custom) {
            case 'consultation_types':
                return form.querySelectorAll('input[name="consultation_types[]"]:checked').length > 0
                    ? []
                    : ['Consultation Type (at least one)'];
            case 'pmh_completed':
                return hasAnyExtendedChecked(PMH_KEYS) ? [] : ['Past Medical History (at least one item)'];
            case 'psh_surgical_completed': {
                const ok = hasClientValue(readFormFieldValue('surgical_op_1', true))
                    || hasClientValue(readFormFieldValue('surgical_date_1', true))
                    || hasClientValue(readFormFieldValue('surgical_op_2', true))
                    || hasClientValue(readFormFieldValue('surgical_date_2', true));
                return ok ? [] : ['Past Surgical History'];
            }
            case 'fh_completed':
                return hasAnyExtendedChecked(FH_KEYS) ? [] : ['Family History (at least one item)'];
            case 'philhealth_format': {
                const raw = readFormFieldValue('philhealth_no', false);
                if (!hasClientValue(raw)) return [];
                const digits = String(raw).replace(/\D+/g, '');
                return (digits.length === 12 || digits.length === 14)
                    ? []
                    : ['Invalid PhilHealth Number (12 or 14 digits)'];
            }
            case 'family_serial_format': {
                const raw = String(readFormFieldValue('family_serial_number', false) || '').trim();
                if (!raw) return [];
                return /^[A-Za-z0-9\-]{4,20}$/.test(raw) ? [] : ['Invalid Family Serial Number'];
            }
            case 'epress_if_applicable': {
                if (!isEpressApplicableClient()) return [];
                const missing = [];
                const hasDx = hasClientValue(readFormFieldValue('diagnosis'))
                    || form.querySelectorAll('.diagnosis-row, [data-diagnosis-id]').length > 0;
                const hasMed = hasClientValue(readFormFieldValue('medication_treatment'))
                    || form.querySelectorAll('.medication-row, [data-medication-id]').length > 0;
                if (!hasDx) missing.push('Diagnosis');
                if (!hasMed) missing.push('Medication / Treatment');
                if (!hasClientValue(readFormFieldValue('healthcare_provider'))) missing.push('Healthcare Provider');
                return missing;
            }
            default:
                return [];
        }
    }

    function collectMissingFromRule(rule) {
        const missing = [];
        const prefix = rule.label ? `${rule.label}: ` : '';

        for (const field of [...(rule.patient || []), ...(rule.patient_consent || [])]) {
            if (!hasClientValue(readFormFieldValue(field, false))) {
                missing.push(prefix + humanFieldLabel(field));
            }
        }
        for (const field of rule.treatment || []) {
            if (!hasClientValue(readFormFieldValue(field, false))) {
                missing.push(prefix + humanFieldLabel(field));
            }
        }
        for (const field of rule.extended || []) {
            if (!hasClientValue(readFormFieldValue(field, true))) {
                missing.push(prefix + humanFieldLabel(field));
            }
        }
        for (const custom of rule.custom || []) {
            collectCustomMissing(custom).forEach((label) => {
                missing.push(prefix + label);
            });
        }
        return missing;
    }

    function collectPageMissingFields(pageIndex) {
        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${pageIndex}"]`);
        if (!pane || pane.dataset.isReview === '1') return [];

        const pageId = pane.dataset.pageId || '';
        const ruleKeys = Object.entries(cfg.completenessPageMap || {})
            .filter(([, pid]) => pid === pageId)
            .map(([key]) => key);
        const rules = cfg.completenessRules || {};
        const missing = [];

        for (const key of ruleKeys) {
            const rule = rules[key];
            if (!rule) continue;
            if (rule.owner === 'doctor' && cfg.role === 'records_office') continue;
            if (rule.conditional && !isEpressApplicableClient()) continue;
            missing.push(...collectMissingFromRule(rule));
        }

        if (!missing.length && (paneHasEmptyCoreFields(pane) || !isPaneRequiredFilled(pane))) {
            missing.push('Some required fields on this page are still empty');
        }

        return [...new Set(missing)];
    }

    function doesCompletenessRulePass(rule) {
        return collectMissingFromRule(rule).length === 0;
    }

    function doesCustomCompletenessPass(custom) {
        return collectCustomMissing(custom).length === 0;
    }

    function markPageFillStatus(pageIndex) {
        if (!isEncodingPageIndex(pageIndex)) return;
        pageFillStatus[pageIndex] = isPageFilled(pageIndex) ? 'complete' : 'incomplete';
        applyNavFillClasses();
    }

    /** Live-update ✓/✕ only after the page was already left once (already marked). */
    function refreshMarkedPageFillStatus(pageIndex) {
        if (pageFillStatus[pageIndex] === undefined) return;
        markPageFillStatus(pageIndex);
    }

    function applyNavFillClasses() {
        document.querySelectorAll('.wizard-nav-item').forEach((btn) => {
            const p = parseInt(btn.dataset.page, 10);
            const status = pageFillStatus[p];
            btn.classList.toggle('completed', status === 'complete');
            btn.classList.toggle('is-incomplete', status === 'incomplete');
            updateNavMissingTooltip(btn, p, status);
        });
    }

    function updateNavMissingTooltip(btn, pageIndex, status) {
        if (!btn.dataset.baseTitle) {
            btn.dataset.baseTitle = (btn.getAttribute('title') || btn.querySelector('.wizard-nav-title')?.textContent || '').trim();
        }

        if (window.bootstrap?.Tooltip) {
            const existing = bootstrap.Tooltip.getInstance(btn);
            if (existing) existing.dispose();
        }

        if (status !== 'incomplete') {
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-html');
            btn.removeAttribute('data-bs-custom-class');
            btn.removeAttribute('data-bs-title');
            btn.setAttribute('title', btn.dataset.baseTitle || '');
            return;
        }

        const missing = collectPageMissingFields(pageIndex);
        const listHtml = missing.length
            ? `<ul class="wizard-missing-tip-list">${missing.map((m) => `<li>${escapeHtml(m)}</li>`).join('')}</ul>`
            : '<div>This form still has incomplete fields.</div>';
        const tipHtml = `<div class="wizard-missing-tip"><strong>Not filled up:</strong>${listHtml}</div>`;
        const plainTip = missing.length
            ? `Not filled up:\n${missing.map((m) => `• ${m}`).join('\n')}`
            : 'This form still has incomplete fields.';

        btn.setAttribute('title', plainTip);
        btn.setAttribute('data-bs-title', tipHtml);
        btn.setAttribute('data-bs-toggle', 'tooltip');
        btn.setAttribute('data-bs-html', 'true');
        btn.setAttribute('data-bs-placement', 'right');
        btn.setAttribute('data-bs-custom-class', 'wizard-nav-missing-tooltip');

        if (window.bootstrap?.Tooltip) {
            new bootstrap.Tooltip(btn, {
                html: true,
                sanitize: false,
                trigger: 'hover focus',
                container: 'body',
                placement: 'right',
                customClass: 'wizard-nav-missing-tooltip',
                title: tipHtml,
            });
        }
    }

    function goToPage(index, animate = true) {
        index = Math.max(0, Math.min(cfg.totalPages - 1, index));
        const leaving = currentPage;

        if (index !== leaving && isEncodingPageIndex(leaving)) {
            markPageFillStatus(leaving);
        }

        currentPage = index;
        if (formProgressInput) {
            formProgressInput.value = String(index);
        }

        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${index}"]`);
        const isReviewPane = pane?.dataset.isReview === '1';

        document.querySelectorAll('.wizard-page-pane').forEach((p) => {
            p.classList.toggle('active', parseInt(p.dataset.pageIndex, 10) === index);
        });

        document.querySelectorAll('.wizard-nav-item').forEach((btn) => {
            const p = parseInt(btn.dataset.page, 10);
            if (isReviewPane) {
                btn.classList.toggle('active', p === reviewActivePageIndex);
            } else {
                btn.classList.toggle('active', p === index);
            }
        });
        applyNavFillClasses();

        const legend = document.getElementById('wizardNavLegend');
        const chrome = document.getElementById('reviewWorkspaceChrome');
        const root = document.getElementById('emrEncodingRoot');
        legend?.classList.toggle('d-none', isReviewPane);
        chrome?.classList.toggle('d-none', !isReviewPane);
        root?.classList.toggle('is-review-workspace', isReviewPane);
        document.getElementById('formWorkspaceToolbar')?.classList.toggle('is-review-actions', isReviewPane);
        document.getElementById('toolbarMetaGroup')?.classList.toggle('d-none', isReviewPane);

        if (progressText) {
            progressText.textContent = isReviewPane
                ? 'Review & Submit'
                : `Page ${index + 1} of ${cfg.totalPages}`;
        }
        if (toolbarPageHint) {
            toolbarPageHint.textContent = isReviewPane
                ? 'Review & Submit'
                : `Page ${index + 1} of ${cfg.totalPages}`;
        }
        const progressFill = document.getElementById('wizardProgressFill');
        if (progressFill) {
            progressFill.style.width = `${Math.round(((index + 1) / cfg.totalPages) * 100)}%`;
        }

        updateFooterButtons(index);

        try {
            localStorage.setItem(localStorageKey(), String(index));
        } catch (e) { /* ignore quota / private mode */ }

        document.getElementById('wizardMain')?.classList.toggle('is-review-mode', isReviewPane);
        document.getElementById('wizardLayout')?.classList.toggle('is-review-layout', isReviewPane);

        if (isReviewPane) {
            try {
                buildDocumentReviewCenter();
            } catch (err) {
                console.error('Document Review Center failed:', err);
                const stack = document.getElementById('drcDocumentStack');
                if (stack) {
                    stack.innerHTML = '<p class="text-danger small p-3 mb-0">Could not load document preview. Use Previous to return to a form page.</p>';
                }
            }
            const chrome = document.getElementById('reviewWorkspaceChrome');
            if (chrome && !chrome.dataset.readonlyHint) {
                chrome.dataset.readonlyHint = '1';
                const hint = document.createElement('div');
                hint.className = 'review-readonly-hint';
                hint.innerHTML = '<strong>Read-only preview.</strong> Click a form in the left sidebar (or Previous) to continue editing.';
                chrome.appendChild(hint);
            }
        }

        if (animate) {
            const viewport = document.getElementById('paperViewport');
            if (viewport) {
                viewport.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        requestAnimationFrame(() => {
            applyFitWidth();
            try {
                PatientProfileSync.applyAll();
            } catch (e) { /* ignore */ }
            applyPermissionsToDom();
        });

        try {
            PatientProfileSync.onPageChange();
        } catch (e) {
            console.error('Profile sync on page change failed:', e);
        }

        // Re-sync after profile mirror may touch fields on the newly active page
        applyPermissionsToDom();
    }

    function isItRole() {
        if (cfg.canSendToDoctor) return true;
        const role = String(cfg.role || '').toLowerCase();
        const raw = String(cfg.roleRaw || '').toLowerCase();
        return role === 'it_department'
            || role === 'it'
            || raw === 'it'
            || raw.includes('it department')
            || raw.includes('it_department');
    }

    function isEpressPage(index) {
        if (Number(index) === Number(cfg.lastRhuFormPage)) {
            return true;
        }
        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${index}"]`);
        return (pane?.dataset.pageId || '') === 'epress';
    }

    /**
     * Encoding pages → Previous + Save + Next (Records) / Save Draft + Next (others)
     * Last RHU form  → Previous + Save (Records: no Review & Submit)
     *                 → Previous + Save Draft + Review & Submit (other encoding roles)
     * Review page    → Previous + Preview Final Print + Submit to IT Review
     * IT on EPRESS (Page 7) → Previous + Save Draft + Send to Doctor
     */
    function updateFooterButtons(index) {
        const isFirst = index === 0;
        const isReview = index === cfg.recordsLastPage;
        const isLastRhu = isEpressPage(index);
        const isDoctorPage = cfg.role === 'doctor' && index === cfg.doctorPage;
        const isDoctorWorkspace = cfg.role === 'doctor' && !isReview && !cfg.isLocked;
        const isIt = isItRole();

        if (isReview) {
            btnPrev?.classList.remove('d-none');
            btnNext?.classList.add('d-none');
            btnGoReview?.classList.add('d-none');
            btnSaveDraft?.classList.add('d-none');
            btnSendToDoctor?.classList.add('d-none');
            btnPreviewFinal?.classList.remove('d-none');
            btnComplete?.classList.add('d-none');
            const canRole = isRecordsRole && !cfg.isLocked;
            btnSubmitIT?.classList.toggle('d-none', !canRole);
            if (btnSubmitIT && !canRole) btnSubmitIT.disabled = true;
            // Reorder footer buttons: Previous | Preview | Submit
            const group = document.getElementById('toolbarNavGroup');
            if (group && btnPrev && btnPreviewFinal && btnSubmitIT) {
                group.appendChild(btnPrev);
                group.appendChild(btnPreviewFinal);
                group.appendChild(btnSubmitIT);
            }
            return;
        }

        btnPrev?.classList.toggle('d-none', isFirst);
        btnNext?.classList.toggle('d-none', isLastRhu || isReview);
        // Records: no Review & Submit. IT uses Send to Doctor on EPRESS instead.
        btnGoReview?.classList.toggle('d-none', isRecordsRole || !isLastRhu || isIt);
        const canSave = cfg.canSaveDraft !== false && !cfg.isLocked;
        btnSaveDraft?.classList.toggle('d-none', isReview || !canSave);
        if (btnSaveDraft && canSave) {
            btnSaveDraft.innerHTML = SAVE_DRAFT_LABEL;
        }
        const patientId = Number(document.getElementById('patientDbId')?.value || cfg.patientId || 0);
        // Show on EPRESS for IT whenever encoding is editable
        const canSendToDoctor = isIt
            && isLastRhu
            && !cfg.isLocked
            && !cfg.waitingForDoctor;
        if (btnSendToDoctor) {
            btnSendToDoctor.classList.toggle('d-none', !canSendToDoctor);
            btnSendToDoctor.disabled = false;
            btnSendToDoctor.removeAttribute('disabled');
            btnSendToDoctor.title = 'Save latest data and send this consultation to the Doctor Queue';
            if (canSendToDoctor) {
                const group = document.getElementById('toolbarNavGroup');
                if (group && btnSaveDraft) {
                    btnSaveDraft.after(btnSendToDoctor);
                }
            }
        }
        btnPreviewFinal?.classList.add('d-none');
        btnSubmitIT?.classList.add('d-none');
        // Doctor: keep Send to Pharmacy available on clinical form pages
        btnComplete?.classList.toggle('d-none', !(isDoctorWorkspace || isDoctorPage));
    }

    function validateCurrentPage() {
        if (cfg.skipPageValidation) return true;

        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${currentPage}"]`);
        if (!pane || pane.dataset.isReview === '1') return true;

        const required = pane.querySelectorAll('[required]:not([disabled])');
        const checkedRadioGroups = new Set();

        for (const el of required) {
            if (el.type === 'radio') {
                if (checkedRadioGroups.has(el.name)) continue;
                checkedRadioGroups.add(el.name);
                const checked = pane.querySelector(`input[type="radio"][name="${CSS.escape(el.name)}"]:checked`);
                if (!checked) {
                    el.focus();
                    Swal.fire('Required Field', 'Please complete all required fields on this page.', 'warning');
                    return false;
                }
                continue;
            }

            const value = (el.value ?? '').toString().trim();
            if (!value) {
                el.focus();
                Swal.fire('Required Field', 'Please complete all required fields on this page.', 'warning');
                return false;
            }
        }

        return true;
    }

    function getFieldValue(name, extended) {
        if (name === 'consultation_types') {
            const checked = [];
            form.querySelectorAll('input[name="consultation_types[]"]:checked').forEach((cb) => {
                checked.push(cb.value);
            });
            return checked.length ? checked.join(', ') : '—';
        }

        const fieldName = extended ? `extended[${name}]` : name;

        if (PatientProfileSync.isPatientColumn(name) || PatientProfileSync.isExtendedProfileKey(name)) {
            const val = PatientProfileSync.getProfileValue(name, extended);
            return val || '—';
        }

        const radios = form.querySelectorAll(`input[type="radio"][name="${fieldName}"]`);
        if (radios.length) {
            const checked = Array.from(radios).find((r) => r.checked);
            return checked ? checked.value : '—';
        }

        const checkboxes = form.querySelectorAll(`input[type="checkbox"][name="${fieldName}"]`);
        if (checkboxes.length === 1) {
            return checkboxes[0].checked ? 'Yes' : 'No';
        }

        const selector = extended
            ? `[name="extended[${name}]"]`
            : `[name="${name}"]`;
        const el = form.querySelector(selector);
        if (!el) return '—';

        const val = (el.value || '').trim();
        return val || '—';
    }

    function buildDocumentReviewCenter() {
        initReviewPreviewControls();
        const panes = getPaperPagePanes();
        const initial = panes.some((p) => parseInt(p.dataset.pageIndex, 10) === reviewActivePageIndex)
            ? reviewActivePageIndex
            : (panes[0] ? parseInt(panes[0].dataset.pageIndex, 10) : 0);
        showReviewDocument(initial);
        refreshValidationSummary();
    }

    function getPaperPagePanes() {
        return Array.from(document.querySelectorAll('.wizard-page-pane')).filter(
            (pane) => pane.dataset.isReview !== '1'
        );
    }

    function getReviewChipLabel(pane, index) {
        const id = pane?.dataset.pageId || '';
        const map = {
            pkrf: 'PKRF',
            itr: 'ITR',
            enrollment: 'Enrollment',
            history: 'Medical History',
            personal: 'Social History',
            ekas: 'EKAS',
        };
        if (map[id]) return map[id];
        return pane?.querySelector('.wizard-page-title')?.textContent?.trim()
            || cfg.reviewMeta?.[index]?.title
            || `Form ${index + 1}`;
    }

    function getReviewDocNumber(index) {
        const btn = document.querySelector(`.wizard-nav-item[data-page="${index}"]`);
        return btn?.dataset.docNum || String(index + 1);
    }

    function showReviewDocument(index) {
        const stack = document.getElementById('drcDocumentStack');
        if (!stack) return;

        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${index}"]`);
        if (!pane || pane.dataset.isReview === '1') return;

        reviewActivePageIndex = index;
        reviewFitMode = true;

        document.querySelectorAll('.wizard-nav-item').forEach((btn) => {
            btn.classList.toggle('active', parseInt(btn.dataset.page, 10) === index);
        });

        const source = pane.querySelector('.paper-sheet') || pane.querySelector('.wizard-page-shell');
        if (!source) {
            stack.innerHTML = '<p class="text-muted small mb-0 p-3">Preview unavailable for this document.</p>';
            return;
        }

        // Fluid working view — strip A4 paper wrapper constraints (print preview keeps originals)
        const section = document.createElement('section');
        section.className = 'drc-working-page';
        section.id = `drc-page-${index}`;
        section.dataset.pageIndex = String(index);
        section.dataset.pageId = pane.dataset.pageId || '';

        const clone = source.cloneNode(true);
        clone.classList.remove('paper-sheet');
        clone.classList.add('drc-working-sheet');
        clone.removeAttribute('style');
        clone.querySelectorAll('[style]').forEach((el) => {
            const style = el.getAttribute('style') || '';
            if (/(\d+\s*mm)|max-width\s*:\s*\d/i.test(style)) {
                el.style.maxWidth = 'none';
                if (/width\s*:\s*\d+\s*mm/i.test(style)) {
                    el.style.width = '100%';
                }
            }
        });
        clone.querySelectorAll('input, select, textarea, button').forEach((el) => {
            el.disabled = true;
            el.readOnly = true;
            el.removeAttribute('name');
            el.removeAttribute('id');
            el.tabIndex = -1;
        });
        section.appendChild(clone);

        stack.innerHTML = '';
        stack.appendChild(section);
        stack.classList.add('is-working-view');

        // Unlock outer A4 zoom wrappers for review workspace
        const stage = document.getElementById('paperZoomStage');
        const frame = document.getElementById('paperZoomFrame');
        if (stage) {
            stage.style.width = '100%';
            stage.style.maxWidth = 'none';
            stage.style.transform = 'none';
            stage.style.height = '100%';
            stage.style.marginBottom = '';
        }
        if (frame) {
            frame.style.width = '100%';
            frame.style.height = '100%';
            frame.style.maxWidth = 'none';
        }

        updateReviewPreviewTitle(index);
        const scroller = document.getElementById('drcDocumentScroll');
        if (scroller) {
            scroller.scrollTop = 0;
            scroller.classList.add('is-working-view');
            scroller.classList.remove('is-page-centered');
        }
        const scaleWrap = document.getElementById('drcDocumentScale');
        scaleWrap?.classList.add('is-working-view');

        requestAnimationFrame(() => applyReviewFitWidth());
    }

    function initReviewPreviewControls() {
        const zoomOut = document.getElementById('drcZoomOut');
        const zoomIn = document.getElementById('drcZoomIn');
        const fitWidth = document.getElementById('drcFitWidth');
        const fullPreview = document.getElementById('drcFullPreview');

        if (!zoomOut || zoomOut.dataset.bound === '1') return;

        zoomOut.dataset.bound = '1';
        zoomOut.addEventListener('click', () => {
            reviewFitMode = false;
            reviewZoom = Math.max(0.85, Math.round((reviewZoom - 0.1) * 10) / 10);
            applyReviewZoom();
        });
        zoomIn?.addEventListener('click', () => {
            reviewFitMode = false;
            reviewZoom = Math.min(1.6, Math.round((reviewZoom + 0.1) * 10) / 10);
            applyReviewZoom();
        });
        fitWidth?.addEventListener('click', () => {
            reviewFitMode = true;
            applyReviewFitWidth();
        });
        fullPreview?.addEventListener('click', () => openFinalPrintPreview());

        window.addEventListener('resize', () => {
            if (document.getElementById('wizardMain')?.classList.contains('is-review-mode') && reviewFitMode) {
                applyReviewFitWidth();
            }
        });
    }

    function setSubmitEnabled(complete) {
        const canRole = cfg.role === 'records_office' && !cfg.isLocked;
        if (!btnSubmitIT) return;
        btnSubmitIT.disabled = !(canRole && complete);
        btnSubmitIT.title = complete
            ? 'Submit all forms to IT for validation'
            : 'Complete all required documents before submitting';
    }

    /** Working view: fill available width (not A4). Zoom is a readability multiplier. */
    function applyReviewFitWidth() {
        reviewFitMode = true;
        // Comfortable working size (not print A4 zoom)
        reviewZoom = 1.12;
        applyReviewZoom();
    }

    function applyReviewZoom() {
        const scroller = document.getElementById('drcDocumentScroll');
        const scaleWrap = document.getElementById('drcDocumentScale');
        const stack = document.getElementById('drcDocumentStack');
        const label = document.getElementById('drcZoomLabel');
        if (!stack) return;

        // Keep outer A4 wrappers unlocked while reviewing
        const stage = document.getElementById('paperZoomStage');
        const frame = document.getElementById('paperZoomFrame');
        if (stage) {
            stage.style.transform = 'none';
            stage.style.width = '100%';
            stage.style.maxWidth = 'none';
            stage.style.height = '100%';
        }
        if (frame) {
            frame.style.width = '100%';
            frame.style.maxWidth = 'none';
            frame.style.height = '100%';
        }

        // Clear any leftover A4 transform sizing from older logic
        stack.style.transform = 'none';
        stack.style.width = '100%';
        stack.style.maxWidth = 'none';
        stack.style.height = '';
        if (scaleWrap) {
            scaleWrap.style.width = '100%';
            scaleWrap.style.maxWidth = 'none';
            scaleWrap.style.height = '';
            scaleWrap.style.marginTop = '';
            scaleWrap.style.setProperty('--drc-zoom', String(reviewZoom));
        }
        stack.style.setProperty('--drc-zoom', String(reviewZoom));
        scroller?.classList.remove('is-page-centered');

        if (label) label.textContent = `${Math.round(reviewZoom * 100)}%`;
        updateReviewPreviewTitle(reviewActivePageIndex);
    }

    function updateReviewPreviewTitle(index) {
        const titleEl = document.getElementById('drcPreviewTitle');
        if (!titleEl) return;
        const pane = document.querySelector(`.wizard-page-pane[data-page-index="${index}"]`);
        const title = (pane?.querySelector('.wizard-page-title')?.textContent?.trim()
            || cfg.reviewMeta?.[index]?.title
            || 'Document Preview').toUpperCase();
        const num = getReviewDocNumber(index);
        titleEl.textContent = `${num}. ${title}`;
    }

    function groupIssuesByPage(issues) {
        const map = cfg.completenessPageMap || {};
        const byPage = {};

        (issues || []).forEach((issue) => {
            // Prefer explicit map by matching known labels
            let pageId = null;
            for (const [key, pid] of Object.entries(map)) {
                if ((issue.form || '').indexOf(key.replace(/_/g, ' ')) !== -1) {
                    pageId = pid;
                    break;
                }
            }
            if (!pageId) {
                const label = (issue.form || '').toLowerCase();
                if (label.includes('philhealth') || label.includes('pkrf')) pageId = 'pkrf';
                else if (label.includes('individual treatment')) pageId = 'itr';
                else if (label.includes('epress') || label.includes('prescription')) pageId = 'epress';
                else if (label.includes('enrollment')) pageId = 'enrollment';
                else if (label.includes('past medical') || label.includes('surgical') || label.includes('family history')) pageId = 'history';
                else if (label.includes('personal') || label.includes('ncd') || label.includes('photo')) pageId = 'personal';
                else if (label.includes('ekas') || label.includes('availment')) pageId = 'ekas';
            }

            const pane = pageId
                ? document.querySelector(`.wizard-page-pane[data-page-id="${pageId}"]`)
                : null;
            const pageIndex = pane ? parseInt(pane.dataset.pageIndex, 10) : 0;
            if (!byPage[pageIndex]) {
                byPage[pageIndex] = {
                    pageIndex,
                    pageId: pageId || '',
                    title: pane?.querySelector('.wizard-page-title')?.textContent
                        || issue.form
                        || `Page ${pageIndex + 1}`,
                    fields: [],
                };
            }
            byPage[pageIndex].fields.push(...(issue.fields || []));
        });

        return Object.values(byPage).sort((a, b) => a.pageIndex - b.pageIndex);
    }

    async function refreshValidationSummary() {
        const list = document.getElementById('drcPageStatusList');
        const issuesPanel = document.getElementById('drcIssuesPanel');
        const pctEl = document.getElementById('drcCompletionPct');
        const statusEl = document.getElementById('drcReadyStatus');
        if (!list) return;

        const paperPanes = getPaperPagePanes();
        const patientDbId = parseInt(document.getElementById('patientDbId')?.value || '0', 10);

        let serverIssues = [];
        let complete = false;

        if (statusEl) {
            statusEl.textContent = 'Checking…';
            statusEl.classList.remove('is-ready', 'is-incomplete');
        }
        setSubmitEnabled(false);

        if (patientDbId > 0) {
            try {
                if (cfg.role === 'records_office' && !cfg.isLocked) {
                    await saveDraft(true);
                }
                const res = await fetch(`${cfg.baseUrl}/api/check_form_completeness.php?patient_id=${patientDbId}`);
                const data = await res.json();
                if (data.success) {
                    complete = !!data.complete;
                    serverIssues = data.issues || [];
                } else if (statusEl) {
                    statusEl.textContent = 'Validation unavailable';
                }
            } catch (e) {
                complete = false;
                if (statusEl) statusEl.textContent = 'Validation unavailable';
            }
        } else if (statusEl) {
            statusEl.textContent = 'Save patient profile first';
            statusEl.classList.add('is-incomplete');
        }

        const grouped = groupIssuesByPage(serverIssues);
        const incompleteIndexes = new Set(grouped.map((g) => g.pageIndex));
        const treatAllComplete = complete && grouped.length === 0;
        const completeCount = treatAllComplete
            ? paperPanes.length
            : paperPanes.filter((p) => !incompleteIndexes.has(parseInt(p.dataset.pageIndex, 10))).length;
        const pct = paperPanes.length
            ? (patientDbId > 0 ? Math.round((completeCount / paperPanes.length) * 100) : 0)
            : 0;

        if (pctEl) pctEl.textContent = `${pct}%`;
        if (statusEl && patientDbId > 0) {
            statusEl.textContent = complete
                ? 'All documents are ready for submission'
                : (grouped.length ? 'Incomplete — corrections required' : statusEl.textContent);
            statusEl.classList.toggle('is-ready', complete);
            statusEl.classList.toggle('is-incomplete', !complete && grouped.length > 0);
        }

        setSubmitEnabled(complete && patientDbId > 0);

        list.innerHTML = paperPanes.map((pane) => {
            const index = parseInt(pane.dataset.pageIndex, 10);
            const chip = getReviewChipLabel(pane, index);
            const ok = treatAllComplete || (patientDbId > 0 && !incompleteIndexes.has(index) && (complete || grouped.length > 0));
            const label = !patientDbId
                ? 'Pending'
                : (ok ? 'Complete' : (incompleteIndexes.has(index) ? 'Incomplete' : 'Complete'));
            const isComplete = label === 'Complete';

            document.querySelectorAll(`.wizard-nav-item[data-page="${index}"]`).forEach((btn) => {
                btn.classList.toggle('completed', isComplete);
                btn.classList.toggle('is-incomplete', !isComplete && patientDbId > 0);
                const status = btn.querySelector('.wizard-nav-status');
                if (status) {
                    status.innerHTML = `<i class="bi ${isComplete ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i>`;
                }
            });

            return `<li class="drc-page-status ${isComplete ? 'is-complete' : 'is-incomplete'}" title="${escapeHtml(chip)} — ${escapeHtml(label)}">
                <span class="drc-status-icon"><i class="bi ${isComplete ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i></span>
                <span class="drc-status-name">${escapeHtml(chip)}</span>
            </li>`;
        }).join('');

        if (!issuesPanel) return;

        const missingFields = [...new Set(grouped.flatMap((g) => g.fields || []))];
        if (!missingFields.length) {
            issuesPanel.classList.add('d-none');
            issuesPanel.innerHTML = '';
            return;
        }

        issuesPanel.classList.remove('d-none');
        issuesPanel.innerHTML = `
            <div class="drc-missing-block">
                <div class="drc-issue-label">Missing</div>
                <ul class="drc-missing-list">${missingFields.map((f) => `<li>${escapeHtml(f)}</li>`).join('')}</ul>
            </div>
        `;
        // Keep go-to links for incomplete pages via sidebar; optional jump buttons:
        issuesPanel.innerHTML += grouped.map((group) => `
            <button type="button" class="btn btn-sm btn-link drc-goto-btn p-0" data-page="${group.pageIndex}">
                Fix ${escapeHtml(getReviewChipLabel(
                    document.querySelector(`.wizard-page-pane[data-page-index="${group.pageIndex}"]`),
                    group.pageIndex
                ))}
            </button>
        `).join(' ');

        issuesPanel.querySelectorAll('.drc-goto-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page, 10);
                goToPage(page);
            });
        });
    }

    function openFinalPrintPreview() {
        // Build full multi-page stack inside fullscreen only
        let overlay = document.getElementById('drcFullscreenPreview');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'drcFullscreenPreview';
            overlay.className = 'drc-fullscreen-preview';
            overlay.innerHTML = `
                <div class="drc-fullscreen-toolbar no-print">
                    <span class="drc-fullscreen-title">Final Print Preview</span>
                    <div class="drc-fullscreen-actions">
                        <button type="button" class="btn btn-sm btn-outline-light" id="drcFsPrint"><i class="bi bi-printer"></i> Print</button>
                        <button type="button" class="btn btn-sm btn-light" id="drcFsClose">Close</button>
                    </div>
                </div>
                <div class="drc-fullscreen-body" id="drcFullscreenBody"></div>`;
            document.body.appendChild(overlay);
            overlay.querySelector('#drcFsClose')?.addEventListener('click', () => closeFinalPrintPreview());
            overlay.querySelector('#drcFsPrint')?.addEventListener('click', () => window.print());
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeFinalPrintPreview();
            });
        }

        const body = document.getElementById('drcFullscreenBody');
        if (body) {
            body.innerHTML = '<div class="drc-document-stack" id="drcFullscreenStack"></div>';
            const fsStack = document.getElementById('drcFullscreenStack');
            getPaperPagePanes().forEach((pane) => {
                const index = parseInt(pane.dataset.pageIndex, 10);
                const source = pane.querySelector('.paper-sheet') || pane.querySelector('.wizard-page-shell');
                if (!source || !fsStack) return;
                const section = document.createElement('section');
                section.className = 'drc-paper-page';
                const label = document.createElement('div');
                label.className = 'drc-paper-label no-print';
                label.textContent = `Page ${index + 1} — ${pane.querySelector('.wizard-page-title')?.textContent || ''}`;
                section.appendChild(label);
                const clone = source.cloneNode(true);
                clone.querySelectorAll('input, select, textarea, button').forEach((el) => {
                    el.disabled = true;
                    el.readOnly = true;
                    el.removeAttribute('name');
                    el.removeAttribute('id');
                    el.tabIndex = -1;
                });
                section.appendChild(clone);
                fsStack.appendChild(section);
            });
        }

        document.body.classList.add('drc-preview-open');
        overlay.classList.add('is-open');
    }

    function closeFinalPrintPreview() {
        document.body.classList.remove('drc-preview-open');
        document.getElementById('drcFullscreenPreview')?.classList.remove('is-open');
    }

    async function submitToItReview() {
        const patientDbId = parseInt(document.getElementById('patientDbId').value, 10);
        if (!patientDbId) {
            Swal.fire(
                'Patient Profile Incomplete',
                'Please complete Last Name, First Name, Sex, and Birth Date before submitting.',
                'warning'
            );
            return;
        }

        const saved = await saveDraft(true);
        if (!saved) return;

        let checkData = null;
        try {
            const res = await fetch(`${cfg.baseUrl}/api/check_form_completeness.php?patient_id=${patientDbId}`);
            checkData = await res.json();
        } catch (e) {
            Swal.fire('Error', 'Could not validate forms before submission.', 'error');
            return;
        }

        if (!checkData?.success) {
            Swal.fire('Error', checkData?.message || 'Validation failed.', 'error');
            return;
        }

        if (!checkData.complete) {
            await refreshValidationSummary();
            const grouped = groupIssuesByPage(checkData.issues || []);
            const html = grouped.map((group) => `
                <div class="text-start mb-2">
                    <strong>Page ${group.pageIndex + 1}</strong>
                    <div class="small text-muted mb-1">Missing:</div>
                    <ul class="small mb-2">${[...new Set(group.fields)].map((f) => `<li>${escapeHtml(f)}</li>`).join('')}</ul>
                    <button type="button" class="swal-goto btn btn-sm btn-outline-primary" data-page="${group.pageIndex}">Go to Page ${group.pageIndex + 1}</button>
                </div>
            `).join('');

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Cannot Submit — Incomplete Forms',
                html: `<p class="mb-3">Fix the following before submitting to IT Review:</p>${html}`,
                showConfirmButton: true,
                confirmButtonText: 'OK',
                confirmButtonColor: '#1a6fc4',
                didOpen: () => {
                    document.querySelectorAll('.swal-goto').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            Swal.close();
                            goToPage(parseInt(btn.dataset.page, 10));
                        });
                    });
                },
            });
            return;
        }

        const confirm = await Swal.fire({
            title: 'Submit to IT Review?',
            text: 'This record will be locked and sent to the IT Department for validation and printing.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Submit to IT Review',
            confirmButtonColor: '#16a34a',
        });
        if (!confirm.isConfirmed) return;

        await runWorkflow('submit_to_it_review');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    async function loadPatient(id) {
        try {
            let url = `${cfg.baseUrl}/api/get_patient.php?id=${id}`;
            if (cfg.consultationId) {
                url += `&consultation_id=${cfg.consultationId}`;
            } else if (cfg.newConsultation) {
                url += '&new_consultation=1';
            }
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            populateForm(data.patient, data.treatment, data.extended);

            if (data.profile_required) {
                cfg.profileRequired = data.profile_required;
            }

            if (cfg.newConsultation) {
                cfg.lockPatientProfile = true;
                lockProfileFieldsForNewConsultation();
            }

            const storedPage = localStorage.getItem(localStorageKey());
            const resumePage = cfg.newConsultation
                ? cfg.startPage
                : (storedPage !== null ? parseInt(storedPage, 10) : data.form_progress);
            // Never open on Review & Submit — that view is read-only clones, not the live form
            const page = clampToEditablePage(
                !isNaN(resumePage) && resumePage >= 0 && resumePage < cfg.totalPages
                    ? resumePage
                    : cfg.startPage
            );

            updateToolbar(data.patient);
            goToPage(page, false);
            requestAnimationFrame(() => applyPermissionsToDom());
        } catch (err) {
            Swal.fire('Error', err.message || 'Failed to load patient.', 'error');
        }
    }

    function lockProfileFieldsForNewConsultation() {
        form.querySelectorAll('[data-profile-field], [name="patient_id"]').forEach((el) => {
            el.dataset.profileReadonly = '1';
            el.setAttribute('readonly', 'readonly');
            el.readOnly = true;
            if (el.tagName === 'SELECT' || el.type === 'radio' || el.type === 'checkbox') {
                el.setAttribute('disabled', 'disabled');
                el.disabled = true;
            }
            el.classList.add('pf-profile-readonly');
        });
        form.querySelectorAll('[data-field-group="records_enrollment"], [data-field-group="records_consent"], [data-field-group="it"]').forEach((el) => {
            el.dataset.permEditable = '0';
            el.dataset.permReason = 'profile_readonly';
            el.setAttribute('readonly', 'readonly');
            el.readOnly = true;
            if (el.tagName === 'SELECT' || el.type === 'radio' || el.type === 'checkbox') {
                el.setAttribute('disabled', 'disabled');
                el.disabled = true;
            } else {
                el.removeAttribute('disabled');
                el.disabled = false;
            }
            el.classList.add('pf-profile-readonly');
        });
    }

    function populateForm(patient, treatment, extended) {
        PatientProfileSync.loadFromApi(patient || {}, extended || {});

        // Clinical / visit columns come only from this consultation row (blank for new visits).
        Object.entries(treatment || {}).forEach(([key, value]) => {
            if (key === 'consultation_types' || key === 'receptionist_data' || key === 'it_data' || key === 'id' || key === 'patient_id') {
                return;
            }
            if (isProfileColumn(key)) return;
            setAllNamedFields(key, value, false);
        });

        if (extended) {
            Object.entries(extended).forEach(([key, value]) => {
                if (isProfileExtendedKey(key)) return;
                setAllNamedFields(key, value, true);
            });
        }

        // Ensure visit-specific slips are blank unless present on THIS consultation.
        clearMissingVisitExtended(extended || {});

        form.querySelectorAll('input[type="radio"]').forEach((radio) => {
            if (radio.dataset.profileField) return;
            let value = null;
            const match = radio.name.match(/^extended\[(.+)\]$/);
            if (match) {
                value = extended?.[match[1]];
            } else {
                value = patient?.[radio.name] ?? treatment?.[radio.name];
            }
            if (value != null && String(value) === radio.value) {
                radio.checked = true;
            } else if (!isProfileColumn(radio.name) && !(match && isProfileExtendedKey(match[1]))) {
                radio.checked = false;
            }
        });

        form.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            if (cb.name === 'consultation_types[]') return;
            if (cb.dataset.profileField) return;

            let value = null;
            const match = cb.name.match(/^extended\[(.+)\]$/);
            if (match) {
                value = extended?.[match[1]];
            } else {
                value = patient?.[cb.name] ?? treatment?.[cb.name];
            }

            if (value === '1' || value === 1 || value === true || value === cb.value) {
                cb.checked = true;
            } else if (!isProfileColumn(cb.name) && !(match && isProfileExtendedKey(match[1]))) {
                cb.checked = false;
            }
        });

        if (Array.isArray(treatment?.consultation_types)) {
            form.querySelectorAll('input[name="consultation_types[]"]').forEach((cb) => {
                cb.checked = treatment.consultation_types.includes(cb.value);
            });
        } else {
            form.querySelectorAll('input[name="consultation_types[]"]').forEach((cb) => {
                cb.checked = false;
            });
        }
    }

    function clearMissingVisitExtended(extended) {
        form.querySelectorAll('[name^="extended[ekas_"], [name^="extended[epress_"]').forEach((el) => {
            const match = el.name.match(/^extended\[(.+)\]$/);
            if (!match) return;
            const key = match[1];
            if (Object.prototype.hasOwnProperty.call(extended, key) && extended[key] != null && extended[key] !== '') {
                return;
            }
            if (el.type === 'radio' || el.type === 'checkbox') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
    }

    function isProfileColumn(key) {
        return PatientProfileSync.isPatientColumn(key);
    }

    function isProfileExtendedKey(key) {
        return PatientProfileSync.isExtendedProfileKey(key);
    }

    function setAllNamedFields(name, value, extended) {
        const selector = extended ? `[name="extended[${name}]"]` : `[name="${name}"]`;
        form.querySelectorAll(selector).forEach((el) => {
            if (el.dataset.profileField) return;
            if (el.type === 'radio' || el.type === 'checkbox') return;
            el.value = value ?? '';
        });
    }

    function updateToolbar(patient) {
        const badge = document.getElementById('statusBadge');
        const ref = document.getElementById('patientRefBadge');
        if (ref) ref.textContent = patient.patient_id || '—';
        if (badge) {
            badge.textContent = patient.status;
            badge.className = 'badge-status ' + getStatusClass(patient.status);
        }

        const middle = (patient.middle_name || '').trim();
        const formal = [patient.last_name, [patient.first_name, middle].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        const nameEl = document.getElementById('summaryFullName');
        const sexEl = document.getElementById('summarySex');
        const ageEl = document.getElementById('summaryAge');
        const phEl = document.getElementById('summaryPhilhealth');
        const brgyEl = document.getElementById('summaryBarangay');

        if (nameEl) nameEl.textContent = formal || patient.full_name || '—';
        if (sexEl) sexEl.textContent = patient.sex || '—';
        if (phEl) phEl.textContent = patient.philhealth_no || '—';
        if (ageEl) {
            if (patient.birth_date) {
                const age = calcAgeYears(patient.birth_date);
                ageEl.textContent = age != null ? `${age} years old` : '—';
            } else {
                ageEl.textContent = '—';
            }
        }
        if (brgyEl) {
            brgyEl.textContent = extractBarangay(patient.residential_address) || '—';
        }
    }

    function calcAgeYears(birthDate) {
        const d = new Date(birthDate);
        if (Number.isNaN(d.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - d.getFullYear();
        const m = today.getMonth() - d.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < d.getDate())) age -= 1;
        return age;
    }

    function extractBarangay(address) {
        if (!address) return '';
        const m = String(address).match(/,\s*([^,]+)\s*$/);
        return m ? m[1].trim() : address;
    }

    function markSavedUi(message) {
        if (!saveStatus) return;
        saveStatus.classList.remove('d-none');
        saveStatus.classList.add('saved');
        saveStatus.innerHTML = `<i class="bi bi-check2-circle"></i> ${message || 'All Changes Saved'}`;
        const last = document.getElementById('saveLastTime');
        if (last) {
            last.classList.remove('d-none');
            last.textContent = `Last saved: just now`;
        }
    }

    function initPaperZoomControls() {
        // Legacy zoom controls removed; print handled by Final Print Preview.
        document.getElementById('btnPrintPreview')?.addEventListener('click', () => openFinalPrintPreview());
    }

    function getStatusClass(status) {
        const map = {
            Draft: 'badge-draft',
            Rejected: 'badge-rejected',
            'Returned for Correction': 'badge-returned',
            'Pending Receptionist': 'badge-clinical',
            'Pending Doctor': 'badge-clinical',
            'Pending IT Review': 'badge-it-review',
            'Pending IT Validation': 'badge-it-review',
            'Ready for Printing': 'badge-ready-print',
            Printed: 'badge-printed',
            Archived: 'badge-archived',
        };
        return map[status] || 'badge-draft';
    }

    /** Include disabled controls in FormData (browser omits them by default). */
    function buildFormDataIncludingDisabled() {
        const disabled = [];
        form.querySelectorAll('input[disabled], select[disabled], textarea[disabled]').forEach((el) => {
            disabled.push(el);
            el.disabled = false;
        });
        const formData = new FormData(form);
        disabled.forEach((el) => { el.disabled = true; });
        return formData;
    }

    function missingProfileCreateFields() {
        const required = cfg.profileRequired || ['last_name', 'first_name', 'sex', 'birth_date'];
        const labels = {
            last_name: 'Last Name',
            first_name: 'First Name',
            sex: 'Sex',
            birth_date: 'Birth Date',
        };
        return required.filter((k) => !(PatientProfileSync.getProfileValue(k, false) || '').trim())
            .map((k) => labels[k] || k);
    }

    async function saveDraft(silent = false) {
        // Harvest first — never reload a local_only response over live form values
        // (that wipe was clearing Sex / Birth Date mid-typing and blocking create).
        const profilePayload = typeof PatientProfileSync.getPayloadForSave === 'function'
            ? PatientProfileSync.getPayloadForSave()
            : (PatientProfileSync.harvestFromForm(), PatientProfileSync.buildProfileFromState());
        PatientProfileSync.applyAll();
        applyPermissionsToDom();

        const formData = buildFormDataIncludingDisabled();
        // Persist last editable form page — not Review (preview-only)
        const progressPage = isReviewPageIndex(currentPage)
            ? clampToEditablePage(currentPage)
            : currentPage;
        formData.set('form_progress', String(progressPage));
        if (cfg.consultationId) {
            formData.set('consultation_id', String(cfg.consultationId));
        }
        if (cfg.newConsultation && !cfg.consultationId) {
            formData.set('new_consultation', '1');
        }

        formData.set('profile_json', JSON.stringify(profilePayload));

        Object.entries(profilePayload.patient || {}).forEach(([key, value]) => {
            if (value != null && value !== '') {
                formData.set(key, value);
            }
        });
        Object.entries(profilePayload.extended || {}).forEach(([key, value]) => {
            if (value != null && value !== '') {
                formData.set(`extended[${key}]`, value);
            }
        });

        const consultationTypes = [];
        form.querySelectorAll('input[name="consultation_types[]"]:checked').forEach((cb) => {
            consultationTypes.push(cb.value);
        });
        formData.delete('consultation_types[]');
        formData.append('consultation_types', JSON.stringify(consultationTypes));

        if (btnSaveDraft && !silent) {
            btnSaveDraft.disabled = true;
            btnSaveDraft.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        }

        try {
            const res = await fetch(`${cfg.baseUrl}/api/save_patient.php`, {
                method: 'POST',
                body: formData,
            });
            const raw = await res.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                throw new Error(res.ok
                    ? 'Server returned an invalid response while saving.'
                    : `Save failed (HTTP ${res.status}).`);
            }
            if (!data.success) throw new Error(data.message || 'Failed to save draft.');

            if (data.patient_db_id != null) {
                document.getElementById('patientDbId').value = data.patient_db_id;
            }

            if (cfg.patientId === 0 && data.patient_db_id > 0) {
                cfg.patientId = data.patient_db_id;
                history.replaceState(null, '', `?id=${data.patient_db_id}`);
                localStorage.removeItem('rhu_wizard_page_new');
                localStorage.setItem(`rhu_wizard_page_${data.patient_db_id}`, String(currentPage));
                PatientProfileSync.clearLocalDraft?.();
            }

            if (data.consultation_id) {
                const createdNewVisit = formData.get('new_consultation') === '1';
                cfg.consultationId = data.consultation_id;
                const params = new URLSearchParams(window.location.search);
                params.set('id', String(data.patient_db_id || cfg.patientId));
                params.set('consultation_id', String(data.consultation_id));
                params.delete('new_consultation');
                if (!params.get('page')) {
                    params.set('page', 'itr');
                }
                history.replaceState(null, '', `?${params.toString()}`);
                cfg.newConsultation = false;
                if (createdNewVisit) {
                    cfg.lockPatientProfile = true;
                }
            }

            // local_only: keep the in-form profile. Reloading wiped fields users had just typed.
            if (data.local_only) {
                applyPermissionsToDom();
                const missing = (data.missing_fields || []).length
                    ? data.missing_fields
                    : missingProfileCreateFields();
                if (!silent) {
                    markSavedUi('Complete required profile fields to create record');
                    const list = missing.length
                        ? `<ul class="text-start mb-0">${missing.map((m) => `<li>${escapeHtml(m)}</li>`).join('')}</ul>`
                        : '';
                    await Swal.fire({
                        icon: 'info',
                        title: 'Draft kept on this device',
                        html: `<p class="mb-2">${escapeHtml(data.message || 'Complete the required identity fields to create the patient record.')}</p>${list}`,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#1a6fc4',
                    });
                }
                return true;
            }

            if (data.patient) {
                updateToolbar(data.patient);
                PatientProfileSync.loadFromApi(data.patient, data.extended || {});
            }

            if (cfg.lockPatientProfile) {
                lockProfileFieldsForNewConsultation();
            }
            PatientProfileSync.applyAll();
            applyPermissionsToDom();
            updateFooterButtons(currentPage);
            markSavedUi(cfg.consultationId ? 'Consultation visit saved' : 'All Changes Saved');

            if (!silent) {
                Swal.fire({
                    icon: 'success',
                    title: formData.get('new_consultation') === '1'
                        ? 'New Consultation Saved'
                        : 'Draft Saved',
                    text: data.message || 'You can continue later from this page.',
                    timer: 1800,
                    showConfirmButton: false,
                });
            }

            return true;
        } catch (err) {
            if (!silent) {
                Swal.fire('Error', err.message || 'Failed to save draft.', 'error');
            }
            return false;
        } finally {
            if (btnSaveDraft && !silent) {
                btnSaveDraft.disabled = false;
                btnSaveDraft.innerHTML = SAVE_DRAFT_LABEL;
            }
        }
    }

    async function sendToDoctor() {
        if (!isItRole()) return;

        // Ensure hidden patient id is synced from config (fixes disabled/unclickable state)
        const dbInput = document.getElementById('patientDbId');
        let patientId = Number(dbInput?.value || cfg.patientId || 0);
        if (patientId > 0 && dbInput && !dbInput.value) {
            dbInput.value = String(patientId);
        }

        const confirm = await Swal.fire({
            title: 'Send to Doctor?',
            html: '<p>This will place the <strong>same consultation</strong> in the Doctor Consultation Queue with status <strong>Waiting for Doctor</strong>.</p><p class="text-muted small mb-0">Latest form data will be saved first. No duplicate patient record is created.</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Send to Doctor',
            confirmButtonColor: '#16a34a',
        });
        if (!confirm.isConfirmed) return;

        const saved = await saveDraft(true);
        if (!saved) return;

        patientId = Number(document.getElementById('patientDbId')?.value || cfg.patientId || 0);
        if (patientId <= 0) {
            Swal.fire('Save failed', 'Patient record was not created. Complete required profile fields, click Save Draft, then try Send to Doctor again.', 'warning');
            return;
        }

        await runWorkflow('send_to_doctor');
    }

    async function runWorkflow(action) {
        const patientId = document.getElementById('patientDbId').value;

        try {
            const body = new FormData();
            body.append('action', action);
            body.append('patient_id', patientId);
            if (cfg.consultationId) {
                body.append('consultation_id', String(cfg.consultationId));
            }

            const res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, {
                method: 'POST',
                body,
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            localStorage.removeItem(localStorageKey());

            await Swal.fire({
                icon: 'success',
                title: 'Done!',
                text: data.message,
                confirmButtonColor: '#1a6fc4',
            });

            if (action === 'send_to_doctor') {
                // Reload encoding page — locked + Waiting for Doctor badge
                const params = new URLSearchParams();
                params.set('id', String(patientId));
                if (cfg.consultationId) {
                    params.set('consultation_id', String(cfg.consultationId));
                }
                params.set('page', 'epress');
                window.location.href = `${cfg.baseUrl}/forms/doh-form.php?${params.toString()}`;
            } else if (action === 'submit_to_it_review') {
                window.location.href = `${cfg.baseUrl}/patient-records.php`;
            } else {
                window.location.href = `${cfg.baseUrl}/workflow/queue.php`;
            }
        } catch (err) {
            Swal.fire('Error', err.message || 'Action failed.', 'error');
        }
    }
});
