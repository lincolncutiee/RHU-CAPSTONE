/**
 * IT Print module — patient rows with Consultation Date dropdown.
 * Print/Reprint always uses the currently selected consultation for that patient.
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.getElementById('printQueuePage');
    const cfg = window.RHU_PRINT || {};
    if (!page) return;

    const tbody = document.getElementById('pqTableBody');
    const showing = document.getElementById('pqShowing');
    const search = document.getElementById('pqSearch');
    const status = document.getElementById('pqStatus');
    const resetBtn = document.getElementById('pqReset');

    let activeJob = null;
    /** @type {string[]} remaining document ids */
    let printQueue = [];
    /** @type {string[]} full selection for this run (for progress labels) */
    let printBatch = [];
    let printingBusy = false;

    function rows() {
        return tbody ? Array.from(tbody.querySelectorAll('tr.pq-row')) : [];
    }

    function applyConsultationToRow(row, option) {
        if (!row || !option) return;

        const cid = Number(option.value || 0);
        const dateIso = option.dataset.date || '';
        const dateDisplay = option.dataset.dateDisplay || '';
        const printStatus = option.dataset.status || 'printed';
        const formsCount = Number(option.dataset.formsCount || cfg.documentCount || 0);
        const isReady = printStatus === 'ready';

        row.dataset.consultationId = String(cid);
        row.dataset.date = dateIso;
        row.dataset.dateDisplay = dateDisplay.toLowerCase();
        row.dataset.status = printStatus;
        row.dataset.formsCount = String(formsCount);

        const formsEl = row.querySelector('.pq-forms-count');
        if (formsEl) {
            formsEl.textContent = `${formsCount} Documents`;
        }

        const badge = row.querySelector('.pq-badge');
        if (badge) {
            badge.className = `pq-badge pq-badge--${isReady ? 'ready' : 'printed'}`;
            badge.textContent = isReady ? 'Ready to Print' : 'Already Printed';
        }

        const btn = row.querySelector('.pq-btn-print');
        if (btn) {
            btn.dataset.consultationId = String(cid);
            btn.dataset.status = printStatus;
            const label = btn.querySelector('.pq-btn-label');
            if (label) {
                label.textContent = 'Print';
            } else {
                btn.innerHTML = '<i class="bi bi-printer"></i> <span class="pq-btn-label">Print</span>';
            }
        }
    }

    function matches(row) {
        const q = (search?.value || '').trim().toLowerCase();
        const st = status?.value || '';

        const id = (row.dataset.patientId || '').toLowerCase();
        const name = (row.dataset.patientName || '').toLowerCase();
        const allDates = (row.dataset.allDates || '').toLowerCase();
        const allStatuses = (row.dataset.allStatuses || '').split(',').filter(Boolean);
        const rowSt = row.dataset.status || '';

        if (q) {
            const hit = id.includes(q)
                || name.includes(q)
                || allDates.includes(q)
                || (row.dataset.dateDisplay || '').includes(q)
                || (row.dataset.date || '').includes(q);
            if (!hit) return false;
        }

        // Status filter: show patient if selected visit matches, OR any visit matches
        if (st) {
            if (rowSt === st) return true;
            return allStatuses.includes(st);
        }
        return true;
    }

    function render() {
        if (!tbody) return;
        let visible = 0;
        rows().forEach((row) => {
            const ok = matches(row);
            row.hidden = !ok;
            if (ok) visible += 1;

            // If filtering by status and current selection doesn't match, switch to a matching visit
            const st = status?.value || '';
            if (ok && st && row.dataset.status !== st) {
                const select = row.querySelector('.pq-consult-select');
                const matchOpt = select
                    ? Array.from(select.options).find((o) => o.dataset.status === st)
                    : null;
                if (matchOpt) {
                    select.value = matchOpt.value;
                    applyConsultationToRow(row, matchOpt);
                }
            }
        });

        let empty = tbody.querySelector('.pq-filter-empty');
        if (visible === 0) {
            if (!empty) {
                empty = document.createElement('tr');
                empty.className = 'pq-filter-empty';
                empty.innerHTML = '<td colspan="6"><div class="pq-empty pq-empty-sm">No matching patients.</div></td>';
                tbody.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }

        if (showing) {
            showing.textContent = `Showing ${visible} of ${rows().length}`;
        }
    }

    search?.addEventListener('input', render);
    status?.addEventListener('change', render);
    resetBtn?.addEventListener('click', () => {
        if (search) search.value = '';
        if (status) status.value = '';
        // Restore each row to its latest consultation
        rows().forEach((row) => {
            const select = row.querySelector('.pq-consult-select');
            if (!select) return;
            const latest = Array.from(select.options).find((o) => o.dataset.latest === '1')
                || select.options[0];
            if (latest) {
                select.value = latest.value;
                applyConsultationToRow(row, latest);
            }
        });
        render();
    });

    // Consultation Date dropdown — keeps selection active until changed
    tbody?.addEventListener('change', (e) => {
        const select = e.target.closest('.pq-consult-select');
        if (!select) return;
        const row = select.closest('tr.pq-row');
        const option = select.options[select.selectedIndex];
        applyConsultationToRow(row, option);
        render();
    });

    page.querySelectorAll('.pq-btn-print').forEach((btn) => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr.pq-row');
            const select = row?.querySelector('.pq-consult-select');
            const option = select?.options[select.selectedIndex];
            // Sync from dropdown so Print always uses the selected visit
            if (row && option) {
                applyConsultationToRow(row, option);
            }

            const consultDate = option?.dataset.dateDisplay || row?.dataset.dateDisplay || '';
            activeJob = {
                patientDbId: Number(btn.dataset.patientDbId || row?.dataset.patientDbId || 0),
                patientId: btn.dataset.patientId || row?.dataset.patientId || '',
                patientName: btn.dataset.patientName || row?.dataset.patientName || '',
                consultationId: Number(btn.dataset.consultationId || row?.dataset.consultationId || 0),
                status: btn.dataset.status || row?.dataset.status || 'ready',
                consultationDate: consultDate,
            };

            const meta = document.getElementById('pqModalPatient');
            if (meta) {
                meta.textContent = `${activeJob.patientId} — ${activeJob.patientName}`
                    + (activeJob.consultationDate ? ` · ${activeJob.consultationDate}` : '')
                    + (activeJob.consultationId ? ` · Visit #${activeJob.consultationId}` : '');
            }
            const modalTitle = document.getElementById('pqPrintModalLabel');
            if (modalTitle) {
                modalTitle.textContent = 'Print Forms';
            }
            document.querySelectorAll('#pqFormChecklist input[type="checkbox"]').forEach((cb) => {
                cb.checked = true;
            });
            const modalEl = document.getElementById('pqPrintModal');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });

    document.getElementById('pqSelectAll')?.addEventListener('click', () => {
        document.querySelectorAll('#pqFormChecklist input[type="checkbox"]').forEach((cb) => {
            cb.checked = true;
        });
    });
    document.getElementById('pqSelectNone')?.addEventListener('click', () => {
        document.querySelectorAll('#pqFormChecklist input[type="checkbox"]').forEach((cb) => {
            cb.checked = false;
        });
    });

    /** @type {Window|null} */
    let activePrintPopup = null;
    let printBatchStarting = false;

    document.getElementById('pqPrintSelected')?.addEventListener('click', async () => {
        if (printBatchStarting || printingBusy) return;
        if (!activeJob?.patientDbId) return;
        printBatchStarting = true;
        try {
        const selected = Array.from(document.querySelectorAll('#pqFormChecklist input[type="checkbox"]:checked'))
            .map((cb) => cb.value)
            .filter(Boolean);
        if (!selected.length) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No documents selected',
                    text: 'Choose at least one document to print.',
                    confirmButtonColor: '#1a6fc4',
                });
            } else {
                alert('Choose at least one document to print.');
            }
            return;
        }

        const selectModal = document.getElementById('pqPrintModal');
        if (selectModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(selectModal).hide();
        }

        // Official order; separate jobs for Legal / A4 / Landscape
        const order = (cfg.documents || []).map((d) => d.id);
        printBatch = order.filter((id) => selected.includes(id));
        printQueue = printBatch.slice();

        if (printBatch.length > 1 && window.Swal) {
            await Swal.fire({
                icon: 'info',
                title: `${printBatch.length} print jobs`,
                html: '<p class="mb-2 text-start">Browsers can only use <strong>one paper size</strong> per print dialog.</p>'
                    + '<ol class="text-start small mb-2">'
                    + printBatch.map((id, i) => {
                        const meta = (cfg.documents || []).find((d) => d.id === id);
                        return `<li><strong>${i + 1}.</strong> ${escapeHtml(meta?.label || id)}`
                            + (meta?.hint ? ` <span class="text-muted">(${escapeHtml(meta.hint)})</span>` : '')
                            + '</li>';
                    }).join('')
                    + '</ol>'
                    + '<p class="mb-0 small text-muted">Job 1 opens next. After each print, click <strong>Print Next</strong>.</p>',
                confirmButtonText: 'Start printing',
                confirmButtonColor: '#1a6fc4',
            });
        }

        await printNextDocument();
        } finally {
            printBatchStarting = false;
        }
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function documentPreviewUrl(documentId) {
        let url = `${cfg.baseUrl}/it/preview.php?id=${activeJob.patientDbId}`
            + `&document=${encodeURIComponent(documentId)}`;
        if (activeJob.consultationId) {
            url += `&consultation=${activeJob.consultationId}`;
        }
        return url;
    }

    function updateProgressUi(currentDocId) {
        const progress = document.getElementById('pqPrintProgress');
        const nextBtn = document.getElementById('pqPrintNext');
        const markBtn = document.getElementById('pqMarkPrinted');
        const doneCount = printBatch.length - printQueue.length;
        const total = printBatch.length;
        const meta = (cfg.documents || []).find((d) => d.id === currentDocId);
        const nextMeta = printQueue.length
            ? (cfg.documents || []).find((d) => d.id === printQueue[0])
            : null;

        if (progress) {
            progress.textContent = total > 1
                ? `Job ${doneCount} of ${total}`
                    + (meta?.hint ? ` · ${meta.hint}` : '')
                    + (nextMeta ? ` · Next: ${nextMeta.label}` : ' · Last job')
                : (meta?.hint || '');
        }

        if (nextBtn) {
            const hasMore = printQueue.length > 0;
            nextBtn.classList.toggle('d-none', !hasMore);
            nextBtn.disabled = printingBusy;
            nextBtn.innerHTML = hasMore
                ? `<i class="bi bi-arrow-right-circle"></i> Print Next (${printQueue.length} left)`
                : '<i class="bi bi-arrow-right-circle"></i> Print Next';
        }

        if (markBtn) {
            const showMark = !printQueue.length && activeJob?.status === 'ready';
            markBtn.classList.toggle('d-none', !showMark);
        }
    }

    async function printNextDocument() {
        if (printingBusy) return;
        if (!printQueue.length) {
            updateProgressUi('');
            return;
        }

        printingBusy = true;
        const docId = printQueue.shift();
        const docs = cfg.documents || [];
        const meta = docs.find((d) => d.id === docId);
        const label = meta?.label || docId;
        const hint = meta?.hint || '';

        const frame = document.getElementById('pqPreviewFrame');
        const title = document.getElementById('pqPreviewModalLabel');
        if (title) {
            title.textContent = `${label}${hint ? ` · ${hint}` : ''}`;
        }

        const url = documentPreviewUrl(docId);
        updateProgressUi(docId);

        const modalEl = document.getElementById('pqPreviewModal');
        const popupOpened = openPrintPopup(url, docId);
        const doPrintBtn = document.getElementById('pqDoPrint');

        if (popupOpened) {
            // Print dialog opens from parent via printjob=1 popup — skip iframe (avoids double dialog).
            if (frame) {
                frame.src = 'about:blank';
            }
            if (doPrintBtn) {
                doPrintBtn.classList.add('d-none');
            }
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        } else {
            // Popup blocked — single path: iframe preview + print from iframe
            if (doPrintBtn) {
                doPrintBtn.classList.remove('d-none');
            }
            if (frame) {
                frame.src = 'about:blank';
                await sleep(120);
                await loadFrame(frame, url);
            }
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            setTimeout(() => {
                try {
                    frame?.contentWindow?.focus();
                    frame?.contentWindow?.print();
                } catch (e) { /* ignore */ }
            }, 600);
        }

        printingBusy = false;
        updateProgressUi(docId);
    }

    /** @returns {boolean} true when the print popup opened */
    function openPrintPopup(url, docId) {
        const printUrl = url + (url.includes('?') ? '&' : '?') + 'printjob=1';

        if (activePrintPopup && !activePrintPopup.closed) {
            try {
                activePrintPopup.location.href = printUrl;
                activePrintPopup.focus();
                triggerPopupPrintOnce(activePrintPopup);
                return true;
            } catch (e) {
                activePrintPopup = null;
            }
        }

        const name = `rhu_print_${docId}_${Date.now()}`;
        let popup = null;
        try {
            // Do not use noopener — parent must call print() exactly once after load.
            popup = window.open(printUrl, name, 'noreferrer,width=960,height=720');
        } catch (e) {
            popup = null;
        }

        if (!popup) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Allow pop-ups for printing',
                    text: 'Chrome blocked the print window. Printing from the preview below instead.',
                    confirmButtonColor: '#1a6fc4',
                    timer: 3500,
                    showConfirmButton: true,
                });
            }
            return false;
        }

        activePrintPopup = popup;
        triggerPopupPrintOnce(popup);
        return true;
    }

    function triggerPopupPrintOnce(popup) {
        let fired = false;
        const deadline = Date.now() + 20000;

        const attempt = () => {
            if (fired || Date.now() > deadline) {
                return;
            }
            if (popup.closed) {
                activePrintPopup = null;
                return;
            }

            let ready = false;
            let previewReady = false;
            try {
                ready = popup.document?.readyState === 'complete';
                previewReady = popup.__RHU_PREVIEW_READY === true;
            } catch (e) {
                ready = false;
                previewReady = false;
            }

            if (!ready || !previewReady) {
                setTimeout(attempt, 150);
                return;
            }

            fired = true;
            setTimeout(() => {
                if (popup.closed) {
                    return;
                }
                try {
                    popup.focus();
                    popup.print();
                } catch (e) { /* ignore */ }
            }, 350);
        };

        setTimeout(attempt, 300);
    }

    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function loadFrame(frame, url) {
        return new Promise((resolve) => {
            let settled = false;
            const done = () => {
                if (settled) return;
                settled = true;
                frame.removeEventListener('load', onLoad);
                setTimeout(resolve, 250);
            };
            const onLoad = () => done();
            frame.addEventListener('load', onLoad);
            // Safety timeout if load never fires
            setTimeout(done, 8000);
            frame.src = url;
        });
    }

    document.getElementById('pqDoPrint')?.addEventListener('click', () => {
        const frame = document.getElementById('pqPreviewFrame');
        try {
            frame?.contentWindow?.focus();
            frame?.contentWindow?.print();
        } catch (e) {
            if (window.Swal) {
                Swal.fire('Print', 'Unable to print from preview. Try again.', 'warning');
            }
        }
    });

    document.getElementById('pqPrintNext')?.addEventListener('click', async () => {
        if (printingBusy || !printQueue.length) return;
        await printNextDocument();
    });

    document.getElementById('pqMarkPrinted')?.addEventListener('click', async () => {
        if (!activeJob?.patientDbId || activeJob.status !== 'ready') return;
        const confirm = window.Swal
            ? await Swal.fire({
                title: 'Mark as Printed?',
                text: 'This consultation will be marked as already printed.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Mark as Printed',
                confirmButtonColor: '#16a34a',
            })
            : { isConfirmed: window.confirm('Mark as Printed?') };
        if (!confirm.isConfirmed) return;

        try {
            const body = new FormData();
            body.append('action', 'mark_printed');
            body.append('patient_id', String(activeJob.patientDbId));
            if (activeJob.consultationId) {
                body.append('consultation_id', String(activeJob.consultationId));
            }
            const res = await fetch(`${cfg.baseUrl}/api/workflow_action.php`, { method: 'POST', body });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed');
            if (window.Swal) {
                await Swal.fire({ icon: 'success', title: 'Marked as Printed', timer: 1400, showConfirmButton: false });
            }
            window.location.reload();
        } catch (err) {
            if (window.Swal) {
                Swal.fire('Error', err.message || 'Could not mark as printed.', 'error');
            } else {
                alert(err.message || 'Could not mark as printed.');
            }
        }
    });

    const previewModal = document.getElementById('pqPreviewModal');
    previewModal?.addEventListener('hidden.bs.modal', () => {
        const frame = document.getElementById('pqPreviewFrame');
        if (frame && !printingBusy && !printQueue.length) {
            frame.src = 'about:blank';
            printBatch = [];
        }
    });

    const preselectPatientId = new URLSearchParams(window.location.search).get('patient_id');
    if (preselectPatientId && search) {
        search.value = preselectPatientId;
        if (status) {
            status.value = 'ready';
        }
    }

    render();
});
