/**
 * IT Review Queue — compact worklist filters + pagination.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('itReviewQueuePage');
    if (!page) return;

    const tbody = document.getElementById('itReviewTableBody');
    const footer = document.getElementById('itqTableFooter');
    const showing = document.getElementById('itqShowing');
    const pager = document.getElementById('itqPager');

    const search = document.getElementById('itqSearch');
    const status = document.getElementById('itqStatus');
    const submitter = document.getElementById('itqSubmitter');
    const dateFrom = document.getElementById('itqDateFrom');
    const dateTo = document.getElementById('itqDateTo');
    const resetBtn = document.getElementById('itqResetFilters');

    const pageSize = 12;
    let currentPage = 1;
    let debounce = null;

    function allRows() {
        return Array.from(tbody?.querySelectorAll('tr.wl-row[data-patient-id]') || []);
    }

    function matches(row) {
        const q = (search?.value || '').trim().toLowerCase();
        const st = status?.value || '';
        const by = submitter?.value || '';
        const from = dateFrom?.value || '';
        const to = dateTo?.value || '';

        const id = (row.dataset.patientId || '').toLowerCase();
        const name = (row.dataset.patientName || '').toLowerCase();
        const badge = row.dataset.badge || '';
        const who = row.dataset.submitter || '';
        const submitted = row.dataset.submitted || '';

        if (q && !id.includes(q) && !name.includes(q)) return false;
        if (st && badge !== st) return false;
        if (by && who !== by) return false;
        if (from && submitted && submitted < from) return false;
        if (to && submitted && submitted > to) return false;
        if ((from || to) && !submitted) return false;
        return true;
    }

    function render() {
        const rows = allRows();
        if (!rows.length) {
            if (footer) footer.hidden = true;
            return;
        }

        const filtered = rows.filter(matches);
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / pageSize) || 1);
        if (currentPage > pages) currentPage = pages;

        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        rows.forEach((row) => row.classList.add('is-hidden'));
        filtered.forEach((row, index) => {
            if (index >= start && index < end) row.classList.remove('is-hidden');
        });

        let empty = tbody.querySelector('.wl-filter-empty');
        if (total === 0) {
            if (!empty) {
                empty = document.createElement('tr');
                empty.className = 'wl-filter-empty';
                empty.innerHTML = `<td colspan="8"><div class="wl-empty">No matching consultations.</div></td>`;
                tbody.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }

        if (footer) footer.hidden = false;
        if (showing) {
            const fromN = total === 0 ? 0 : start + 1;
            const toN = Math.min(end, total);
            showing.textContent = `Showing ${fromN} to ${toN} of ${total} entries`;
        }

        if (pager) {
            pager.innerHTML = '';
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.textContent = 'Previous';
            prev.disabled = currentPage <= 1 || total === 0;
            prev.addEventListener('click', () => {
                currentPage -= 1;
                render();
            });
            pager.appendChild(prev);

            for (let i = 1; i <= pages; i += 1) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = String(i);
                if (i === currentPage) btn.classList.add('is-active');
                btn.addEventListener('click', () => {
                    currentPage = i;
                    render();
                });
                pager.appendChild(btn);
            }

            const next = document.createElement('button');
            next.type = 'button';
            next.textContent = 'Next';
            next.disabled = currentPage >= pages || total === 0;
            next.addEventListener('click', () => {
                currentPage += 1;
                render();
            });
            pager.appendChild(next);
        }
    }

    function schedule() {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            currentPage = 1;
            render();
        }, 160);
    }

    search?.addEventListener('input', schedule);
    status?.addEventListener('change', () => { currentPage = 1; render(); });
    submitter?.addEventListener('change', () => { currentPage = 1; render(); });
    dateFrom?.addEventListener('change', () => { currentPage = 1; render(); });
    dateTo?.addEventListener('change', () => { currentPage = 1; render(); });

    resetBtn?.addEventListener('click', () => {
        if (search) search.value = '';
        if (status) status.value = '';
        if (submitter) submitter.value = '';
        if (dateFrom) dateFrom.value = '';
        if (dateTo) dateTo.value = '';
        currentPage = 1;
        render();
    });

    render();
});
