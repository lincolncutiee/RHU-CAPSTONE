(function () {
    const cfg = window.ADMIN_FORM_INLINE;
    if (!cfg?.apiUrl) return;

    let cachedPassword = '';

    document.addEventListener('DOMContentLoaded', () => {
        document.body.addEventListener('focusin', (e) => {
            const el = e.target.closest('.admin-tpl-editable');
            if (!el) return;
            el.classList.add('is-editing');
            el.dataset.originalText = el.textContent.trim();
        });

        document.body.addEventListener('focusout', (e) => {
            const el = e.target.closest('.admin-tpl-editable');
            if (!el) return;
            el.classList.remove('is-editing');
            const key = el.getAttribute('data-template-key');
            const next = el.textContent.trim();
            const prev = el.dataset.originalText ?? '';
            if (!key || next === prev) return;
            saveLabel(el, key, next, prev);
        });

        document.body.addEventListener('keydown', (e) => {
            const el = e.target.closest('.admin-tpl-editable');
            if (!el) return;
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                el.blur();
            }
            if (e.key === 'Escape') {
                el.textContent = el.dataset.originalText ?? el.textContent;
                el.blur();
            }
        });
    });

    async function saveLabel(el, key, text, rollback) {
        el.classList.add('is-saving');
        try {
            const password = await getPassword();
            if (!password) {
                el.textContent = rollback;
                return;
            }
            const body = new FormData();
            body.append('action', 'save_string');
            body.append('template_key', key);
            body.append('custom_text', text);
            body.append('admin_password', password);
            const res = await fetch(cfg.apiUrl, { method: 'POST', body, credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) {
                el.textContent = rollback;
                alert(data.message || 'Save failed.');
                return;
            }
            el.dataset.originalText = text;
            showToast('Saved');
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'admin-template-saved', key }, '*');
            }
        } catch (err) {
            el.textContent = rollback;
            alert('Could not save. Check your connection.');
        } finally {
            el.classList.remove('is-saving');
        }
    }

    function getPassword() {
        if (cachedPassword) return Promise.resolve(cachedPassword);
        const entered = window.prompt('Enter your admin password to save this label:');
        if (!entered) return Promise.resolve('');
        cachedPassword = entered;
        return Promise.resolve(entered);
    }

    function showToast(msg) {
        let t = document.getElementById('adminTplToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'adminTplToast';
            t.className = 'admin-tpl-toast';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.classList.add('is-visible');
        clearTimeout(t._hideTimer);
        t._hideTimer = setTimeout(() => t.classList.remove('is-visible'), 1600);
    }
})();
