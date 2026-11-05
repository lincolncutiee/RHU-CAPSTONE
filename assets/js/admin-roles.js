(function () {
    const cfg = window.ADMIN_ROLES_CFG;
    if (!cfg?.apiUrl) return;

    const ROLE_ICONS = {
        records_office: 'bi-folder2-open',
        doctor: 'bi-heart-pulse',
        pharmacy: 'bi-capsule',
        it_department: 'bi-pc-display',
        receptionist: 'bi-person-lines-fill',
        system_administrator: 'bi-shield-lock',
    };

    const GROUP_ICONS = {
        patient: 'bi-person-vcard',
        clinical: 'bi-clipboard2-pulse',
        pharmacy: 'bi-capsule',
        it: 'bi-printer',
        workflow: 'bi-diagram-3',
        surveillance: 'bi-map',
        admin: 'bi-gear',
    };

    let state = {
        roles: {},
        groups: {},
        roleVisibleGroups: {},
        matrix: {},
        defaults: {},
        lockedRole: 'system_administrator',
        selectedRole: null,
        dirty: false,
        filter: '',
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('adminRolesSave')?.addEventListener('click', saveRole);
        document.getElementById('adminRolesSavebarBtn')?.addEventListener('click', saveRole);
        document.getElementById('adminRolesReset')?.addEventListener('click', resetToDefaults);
        document.getElementById('adminRolesBack')?.addEventListener('click', () => {
            state.selectedRole = null;
            state.dirty = false;
            setDirtyUi();
            showEditorPanel(false);
            renderRoleList();
        });
        document.getElementById('adminRolesFilter')?.addEventListener('input', (e) => {
            state.filter = e.target.value.trim().toLowerCase();
            applyFilter();
        });
        loadMatrix();
    });

    function visibleGroupKeys(slug) {
        const configured = state.roleVisibleGroups[slug];
        if (Array.isArray(configured) && configured.length > 0) {
            return configured.filter((k) => state.groups[k]);
        }
        return Object.keys(state.groups);
    }

    function permissionKeysForRole(slug) {
        const keys = [];
        visibleGroupKeys(slug).forEach((groupKey) => {
            Object.keys(state.groups[groupKey]?.permissions || {}).forEach((k) => keys.push(k));
        });
        return keys;
    }

    function showEditorPanel(show) {
        document.getElementById('adminRolesPlaceholder')?.classList.toggle('d-none', show);
        document.getElementById('adminRolesEditorWrap')?.classList.toggle('d-none', !show);
        document.getElementById('adminRolesLayout')?.classList.toggle('admin-roles-layout--editing', show);
        if (!show) {
            document.getElementById('adminRolesSave').disabled = true;
            document.getElementById('adminRolesReset').disabled = true;
        }
    }

    async function loadMatrix() {
        showLoading(true);
        try {
            const res = await fetch(cfg.apiUrl, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) {
                await alertError(data.message || 'Failed to load permissions.');
                return;
            }
            state.roles = data.roles || {};
            state.groups = data.groups || {};
            state.roleVisibleGroups = data.role_visible_groups || {};
            state.matrix = data.matrix || {};
            state.defaults = data.defaults || {};
            state.lockedRole = data.locked_role || state.lockedRole;
            renderRoleList();
            showEditorPanel(false);
        } catch (e) {
            await alertError('Could not load role permissions.');
        } finally {
            showLoading(false);
        }
    }

    function showLoading(on) {
        document.getElementById('adminRolesLoading')?.classList.toggle('d-none', !on);
        if (on) {
            document.getElementById('adminRolesEditorWrap')?.classList.add('d-none');
            document.getElementById('adminRolesPlaceholder')?.classList.add('d-none');
        }
    }

    function enabledCount(slug) {
        const perms = state.matrix[slug] || {};
        return permissionKeysForRole(slug).filter((k) => perms[k]).length;
    }

    function totalCountForRole(slug) {
        return permissionKeysForRole(slug).length;
    }

    function renderRoleList() {
        const list = document.getElementById('adminRolesList');
        if (!list) return;
        list.innerHTML = Object.entries(state.roles).map(([slug, label]) => {
            const active = slug === state.selectedRole ? ' is-active' : '';
            const icon = ROLE_ICONS[slug] || 'bi-person-badge';
            const on = enabledCount(slug);
            const total = totalCountForRole(slug) || on;
            return `<button type="button" class="admin-roles-role-btn${active}" data-role="${escapeHtml(slug)}" role="tab" aria-selected="${slug === state.selectedRole ? 'true' : 'false'}" aria-controls="adminRolesEditor">
                <span class="admin-roles-role-icon"><i class="bi ${icon}" aria-hidden="true"></i></span>
                <span class="admin-roles-role-text">${escapeHtml(label)}</span>
                <span class="admin-roles-role-count">${on}/${total}</span>
            </button>`;
        }).join('');
        list.querySelectorAll('[data-role]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const slug = btn.getAttribute('data-role');
                if (slug === state.selectedRole) return;
                if (state.dirty && !confirm('You have unsaved changes. Switch role anyway?')) {
                    return;
                }
                selectRole(slug, true);
            });
        });
    }

    function selectRole(slug, resetDirty) {
        state.selectedRole = slug;
        if (resetDirty) state.dirty = false;
        setDirtyUi();
        showEditorPanel(true);
        document.getElementById('adminRolesSave').disabled = false;
        document.getElementById('adminRolesReset').disabled = false;
        const filterEl = document.getElementById('adminRolesFilter');
        if (filterEl) filterEl.value = '';
        state.filter = '';
        renderRoleList();
        renderEditor(slug);
        document.getElementById('adminRolesEditor')?.scrollTo(0, 0);
    }

    function setDirtyUi() {
        const dirty = state.dirty;
        document.getElementById('adminRolesDirtyBadge')?.classList.toggle('d-none', !dirty);
        document.getElementById('adminRolesSavebar')?.classList.toggle('d-none', !dirty);
        const saveBtn = document.getElementById('adminRolesSave');
        if (saveBtn) {
            saveBtn.classList.toggle('btn-primary', dirty);
            saveBtn.classList.toggle('btn-outline-primary', !dirty);
        }
    }

    function updateProgress(slug) {
        const on = enabledCount(slug);
        const total = totalCountForRole(slug) || 1;
        const pct = Math.round((on / total) * 100);
        const bar = document.getElementById('adminRolesProgressBar');
        if (bar) bar.style.width = `${pct}%`;
        const meta = document.getElementById('adminRolesEditorMeta');
        if (meta) {
            meta.textContent = `${on} of ${total} permissions enabled for this role (${pct}%)`;
        }
    }

    function renderEditor(slug) {
        const editor = document.getElementById('adminRolesEditor');
        if (!editor) return;
        const perms = state.matrix[slug] || {};
        const isLocked = slug === state.lockedRole;
        const title = document.getElementById('adminRolesEditorTitle');
        if (title) title.textContent = state.roles[slug] || slug;

        updateProgress(slug);

        let html = '';
        if (isLocked) {
            html += `<div class="admin-roles-callout admin-roles-callout--info">
                <i class="bi bi-info-circle-fill flex-shrink-0" aria-hidden="true"></i>
                <span><strong>System Administrator</strong> always keeps full <em>Administration</em> permissions (users, roles, templates, audit).</span>
            </div>`;
        }

        visibleGroupKeys(slug).forEach((groupKey) => {
            const group = state.groups[groupKey];
            if (!group) return;
            const gIcon = GROUP_ICONS[groupKey] || 'bi-grid';
            const permEntries = Object.entries(group.permissions || {});
            html += `<section class="admin-roles-group" data-group="${escapeHtml(groupKey)}">
                <div class="admin-roles-group-head">
                    <h4 class="admin-roles-group-title"><i class="bi ${gIcon}" aria-hidden="true"></i>${escapeHtml(group.label || groupKey)}</h4>
                    <div class="admin-roles-group-actions">
                        <button type="button" data-group-all="${escapeHtml(groupKey)}">All</button>
                        <button type="button" data-group-none="${escapeHtml(groupKey)}">None</button>
                    </div>
                </div>
                <div class="admin-roles-group-body">`;
            permEntries.forEach(([key, label]) => {
                const checked = perms[key] ? ' checked' : '';
                const on = perms[key] ? ' is-on' : '';
                const adminLock = isLocked && key.startsWith('admin.') ? ' disabled is-disabled' : '';
                html += `<label class="admin-roles-perm${on}${adminLock}" data-perm-label="${escapeHtml(label.toLowerCase())}">
                    <input class="form-check-input admin-role-perm" type="checkbox" data-key="${escapeHtml(key)}"${checked}${adminLock ? ' disabled' : ''}>
                    <span class="admin-roles-perm-label">${escapeHtml(label)}</span>
                </label>`;
            });
            html += '</div></section>';
        });

        html += '<p class="admin-roles-empty-filter d-none" id="adminRolesEmptyFilter">No permissions match your filter.</p>';
        editor.innerHTML = html;

        editor.querySelectorAll('.admin-role-perm').forEach((input) => {
            input.addEventListener('change', () => onPermChange(slug, input));
        });
        editor.querySelectorAll('.admin-roles-perm:not(.is-disabled)').forEach((label) => {
            label.addEventListener('click', (e) => {
                if (e.target.tagName === 'INPUT') return;
                const input = label.querySelector('input');
                if (!input || input.disabled) return;
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        editor.querySelectorAll('[data-group-all]').forEach((btn) => {
            btn.addEventListener('click', () => setGroup(slug, btn.getAttribute('data-group-all'), true, isLocked));
        });
        editor.querySelectorAll('[data-group-none]').forEach((btn) => {
            btn.addEventListener('click', () => setGroup(slug, btn.getAttribute('data-group-none'), false, isLocked));
        });
        applyFilter();
    }

    function onPermChange(slug, input) {
        state.dirty = true;
        setDirtyUi();
        const key = input.getAttribute('data-key');
        if (!state.matrix[slug]) state.matrix[slug] = {};
        state.matrix[slug][key] = input.checked ? 1 : 0;
        const label = input.closest('.admin-roles-perm');
        label?.classList.toggle('is-on', input.checked);
        updateProgress(slug);
        renderRoleList();
    }

    function setGroup(slug, groupKey, on, isLocked) {
        if (!visibleGroupKeys(slug).includes(groupKey)) return;
        const group = state.groups[groupKey];
        if (!group) return;
        Object.keys(group.permissions || {}).forEach((key) => {
            if (isLocked && key.startsWith('admin.')) return;
            if (!state.matrix[slug]) state.matrix[slug] = {};
            state.matrix[slug][key] = on ? 1 : 0;
        });
        state.dirty = true;
        setDirtyUi();
        renderEditor(slug);
        renderRoleList();
    }

    function applyFilter() {
        const q = state.filter;
        let visible = 0;
        document.querySelectorAll('.admin-roles-group').forEach((section) => {
            let groupVisible = 0;
            section.querySelectorAll('.admin-roles-perm').forEach((row) => {
                const text = row.getAttribute('data-perm-label') || '';
                const show = !q || text.includes(q);
                row.classList.toggle('is-hidden', !show);
                if (show) groupVisible += 1;
            });
            section.classList.toggle('is-filter-hidden', groupVisible === 0);
            visible += groupVisible;
        });
        document.getElementById('adminRolesEmptyFilter')?.classList.toggle('d-none', visible > 0 || !q);
    }

    function resetToDefaults() {
        const slug = state.selectedRole;
        if (!slug) return;
        const defs = state.defaults[slug];
        if (!defs) return;
        if (!confirm(`Reset "${state.roles[slug] || slug}" to factory default permissions?`)) return;
        state.matrix[slug] = { ...defs };
        state.dirty = true;
        setDirtyUi();
        renderEditor(slug);
        renderRoleList();
    }

    async function saveRole() {
        const slug = state.selectedRole;
        if (!slug) return;
        const password = await requireAdminPassword('Save role permissions');
        if (!password) return;

        const permissions = { ...(state.matrix[slug] || {}) };
        try {
            const res = await fetch(cfg.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_slug: slug, permissions, password }),
            });
            const data = await res.json();
            if (!data.success) {
                await alertError(data.message || 'Save failed.');
                return;
            }
            state.matrix = data.matrix || state.matrix;
            state.dirty = false;
            setDirtyUi();
            await Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: 'Staff with this role should log in again to refresh permissions.',
                timer: 2800,
                showConfirmButton: false,
            });
            renderEditor(slug);
            renderRoleList();
        } catch (e) {
            await alertError('Save failed.');
        }
    }

    async function requireAdminPassword(actionLabel) {
        const usersApi = (window.ADMIN_USERS_CFG && window.ADMIN_USERS_CFG.apiUrl)
            || cfg.apiUrl.replace('admin_roles.php', 'admin_users.php');
        const { value: password, isConfirmed } = await Swal.fire({
            title: actionLabel || 'Confirm action',
            input: 'password',
            inputLabel: 'Enter your admin password',
            showCancelButton: true,
            confirmButtonText: 'Continue',
            confirmButtonColor: '#1a6fc4',
        });
        if (!isConfirmed || !password) return null;
        const body = new FormData();
        body.append('action', 'verify_password');
        body.append('admin_password', password);
        const res = await fetch(usersApi, { method: 'POST', body, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) {
            await alertError(data.message || 'Incorrect password.');
            return null;
        }
        return password;
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    async function alertError(msg) {
        await Swal.fire({ icon: 'error', title: 'Error', text: msg });
    }
})();
