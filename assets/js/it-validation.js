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

    // Unlock Records + PKRF fields for IT; keep doctor clinical fields read-only
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

    document.getElementById('btnReturnForCorrection')?.addEventListener('click', () => returnForCorrection());
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
                text: 'Records / PKRF fields were saved.',
                timer: 1600,
                showConfirmButton: false,
            });
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

    async function returnForCorrection() {
        const text = (remarks?.value || '').trim();
        if (!text) {
            Swal.fire({
                icon: 'warning',
                title: 'Remarks Required',
                text: 'Please enter remarks so the encoder knows what to correct.',
                confirmButtonColor: '#1a6fc4',
            });
            remarks?.focus();
            return;
        }

        const confirm = await Swal.fire({
            title: 'Return for Correction?',
            text: 'This consultation will leave the IT Review Queue and return to the encoder.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Return for Correction',
            confirmButtonColor: '#d97706',
        });
        if (!confirm.isConfirmed) return;

        await runAction('return_for_correction', text, 'Returned for Correction');
    }

    async function approveToPrint() {
        const text = (remarks?.value || '').trim();
        const confirm = await Swal.fire({
            title: 'Approve & Send to Print Queue?',
            text: 'This consultation will be marked Ready for Printing and removed from the IT Review Queue.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Approve & Send to Print Queue',
            confirmButtonColor: '#16a34a',
        });
        if (!confirm.isConfirmed) return;

        await runAction('approve_for_printing', text, 'Sent to Print Queue');
    }

    async function runAction(action, text, successTitle) {
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

    showForm(0);
    applyZoom();
});
