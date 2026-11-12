/**
 * IT Validation Workspace — DMS document viewer + validation actions
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.IT_VALIDATION;
    if (!cfg) return;

    const formIds = cfg.documents || [];
    let activeIndex = 0;
    let zoom = 1;

    const canvas = document.getElementById('itvViewerCanvas');
    const stage = document.getElementById('itvViewerStage');
    const formIndicator = document.getElementById('itvFormIndicator');
    const zoomLabel = document.getElementById('itvZoomLabel');
    const remarks = document.getElementById('itvRemarks');
    const remarkCount = document.getElementById('itvRemarkCount');

    // Populate all form panes with patient data
    if (typeof populateRecord === 'function') {
        populateRecord(
            document.getElementById('itvViewerCanvas'),
            cfg.patient,
            cfg.treatment,
            cfg.extended
        );
    }

    // Unlock Records + PCSF fields for IT; keep doctor clinical fields read-only
    const editableGroups = new Set([
        'it',
        'records_enrollment',
        'records_consent',
        'records_treatment',
    ]);
    document.querySelectorAll('#itvViewerCanvas input, #itvViewerCanvas select, #itvViewerCanvas textarea').forEach((el) => {
        const group = el.getAttribute('data-field-group') || '';
        const isSystemReadonly = el.dataset.profileReadonly === '1'
            || el.classList.contains('pf-profile-computed')
            || el.dataset.permReason === 'system_readonly'
            || el.name === 'patient_id';

        if (isSystemReadonly || !editableGroups.has(group)) {
            el.disabled = true;
            el.readOnly = true;
            el.tabIndex = -1;
            return;
        }

        el.disabled = false;
        el.readOnly = false;
        el.removeAttribute('readonly');
        el.removeAttribute('disabled');
        el.tabIndex = 0;
    });

    const FORM_KEY_TO_DOC = {
        pcsf: 'pcsf',
        itr: 'itr',
        enrollment: 'enrollment',
        past_medical_history: 'history',
        past_surgical_history: 'history',
        family_history: 'history',
        personal_social_history: 'personal',
        ncd_assessment: 'personal',
        photo_consent: 'personal',
        ekas: 'ekas',
        epress: 'epress',
    };

    let completenessByDoc = {};

    function docIdForIssue(issue) {
        const key = issue?.form_key || '';
        if (key && FORM_KEY_TO_DOC[key]) {
            return FORM_KEY_TO_DOC[key];
        }
        const label = (issue?.form || '').toLowerCase();
        if (label.includes('philhealth') || label.includes('pcsf') || label.includes('primary care')) return 'pcsf';
        if (label.includes('individual treatment')) return 'itr';
        if (label.includes('enrollment')) return 'enrollment';
        if (label.includes('past medical') || label.includes('surgical') || label.includes('family history')) return 'history';
        if (label.includes('personal') || label.includes('ncd') || label.includes('photo')) return 'personal';
        if (label.includes('ekas') || label.includes('availment')) return 'ekas';
        if (label.includes('epress') || label.includes('prescription')) return 'epress';
        return null;
    }

    function buildCompletenessByDoc(issues) {
        completenessByDoc = {};
        formIds.forEach((id) => {
            completenessByDoc[id] = [];
        });

        (issues || []).forEach((issue) => {
            const docId = docIdForIssue(issue);
            if (!docId || !completenessByDoc[docId]) return;

            const items = Array.isArray(issue.items) && issue.items.length
                ? issue.items
                : (issue.fields || []).map((label) => ({ label, key: '', extended: false }));

            const issueOwner = issue.owner || 'records_office';
            items.forEach((item) => {
                const sig = `${item.key || ''}|${item.label || ''}`;
                if (completenessByDoc[docId].some((x) => `${x.key || ''}|${x.label || ''}` === sig)) {
                    return;
                }
                completenessByDoc[docId].push({ ...item, owner: issueOwner });
            });
        });
    }

    function resolveFieldElement(pane, item) {
        if (!pane || !item) return null;
        const key = item.key || '';

        if (key.startsWith('__custom:')) {
            const custom = key.slice(9);
            if (custom === 'pmh_completed') {
                return pane.querySelector('input[name^="extended[pmh_"]')
                    || pane.querySelector('[name="extended[pmh_none]"]');
            }
            if (custom === 'fh_completed') {
                return pane.querySelector('input[name^="extended[fh_"]')
                    || pane.querySelector('[name="extended[fh_none]"]');
            }
            if (custom === 'psh_surgical_completed') {
                return pane.querySelector('[name="extended[surgical_op_1]"]')
                    || pane.querySelector('[name="extended[surgical_date_1]"]');
            }
            return null;
        }

        if (key === 'consultation_types[]') {
            return pane.querySelector('input[name="consultation_types[]"]');
        }

        if (item.extended) {
            const ext = pane.querySelector(`[name="extended[${CSS.escape(key)}]"]`);
            if (ext) return ext;
        }

        return pane.querySelector(`[name="${CSS.escape(key)}"]`);
    }

    function flashField(el) {
        if (!el) return;
        const row = el.closest('tr, .pf-field, .row, .mb-2, .form-group') || el;
        row.classList.add('itv-field-flash');
        const target = row.querySelector('th, label, .pf-label') || el;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (!el.disabled && typeof el.focus === 'function') {
            try {
                el.focus({ preventScroll: true });
            } catch (ignored) {
                el.focus();
            }
        }
        window.setTimeout(() => row.classList.remove('itv-field-flash'), 2800);
    }

    function jumpToField(docId, item) {
        const idx = formIds.indexOf(docId);
        if (idx >= 0) {
            activeIndex = idx;
            const formId = formIds[activeIndex];
            document.querySelectorAll('.itv-form-pane').forEach((pane) => {
                pane.classList.toggle('is-active', pane.dataset.form === formId);
            });
            document.querySelectorAll('.itv-folder-item').forEach((btn) => {
                btn.classList.toggle('is-active', btn.dataset.form === formId);
            });
            if (formIndicator) {
                formIndicator.textContent = `Form ${activeIndex + 1} of ${formIds.length}`;
            }
        }

        const pane = document.querySelector(`.itv-form-pane[data-form="${docId}"]`);
        flashField(resolveFieldElement(pane, item));
        updateActiveIssuesPanel();
    }

    function updateFolderStatuses() {
        document.querySelectorAll('.itv-folder-item').forEach((btn) => {
            const docId = btn.dataset.form;
            const count = (completenessByDoc[docId] || []).length;
            const status = btn.querySelector('.itv-folder-status');
            btn.classList.toggle('has-errors', count > 0);
            btn.classList.toggle('is-complete', count === 0);
            if (status) {
                if (count > 0) {
                    status.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
                    status.title = `${count} missing required field(s)`;
                } else {
                    status.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                    status.title = 'Required fields complete';
                }
            }
        });
    }

    function updateDocChips() {
        document.querySelectorAll('.itv-chip[data-form]').forEach((chip) => {
            const docId = chip.dataset.form;
            const count = (completenessByDoc[docId] || []).length;
            chip.classList.toggle('is-ok', count === 0);
            chip.classList.toggle('is-error', count > 0);
            chip.classList.remove('is-pending');
            const icon = chip.querySelector('i');
            if (icon) {
                icon.className = count > 0 ? 'bi bi-x-circle-fill' : 'bi bi-check-circle-fill';
            }
        });
    }

    function updateOverallCompletion(complete) {
        const pctEl = document.getElementById('itvCompletionPct');
        const textEl = document.getElementById('itvReadyText');
        const total = formIds.length;
        const incompleteDocs = formIds.filter((id) => (completenessByDoc[id] || []).length > 0).length;
        const okDocs = total - incompleteDocs;
        const pct = total ? Math.round((okDocs / total) * 100) : 0;

        if (pctEl) {
            pctEl.textContent = `${pct}%`;
            pctEl.classList.toggle('is-ready', complete || pct === 100);
            pctEl.classList.toggle('is-incomplete', !complete && pct < 100);
        }
        if (textEl) {
            textEl.textContent = complete
                ? 'All required fields complete'
                : (incompleteDocs
                    ? `${incompleteDocs} form(s) need corrections`
                    : 'Review patient data carefully');
        }
    }

    function itCanEditIssueOwner(owner) {
        const o = (owner || '').toLowerCase();
        return o === '' || o === 'records_office' || o === 'records' || o === 'it' || o === 'it_department';
    }

    function updateActiveIssuesPanel() {
        const panel = document.getElementById('itvIssuesPanel');
        const list = document.getElementById('itvIssuesList');
        const title = panel?.querySelector('.itv-issues-title');
        if (!panel || !list) return;

        const formId = formIds[activeIndex];
        const items = completenessByDoc[formId] || [];

        if (!items.length) {
            panel.classList.add('d-none');
            list.innerHTML = '';
            return;
        }

        const hasViewOnly = items.some((item) => !itCanEditIssueOwner(item.owner));
        if (title) {
            title.textContent = hasViewOnly
                ? 'Missing information (some fields are read-only for IT)'
                : 'Missing information on this form';
        }

        panel.classList.remove('d-none');
        list.innerHTML = items.map((item) => {
            const key = escapeHtml(item.key || '');
            const ext = item.extended ? '1' : '0';
            const ro = !itCanEditIssueOwner(item.owner);
            const roBadge = ro ? ' <span class="itv-issues-ro">View only</span>' : '';
            const roClass = ro ? ' itv-issues-link--readonly' : '';
            return `<li><button type="button" class="itv-issues-link${roClass}" data-doc="${escapeHtml(formId)}" data-key="${key}" data-ext="${ext}">${escapeHtml(item.label || 'Missing field')}${roBadge}</button></li>`;
        }).join('');

        list.querySelectorAll('.itv-issues-link').forEach((btn) => {
            btn.addEventListener('click', () => {
                jumpToField(btn.dataset.doc, {
                    key: btn.dataset.key,
                    extended: btn.dataset.ext === '1',
                    label: btn.textContent,
                });
            });
        });
    }

    async function refreshCompleteness() {
        try {
            const res = await fetch(
                `${cfg.baseUrl}/api/check_form_completeness.php?patient_id=${cfg.patientDbId}&scope=display`
            );
            const data = await res.json();
            if (!data.success) return;

            buildCompletenessByDoc(data.issues || []);
            updateFolderStatuses();
            updateDocChips();
            updateOverallCompletion(!!data.complete);
            updateActiveIssuesPanel();
        } catch (e) {
            const textEl = document.getElementById('itvReadyText');
            if (textEl) textEl.textContent = 'Could not check required fields';
        }
    }

    function showForm(index) {
        if (!formIds.length) return;
        activeIndex = Math.max(0, Math.min(formIds.length - 1, index));
        const formId = formIds[activeIndex];

        document.querySelectorAll('.itv-form-pane').forEach((pane) => {
            pane.classList.toggle('is-active', pane.dataset.form === formId);
        });
        document.querySelectorAll('.itv-folder-item').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.form === formId);
        });

        if (formIndicator) {
            formIndicator.textContent = `Form ${activeIndex + 1} of ${formIds.length}`;
        }

        updateActiveIssuesPanel();

        if (stage) {
            stage.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function applyZoom() {
        if (!canvas) return;
        canvas.style.transform = `scale(${zoom})`;
        if (zoomLabel) zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
    }

    document.querySelectorAll('.itv-folder-item').forEach((btn) => {
        btn.addEventListener('click', () => {
            const idx = formIds.indexOf(btn.dataset.form);
            if (idx >= 0) showForm(idx);
        });
    });

    document.querySelectorAll('.itv-chip[data-form]').forEach((chip) => {
        chip.style.cursor = 'pointer';
        chip.addEventListener('click', () => {
            const idx = formIds.indexOf(chip.dataset.form);
            if (idx >= 0) showForm(idx);
        });
    });

    document.getElementById('itvPrevForm')?.addEventListener('click', () => showForm(activeIndex - 1));
    document.getElementById('itvNextForm')?.addEventListener('click', () => showForm(activeIndex + 1));

    document.getElementById('itvZoomIn')?.addEventListener('click', () => {
        zoom = Math.min(1.6, Math.round((zoom + 0.1) * 10) / 10);
        applyZoom();
    });
    document.getElementById('itvZoomOut')?.addEventListener('click', () => {
        zoom = Math.max(0.7, Math.round((zoom - 0.1) * 10) / 10);
        applyZoom();
    });
    document.getElementById('itvFitWidth')?.addEventListener('click', () => {
        zoom = 1;
        applyZoom();
        stage?.scrollTo({ left: 0, top: 0 });
    });

    document.getElementById('itvFullscreen')?.addEventListener('click', () => openFullscreen());
    document.getElementById('itvFsClose')?.addEventListener('click', () => closeFullscreen());
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeFullscreen();
    });

    function openFullscreen() {
        const overlay = document.getElementById('itvFsOverlay');
        const body = document.getElementById('itvFsBody');
        const title = document.getElementById('itvFsTitle');
        const active = document.querySelector('.itv-form-pane.is-active');
        if (!overlay || !body || !active) return;

        const formId = active.dataset.form;
        if (title) title.textContent = (cfg.documentLabels && cfg.documentLabels[formId]) || 'Document Preview';
        body.innerHTML = '';
        const clone = active.cloneNode(true);
        clone.classList.add('is-active');
        clone.style.display = 'block';
        clone.querySelectorAll('input, select, textarea, button').forEach((el) => {
            el.disabled = true;
            el.readOnly = true;
        });
        body.appendChild(clone);
        overlay.hidden = false;
        overlay.classList.add('is-open');
        document.body.classList.add('itv-fs-open');
    }

    function closeFullscreen() {
        const overlay = document.getElementById('itvFsOverlay');
        overlay?.classList.remove('is-open');
        if (overlay) overlay.hidden = true;
        document.body.classList.remove('itv-fs-open');
        const body = document.getElementById('itvFsBody');
        if (body) body.innerHTML = '';
    }

    remarks?.addEventListener('input', () => {
        if (remarkCount) remarkCount.textContent = String(remarks.value.length);
    });

    const returnModalEl = document.getElementById('itvReturnModal');
    const returnReasonEl = document.getElementById('itvReturnReason');
    const returnReasonCountEl = document.getElementById('itvReturnReasonCount');
    const returnUserEl = document.getElementById('itvReturnToUser');
    const returnErrorEl = document.getElementById('itvReturnError');
    const returnSubmitBtn = document.getElementById('itvReturnSubmit');
    let returnModal;

    function syncReturnReasonCount() {
        if (returnReasonCountEl && returnReasonEl) {
            returnReasonCountEl.textContent = String(returnReasonEl.value.length);
        }
    }

    function showReturnError(message) {
        if (!returnErrorEl) return;
        if (!message) {
            returnErrorEl.classList.add('d-none');
            returnErrorEl.textContent = '';
            return;
        }
        returnErrorEl.textContent = message;
        returnErrorEl.classList.remove('d-none');
    }

    returnReasonEl?.addEventListener('input', syncReturnReasonCount);

    document.getElementById('btnReturnForCorrection')?.addEventListener('click', () => returnForCorrection());
    returnSubmitBtn?.addEventListener('click', () => submitReturnForCorrection());

    returnModalEl?.addEventListener('shown.bs.modal', () => {
        returnReasonEl?.focus();
    });

    returnModalEl?.addEventListener('hidden.bs.modal', () => {
        showReturnError('');
        if (returnSubmitBtn) {
            returnSubmitBtn.disabled = false;
        }
    });

    document.getElementById('btnApproveToPrint')?.addEventListener('click', () => approveToPrint());
    document.getElementById('btnSaveRecordsFields')?.addEventListener('click', () => saveRecordsFields());
    // Back-compat if old button id still present
    document.getElementById('btnSavePkRf')?.addEventListener('click', () => saveRecordsFields());

    function appendNamedControl(body, el) {
        const name = el.name || '';
        if (!name || el.disabled) return;

        if (el.type === 'checkbox') {
            if (el.checked) body.append(name, el.value || '1');
            return;
        }
        if (el.type === 'radio') {
            if (el.checked) body.append(name, el.value);
            return;
        }
        body.append(name, (el.value || '').toString());
    }

    async function saveRecordsFields() {
        const root = document.getElementById('itvViewerCanvas');
        if (!root) {
            Swal.fire({ icon: 'error', title: 'Forms not found', confirmButtonColor: '#1a6fc4' });
            return;
        }

        const body = new FormData();
        body.append('patient_db_id', String(cfg.patientDbId));
        if (cfg.consultationId) {
            body.append('consultation_id', String(cfg.consultationId));
        }

        const editableGroups = new Set([
            'it',
            'records_enrollment',
            'records_consent',
            'records_treatment',
        ]);

        root.querySelectorAll('input, select, textarea').forEach((el) => {
            const group = el.getAttribute('data-field-group') || '';
            if (!editableGroups.has(group)) return;
            if (el.dataset.profileReadonly === '1' || el.name === 'patient_id') return;
            appendNamedControl(body, el);
        });

        const btn = document.getElementById('btnSaveRecordsFields') || document.getElementById('btnSavePkRf');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
        }

        try {
            const res = await fetch(`${cfg.baseUrl}/api/save_patient.php`, { method: 'POST', body });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Save failed');
            if (data.extended) cfg.extended = { ...cfg.extended, ...data.extended };
            if (data.patient) cfg.patient = { ...cfg.patient, ...data.patient };
            if (data.treatment) cfg.treatment = { ...cfg.treatment, ...data.treatment };
            Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: 'Records / PCSF fields were saved.',
                timer: 1600,
                showConfirmButton: false,
            });
            await refreshCompleteness();
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: err.message || 'Unable to save Records fields.',
                confirmButtonColor: '#1a6fc4',
            });
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml || '<i class="bi bi-save"></i> Save Records Fields';
            }
        }
    }

    function returnForCorrection() {
        if (!returnModalEl) return;

        if (!returnUserEl || returnUserEl.options.length === 0 || !returnUserEl.value) {
            Swal.fire({
                icon: 'error',
                title: 'No Users',
                text: 'Walay active users sa system. Pag-add una og user sa admin.',
                confirmButtonColor: '#1a6fc4',
            });
            return;
        }

        const prefillReason = (remarks?.value || '').trim();
        if (returnReasonEl) {
            returnReasonEl.value = prefillReason;
        }
        syncReturnReasonCount();
        showReturnError('');

        if (!returnModal && typeof bootstrap !== 'undefined') {
            returnModal = bootstrap.Modal.getOrCreateInstance(returnModalEl);
        }
        returnModal?.show();
    }

    async function submitReturnForCorrection() {
        const userId = returnUserEl ? parseInt(returnUserEl.value, 10) : 0;
        const reason = (returnReasonEl?.value || '').trim();

        if (!userId) {
            showReturnError('Palihog pilia kinsang user ang i-return-an.');
            returnUserEl?.focus();
            return;
        }
        if (!reason) {
            showReturnError('Palihog sulati ang reason for returning.');
            returnReasonEl?.focus();
            return;
        }
        if (reason.length > 500) {
            showReturnError('Ang reason kinahanglang 500 characters or less.');
            return;
        }

        showReturnError('');
        if (returnSubmitBtn) {
            returnSubmitBtn.disabled = true;
        }

        if (remarks) {
            remarks.value = reason;
            if (remarkCount) {
                remarkCount.textContent = String(reason.length);
            }
        }

        returnModal?.hide();

        await runAction('return_for_correction', reason, 'Returned for Correction', userId);

        if (returnSubmitBtn) {
            returnSubmitBtn.disabled = false;
        }
    }

    async function approveToPrint() {
        const text = (remarks?.value || '').trim();
        const confirm = await Swal.fire({
            title: 'Approve & Send to Print Queue?',
            text: 'This consultation will be marked Ready for Printing and removed from the Review Queue.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Approve & Send to Print Queue',
            confirmButtonColor: '#16a34a',
        });
        if (!confirm.isConfirmed) return;

        await runAction('approve_for_printing', text, 'Sent to Print Queue');
    }

    async function runAction(action, text, successTitle, returnToUserId = 0) {
        try {
            const body = new FormData();
            body.append('action', action);
            body.append('patient_id', String(cfg.patientDbId));
            if (cfg.consultationId) {
                body.append('consultation_id', String(cfg.consultationId));
            }
            if (text) {
                body.append('rejection_reason', text);
            }
            if (returnToUserId > 0) {
                body.append('return_to_user_id', String(returnToUserId));
            }

            const res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, {
                method: 'POST',
                body,
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'Action failed.');
            }

            await Swal.fire({
                icon: 'success',
                title: successTitle,
                text: data.message,
                confirmButtonColor: '#1a6fc4',
            });

            window.location.href = action === 'approve_for_printing'
                ? `${cfg.baseUrl}/it/print-queue.php`
                : `${cfg.baseUrl}/validation-status.php`;
        } catch (err) {
            Swal.fire('Error', err.message || 'Action failed.', 'error');
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    showForm(0);
    applyZoom();
    refreshCompleteness();
});
