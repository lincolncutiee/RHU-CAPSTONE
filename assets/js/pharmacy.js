/**
 * Pharmacy dispense actions.
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('pharmacyDispense');
    if (!root) return;

    const patientId = Number(root.dataset.patientId || 0);
    const consultationId = Number(root.dataset.consultationId || 0);
    const remarksEl = document.getElementById('pharmacyRemarks');

    async function runAction(action, confirmTitle, confirmText) {
        const result = await Swal.fire({
            title: confirmTitle,
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1a6fc4',
            confirmButtonText: 'Confirm',
        });
        if (!result.isConfirmed) return;

        try {
            const res = await fetch(`${BASE_URL}/api/pharmacy_action.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action,
                    patient_id: patientId,
                    consultation_id: consultationId,
                    remarks: remarksEl?.value?.trim() || '',
                }),
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Action failed');

            await Swal.fire({
                icon: 'success',
                title: 'Updated',
                text: data.message || 'Pharmacy status updated.',
                timer: 1600,
                showConfirmButton: false,
            });

            if (action === 'mark_dispensed') {
                window.location.href = `${BASE_URL}/dashboard.php`;
            } else {
                window.location.reload();
            }
        } catch (err) {
            Swal.fire('Error', err.message || 'Unable to update pharmacy status.', 'error');
        }
    }

    document.getElementById('btnStartDispensing')?.addEventListener('click', () => {
        runAction(
            'start_dispensing',
            'Start Dispensing?',
            'Mark this prescription as currently being dispensed.'
        );
    });

    document.getElementById('btnMarkDispensed')?.addEventListener('click', () => {
        runAction(
            'mark_dispensed',
            'Mark as Dispensed?',
            'This will record you as the dispenser with the current date and time.'
        );
    });
});
