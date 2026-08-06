/**
 * Receptionist My Queue — desk actions, filters, Call Next.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('receptionQueuePage');
    if (!page) return;

    const apiUrl = page.dataset.api;
    const tbody = document.getElementById('receptionQueueBody');
    const btnCallNext = document.getElementById('btnCallNext');
    const btnRefresh = document.getElementById('btnRefreshQueue');
    const btnClear = document.getElementById('btnClearFilters');

    const filterPatientId = document.getElementById('filterPatientId');
    const filterName = document.getElementById('filterName');
    const filterStatus = document.getElementById('filterStatus');
    const filterBarangay = document.getElementById('filterBarangay');
    const filterDate = document.getElementById('filterDate');

    let highlightId = null;
    let debounceTimer = null;

    function filters() {
        return {
            date: filterDate?.value || page.dataset.today || '',
            status: filterStatus?.value || '',
            barangay: filterBarangay?.value || '',
            patient_id: filterPatientId?.value?.trim() || '',
            name: filterName?.value?.trim() || '',
        };
    }

    function queryString(obj) {
        const params = new URLSearchParams({ action: 'list' });
        Object.entries(obj).forEach(([k, v]) => {
            if (v) params.set(k, v);
        });
        return params.toString();
    }

    async function postAction(action, queueId = null) {
        const body = new FormData();
        body.append('action', action);
        if (queueId != null) body.append('queue_id', String(queueId));

        const res = await fetch(apiUrl, { method: 'POST', body });
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Request failed');
        }
        return data;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function formatTime(iso) {
        if (!iso) return '—';
        const d = new Date(String(iso).replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return iso;
        return d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    }

    function updateStats(stats = {}) {
        page.querySelectorAll('[data-stat]').forEach((el) => {
            const key = el.dataset.stat;
            el.textContent = String(stats[key] ?? (key === 'avg_wait_label' ? '—' : 0));
        });
    }

    function actionButtons(item) {
        const status = item.desk_status;
        let html = `<a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(item.view_url)}" title="View patient record (read-only)">
            <i class="bi bi-eye"></i> View
        </a>`;

        if (status === 'waiting' || status === 'skipped') {
            html += `<button type="button" class="btn btn-sm btn-primary" data-action="call">Call Patient</button>`;
            html += `<button type="button" class="btn btn-sm btn-outline-primary" data-action="serve">Mark Serving</button>`;
        }
        if (status === 'serving') {
            html += `<button type="button" class="btn btn-sm btn-success" data-action="complete">Complete</button>`;
        }
        if (status === 'waiting' || status === 'serving') {
            html += `<button type="button" class="btn btn-sm btn-outline-warning" data-action="skip">Skip</button>`;
            html += `<button type="button" class="btn btn-sm btn-outline-danger" data-action="cancel">Cancel</button>`;
        }
        return html;
    }

    function renderRows(items) {
        if (!tbody) return;
        if (!items.length) {
            tbody.innerHTML = `<tr class="rq-empty-row"><td colspan="10" class="text-center text-muted py-5">
                No patients in the reception queue for this date.
            </td></tr>`;
            return;
        }

        tbody.innerHTML = items.map((item) => {
            const serving = item.desk_status === 'serving';
            const highlighted = highlightId != null && Number(item.queue_id) === Number(highlightId);
            const rowClass = [serving || highlighted ? 'rq-row-serving' : '', highlighted ? 'is-highlighted' : '']
                .filter(Boolean)
                .join(' ');

            return `<tr data-queue-id="${item.queue_id}" data-desk-status="${escapeHtml(item.desk_status)}" class="${rowClass}">
                <td class="fw-bold text-primary rq-qnum">${escapeHtml(item.queue_number || '—')}</td>
                <td class="small">${escapeHtml(item.patient_id)}</td>
                <td class="fw-semibold">${escapeHtml(item.patient_name)}</td>
                <td>${escapeHtml(item.sex)}</td>
                <td>${escapeHtml(item.age)}</td>
                <td>${escapeHtml(item.barangay)}</td>
                <td class="small">${escapeHtml(formatTime(item.arrival_at))}</td>
                <td><span class="rq-badge rq-badge-${escapeHtml(item.desk_status)}">${escapeHtml(item.desk_status_label)}</span></td>
                <td class="small">${escapeHtml(item.assigned_doctor || '—')}</td>
                <td class="text-end rq-row-actions">${actionButtons(item)}</td>
            </tr>`;
        }).join('');

        if (highlightId) {
            const row = tbody.querySelector(`tr[data-queue-id="${highlightId}"]`);
            row?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    async function refresh() {
        try {
            const res = await fetch(`${apiUrl}?${queryString(filters())}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load queue');
            renderRows(data.items || []);
            updateStats(data.stats || data.counts || {});
        } catch (err) {
            Swal.fire('Error', err.message || 'Could not refresh queue.', 'error');
        }
    }

    async function run(action, queueId = null, confirmCfg = null) {
        try {
            if (confirmCfg) {
                const result = await Swal.fire({
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#1a6fc4',
                    ...confirmCfg,
                });
                if (!result.isConfirmed) return;
            }

            const data = await postAction(action, queueId);
            if (data.highlight_id) {
                highlightId = data.highlight_id;
            } else if (data.entry?.id) {
                highlightId = data.entry.id;
            }
            await refresh();
            Swal.fire({
                icon: 'success',
                title: 'Updated',
                text: data.message || 'Queue updated.',
                timer: 1600,
                showConfirmButton: false,
            });
        } catch (err) {
            Swal.fire('Error', err.message || 'Action failed.', 'error');
        }
    }

    btnCallNext?.addEventListener('click', () => {
        run('call_next', null, {
            title: 'Call next patient?',
            text: 'The first Waiting patient will be marked Serving and sent to the Doctor queue.',
            confirmButtonText: 'Call Next Patient',
        });
    });

    btnRefresh?.addEventListener('click', () => refresh());

    btnClear?.addEventListener('click', () => {
        if (filterPatientId) filterPatientId.value = '';
        if (filterName) filterName.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterBarangay) filterBarangay.value = '';
        if (filterDate) filterDate.value = page.dataset.today || '';
        highlightId = null;
        refresh();
    });

    const scheduleRefresh = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, 280);
    };

    [filterPatientId, filterName].forEach((el) => el?.addEventListener('input', scheduleRefresh));
    [filterStatus, filterBarangay, filterDate].forEach((el) => el?.addEventListener('change', refresh));

    tbody?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const row = btn.closest('tr[data-queue-id]');
        if (!row) return;
        const queueId = parseInt(row.dataset.queueId, 10);
        const action = btn.dataset.action;

        const confirms = {
            call: {
                title: 'Call this patient?',
                text: 'Status will change to Serving and the patient will proceed to the Doctor.',
                confirmButtonText: 'Call Patient',
            },
            serve: {
                title: 'Mark as Serving?',
                text: 'Patient will be marked Serving and sent to the Doctor queue if still at reception.',
                confirmButtonText: 'Mark Serving',
            },
            complete: {
                title: 'Mark Completed?',
                text: 'Use after the patient has finished consultation.',
                confirmButtonText: 'Complete',
                confirmButtonColor: '#16a34a',
            },
            skip: {
                title: 'Skip this patient?',
                text: 'You can call them again later from Skipped.',
                confirmButtonText: 'Skip',
            },
            cancel: {
                title: 'Cancel this queue entry?',
                text: 'Cancelled patients leave the active queue.',
                confirmButtonText: 'Cancel Entry',
                confirmButtonColor: '#dc2626',
            },
        };

        run(action, queueId, confirms[action] || null);
    });

    refresh();
});
