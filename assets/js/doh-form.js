/**
 * DOH Form — AJAX save/load with workflow actions
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('dohForm');
    const cfg = window.RHU_FORM;
    if (!form || !cfg) return;

    const btnSave = document.getElementById('btnSaveForm');
    const btnWorkflow = document.getElementById('btnWorkflowAction');
    const btnWorkflowLabel = document.getElementById('btnWorkflowActionLabel');
    const btnMarkValidated = document.getElementById('btnMarkValidated');
    const printGroup = document.getElementById('printDropdownGroup');
    const btnPrint1 = document.getElementById('btnPrintForm1');
    const btnPrint2 = document.getElementById('btnPrintForm2');

    let currentStatus = null;
    let currentAction = null;

    const STATUS_ACTIONS = {
        records_office: { Draft: 'submit_to_it' },
        it_department: { 'Pending IT Validation': 'forward_to_receptionist' },
        receptionist: { 'Pending Receptionist': 'forward_to_doctor' },
        doctor: {
            'Pending Doctor': 'send_to_pharmacy',
            'Waiting for Doctor': 'send_to_pharmacy',
        },
    };

    const SAVE_BEFORE_ACTION = ['submit_to_it', 'complete_consultation', 'send_to_pharmacy'];

    if (cfg.patientId > 0) {
        loadPatient(cfg.patientId);
    } else {
        updateWorkflowButtons(null);
    }

    btnSave?.addEventListener('click', () => saveForm());

    btnWorkflow?.addEventListener('click', () => {
        if (!currentAction) return;

        const labels = {
            submit_to_it: {
                title: 'Submit to IT Department?',
                text: 'This record will be sent to the IT Department. It will leave your pending queue.',
            },
            forward_to_receptionist: {
                title: 'Forward to Receptionist?',
                text: 'This record will be assigned to the Receptionist.',
            },
            forward_to_doctor: {
                title: 'Forward to Doctor?',
                text: 'This record will be assigned to the Doctor for consultation.',
            },
            complete_consultation: {
                title: 'Send to Pharmacy?',
                text: 'Doctor findings will be saved and the prescription will be sent to the Pharmacy Queue.',
            },
            send_to_pharmacy: {
                title: 'Send to Pharmacy?',
                text: 'Doctor findings will be saved and the prescription will be sent to the Pharmacy Queue.',
            },
        };

        const confirm = labels[currentAction] || { title: 'Proceed?', text: 'Continue with this action?' };

        Swal.fire({
            title: confirm.title,
            text: confirm.text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirm',
            confirmButtonColor: '#1a6fc4',
        }).then(async (result) => {
            if (!result.isConfirmed) return;

            if (SAVE_BEFORE_ACTION.includes(currentAction)) {
                const saved = await saveForm(true);
                if (!saved) return;
            }

            await runWorkflowAction(currentAction);
        });
    });

    btnMarkValidated?.addEventListener('click', () => {
        Swal.fire({
            title: 'Mark as Validated?',
            text: 'Confirm that DOH forms have been printed and the record is complete.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Mark Validated',
            confirmButtonColor: '#16a34a',
        }).then((result) => {
            if (result.isConfirmed) runWorkflowAction('mark_validated');
        });
    });

    btnPrint1?.addEventListener('click', (e) => {
        e.preventDefault();
        openPrint('print-form1.php');
    });

    btnPrint2?.addEventListener('click', (e) => {
        e.preventDefault();
        openPrint('print-form2.php');
    });

    function openPrint(formFile) {
        const id = document.getElementById('patientDbId').value;
        if (!id || id === '0') {
            Swal.fire('Save First', 'Please save the patient record before printing.', 'warning');
            return;
        }
        window.open(`${cfg.baseUrl}/forms/${formFile}?id=${id}`, '_blank');
    }

    async function loadPatient(id) {
        try {
            const res = await fetch(`${cfg.baseUrl}/api/get_patient.php?id=${id}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            populateForm(data.patient, data.treatment);
            updateToolbar(data.patient);
        } catch (err) {
            Swal.fire('Error', err.message || 'Failed to load patient.', 'error');
        }
    }

    function populateForm(patient, treatment) {
        const fields = { ...patient, ...treatment };
        Object.entries(fields).forEach(([key, value]) => {
            const el = form.elements[key];
            if (!el) return;
            if (el.type === 'checkbox') return;

            if (key === 'consultation_types' && Array.isArray(value)) {
                form.querySelectorAll('input[name="consultation_types[]"]').forEach((cb) => {
                    cb.checked = value.includes(cb.value);
                });
                return;
            }

            el.value = value ?? '';
        });
    }

    function updateToolbar(patient) {
        document.getElementById('patientRefBadge').textContent = patient.patient_id;
        const badge = document.getElementById('statusBadge');
        if (badge) {
            badge.textContent = patient.status;
            badge.className = 'badge-status ' + getStatusClass(patient.status);
        }
        currentStatus = patient.status;
        updateWorkflowButtons(patient.status);
    }

    function updateWorkflowButtons(status) {
        currentAction = null;

        const roleMap = STATUS_ACTIONS[cfg.role] || {};
        const action = status ? roleMap[status] : null;

        if (action && btnWorkflow) {
            currentAction = action;
            btnWorkflow.classList.remove('d-none');
            btnWorkflowLabel.textContent = cfg.workflowActions[action] || action;
        } else if (btnWorkflow) {
            btnWorkflow.classList.add('d-none');
        }

        const canPrint = cfg.role === 'it_department'
            && (status === 'Pending Print' || status === 'Validated');

        printGroup?.classList.toggle('d-none', !canPrint);

        const showMarkValidated = cfg.role === 'it_department' && status === 'Pending Print';
        btnMarkValidated?.classList.toggle('d-none', !showMarkValidated);
    }

    function getStatusClass(status) {
        const map = {
            Draft: 'badge-draft',
            'Pending IT Validation': 'badge-pending',
            Rejected: 'badge-rejected',
            Validated: 'badge-validated',
            'Pending Receptionist': 'badge-pending',
            'Pending Doctor': 'badge-pending',
            'Pending Print': 'badge-pending',
        };
        return map[status] || 'badge-draft';
    }

    async function saveForm(silent = false) {
        const formData = new FormData(form);
        formData.append('role', cfg.role);

        const consultationTypes = [];
        form.querySelectorAll('input[name="consultation_types[]"]:checked').forEach((cb) => {
            consultationTypes.push(cb.value);
        });
        formData.delete('consultation_types[]');
        formData.append('consultation_types', JSON.stringify(consultationTypes));

        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        }

        try {
            const res = await fetch(`${cfg.baseUrl}/api/save_patient.php`, {
                method: 'POST',
                body: formData,
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.message);

            document.getElementById('patientDbId').value = data.patient_db_id;

            if (cfg.patientId === 0) {
                history.replaceState(null, '', `?id=${data.patient_db_id}`);
                cfg.patientId = data.patient_db_id;
            }

            if (!silent) {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false,
                });
            }

            if (data.patient) updateToolbar(data.patient);
            return true;
        } catch (err) {
            Swal.fire('Error', err.message || 'Failed to save.', 'error');
            return false;
        } finally {
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="bi bi-save"></i> Save';
            }
        }
    }

    async function runWorkflowAction(action) {
        const patientId = document.getElementById('patientDbId').value;

        if (btnWorkflow) {
            btnWorkflow.disabled = true;
        }

        try {
            const body = new FormData();
            body.append('action', action);
            body.append('patient_id', patientId);

            const res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, {
                method: 'POST',
                body,
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.message);

            await Swal.fire({
                icon: 'success',
                title: 'Done!',
                text: data.message,
                confirmButtonColor: '#1a6fc4',
            });

            window.location.href = `${cfg.baseUrl}/workflow/queue.php`;
        } catch (err) {
            Swal.fire('Error', err.message || 'Workflow action failed.', 'error');
        } finally {
            if (btnWorkflow) {
                btnWorkflow.disabled = false;
            }
        }
    }
});
