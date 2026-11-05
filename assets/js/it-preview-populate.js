/**
 * IT Print Preview — populate forms then optional auto-print.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.IT_PREVIEW;
    if (!cfg) return;

    const root = document.getElementById('itPreviewPages');
    populateRecord(root, cfg.patient, cfg.treatment, cfg.extended);

    if (cfg.layoutTestMode) {
        applyLayoutTestPresentation(root);
    }

    // Strip inline max-width that fights fixed paper templates (e.g. EKAS 148mm)
    root?.querySelectorAll('.print-page .paper-sheet[style]').forEach((el) => {
        el.style.maxWidth = '';
        el.style.width = '';
    });

    window.__RHU_PREVIEW_READY = true;

    if (cfg.autoprint) {
        // Legacy manual URLs only — batch print uses parent window.print() on printjob=1 popup.
        scheduleAutoprintOnce();
    }
});

function scheduleAutoprintOnce() {
    if (window.__RHU_AUTOPRINT_SCHEDULED) {
        return;
    }
    window.__RHU_AUTOPRINT_SCHEDULED = true;

    // Wait for layout paint before opening the system print dialog (once only)
    setTimeout(() => {
        if (window.__RHU_AUTOPRINT_FIRED) {
            return;
        }
        window.__RHU_AUTOPRINT_FIRED = true;
        try {
            window.focus();
            window.print();
        } catch (e) { /* ignore */ }
    }, 700);
}

function populateRecord(root, patient, treatment, extended) {
    if (!root) return;

    Object.entries({ ...patient, ...treatment }).forEach(([key, value]) => {
        if (key === 'consultation_types' || key === 'receptionist_data' || key === 'id') return;
        setFieldValue(root, key, value);
    });

    if (extended) {
        Object.entries(extended).forEach(([key, value]) => {
            setFieldValue(root, `extended[${key}]`, value);
        });
    }

    root.querySelectorAll('input[type="radio"]').forEach((radio) => {
        let value = null;
        const match = radio.name.match(/^extended\[(.+)\]$/);
        if (match) {
            value = extended?.[match[1]];
        } else {
            value = patient?.[radio.name] ?? treatment?.[radio.name];
        }
        if (value != null && String(value) === radio.value) {
            radio.checked = true;
        }
    });

    root.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
        if (cb.name === 'consultation_types[]') return;
        let value = null;
        const match = cb.name.match(/^extended\[(.+)\]$/);
        if (match) {
            value = extended?.[match[1]];
        } else {
            value = patient?.[cb.name] ?? treatment?.[cb.name];
        }
        if (value === '1' || value === 1 || value === true || value === cb.value) {
            cb.checked = true;
        }
    });

    if (Array.isArray(treatment?.consultation_types)) {
        root.querySelectorAll('input[name="consultation_types[]"]').forEach((cb) => {
            cb.checked = treatment.consultation_types.includes(cb.value);
        });
    }
}

function applyLayoutTestPresentation(root) {
    if (!root) return;

    root.querySelectorAll('input, textarea, select').forEach((el) => {
        el.removeAttribute('required');
        el.removeAttribute('title');
        el.removeAttribute('placeholder');
        el.classList.remove('pf-profile-filled', 'pf-profile-computed', 'is-invalid');

        if (el.tagName === 'SELECT' && !el.value) {
            el.selectedIndex = 0;
        }

        if ((el.type === 'text' || el.type === 'date' || el.type === 'time' || el.tagName === 'TEXTAREA')
            && !String(el.value || '').trim()) {
            el.value = '';
        }
    });
}

function setFieldValue(root, name, value) {
    root.querySelectorAll(`[name="${name}"]`).forEach((el) => {
        if (el.type === 'radio' || el.type === 'checkbox') return;
        el.value = value ?? '';
    });
}
