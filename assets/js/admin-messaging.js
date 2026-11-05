document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.ADMIN_MESSAGING;
    const form = document.getElementById('adminMessagingForm');
    const statusEl = document.getElementById('msgSaveStatus');
    const btnRun = document.getElementById('btnRunReminderQueue');

    function setStatus(text) {
        if (statusEl) statusEl.textContent = text || '';
    }

    async function loadSettings() {
        const res = await fetch(`${cfg.baseUrl}/api/admin_messaging.php?action=settings`);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Load failed');
        const s = data.settings || {};
        const el = (id) => document.getElementById(id);
        el('msgEnabled').checked = String(s.is_enabled) === '1';
        el('msgDaysBefore').value = s.days_before ?? 1;
        el('msgFacilityPhone').value = s.facility_phone || '';
        el('msgSmsMode').value = s.sms_mode || 'log_only';
        el('msgSmsSender').value = s.sms_sender_name || '';
        el('msgSmsApiUrl').value = s.sms_api_url || '';
        el('msgSmsTemplate').value = s.sms_template || '';
        el('msgEmailEnabled').checked = String(s.email_enabled) === '1';
        el('msgSmtpHost').value = s.smtp_host || '';
        el('msgSmtpPort').value = s.smtp_port || 587;
        el('msgSmtpUser').value = s.smtp_user || '';
        el('msgFromEmail').value = s.smtp_from_email || '';
        el('msgFromName').value = s.smtp_from_name || '';
        el('msgEmailSubject').value = s.email_subject_template || '';
        el('msgEmailBody').value = s.email_body_template || '';
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setStatus('Saving…');
        const g = (id) => document.getElementById(id);
        const payload = {
            is_enabled: g('msgEnabled').checked ? 1 : 0,
            days_before: g('msgDaysBefore').value,
            facility_phone: g('msgFacilityPhone').value.trim(),
            sms_mode: g('msgSmsMode').value,
            sms_api_url: g('msgSmsApiUrl').value.trim(),
            sms_api_key: g('msgSmsApiKey').value,
            sms_sender_name: g('msgSmsSender').value.trim(),
            email_enabled: g('msgEmailEnabled').checked ? 1 : 0,
            smtp_host: g('msgSmtpHost').value.trim(),
            smtp_port: g('msgSmtpPort').value,
            smtp_user: g('msgSmtpUser').value.trim(),
            smtp_pass: g('msgSmtpPass').value,
            smtp_from_email: g('msgFromEmail').value.trim(),
            smtp_from_name: g('msgFromName').value.trim(),
            sms_template: g('msgSmsTemplate').value,
            email_subject_template: g('msgEmailSubject').value,
            email_body_template: g('msgEmailBody').value,
        };
        try {
            const res = await fetch(`${cfg.baseUrl}/api/admin_messaging.php?action=save`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Save failed');
            g('msgSmsApiKey').value = '';
            g('msgSmtpPass').value = '';
            setStatus('Saved.');
            await Swal.fire({ icon: 'success', title: 'Saved', timer: 1400, showConfirmButton: false });
        } catch (err) {
            setStatus('');
            Swal.fire('Error', err.message || 'Could not save.', 'error');
        }
    });

    btnRun?.addEventListener('click', async () => {
        const ok = await Swal.fire({
            title: 'Send pending reminders?',
            text: 'This processes all due SMS/email messages now.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Run now',
        });
        if (!ok.isConfirmed) return;
        btnRun.disabled = true;
        try {
            const res = await fetch(`${cfg.baseUrl}/api/admin_messaging.php?action=run_queue`, { method: 'POST' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Run failed');
            const st = data.stats || {};
            Swal.fire(
                'Queue processed',
                `Processed: ${st.processed || 0}, sent: ${st.sent || 0}, failed: ${st.failed || 0}, skipped: ${st.skipped || 0}`,
                'success'
            );
        } catch (err) {
            Swal.fire('Error', err.message || 'Run failed.', 'error');
        } finally {
            btnRun.disabled = false;
        }
    });

    loadSettings().catch((err) => Swal.fire('Error', err.message || 'Could not load settings.', 'error'));
});
