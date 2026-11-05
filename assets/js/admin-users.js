(function () {
    const cfg = window.ADMIN_USERS_CFG;
    if (!cfg?.apiUrl) return;

    let meta = { roles: {}, groups: {}, levels: {} };
    let users = [];
    let modal;
    let resetModal;

    document.addEventListener('DOMContentLoaded', () => {
        modal = document.getElementById('adminUserModal')
            ? bootstrap.Modal.getOrCreateInstance(document.getElementById('adminUserModal'))
            : null;
        resetModal = document.getElementById('adminResetModal')
            ? bootstrap.Modal.getOrCreateInstance(document.getElementById('adminResetModal'))
            : null;

        document.getElementById('adminBtnNewUser')?.addEventListener('click', async () => {
            const pw = await requireAdminPassword('Create new user');
            if (!pw) return;
            await openUserModal(null, pw);
        });
        document.getElementById('adminSaveUser')?.addEventListener('click', saveUser);
        document.getElementById('adminResetSubmit')?.addEventListener('click', submitReset);
        const debouncedLoadUsers = debounce(loadUsers, 300);
        const searchEl = document.getElementById('adminUserSearch');
        const searchClear = document.getElementById('adminUserSearchClear');
        searchEl?.addEventListener('input', () => {
            searchClear?.classList.toggle('d-none', !searchEl.value.trim());
            debouncedLoadUsers();
        });
        searchClear?.addEventListener('click', () => {
            if (!searchEl) return;
            searchEl.value = '';
            searchClear.classList.add('d-none');
            searchEl.focus();
            loadUsers();
        });

        loadUsers();
    });

    async function loadUsers() {
        const q = document.getElementById('adminUserSearch')?.value?.trim() || '';
        const url = q ? `${cfg.apiUrl}?search=${encodeURIComponent(q)}` : cfg.apiUrl;
        const tbody = document.getElementById('adminUsersTbody');
        if (tbody && !tbody.querySelector('.admin-users-loading-row')) {
            tbody.innerHTML = `<tr class="admin-users-loading-row"><td colspan="5"><div class="admin-users-loading"><div class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></div><span>Loading users…</span></div></td></tr>`;
        }
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load');
            meta.roles = data.roles || {};
            meta.groups = data.groups || {};
            meta.levels = data.levels || {};
            users = data.users || [];
            renderTable();
            fillRoleSelect();
            buildPrivilegeGrid({});
        } catch (e) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="text-danger">${escapeHtml(e.message)}</td></tr>`;
        }
    }

    function renderTable() {
        const tbody = document.getElementById('adminUsersTbody');
        const countEl = document.getElementById('adminUserCount');
        if (!tbody) return;

        const q = document.getElementById('adminUserSearch')?.value?.trim() || '';
        if (countEl) {
            const n = users.length;
            countEl.textContent = n === 1 ? '1 user' : `${n} users`;
            countEl.classList.toggle('text-bg-primary', n > 0 && !q);
            countEl.classList.toggle('text-bg-light', n === 0 || q);
        }

        if (!users.length) {
            tbody.innerHTML = `<tr><td colspan="5"><div class="admin-users-empty">
                <i class="bi bi-people admin-users-empty-icon" aria-hidden="true"></i>
                <p class="admin-users-empty-title">${q ? 'No matches' : 'No users yet'}</p>
                <p class="admin-users-empty-hint text-muted">${q ? 'Try a different search term.' : 'Create the first staff account with New user.'}</p>
            </div></td></tr>`;
            return;
        }
        tbody.innerHTML = users.map((u) => {
            const active = Number(u.is_active) === 1;
            const name = u.full_name || u.username || 'User';
            const initials = userInitials(name);
            const roleSlug = u.role_slug || '';
            const roleLabel = u.role_name || roleSlug;
            const adminIcon = Number(u.can_access_admin)
                ? '<span class="admin-access-icon" title="Admin panel access"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i><span class="visually-hidden">Admin panel access</span></span>'
                : '';
            return `<tr class="${active ? '' : 'admin-users-row-inactive'}">
                <td>
                    <div class="admin-user-name-cell">
                        <span class="admin-user-avatar" aria-hidden="true">${escapeHtml(initials)}</span>
                        <span class="admin-user-name">${escapeHtml(name)}</span>
                    </div>
                </td>
                <td><span class="admin-user-username">${escapeHtml(u.username)}</span></td>
                <td>${roleBadge(roleSlug, roleLabel)}</td>
                <td>${active ? '<span class="badge admin-status-badge admin-status-badge--active">Active</span>' : '<span class="badge admin-status-badge admin-status-badge--inactive">Inactive</span>'}</td>
                <td class="admin-users-actions-col">
                    <div class="admin-user-actions">
                        ${adminIcon}
                        <div class="btn-group admin-action-group" role="group" aria-label="User actions">
                            <button type="button" class="btn btn-sm btn-outline-primary admin-action-btn" data-edit="${u.id}" title="Edit user" aria-label="Edit user"><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-warning admin-action-btn" data-reset="${u.id}" title="Reset password" aria-label="Reset password"><i class="bi bi-key" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-${active ? 'danger' : 'success'} admin-action-btn" data-toggle="${u.id}" data-active="${active ? '0' : '1'}" title="${active ? 'Deactivate user' : 'Activate user'}" aria-label="${active ? 'Deactivate user' : 'Activate user'}"><i class="bi bi-${active ? 'person-x' : 'person-check'}" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('[data-edit]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const pw = await requireAdminPassword('Edit user');
                if (!pw) return;
                await openUserModal(Number(btn.getAttribute('data-edit')), pw);
            });
        });
        tbody.querySelectorAll('[data-reset]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const pw = await requireAdminPassword('Reset password');
                if (!pw) return;
                openResetModal(Number(btn.getAttribute('data-reset')), pw);
            });
        });
        tbody.querySelectorAll('[data-toggle]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                await toggleActive(Number(btn.getAttribute('data-toggle')), btn.getAttribute('data-active') === '1');
            });
        });
    }

    function fillRoleSelect() {
        const sel = document.getElementById('adminRoleSlug');
        if (!sel) return;
        sel.innerHTML = Object.entries(meta.roles).map(([slug, label]) =>
            `<option value="${escapeHtml(slug)}">${escapeHtml(label)}</option>`).join('');
    }

    function buildPrivilegeGrid(privileges) {
        const grid = document.getElementById('adminPrivilegeGrid');
        if (!grid) return;
        const levels = Object.keys(meta.levels).length ? meta.levels : {
            inherit: 'Default (from role)',
            none: 'No access',
            view: 'View only',
            edit: 'Can edit',
        };
        grid.innerHTML = Object.entries(meta.groups).map(([group, label]) => {
            const val = privileges[group] || 'inherit';
            const opts = Object.entries(levels).map(([k, t]) =>
                `<option value="${escapeHtml(k)}"${k === val ? ' selected' : ''}>${escapeHtml(t)}</option>`).join('');
            return `<div class="admin-privilege-row">
                <label>${escapeHtml(label)}</label>
                <select class="form-select form-select-sm admin-priv-select" data-group="${escapeHtml(group)}">${opts}</select>
            </div>`;
        }).join('');
    }

    function collectPrivileges() {
        const out = {};
        document.querySelectorAll('.admin-priv-select').forEach((sel) => {
            out[sel.getAttribute('data-group')] = sel.value;
        });
        return out;
    }

    async function openUserModal(userId, verifiedPassword = '') {
        const isNew = !userId;
        document.getElementById('adminUserModalTitle').textContent = isNew ? 'New user' : 'Edit user';
        document.getElementById('adminUserId').value = isNew ? '' : String(userId);
        document.getElementById('adminUsername').readOnly = !isNew;
        document.getElementById('adminNewPasswordWrap').classList.toggle('d-none', !isNew);
        document.getElementById('adminConfirmPassword').value = verifiedPassword;

        if (isNew) {
            document.getElementById('adminUserForm').reset();
            document.getElementById('adminUsername').readOnly = false;
            buildPrivilegeGrid({});
            modal?.show();
            return;
        }

        const res = await fetch(`${cfg.apiUrl}?action=get&id=${userId}`);
        const data = await res.json();
        if (!data.success) {
            await alertError(data.message);
            return;
        }
        const u = data.user;
        document.getElementById('adminFullName').value = u.full_name || '';
        document.getElementById('adminUsername').value = u.username || '';
        document.getElementById('adminEmail').value = u.email || '';
        document.getElementById('adminRoleSlug').value = u.role_slug || '';
        document.getElementById('adminCanAccessAdmin').checked = Number(u.can_access_admin) === 1;
        buildPrivilegeGrid(u.privileges || {});
        modal?.show();
    }

    async function saveUser() {
        const id = document.getElementById('adminUserId').value;
        const isNew = !id;
        const adminPassword = document.getElementById('adminConfirmPassword').value;
        const body = new FormData();
        body.append('action', isNew ? 'create' : 'update');
        body.append('admin_password', adminPassword);
        body.append('full_name', document.getElementById('adminFullName').value.trim());
        body.append('email', document.getElementById('adminEmail').value.trim());
        body.append('role_slug', document.getElementById('adminRoleSlug').value);
        body.append('can_access_admin', document.getElementById('adminCanAccessAdmin').checked ? '1' : '0');
        const priv = collectPrivileges();
        Object.entries(priv).forEach(([g, v]) => body.append(`privileges[${g}]`, v));

        if (isNew) {
            body.append('username', document.getElementById('adminUsername').value.trim());
            body.append('password', document.getElementById('adminNewUserPassword').value);
        } else {
            body.append('user_id', id);
        }

        const res = await fetch(cfg.apiUrl, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) {
            await alertError(data.message);
            return;
        }
        modal?.hide();
        await Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false });
        loadUsers();
    }

    function openResetModal(userId, verifiedPassword = '') {
        document.getElementById('adminResetUserId').value = String(userId);
        document.getElementById('adminResetNewPassword').value = '';
        document.getElementById('adminResetConfirmPassword').value = verifiedPassword;
        resetModal?.show();
    }

    async function submitReset() {
        const body = new FormData();
        body.append('action', 'reset_password');
        body.append('user_id', document.getElementById('adminResetUserId').value);
        body.append('new_password', document.getElementById('adminResetNewPassword').value);
        body.append('admin_password', document.getElementById('adminResetConfirmPassword').value);
        const res = await fetch(cfg.apiUrl, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) {
            await alertError(data.message);
            return;
        }
        resetModal?.hide();
        await Swal.fire({ icon: 'success', title: 'Password reset', timer: 1200, showConfirmButton: false });
    }

    async function toggleActive(userId, activate) {
        const confirm = await Swal.fire({
            title: activate ? 'Reactivate user?' : 'Deactivate user?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: activate ? '#16a34a' : '#dc2626',
        });
        if (!confirm.isConfirmed) return;

        const password = await requireAdminPassword(activate ? 'Reactivate user' : 'Deactivate user');
        if (!password) return;

        const body = new FormData();
        body.append('action', 'set_active');
        body.append('user_id', String(userId));
        body.append('is_active', activate ? '1' : '0');
        body.append('admin_password', password);
        const res = await fetch(cfg.apiUrl, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) {
            await alertError(data.message);
            return;
        }
        loadUsers();
    }

    async function requireAdminPassword(actionLabel) {
        const { value: password, isConfirmed } = await Swal.fire({
            title: actionLabel || 'Confirm action',
            input: 'password',
            inputLabel: 'Enter your admin password',
            inputPlaceholder: 'Password',
            showCancelButton: true,
            confirmButtonText: 'Continue',
            confirmButtonColor: '#1a6fc4',
            inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'current-password' },
        });
        if (!isConfirmed || !password) {
            return null;
        }
        const body = new FormData();
        body.append('action', 'verify_password');
        body.append('admin_password', password);
        try {
            const res = await fetch(cfg.apiUrl, { method: 'POST', body, credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) {
                await alertError(data.message || 'Incorrect password.');
                return null;
            }
            return password;
        } catch (e) {
            await alertError('Could not verify password. Check your connection.');
            return null;
        }
    }

    function alertError(msg) {
        return Swal.fire({ icon: 'error', title: 'Error', text: msg || 'Request failed', confirmButtonColor: '#1a6fc4' });
    }

    function userInitials(name) {
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function roleBadge(slug, label) {
        const safe = escapeHtml(label);
        const mod = roleBadgeMod(slug);
        return `<span class="badge admin-role-badge admin-role-badge--${mod}">${safe}</span>`;
    }

    function roleBadgeMod(slug) {
        const s = String(slug).toLowerCase();
        if (s.includes('system') || s.includes('admin')) return 'admin';
        if (s.includes('doctor')) return 'doctor';
        if (s.includes('record')) return 'records';
        if (s.includes('it')) return 'it';
        if (s.includes('pharm')) return 'pharmacy';
        if (s.includes('recept')) return 'reception';
        return 'default';
    }

    function escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t ?? '';
        return d.innerHTML;
    }

    function debounce(fn, ms) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }
})();
