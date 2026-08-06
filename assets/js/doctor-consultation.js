/**
 * Doctor Consultation Workspace — clinical findings only.
 * Loads demographics + vitals from the same consultation visit (IT data).
 * Saves doctor findings into that visit — never opens Patient Encoding.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.DOCTOR_CONSULTATION_CFG;
    if (!cfg) {
        console.error('DOCTOR_CONSULTATION_CFG missing — Send to Pharmacy cannot run.');
        return;
    }

    const form = document.getElementById('doctorConsultationForm');
    const btnSave = document.getElementById('btnDcSaveDraft');
    const btnSubmit = document.getElementById('btnDcSubmit');
    const saveStatus = document.getElementById('dcSaveStatus');
    const bmiEl = document.getElementById('bmi_display');

    const SAVE_LABEL = '<i class="bi bi-save"></i> Save Consultation';
    const SUBMIT_LABEL = '<i class="bi bi-capsule"></i> Send to Pharmacy';

    const diagnosisNotes = window.ClinicalAutocomplete?.createDiagnosisNotes({
        root: document.getElementById('diagnosisNotesRoot'),
        textarea: document.getElementById('diagnosis'),
        listEl: document.getElementById('diagnosisSuggestions'),
        datasetMsgEl: document.getElementById('diagnosisDatasetMsg'),
        idsInput: document.getElementById('diagnosis_ids'),
        baseUrl: cfg.baseUrl,
        canEdit: !!cfg.canEdit,
    });

    const medicationNotes = window.ClinicalAutocomplete?.createMedicationNotes({
        root: document.getElementById('medicationNotesRoot'),
        textarea: document.getElementById('medication_treatment'),
        listEl: document.getElementById('medicationSuggestions'),
        datasetMsgEl: document.getElementById('medicationDatasetMsg'),
        idsInput: document.getElementById('medication_ids'),
        baseUrl: cfg.baseUrl,
        canEdit: !!cfg.canEdit,
    });

    function displayOrDash(value) {
        const v = value != null ? String(value).trim() : '';
        return v !== '' ? v : '—';
    }

    function computeBmi(heightCm, weightKg) {
        const h = parseFloat(heightCm || '');
        const w = parseFloat(weightKg || '');
        if (!bmiEl) return;
        if (!h || !w || h <= 0 || w <= 0) {
            bmiEl.textContent = '—';
            return;
        }
        const m = h / 100;
        const bmi = w / (m * m);
        bmiEl.textContent = Number.isFinite(bmi) ? bmi.toFixed(1) : '—';
    }

    function setStatus(text) {
        if (saveStatus) saveStatus.textContent = text || '';
    }

    function setReadonly(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = displayOrDash(value);
    }

    function resetSubmitButton() {
        if (!btnSubmit) return;
        btnSubmit.disabled = !cfg.canEdit;
        btnSubmit.innerHTML = SUBMIT_LABEL;
    }

    function resetSaveButton() {
        if (!btnSave) return;
        btnSave.disabled = !cfg.canEdit;
        btnSave.innerHTML = SAVE_LABEL;
    }

    async function parseJsonResponse(res, fallbackLabel) {
        const text = await res.text();
        let data = null;
        try {
            data = text ? JSON.parse(text) : null;
        } catch (err) {
            const snippet = (text || '').replace(/\s+/g, ' ').trim().slice(0, 180);
            throw new Error(
                `${fallbackLabel} returned invalid JSON (HTTP ${res.status}). `
                + (snippet ? `Server said: ${snippet}` : 'Empty response — check PHP/server logs.')
            );
        }
        if (!res.ok && (!data || data.success !== true)) {
            throw new Error(
                (data && data.message) || `${fallbackLabel} failed (HTTP ${res.status}).`
            );
        }
        return data || {};
    }

    function fillFields(treatment, extended, patient, diagnoses, medications) {
        const t = treatment || {};
        const ext = extended || {};
        const p = patient || {};

        if (p.last_name || p.first_name) {
            const middle = (p.middle_name || '').trim();
            const name = `${p.last_name || ''}, ${p.first_name || ''}${middle ? ` ${middle}` : ''}`.replace(/^,\s*/, '').trim();
            setReadonly('dcPatientName', name);
        }
        if (p.sex) setReadonly('dcPatientSex', p.sex);
        if (p.contact_number) setReadonly('dcPatientContact', p.contact_number);
        if (p.residential_address) setReadonly('dcPatientAddress', p.residential_address);
        if (ext.address_street || ext.address_barangay) {
            const addr = [
                ext.address_street,
                ext.address_barangay,
                ext.address_municipality,
                ext.address_province,
            ].map((x) => (x || '').trim()).filter(Boolean).join(', ');
            if (addr) setReadonly('dcPatientAddress', addr);
        }

        setReadonly('dc_blood_pressure', t.blood_pressure);
        setReadonly('dc_height_cm', t.height_cm);
        setReadonly('dc_weight_kg', t.weight_kg);
        computeBmi(t.height_cm, t.weight_kg);

        const chiefEl = document.getElementById('chief_complaints')
            || form?.querySelector('[name="chief_complaints"]');
        if (chiefEl) {
            const fromVisit = t.chief_complaints != null ? String(t.chief_complaints).trim() : '';
            chiefEl.value = fromVisit;
        }

        const assessEl = document.getElementById('laboratory_findings')
            || form?.querySelector('[name="laboratory_findings"]');
        if (assessEl) {
            assessEl.value = t.laboratory_findings != null && t.laboratory_findings !== ''
                ? t.laboratory_findings
                : '';
        }

        if (Array.isArray(diagnoses) && diagnoses.length) {
            diagnosisNotes?.setTextFromDiagnoses(diagnoses);
        } else {
            diagnosisNotes?.setTextFromPlain(t.diagnosis || '');
        }
        if (Array.isArray(medications) && medications.length) {
            medicationNotes?.setTextFromMedications(medications);
        } else {
            medicationNotes?.setTextFromPlain(t.medication_treatment || '');
        }

        const planEl = form.querySelector('[name="extended[doctor_treatment_plan]"]');
        if (planEl) planEl.value = ext.doctor_treatment_plan || '';
        const followEl = form.querySelector('[name="extended[doctor_follow_up]"]');
        if (followEl) followEl.value = ext.doctor_follow_up || '';

        if (t.id) {
            document.getElementById('consultationId').value = String(t.id);
            cfg.consultationId = Number(t.id);
        }
    }

    async function loadConsultation() {
        if (!cfg.patientId) return;
        try {
            let url = `${cfg.baseUrl}/api/get_patient.php?id=${cfg.patientId}`;
            if (cfg.consultationId > 0) {
                url += `&consultation_id=${cfg.consultationId}`;
            }
            const res = await fetch(url);
            const data = await parseJsonResponse(res, 'Load consultation');
            if (!data.success) throw new Error(data.message || 'Failed to load');
            fillFields(
                data.treatment || {},
                data.extended || {},
                data.patient || {},
                data.diagnoses || [],
                data.medications || []
            );
            if (data.consultation_id) {
                document.getElementById('consultationId').value = String(data.consultation_id);
                cfg.consultationId = Number(data.consultation_id);
            }
        } catch (err) {
            Swal.fire('Error', err.message || 'Could not load consultation data.', 'error');
        }
    }

    function syncClinicalIds() {
        diagnosisNotes?.syncIdsFromText();
        medicationNotes?.syncIdsFromText();
    }

    function parsedMedicationIds() {
        syncClinicalIds();
        try {
            const raw = document.getElementById('medication_ids')?.value || '[]';
            const ids = JSON.parse(raw);
            return Array.isArray(ids) ? ids.filter((id) => Number(id) > 0) : [];
        } catch (err) {
            return [];
        }
    }

    async function saveDraft(options = {}) {
        const showSuccess = options.showSuccess === true;
        const showErrors = options.showErrors !== false;
        const rethrow = options.rethrow === true;

        if (!cfg.canEdit) {
            const message = 'This consultation is read-only and cannot be sent to Pharmacy.';
            if (showErrors) {
                await Swal.fire('Read-only', message, 'info');
            }
            if (rethrow) throw new Error(message);
            return false;
        }

        syncClinicalIds();

        const dxIds = document.getElementById('diagnosis_ids')?.value || '[]';
        const medIds = document.getElementById('medication_ids')?.value || '[]';

        if (showSuccess && btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
        }
        setStatus('Saving…');

        try {
            const body = new FormData(form);
            body.set('patient_db_id', String(cfg.patientId));
            body.set('form_progress', '0');
            body.set('diagnosis_ids', dxIds);
            body.set('medication_ids', medIds);
            body.set('diagnosis', document.getElementById('diagnosis')?.value || '');
            body.set('medication_treatment', document.getElementById('medication_treatment')?.value || '');
            if (cfg.consultationId > 0) {
                body.set('consultation_id', String(cfg.consultationId));
            }
            if (!body.get('date_of_consultation')) {
                body.set('date_of_consultation', new Date().toISOString().slice(0, 10));
            }

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 45000);

            let res;
            try {
                res = await fetch(`${cfg.baseUrl}/api/save_patient.php`, {
                    method: 'POST',
                    body,
                    signal: controller.signal,
                    credentials: 'same-origin',
                });
            } finally {
                clearTimeout(timeout);
            }

            const data = await parseJsonResponse(res, 'Save consultation');
            if (!data.success) {
                throw new Error(data.message || 'Save failed');
            }

            if (data.consultation_id) {
                cfg.consultationId = Number(data.consultation_id);
                document.getElementById('consultationId').value = String(data.consultation_id);
                const params = new URLSearchParams(window.location.search);
                params.set('id', String(cfg.patientId));
                params.set('consultation_id', String(data.consultation_id));
                history.replaceState(null, '', `?${params.toString()}`);
            }

            setStatus('Draft saved');
            if (showSuccess) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Consultation Saved',
                    text: 'Findings saved to this consultation record (original RHU forms).',
                    timer: 1600,
                    showConfirmButton: false,
                });
            }
            return true;
        } catch (err) {
            setStatus('');
            const message = err.name === 'AbortError'
                ? 'Save timed out. Check the database connection and try again.'
                : (err.message || 'Failed to save draft.');
            if (showErrors) {
                await Swal.fire('Save failed', message, 'error');
            }
            if (rethrow) throw new Error(message);
            return false;
        } finally {
            if (showSuccess) resetSaveButton();
        }
    }

    async function sendToPharmacy() {
        if (!cfg.canEdit) {
            await Swal.fire('Read-only', 'This consultation cannot be sent to Pharmacy.', 'info');
            return;
        }

        const result = await Swal.fire({
            title: 'Send to Pharmacy?',
            html: 'Save this consultation and send the prescription to the <strong>Pharmacy Queue</strong> (Waiting for Pharmacy).',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            confirmButtonText: 'Send to Pharmacy',
            cancelButtonText: 'Cancel',
        });
        if (!result.isConfirmed) return;

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
        }
        setStatus('Sending to Pharmacy…');

        try {
            const medIds = parsedMedicationIds();
            const medText = (document.getElementById('medication_treatment')?.value || '').trim();
            if (medIds.length === 0) {
                throw new Error(
                    medText
                        ? 'Missing medications in the master list. Choose each medicine from the suggestion list before sending.'
                        : 'Missing medications. Add at least one prescription before sending to Pharmacy.'
                );
            }

            await saveDraft({ showSuccess: false, showErrors: false, rethrow: true });

            if (!cfg.consultationId || Number(cfg.consultationId) <= 0) {
                throw new Error('Missing consultation_id. Save the consultation first, then send again.');
            }

            const body = new FormData();
            body.append('action', 'send_to_pharmacy');
            body.append('patient_id', String(cfg.patientId));
            body.append('consultation_id', String(cfg.consultationId));

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 45000);

            let res;
            try {
                res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, {
                    method: 'POST',
                    body,
                    signal: controller.signal,
                    credentials: 'same-origin',
                });
            } finally {
                clearTimeout(timeout);
            }

            const data = await parseJsonResponse(res, 'Send to Pharmacy');
            if (!data.success) {
                throw new Error(data.message || 'Send failed');
            }

            setStatus('Sent to Pharmacy');
            await Swal.fire({
                icon: 'success',
                title: 'Sent to Pharmacy',
                text: data.message || 'Patient is now Waiting for Pharmacy.',
                confirmButtonColor: '#1a6fc4',
            });
            window.location.href = `${cfg.baseUrl}/workflow/queue.php`;
        } catch (err) {
            setStatus('');
            const message = err.name === 'AbortError'
                ? 'Send timed out. Check the database connection and PHP/server logs.'
                : (err.message || 'Send failed.');
            console.error('Send to Pharmacy failed:', err);
            await Swal.fire('Send to Pharmacy failed', message, 'error');
        } finally {
            resetSubmitButton();
        }
    }

    btnSave?.addEventListener('click', async () => {
        await saveDraft({ showSuccess: true, showErrors: true, rethrow: false });
    });
    btnSubmit?.addEventListener('click', () => {
        sendToPharmacy().catch((err) => {
            console.error(err);
            resetSubmitButton();
        });
    });

    loadConsultation();
});
