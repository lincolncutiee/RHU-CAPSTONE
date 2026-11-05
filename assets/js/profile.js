(function () {
    const cfg = window.PROFILE_CFG;
    if (!cfg?.apiUrl) return;

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('profileAccountForm')?.addEventListener('submit', saveProfile);
        document.getElementById('profilePasswordForm')?.addEventListener('submit', changePassword);
        loadProfile();
    });

    async function loadProfile() {
        try {
            const res = await fetch(cfg.apiUrl, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load profile');
            applyProfile(data.user);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'Could not load profile.' });
        }
    }

    function applyProfile(u) {
        if (!u) return;
        document.getElementById('profileUsername').value = u.username || '';
        document.getElementById('profileRole').value = u.role_name || u.role_slug || '';
        document.getElementById('profileFullName').value = u.full_name || '';
        document.getElementById('profileEmail').value = u.email || '';

        const avatar = document.getElementById('profileAvatar');
        if (avatar) avatar.textContent = initials(u.full_name || u.username);

        const meta = document.getElementById('profileMeta');
        if (meta) {
            const parts = [];
            if (Number(u.can_access_admin) === 1) {
                parts.push('<span class="badge text-bg-primary">Admin panel access</span>');
            }
            parts.push(Number(u.is_active) === 1 ? 'Account active' : 'Account inactive');
            meta.innerHTML = parts.join(' · ');
        }
    }

    async function saveProfile(e) {
        e.preventDefault();
        const body = new FormData();
        body.append('action', 'update_profile');
        body.append('full_name', document.getElementById('profileFullName').value.trim());
        body.append('email', document.getElementById('profileEmail').value.trim());

        const res = await fetch(cfg.apiUrl, { method: 'POST', body, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Save failed' });
            return;
        }
        applyProfile(data.user);
        Swal.fire({ icon: 'success', title: 'Profile saved', timer: 1400, showConfirmButton: false });
        document.querySelectorAll('.topnav-user-btn .user-name').forEach((el) => {
            if (data.user?.full_name) el.textContent = data.user.full_name;
        });
    }

    async function changePassword(e) {
        e.preventDefault();
        const current = document.getElementById('profileCurrentPassword').value;
        const next = document.getElementById('profileNewPassword').value;
        const confirm = document.getElementById('profileConfirmPassword').value;
        if (next !== confirm) {
            Swal.fire({ icon: 'warning', title: 'Passwords do not match', text: 'Confirm password must match the new password.' });
            return;
        }

        const body = new FormData();
        body.append('action', 'change_password');
        body.append('current_password', current);
        body.append('new_password', next);

        const res = await fetch(cfg.apiUrl, { method: 'POST', body, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not update password' });
            return;
        }

        document.getElementById('profilePasswordForm').reset();
        Swal.fire({ icon: 'success', title: 'Password updated', timer: 1400, showConfirmButton: false });
    }

    function initials(name) {
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
})();
