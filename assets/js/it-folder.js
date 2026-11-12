/**
 * IT Patient Folder — populate read-only forms, workflow actions, and print preview.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.IT_FOLDER;
    if (!cfg) return;

    populateRecord(
        document.getElementById('itFolderForms') || document.getElementById('consultationForms'),
        cfg.patient,
        cfg.treatment,
        cfg.extended
    );

    if (cfg.canPrint) {
        document.querySelectorAll('.it-btn-preview').forEach((btn) => {
            btn.addEventListener('click', () => openPreview(btn.dataset.bundle, btn.dataset.markPrinted === '1'));
        });
    }

    if (cfg.layoutTestMode) {
        applyLayoutTestPresentation(
            document.getElementById('itFolderForms') || document.getElementById('consultationForms')
        );
    }

    document.getElementById('btnPreviewPrint')?.addEventListener('click', () => {
        const frame = document.getElementById('printPreviewFrame');
        frame?.contentWindow?.print();
    });

    if (!cfg.populateOnly) {
        document.getElementById('btnMarkReadyForPrint')?.addEventListener('click', () => markReadyForPrint());
        document.getElementById('btnApproveForPrint')?.addEventListener('click', () => markReadyForPrint('approve_for_printing'));
        document.getElementById('btnRejectToEncoder')?.addEventListener('click', () => rejectToEncoder());
        document.getElementById('btnMarkPrinted')?.addEventListener('click', () => runWorkflow('mark_printed', {
            title: 'Mark as Printed?',
            text: 'Confirm that all official RHU forms have been printed successfully.',
            confirmText: 'Mark as Printed',
        }));
        document.getElementById('btnArchiveRecord')?.addEventListener('click', () => runWorkflow('archive_record', {
            title: 'Archive Record?',
            text: 'This record will be finalized and remain searchable as read-only.',
            confirmText: 'Archive Record',
        }));
    }

    async function rejectToEncoder() {
        const { value: reason } = await Swal.fire({
            title: 'Reject Record?',
            input: 'textarea',
            inputLabel: 'Reason for rejection (required)',
            inputPlaceholder: 'Describe what the encoder must correct…',
            inputAttributes: { 'aria-label': 'Rejection reason' },
            showCancelButton: true,
            confirmButtonText: 'Reject & Return to Encoder',
            confirmButtonColor: '#dc2626',
            inputValidator: (value) => {
                if (!value || !String(value).trim()) return 'A rejection reason is required.';
                return null;
            },
        });
        if (reason === undefined) return;

        try {
            const body = new FormData();
            body.append('action', 'reject_to_encoder');
            body.append('patient_id', String(cfg.patientDbId));
            body.append('rejection_reason', String(reason).trim());
            if (cfg.consultationId) body.append('consultation_id', String(cfg.consultationId));

            const res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, { method: 'POST', body });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            await Swal.fire({ icon: 'success', title: 'Rejected', text: data.message, confirmButtonColor: '#1a6fc4' });
            window.location.href = `${cfg.baseUrl}/validation-status.php`;
        } catch (err) {
            Swal.fire('Error', err.message || 'Reject failed.', 'error');
        }
    }

    async function markReadyForPrint(action = 'mark_ready_for_print') {
        if (cfg.layoutTestMode) {
            const proceed = await Swal.fire({
                icon: 'info',
                title: 'Layout Test Mode Active',
                text: 'Form completeness validation is still enforced before marking Ready for Printing. Disable layout_test_mode in config/it-print.php after layout approval.',
                showCancelButton: true,
                confirmButtonText: 'Continue',
            });
            if (!proceed.isConfirmed) return;
        }

        try {
            const check = await fetch(
                `${cfg.baseUrl}/api/check_form_completeness.php?patient_id=${cfg.patientDbId}&scope=submit`
            );
            const checkData = await check.json();
            if (!checkData.success) throw new Error(checkData.message);

            if (!checkData.complete) {
                const issueHtml = (checkData.issues || [])
                    .map((issue) => `<li><strong>${escapeHtml(issue.form)}</strong>: ${escapeHtml(issue.fields.join(', '))}</li>`)
                    .join('');

                await Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Forms',
                    html: `<p>The record cannot be marked Ready for Printing until all required sections are complete.</p><ul class="text-start small">${issueHtml}</ul>`,
                    confirmButtonColor: '#1a6fc4',
                });
                return;
            }

            const result = await Swal.fire({
                title: action === 'approve_for_printing' ? 'Approve Record?' : 'Mark Ready for Printing?',
                text: 'All required RHU forms are complete. This record will appear in the Print Queue.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: action === 'approve_for_printing' ? 'Approve' : 'Ready for Printing',
                confirmButtonColor: '#1a6fc4',
            });

            if (!result.isConfirmed) return;
            await runWorkflow(action, null, false);
        } catch (err) {
            Swal.fire('Error', err.message || 'Validation failed.', 'error');
        }
    }

    async function runWorkflow(action, confirmCfg = null, showSuccessRedirect = true) {
        if (confirmCfg) {
            const result = await Swal.fire({
                title: confirmCfg.title,
                text: confirmCfg.text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: confirmCfg.confirmText,
                confirmButtonColor: action === 'archive_record' ? '#334155' : '#16a34a',
            });
            if (!result.isConfirmed) return;
        }

        try {
            const body = new FormData();
            body.append('action', action);
            body.append('patient_id', String(cfg.patientDbId));
            if (cfg.consultationId) {
                body.append('consultation_id', String(cfg.consultationId));
            }

            const res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, {
                method: 'POST',
                body,
            });
            const data = await res.json();
            if (!data.success) {
                if (data.validation_error) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'Incomplete Forms',
                        text: data.message,
                    });
                    return;
                }
                throw new Error(data.message);
            }

            if (!showSuccessRedirect) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Ready for Printing',
                    text: data.message,
                    timer: 1800,
                    showConfirmButton: false,
                });
                window.location.reload();
                return;
            }

            await Swal.fire({
                icon: 'success',
                title: 'Updated',
                text: data.message,
                timer: 1800,
                showConfirmButton: false,
            });

            if (action === 'mark_ready_for_print') {
                window.location.href = `${cfg.baseUrl}/it/print-queue.php`;
            } else if (action === 'mark_printed') {
                window.location.reload();
            } else if (action === 'archive_record') {
                window.location.href = `${cfg.baseUrl}/validation-status.php`;
            }
        } catch (err) {
            Swal.fire('Error', err.message || 'Action failed.', 'error');
        }
    }

    function openPreview(bundle, offerMarkPrinted = false) {
        let url = `${cfg.baseUrl}/it/preview.php?id=${cfg.patientDbId}&bundle=${encodeURIComponent(bundle)}`;
        if (cfg.consultationId) {
            url += `&consultation=${cfg.consultationId}`;
        }
        const label = cfg.bundles[bundle] || 'Print Preview';

        const modal = document.getElementById('printPreviewModal');
        const frame = document.getElementById('printPreviewFrame');
        const title = document.getElementById('printPreviewModalLabel');

        if (title) title.textContent = label + ' — Print Preview';
        if (frame) frame.src = url;

        if (modal && window.bootstrap) {
            const instance = bootstrap.Modal.getOrCreateInstance(modal);
            instance.show();

            if (offerMarkPrinted && cfg.workflowActions?.mark_printed) {
                modal.addEventListener('hidden.bs.modal', function onHide() {
                    modal.removeEventListener('hidden.bs.modal', onHide);
                }, { once: true });
            }
        } else {
            window.open(url, '_blank', 'noopener');
        }
    }
});

function populateRecord(root, patient, treatment, extended) {
    if (!root) return;

    Object.entries({ ...patient, ...treatment }).forEach(([key, value]) => {
        if (key === 'consultation_types' || key === 'receptionist_data' || key === 'id') return;
        setFieldValue(root, key, value, false);
    });

    if (extended) {
        Object.entries(extended).forEach(([key, value]) => {
            setFieldValue(root, `extended[${key}]`, value, true);
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

function setFieldValue(root, name, value) {
    root.querySelectorAll(`[name="${name}"]`).forEach((el) => {
        if (el.type === 'radio' || el.type === 'checkbox') return;
        el.value = value ?? '';
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function applyLayoutTestPresentation(root) {
    if (!root) return;

    root.querySelectorAll('input, textarea, select').forEach((el) => {
        el.removeAttribute('required');
        el.removeAttribute('title');
        el.removeAttribute('placeholder');
        el.classList.remove('pf-profile-filled', 'pf-profile-computed', 'is-invalid');
    });
}
