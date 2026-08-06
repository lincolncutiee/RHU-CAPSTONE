/**
 * Offline clinical autocomplete (typeahead) — diagnosis_master / medication_master.
 * Google-search style suggestions; selection required from local MySQL dataset.
 */
window.ClinicalAutocomplete = (() => {
    function create(options) {
        const {
            root,
            type,
            hiddenInput,
            idsInput,
            chipsEl,
            inputEl,
            listEl,
            datasetMsgEl,
            baseUrl,
            canEdit = true,
            icon = 'bi-heart-pulse',
        } = options;

        const selected = []; // { id, label }
        let timer = null;
        let activeIndex = -1;
        let lastResults = [];
        let emptyDataset = false;

        function syncHidden() {
            if (hiddenInput) {
                hiddenInput.value = selected.map((s) => s.label).join('\n');
            }
            if (idsInput) {
                idsInput.value = JSON.stringify(
                    selected.map((s) => Number(s.id) || 0).filter((id) => id > 0)
                );
            }
        }

        function renderChips() {
            if (!chipsEl) return;
            chipsEl.innerHTML = '';
            selected.forEach((item, idx) => {
                const chip = document.createElement('span');
                chip.className = 'dc-ac-chip';
                chip.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span>${escapeHtml(item.label)}</span>`;
                if (canEdit) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'dc-ac-chip-remove';
                    btn.setAttribute('aria-label', 'Remove');
                    btn.innerHTML = '&times;';
                    btn.addEventListener('click', () => {
                        selected.splice(idx, 1);
                        renderChips();
                        syncHidden();
                    });
                    chip.appendChild(btn);
                }
                chipsEl.appendChild(chip);
            });
            syncHidden();
        }

        function setValuesFromItems(items) {
            selected.length = 0;
            (items || []).forEach((item) => {
                if (!item) return;
                const label = String(item.label || '').trim();
                if (!label) return;
                if (selected.some((s) => s.label === label)) return;
                selected.push({
                    id: Number(item.id) || 0,
                    label,
                });
            });
            renderChips();
        }

        function setValuesFromText(text) {
            selected.length = 0;
            String(text || '')
                .split(/[\r\n;|]+/)
                .map((s) => s.trim())
                .filter(Boolean)
                .forEach((label) => {
                    if (!selected.some((s) => s.label === label)) {
                        selected.push({ id: 0, label });
                    }
                });
            renderChips();
        }

        function getValues() {
            return selected.slice();
        }

        function hideList() {
            if (!listEl) return;
            listEl.hidden = true;
            listEl.innerHTML = '';
            activeIndex = -1;
        }

        function showDatasetMessage(msg) {
            emptyDataset = true;
            if (datasetMsgEl) {
                datasetMsgEl.hidden = false;
                datasetMsgEl.textContent = msg || '';
            }
            if (inputEl) {
                inputEl.disabled = true;
                inputEl.placeholder = type === 'medication'
                    ? 'Medication dataset not imported'
                    : 'Diagnosis dataset not imported';
            }
            hideList();
        }

        function clearDatasetMessage() {
            emptyDataset = false;
            if (datasetMsgEl) {
                datasetMsgEl.hidden = true;
                datasetMsgEl.textContent = '';
            }
            if (inputEl && canEdit) {
                inputEl.disabled = false;
                inputEl.placeholder = type === 'medication'
                    ? 'Search Medication...'
                    : 'Search Diagnosis...';
            }
        }

        function renderSuggestions(results) {
            if (!listEl) return;
            lastResults = results || [];
            listEl.innerHTML = '';
            activeIndex = -1;

            if (!lastResults.length) {
                const li = document.createElement('li');
                li.className = 'dc-ac-empty';
                li.textContent = 'No matching results';
                listEl.appendChild(li);
                listEl.hidden = false;
                return;
            }

            lastResults.forEach((row, i) => {
                const li = document.createElement('li');
                li.className = 'dc-ac-item';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                const meta = type === 'diagnosis' && row.icd_code
                    ? `<span class="dc-ac-meta">${escapeHtml(row.icd_code)}</span>`
                    : (type === 'medication' && row.dosage_form
                        ? `<span class="dc-ac-meta">${escapeHtml([row.strength, row.dosage_form].filter(Boolean).join(' '))}</span>`
                        : '');
                li.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i>
                    <span class="dc-ac-label">${escapeHtml(row.label)}</span>${meta}`;
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selectResult(row);
                });
                listEl.appendChild(li);
            });
            listEl.hidden = false;
        }

        function selectResult(row) {
            if (!row || !row.label) return;
            if (!selected.some((s) => s.label === row.label)) {
                selected.push({ id: Number(row.id) || 0, label: row.label });
                renderChips();
            }
            if (inputEl) inputEl.value = '';
            hideList();
            inputEl?.focus();
        }

        async function search(q) {
            if (emptyDataset || !canEdit) return;
            const term = String(q || '').trim();
            if (term.length < 1) {
                hideList();
                return;
            }
            try {
                const url = `${baseUrl}/api/clinical_master_search.php?type=${encodeURIComponent(type)}&q=${encodeURIComponent(term)}&limit=20`;
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) {
                    hideList();
                    return;
                }
                if (data.empty_dataset) {
                    showDatasetMessage(data.message);
                    return;
                }
                clearDatasetMessage();
                renderSuggestions(data.results || []);
            } catch (err) {
                hideList();
            }
        }

        async function checkDataset() {
            try {
                const url = `${baseUrl}/api/clinical_master_search.php?type=${encodeURIComponent(type)}&check=1`;
                const res = await fetch(url);
                const data = await res.json();
                if (data.empty_dataset) {
                    showDatasetMessage(data.message);
                } else {
                    clearDatasetMessage();
                }
            } catch (e) {
                /* ignore */
            }
        }

        function onKeyDown(e) {
            if (listEl?.hidden) return;
            const items = [...(listEl?.querySelectorAll('.dc-ac-item') || [])];
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(items.length - 1, activeIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && lastResults[activeIndex]) {
                    selectResult(lastResults[activeIndex]);
                }
                return;
            } else if (e.key === 'Escape') {
                hideList();
                return;
            } else {
                return;
            }
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
        }

        inputEl?.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => search(inputEl.value), 180);
        });
        inputEl?.addEventListener('keydown', onKeyDown);
        inputEl?.addEventListener('blur', () => {
            setTimeout(hideList, 150);
            // Reject free-typed text — must pick from suggestions
            if (inputEl) inputEl.value = '';
        });
        inputEl?.addEventListener('focus', () => {
            if (inputEl.value.trim()) search(inputEl.value);
        });

        document.addEventListener('click', (e) => {
            if (root && !root.contains(e.target)) hideList();
        });

        if (!canEdit && inputEl) {
            inputEl.disabled = true;
        }

        checkDataset();
        renderChips();

        return { setValuesFromText, setValuesFromItems, getValues, syncHidden, checkDataset };
    }

    /**
     * Clinical-notes style diagnosis textarea with ICD-10 typeahead.
     * Suggestions show diagnosis_name only; selection replaces the current line.
     */
    function createDiagnosisNotes(options) {
        const {
            root,
            textarea,
            listEl,
            datasetMsgEl,
            idsInput,
            baseUrl,
            canEdit = true,
        } = options;

        let timer = null;
        let activeIndex = -1;
        let lastResults = [];
        let emptyDataset = false;
        const nameToId = new Map(); // official diagnosis_name → id

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function hideList() {
            if (!listEl) return;
            listEl.hidden = true;
            listEl.innerHTML = '';
            activeIndex = -1;
        }

        function showDatasetMessage(msg) {
            emptyDataset = true;
            if (datasetMsgEl) {
                datasetMsgEl.hidden = false;
                datasetMsgEl.textContent = msg || '';
            }
            if (textarea && canEdit) {
                textarea.readOnly = true;
            }
            hideList();
        }

        function clearDatasetMessage() {
            emptyDataset = false;
            if (datasetMsgEl) {
                datasetMsgEl.hidden = true;
                datasetMsgEl.textContent = '';
            }
            if (textarea && canEdit) {
                textarea.readOnly = false;
            }
        }

        function getCaretLineInfo() {
            const value = textarea?.value || '';
            const caret = textarea?.selectionStart ?? value.length;
            const before = value.slice(0, caret);
            const lineStart = before.lastIndexOf('\n') + 1;
            const afterBreak = value.indexOf('\n', caret);
            const lineEnd = afterBreak === -1 ? value.length : afterBreak;
            return {
                value,
                caret,
                lineStart,
                lineEnd,
                lineText: value.slice(lineStart, lineEnd),
            };
        }

        function syncIdsFromText() {
            if (!idsInput) return;
            const lines = String(textarea?.value || '')
                .split(/\r?\n/)
                .map((s) => s.trim())
                .filter(Boolean);
            const ids = [];
            lines.forEach((name) => {
                const id = nameToId.get(name);
                if (id > 0 && !ids.includes(id)) ids.push(id);
            });
            idsInput.value = JSON.stringify(ids);
        }

        function setTextFromDiagnoses(items) {
            nameToId.clear();
            const names = [];
            (items || []).forEach((item) => {
                if (!item) return;
                let official = String(item.diagnosis_name || '').trim();
                if (!official && item.label) {
                    official = String(item.label).replace(/\s*\([^)]*\)\s*$/, '').trim();
                }
                if (!official) return;
                if (item.id) nameToId.set(official, Number(item.id));
                if (!names.includes(official)) names.push(official);
            });
            if (textarea) textarea.value = names.join('\n');
            syncIdsFromText();
        }

        function setTextFromPlain(text) {
            nameToId.clear();
            const names = String(text || '')
                .split(/[\r\n;|]+/)
                .map((s) => s.replace(/\s*\([^)]*\)\s*$/, '').trim())
                .filter(Boolean);
            if (textarea) textarea.value = names.join('\n');
            syncIdsFromText();
        }

        function insertOfficialName(row) {
            if (!textarea || !row) return;
            const name = String(row.diagnosis_name || '').trim();
            if (!name) return;
            const id = Number(row.id) || 0;
            if (id > 0) nameToId.set(name, id);

            const info = getCaretLineInfo();
            const next = info.value.slice(0, info.lineStart) + name + info.value.slice(info.lineEnd);
            textarea.value = next;
            const newCaret = info.lineStart + name.length;
            textarea.setSelectionRange(newCaret, newCaret);
            syncIdsFromText();
            hideList();
            textarea.focus();
        }

        function renderSuggestions(results) {
            if (!listEl) return;
            lastResults = results || [];
            listEl.innerHTML = '';
            activeIndex = -1;

            if (!lastResults.length) {
                const li = document.createElement('li');
                li.className = 'dc-ac-empty';
                li.textContent = 'No matching diagnoses';
                listEl.appendChild(li);
                listEl.hidden = false;
                return;
            }

            lastResults.forEach((row, i) => {
                const name = String(row.diagnosis_name || '').trim();
                if (!name) return;
                const li = document.createElement('li');
                li.className = 'dc-ac-item';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                li.innerHTML = `<span class="dc-ac-label">${escapeHtml(name)}</span>`;
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    insertOfficialName(row);
                });
                listEl.appendChild(li);
            });
            listEl.hidden = false;
        }

        async function searchCurrentLine() {
            if (emptyDataset || !canEdit || !textarea) return;
            const term = getCaretLineInfo().lineText.trim();
            if (term.length < 1) {
                hideList();
                return;
            }
            try {
                const url = `${baseUrl}/api/clinical_master_search.php?type=diagnosis&q=${encodeURIComponent(term)}&limit=20`;
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) {
                    hideList();
                    return;
                }
                if (data.empty_dataset) {
                    showDatasetMessage(data.message);
                    return;
                }
                clearDatasetMessage();
                renderSuggestions(data.results || []);
            } catch (e) {
                hideList();
            }
        }

        async function checkDataset() {
            try {
                const url = `${baseUrl}/api/clinical_master_search.php?type=diagnosis&check=1`;
                const res = await fetch(url);
                const data = await res.json();
                if (data.empty_dataset) {
                    showDatasetMessage(data.message);
                } else {
                    clearDatasetMessage();
                }
            } catch (e) {
                /* ignore */
            }
        }

        function onKeyDown(e) {
            if (listEl?.hidden) {
                return;
            }
            const items = [...(listEl?.querySelectorAll('.dc-ac-item') || [])];
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(items.length - 1, activeIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
            } else if (e.key === 'Enter' && activeIndex >= 0 && lastResults[activeIndex]) {
                e.preventDefault();
                insertOfficialName(lastResults[activeIndex]);
                return;
            } else if (e.key === 'Escape') {
                hideList();
                return;
            } else {
                return;
            }
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
        }

        textarea?.addEventListener('input', () => {
            syncIdsFromText();
            clearTimeout(timer);
            timer = setTimeout(searchCurrentLine, 180);
        });
        textarea?.addEventListener('keydown', onKeyDown);
        textarea?.addEventListener('keyup', () => {
            // caret moved with arrows within line may need refresh when list open
        });
        textarea?.addEventListener('click', () => {
            if ((textarea.value || '').trim()) {
                clearTimeout(timer);
                timer = setTimeout(searchCurrentLine, 120);
            }
        });
        textarea?.addEventListener('blur', () => setTimeout(hideList, 150));

        document.addEventListener('click', (e) => {
            if (root && !root.contains(e.target)) hideList();
        });

        if (!canEdit && textarea) {
            textarea.readOnly = true;
        }

        checkDataset();

        return {
            setTextFromDiagnoses,
            setTextFromPlain,
            syncIdsFromText,
            getText: () => textarea?.value || '',
        };
    }

    /**
     * Clinical-notes style medication textarea with local master typeahead.
     * Suggestions show the official medication label; selection replaces the current line.
     */
    function createMedicationNotes(options) {
        const {
            root,
            textarea,
            listEl,
            datasetMsgEl,
            idsInput,
            baseUrl,
            canEdit = true,
        } = options;

        let timer = null;
        let activeIndex = -1;
        let lastResults = [];
        let emptyDataset = false;
        const labelToId = new Map();

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function hideList() {
            if (!listEl) return;
            listEl.hidden = true;
            listEl.innerHTML = '';
            activeIndex = -1;
        }

        function showDatasetMessage(msg) {
            emptyDataset = true;
            if (datasetMsgEl) {
                datasetMsgEl.hidden = false;
                datasetMsgEl.textContent = msg || '';
            }
            if (textarea && canEdit) textarea.readOnly = true;
            hideList();
        }

        function clearDatasetMessage() {
            emptyDataset = false;
            if (datasetMsgEl) {
                datasetMsgEl.hidden = true;
                datasetMsgEl.textContent = '';
            }
            if (textarea && canEdit) textarea.readOnly = false;
        }

        function getCaretLineInfo() {
            const value = textarea?.value || '';
            const caret = textarea?.selectionStart ?? value.length;
            const before = value.slice(0, caret);
            const lineStart = before.lastIndexOf('\n') + 1;
            const afterBreak = value.indexOf('\n', caret);
            const lineEnd = afterBreak === -1 ? value.length : afterBreak;
            return {
                value,
                caret,
                lineStart,
                lineEnd,
                lineText: value.slice(lineStart, lineEnd),
            };
        }

        function syncIdsFromText() {
            if (!idsInput) return;
            const lines = String(textarea?.value || '')
                .split(/\r?\n/)
                .map((s) => s.trim())
                .filter(Boolean);
            const ids = [];
            lines.forEach((label) => {
                const id = labelToId.get(label);
                if (id > 0 && !ids.includes(id)) ids.push(id);
            });
            idsInput.value = JSON.stringify(ids);
        }

        function setTextFromMedications(items) {
            labelToId.clear();
            const labels = [];
            (items || []).forEach((item) => {
                if (!item) return;
                const label = String(item.label || '').trim();
                if (!label) return;
                if (item.id) labelToId.set(label, Number(item.id));
                if (!labels.includes(label)) labels.push(label);
            });
            if (textarea) textarea.value = labels.join('\n');
            syncIdsFromText();
        }

        function setTextFromPlain(text) {
            labelToId.clear();
            const labels = String(text || '')
                .split(/[\r\n;|]+/)
                .map((s) => s.trim())
                .filter(Boolean);
            if (textarea) textarea.value = labels.join('\n');
            syncIdsFromText();
        }

        function insertOfficialLabel(row) {
            if (!textarea || !row) return;
            const label = String(row.label || '').trim();
            if (!label) return;
            const id = Number(row.id) || 0;
            if (id > 0) labelToId.set(label, id);

            const info = getCaretLineInfo();
            const next = info.value.slice(0, info.lineStart) + label + info.value.slice(info.lineEnd);
            textarea.value = next;
            const newCaret = info.lineStart + label.length;
            textarea.setSelectionRange(newCaret, newCaret);
            syncIdsFromText();
            hideList();
            textarea.focus();
        }

        function renderSuggestions(results) {
            if (!listEl) return;
            lastResults = results || [];
            listEl.innerHTML = '';
            activeIndex = -1;

            if (!lastResults.length) {
                const li = document.createElement('li');
                li.className = 'dc-ac-empty';
                li.textContent = 'No matching medications';
                listEl.appendChild(li);
                listEl.hidden = false;
                return;
            }

            lastResults.forEach((row, i) => {
                const label = String(row.label || '').trim();
                if (!label) return;
                const li = document.createElement('li');
                li.className = 'dc-ac-item';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                li.innerHTML = `<span class="dc-ac-label">${escapeHtml(label)}</span>`;
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    insertOfficialLabel(row);
                });
                listEl.appendChild(li);
            });
            listEl.hidden = false;
        }

        async function searchCurrentLine() {
            if (emptyDataset || !canEdit || !textarea) return;
            const term = getCaretLineInfo().lineText.trim();
            if (term.length < 1) {
                hideList();
                return;
            }
            try {
                const url = `${baseUrl}/api/clinical_master_search.php?type=medication&q=${encodeURIComponent(term)}&limit=20`;
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) {
                    hideList();
                    return;
                }
                if (data.empty_dataset) {
                    showDatasetMessage(data.message);
                    return;
                }
                clearDatasetMessage();
                renderSuggestions(data.results || []);
            } catch (e) {
                hideList();
            }
        }

        async function checkDataset() {
            try {
                const url = `${baseUrl}/api/clinical_master_search.php?type=medication&check=1`;
                const res = await fetch(url);
                const data = await res.json();
                if (data.empty_dataset) {
                    showDatasetMessage(data.message);
                } else {
                    clearDatasetMessage();
                }
            } catch (e) {
                /* ignore */
            }
        }

        function onKeyDown(e) {
            if (listEl?.hidden) return;
            const items = [...(listEl?.querySelectorAll('.dc-ac-item') || [])];
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(items.length - 1, activeIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
            } else if (e.key === 'Enter' && activeIndex >= 0 && lastResults[activeIndex]) {
                e.preventDefault();
                insertOfficialLabel(lastResults[activeIndex]);
                return;
            } else if (e.key === 'Escape') {
                hideList();
                return;
            } else {
                return;
            }
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
        }

        textarea?.addEventListener('input', () => {
            syncIdsFromText();
            clearTimeout(timer);
            timer = setTimeout(searchCurrentLine, 180);
        });
        textarea?.addEventListener('keydown', onKeyDown);
        textarea?.addEventListener('click', () => {
            if ((textarea.value || '').trim()) {
                clearTimeout(timer);
                timer = setTimeout(searchCurrentLine, 120);
            }
        });
        textarea?.addEventListener('blur', () => setTimeout(hideList, 150));

        document.addEventListener('click', (e) => {
            if (root && !root.contains(e.target)) hideList();
        });

        if (!canEdit && textarea) {
            textarea.readOnly = true;
        }

        checkDataset();

        return {
            setTextFromMedications,
            setTextFromPlain,
            syncIdsFromText,
            getText: () => textarea?.value || '',
        };
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    return { create, createDiagnosisNotes, createMedicationNotes };
})();
