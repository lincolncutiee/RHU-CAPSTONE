/**
 * Patient Folder — new consultation redirect, timeline expand/collapse.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.PATIENT_FOLDER;
    if (!cfg) return;

    document.getElementById('btnNewConsultation')?.addEventListener('click', openNewConsultationEncoding);

    document.querySelectorAll('.emr-timeline-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const panel = document.getElementById(targetId);
            if (!panel) return;

            const isOpen = !panel.hidden;
            panel.hidden = isOpen;
            btn.setAttribute('aria-expanded', String(!isOpen));
            btn.classList.toggle('is-open', !isOpen);
        });
    });

    async function openNewConsultationEncoding() {
        if (!cfg.canCreateConsultation) return;

        const result = await Swal.fire({
            title: 'Start New Consultation?',
            html: '<p>You will open <strong>Patient Encoding</strong> for a new visit.</p><p class="mb-2">Patient demographics are filled from the existing profile and locked.</p><p class="text-muted small mb-0">Consultation fields (vitals, complaint, diagnosis, medication, etc.) start blank. The visit is created when you save.</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Continue to Encoding',
            confirmButtonColor: '#1a6fc4',
        });

        if (!result.isConfirmed) return;

        window.location.href = `${cfg.baseUrl}/forms/doh-form.php?id=${cfg.patientId}&new_consultation=1&page=itr`;
    }
});
