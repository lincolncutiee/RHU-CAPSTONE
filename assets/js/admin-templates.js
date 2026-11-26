(function () {
    const cfg = window.ADMIN_TPL_CFG;
    if (!cfg?.apiUrl) return;

    const FORM_ABBR = {
        pcsf: 'PCSF',
        itr: 'ITR',
        enrollment: 'Enrollment',
        history: 'History',
        personal: 'Personal',
        ekas: 'EKAS',
        epress: 'EPRESS',
    };

    let catalogData = { forms: [], grouped: {} };

    document.addEventListener('DOMContentLoaded', () => {
        loadAll();
        document.getElementById('adminBrandingForm')?.addEventListener('submit', saveBranding);
        document.getElementById('adminLogoFile')?.addEventListener('change', uploadLogo);
        ['adminSiteTitle', 'adminSiteSubtitle', 'adminFacilityName'].forEach((id) => {
            document.getElementById(id)?.addEventListener('input', updateBrandingPreview);
        });
        document.querySelectorAll('input[name="form_display_style"]').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.admin-form-style-choice').forEach((el) => {
                    const radio = el.querySelector('input[type="radio"]');
                    el.classList.toggle('is-active', Boolean(radio?.checked));
                });
            });
        });
        document.getElementById('adminReloadStrings')?.addEventListener('click', loadStrings);
        document.getElementById('adminStringSearch')?.addEventListener('input', debounce(loadStrings, 350));
        document.getElementById('adminStringPrefix')?.addEventListener('change', loadStrings);
        document.getElementById('adminFormsCatalog')?.addEventListener('click', (e) => {
            const card = e.target.closest('.admin-form-card');
            if (card) {
                openFormEditor(card.getAttribute('data-form-id'));
                return;
            }
            const btn = e.target.closest('.admin-save-string');
            if (btn) saveString(btn.closest('.admin-string-item'));
        });
        document.getElementById('adminStringsList')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.admin-save-string');
            if (btn) saveString(btn.closest('.admin-string-item'));
        });
        document.getElementById('adminFormZoomClose')?.addEventListener('click', closeFormEditor);
        document.getElementById('adminFormZoomBackdrop')?.addEventListener('click', closeFormEditor);
        document.getElementById('adminFormZoomBody')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.admin-save-string');
            if (btn) saveString(btn.closest('.admin-string-item'));
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeFormEditor();
        });
        window.addEventListener('message', (e) => {
            if (e.data?.type !== 'admin-form-preview-height') return;
            const frame = document.getElementById('adminFormZoomFrame');
            if (!frame) return;
            const h = Math.max(720, Math.min(Number(e.data.height) || 0, 2400));
            if (h > 0) frame.style.height = h + 'px';
        });
    });

    async function loadAll() {
        await loadBrandingAndMeta();
        await loadStrings();
    }

    async function loadBrandingAndMeta() {
        const res = await fetch(cfg.apiUrl);
        const data = await res.json();
        if (!data.success) return;
        applyBranding(data.branding);
        syncPrefixOptions(data.prefixes || []);
    }

    function syncPrefixOptions(prefixes) {
        const prefixSel = document.getElementById('adminStringPrefix');
        if (!prefixSel || !prefixes.length) return;
        const cur = prefixSel.value;
        prefixSel.innerHTML = '';
        prefixes.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = p.id ?? '';
            opt.textContent = p.label;
            prefixSel.appendChild(opt);
        });
        if ([...prefixSel.options].some((o) => o.value === cur)) {
            prefixSel.value = cur;
        }
    }

    function logoUrlFromPath(path) {
        const p = path || '/assets/img/rhu-magallanes-logo.png';
        return p.startsWith('http') ? p : `${cfg.baseUrl}/${p.replace(/^\//, '')}`;
    }

    function applyBranding(b) {
        if (!b) return;
        document.getElementById('adminSiteTitle').value = b.site_title || '';
        document.getElementById('adminSiteSubtitle').value = b.site_subtitle || '';
        document.getElementById('adminFacilityName').value = b.facility_name || '';
        document.getElementById('adminLogoPath').value = b.logo_path || '';
        const style = (b.form_display_style || 'official') === 'clean' ? 'clean' : 'official';
        const official = document.getElementById('adminFormStyleOfficial');
        const clean = document.getElementById('adminFormStyleClean');
        if (official) official.checked = style === 'official';
        if (clean) clean.checked = style === 'clean';
        document.querySelectorAll('.admin-form-style-choice').forEach((el) => {
            const input = el.querySelector('input[type="radio"]');
            el.classList.toggle('is-active', Boolean(input?.checked));
        });
        const preview = document.getElementById('adminLogoPreview');
        if (preview) {
            preview.src = logoUrlFromPath(b.logo_path);
        }
        updateBrandingPreview();
    }

    function updateBrandingPreview() {
        const title = document.getElementById('adminSiteTitle')?.value?.trim() || 'RHU Magallanes';
        const sub = document.getElementById('adminSiteSubtitle')?.value?.trim() || 'Agusan del Norte';
        const titleEl = document.getElementById('adminPreviewTitle');
        const subEl = document.getElementById('adminPreviewSubtitle');
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = sub;
        const logoMain = document.getElementById('adminLogoPreview');
        const logoMini = document.getElementById('adminPreviewLogoImg');
        if (logoMain && logoMini && logoMain.src) {
            logoMini.src = logoMain.src;
        }
    }

    async function loadStrings() {
        const search = document.getElementById('adminStringSearch')?.value?.trim() || '';
        const prefix = document.getElementById('adminStringPrefix')?.value || '';
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (prefix) params.set('prefix', prefix);
        const res = await fetch(`${cfg.apiUrl}?${params}`);
        const data = await res.json();
        if (!data.success) return;
        applyBranding(data.branding);
        syncPrefixOptions(data.prefixes || []);
        catalogData = {
            forms: data.forms || [],
            grouped: data.strings_grouped || {},
        };
        const filtering = Boolean(search || prefix);
        if (filtering) {
            renderFormsCatalog([], {}, true);
            renderStrings(data.strings || []);
        } else {
            renderFormsCatalog(catalogData.forms, catalogData.grouped, false);
        }
    }

    function stringItemHtml(row) {
        const custom = row.custom_text ?? '';
        const def = row.default_text ?? '';
        const friendly = friendlyLabel(row.template_key, def);
        return `<div class="admin-string-item" data-key="${escapeAttr(row.template_key)}">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <div>
                        <div class="fw-semibold">${escapeHtml(friendly)}</div>
                        <code class="small">${escapeHtml(row.template_key)}</code>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary admin-save-string">Save</button>
                </div>
                <div class="small text-muted mb-1">Default: ${escapeHtml(def)}</div>
                <textarea class="form-control admin-string-value" rows="2" placeholder="Leave blank to use default">${escapeHtml(custom)}</textarea>
                <input type="password" class="form-control form-control-sm mt-2 admin-string-password" placeholder="Your password to save" autocomplete="current-password">
            </div>`;
    }

    function friendlyLabel(key, defaultText) {
        if (defaultText) return defaultText;
        const m = key.match(/\.([a-z0-9_]+)\.label$/i);
        if (m) return m[1].replace(/_/g, ' ');
        const p = key.match(/^page\.[^.]+\.(.+)$/);
        if (p) return p[1].replace(/[._]/g, ' ');
        return key;
    }

    function paperFormsOnly(forms) {
        return forms.filter((f) => !f.is_system);
    }

    function formCardHtml(form, grouped) {
        const rows = grouped[form.id] || [];
        const abbr = FORM_ABBR[form.id] || form.short_title || form.title;
        return `<button type="button" class="admin-form-card" data-form-id="${escapeAttr(form.id)}" aria-label="Edit ${escapeAttr(form.title)}">
            <span class="admin-form-card-sheet">
                <span class="admin-form-card-abbr">${escapeHtml(abbr)}</span>
                <span class="admin-form-card-lines" aria-hidden="true"><span></span><span></span><span></span></span>
            </span>
            <span class="admin-form-card-body">
                <span class="admin-form-card-seq">Form ${form.sequence}</span>
                <span class="admin-form-card-title">${escapeHtml(form.short_title || form.title)}</span>
                <span class="admin-form-card-meta">${escapeHtml(form.paper_type_label || '')} · ${rows.length} labels</span>
            </span>
            <span class="admin-form-card-edit"><i class="bi bi-pencil-square"></i> Edit</span>
        </button>`;
    }

    function renderFormsCatalog(forms, grouped, hidden) {
        const catalog = document.getElementById('adminFormsCatalog');
        const list = document.getElementById('adminStringsList');
        if (!catalog || !list) return;
        if (hidden || !forms.length) {
            catalog.classList.add('d-none');
            catalog.innerHTML = '';
            list.classList.remove('d-none');
            return;
        }
        catalog.classList.remove('d-none');
        list.classList.add('d-none');

        const paperForms = paperFormsOnly(forms);
        let html = '<div class="admin-forms-grid">';
        html += paperForms.map((f) => formCardHtml(f, grouped)).join('');
        html += '</div>';

        const globalRows = grouped._global || [];
        if (globalRows.length) {
            html += `<p class="text-muted small mt-4 mb-2">Shared labels (multiple forms)</p>
                <div class="admin-forms-grid">
                    <button type="button" class="admin-form-card admin-form-card-shared" data-form-id="_global">
                        <span class="admin-form-card-sheet">
                            <span class="admin-form-card-abbr">Shared</span>
                            <span class="admin-form-card-lines" aria-hidden="true"><span></span><span></span><span></span></span>
                        </span>
                        <span class="admin-form-card-body">
                            <span class="admin-form-card-title">Common field labels</span>
                            <span class="admin-form-card-meta">${globalRows.length} labels</span>
                        </span>
                        <span class="admin-form-card-edit"><i class="bi bi-pencil-square"></i> Edit</span>
                    </button>
                </div>`;
        }

        catalog.innerHTML = html;
    }

    function openFormEditor(formId) {
        if (!formId) return;
        const zoom = document.getElementById('adminFormZoom');
        const body = document.getElementById('adminFormZoomBody');
        const titleEl = document.getElementById('adminFormZoomTitle');
        const subEl = document.getElementById('adminFormZoomSubtitle');
        const kicker = document.getElementById('adminFormZoomKicker');
        if (!zoom || !body) return;

        const previewWrap = document.getElementById('adminFormZoomPreviewWrap');
        const frame = document.getElementById('adminFormZoomFrame');
        const labelsDrawer = document.getElementById('adminFormZoomLabelsDrawer');

        let title = 'Shared labels';
        let subtitle = 'Labels used across multiple forms';
        if (formId !== '_global') {
            const form = catalogData.forms.find((f) => f.id === formId);
            if (!form) return;
            title = form.title;
            subtitle = form.subtitle || form.paper_type_label || '';
            if (kicker) kicker.textContent = `Form ${form.sequence} · Full form preview`;
            if (previewWrap) previewWrap.classList.remove('d-none');
            if (frame) {
                frame.src = `${cfg.baseUrl}/admin/form-preview.php?form=${encodeURIComponent(formId)}`;
            }
            if (labelsDrawer) labelsDrawer.open = false;
        } else if (kicker) {
            kicker.textContent = 'Shared · Edit labels';
            if (previewWrap) previewWrap.classList.add('d-none');
            if (frame) frame.removeAttribute('src');
            if (labelsDrawer) labelsDrawer.open = true;
        }

        const rows = catalogData.grouped[formId] || [];
        body.innerHTML = rows.length
            ? rows.map(stringItemHtml).join('')
            : '<p class="text-muted mb-0">Walay editable labels para niining form.</p>';

        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = subtitle;

        zoom.classList.add('is-open');
        zoom.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-form-zoom-open');
        requestAnimationFrame(() => zoom.classList.add('is-visible'));
        document.getElementById('adminFormZoomClose')?.focus();
    }

    function closeFormEditor() {
        const zoom = document.getElementById('adminFormZoom');
        if (!zoom || !zoom.classList.contains('is-open')) return;
        zoom.classList.remove('is-visible');
        document.body.classList.remove('admin-form-zoom-open');
        window.setTimeout(() => {
            zoom.classList.remove('is-open');
            zoom.setAttribute('aria-hidden', 'true');
            const body = document.getElementById('adminFormZoomBody');
            if (body) body.innerHTML = '';
            const frame = document.getElementById('adminFormZoomFrame');
            if (frame) frame.removeAttribute('src');
        }, 280);
    }

    function renderStrings(rows) {
        const list = document.getElementById('adminStringsList');
        if (!list) return;
        if (!rows.length) {
            list.innerHTML = '<p class="text-muted">No template keys match your filter.</p>';
            return;
        }
        list.innerHTML = rows.map(stringItemHtml).join('');
    }

    async function saveString(item) {
        if (!item) return;
        const key = item.getAttribute('data-key');
        const custom = item.querySelector('.admin-string-value')?.value ?? '';
        const password = item.querySelector('.admin-string-password')?.value ?? '';
        const body = new FormData();
        body.append('action', 'save_string');
        body.append('template_key', key);
        body.append('custom_text', custom);
        body.append('admin_password', password);
        const res = await fetch(cfg.apiUrl, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Save failed' });
            return;
        }
        item.querySelector('.admin-string-password').value = '';
        Swal.fire({ icon: 'success', title: 'Saved', timer: 900, showConfirmButton: false });
    }

    async function saveBranding(e) {
        e.preventDefault();
        const body = new FormData();
        body.append('action', 'save_branding');
        body.append('site_title', document.getElementById('adminSiteTitle').value);
        body.append('site_subtitle', document.getElementById('adminSiteSubtitle').value);
        body.append('facility_name', document.getElementById('adminFacilityName').value);
        body.append('logo_path', document.getElementById('adminLogoPath').value);
        const styleInput = document.querySelector('input[name="form_display_style"]:checked');
        body.append('form_display_style', styleInput?.value || 'official');
        body.append('admin_password', document.getElementById('adminBrandingPassword').value);
        const res = await fetch(cfg.apiUrl, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            return;
        }
        applyBranding(data.branding);
        document.getElementById('adminBrandingPassword').value = '';
        Swal.fire({ icon: 'success', title: 'Branding saved', timer: 1200, showConfirmButton: false });
    }

    async function uploadLogo() {
        const input = document.getElementById('adminLogoFile');
        if (!input?.files?.length) return;
        const { value: password } = await Swal.fire({
            title: 'Your password',
            input: 'password',
            showCancelButton: true,
        });
        if (!password) {
            input.value = '';
            return;
        }
        const body = new FormData();
        body.append('action', 'upload_logo');
        body.append('logo', input.files[0]);
        body.append('admin_password', password);
        const res = await fetch(cfg.apiUrl, { method: 'POST', body });
        const data = await res.json();
        input.value = '';
        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Upload failed', text: data.message });
            return;
        }
        document.getElementById('adminLogoPath').value = data.logo_path;
        document.getElementById('adminLogoPreview').src = data.logo_url;
        updateBrandingPreview();
        Swal.fire({ icon: 'success', title: 'Logo uploaded', timer: 1200, showConfirmButton: false });
    }

    function escapeHtml(t) {
        const d = document.createElement('div');
        d.textContent = t ?? '';
        return d.innerHTML;
    }

    function escapeAttr(t) {
        return escapeHtml(t).replace(/"/g, '&quot;');
    }

    function debounce(fn, ms) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }
})();
