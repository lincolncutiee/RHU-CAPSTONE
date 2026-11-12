/**
 * Add Existing Record — camera capture, optional OCR, create patient from old form.
 */
const HistoricalRecordWizard = (() => {
    const API_URL = `${BASE_URL}/api/historical_records.php`;
    const TESSERACT_SRC = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';

    let modal = null;
    let modalEl = null;
    let mode = 'existing'; // existing | createPatient
    let currentPanel = 'details';
    let patientId = null;
    let patientLabel = '';
    let lockPatientPick = false;
    let photoBlob = null;
    let photoDataUrl = null;
    let ocrRawText = '';
    let createUsedOcr = false;
    let createProcessingMethod = null; // 'ocr' | 'imageOnly'
    let duplicateMatches = [];
    let forceCreate = false;
    let tesseractLoaded = false;
    let tesseractLoading = null;
    let saving = false;
    let moduleOptions = {};
    let formPages = [];
    let currentFormIndex = 0;
    /** @type {Record<string, { included: boolean, photoBlob: Blob|null, photoDataUrl: string|null }>} */
    let formStates = {};

    const PANELS = ['details', 'processingMethod', 'formCapture', 'photo', 'review', 'createPatient'];

    const PANEL_IDS = {
        details: 'hrPanelDetails',
        processingMethod: 'hrPanelProcessingMethod',
        formCapture: 'hrPanelFormCapture',
        photo: 'hrPanelPhoto',
        review: 'hrPanelReview',
        createPatient: 'hrPanelCreatePatient',
    };

    function loadFormPages() {
        if (!modalEl) return [];
        try {
            return JSON.parse(modalEl.dataset.formPages || '[]');
        } catch (_) {
            return [];
        }
    }

    function initFormStates() {
        formStates = {};
        formPages.forEach((page) => {
            formStates[page.id] = { included: false, photoBlob: null, photoDataUrl: null };
        });
    }

    function isImageOnlyFlow() {
        return createProcessingMethod === 'imageOnly';
    }

    /** Accept any common image file — camera capture or gallery upload. */
    function isImageFile(file) {
        if (!file) return false;
        if (file.type && file.type.startsWith('image/')) return true;
        return /\.(jpe?g|png|gif|webp|bmp|heic|heif|tif?f)$/i.test(file.name || '');
    }

    function getFormPage(index) {
        return formPages[index] || null;
    }

    function includedCaptureForms() {
        return formPages.filter((page) => {
            const st = formStates[page.id];
            return st?.included && st.photoBlob;
        });
    }

    function includedCaptureFormsPendingPhoto() {
        return formPages.filter((page) => {
            const st = formStates[page.id];
            return st?.included && !st.photoBlob;
        });
    }

    function $(id) {
        return document.getElementById(id);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function setPanel(panel) {
        currentPanel = panel;
        PANELS.forEach((p) => {
            const el = $(PANEL_IDS[p]);
            if (el) el.hidden = p !== panel;
        });

        modalEl?.classList.toggle('is-form-capture', panel === 'formCapture');

        document.querySelectorAll('.hr-step').forEach((el) => {
            let stepKey = panel;
            if (panel === 'formCapture') stepKey = 'formCapture';
            el.classList.toggle('is-active', el.dataset.hrStep === stepKey);
        });

        const btnBack = $('hrBtnBack');
        const btnNext = $('hrBtnNext');
        const btnFormNext = $('hrBtnFormNext');
        const btnContinueMethod = $('hrBtnContinueMethod');
        const btnSaveRecords = $('hrBtnSaveRecords');
        const btnSaveDigital = $('hrBtnSaveDigital');
        const btnCreatePatient = $('hrBtnCreatePatient');
        const btnCancel = $('hrBtnCancel');

        const onFormCapture = panel === 'formCapture';
        const onLastForm = onFormCapture && currentFormIndex === formPages.length - 1;

        if (btnBack) {
            btnBack.hidden = panel === 'details'
                || (mode === 'createPatient' && panel === 'processingMethod');
        }
        if (btnNext) btnNext.hidden = panel !== 'details' || mode === 'createPatient';
        if (btnContinueMethod) {
            btnContinueMethod.hidden = panel !== 'processingMethod';
            btnContinueMethod.disabled = !createProcessingMethod;
        }
        if (btnFormNext) {
            btnFormNext.hidden = !onFormCapture || onLastForm;
        }
        if (btnSaveRecords) {
            btnSaveRecords.hidden = !(onFormCapture && onLastForm && mode === 'existing');
        }
        if (btnSaveDigital) btnSaveDigital.hidden = panel !== 'review' || mode !== 'existing';
        if (btnCreatePatient) btnCreatePatient.hidden = panel !== 'createPatient';
        if (btnCancel) {
            btnCancel.textContent = panel === 'review' || panel === 'createPatient' ? 'Cancel' : 'Close';
        }

        if (onFormCapture && onLastForm && mode === 'createPatient') {
            if (btnFormNext) btnFormNext.hidden = false;
            if (btnFormNext) btnFormNext.textContent = 'Continue to Patient Info';
        } else if (btnFormNext) {
            btnFormNext.textContent = 'Next';
        }

        if (panel === 'photo') updatePhotoActions();
    }

    function updateStepsForMode() {
        const steps = $('hrSteps');
        if (!steps) return;

        if (mode === 'createPatient') {
            if (isImageOnlyFlow()) {
                steps.innerHTML = `
                    <span class="hr-step" data-hr-step="processingMethod">1. Method</span>
                    <span class="hr-step" data-hr-step="formCapture">2. Forms</span>
                    <span class="hr-step" data-hr-step="createPatient">3. Patient Info</span>
                `;
            } else {
                steps.innerHTML = `
                    <span class="hr-step" data-hr-step="processingMethod">1. Method</span>
                    <span class="hr-step" data-hr-step="photo">2. Photo</span>
                    <span class="hr-step" data-hr-step="createPatient">3. Patient Info</span>
                `;
            }
        } else if (isImageOnlyFlow()) {
            steps.innerHTML = `
                <span class="hr-step" data-hr-step="details">1. Details</span>
                <span class="hr-step" data-hr-step="processingMethod">2. Method</span>
                <span class="hr-step" data-hr-step="formCapture">3. Forms</span>
            `;
        } else {
            steps.innerHTML = `
                <span class="hr-step" data-hr-step="details">1. Details</span>
                <span class="hr-step" data-hr-step="processingMethod">2. Method</span>
                <span class="hr-step" data-hr-step="photo">3. Photo</span>
                <span class="hr-step" data-hr-step="review">4. Review</span>
            `;
        }
    }

    function updateProcessingMethodIntro() {
        const intro = $('hrProcessingMethodIntro');
        if (!intro) return;
        intro.textContent = mode === 'createPatient'
            ? 'Choose one option before continuing. This applies only to patients not yet encoded in the system.'
            : 'Choose one option before continuing. The original photograph is always preserved.';
    }

    function updateModeUi() {
        updateStepsForMode();
        updateProcessingMethodIntro();

        const notEncoded = $('hrNotEncoded');
        const existingDetails = $('hrExistingDetails');
        const patientPick = $('hrPatientPick');
        const title = $('hrModalTitleText');
        const saveLabel = $('hrBtnSavePhotoLabel');

        if (mode === 'createPatient') {
            if (title) title.textContent = 'Create Patient from Existing Form';
            if (notEncoded) notEncoded.hidden = true;
            if (existingDetails) existingDetails.hidden = true;
            if (patientPick) patientPick.hidden = true;
        } else {
            if (title) title.textContent = 'Add Existing Record';
            if (existingDetails) existingDetails.hidden = false;
            if (notEncoded) notEncoded.hidden = lockPatientPick;
            if (patientPick) patientPick.hidden = !!patientId;
        }
        updatePhotoActions();
    }

    function updatePhotoActions() {
        const ocrBtn = $('hrBtnOcr');
        const saveBtn = $('hrBtnSavePhoto');
        const saveLabel = $('hrBtnSavePhotoLabel');
        const intro = $('hrPhotoIntro');

        if (mode !== 'createPatient') {
            if (createProcessingMethod === 'ocr') {
                if (ocrBtn) ocrBtn.hidden = false;
                if (saveBtn) saveBtn.hidden = true;
                if (saveLabel) saveLabel.textContent = 'Save Record';
                if (intro) intro.textContent = 'Take a clear photo of the old form, then run OCR to extract information for review. The original photograph will always be preserved.';
            } else if (createProcessingMethod === 'imageOnly') {
                if (ocrBtn) ocrBtn.hidden = true;
                if (saveBtn) saveBtn.hidden = false;
                if (saveLabel) saveLabel.textContent = 'Save Record';
                if (intro) intro.textContent = 'Photograph the old physical form. The original image will be saved as the historical record — OCR is not required.';
            } else {
                if (ocrBtn) ocrBtn.hidden = false;
                if (saveBtn) saveBtn.hidden = false;
                if (saveLabel) saveLabel.textContent = 'Save Record';
                if (intro) intro.textContent = 'Photograph the old physical form using your device camera. The original photo is always preserved.';
            }
            return;
        }

        if (createProcessingMethod === 'ocr') {
            if (ocrBtn) ocrBtn.hidden = false;
            if (saveBtn) saveBtn.hidden = true;
            if (intro) intro.textContent = 'Take a clear photo of the old form, then run OCR to extract information for review. The original photograph will always be preserved.';
        } else if (createProcessingMethod === 'imageOnly') {
            if (ocrBtn) ocrBtn.hidden = true;
            if (saveBtn) saveBtn.hidden = false;
            if (saveLabel) saveLabel.textContent = 'Save Original Image';
            if (intro) intro.textContent = 'Photograph the old physical form. The original image will be saved as the historical record — OCR is not required.';
        }
    }

    function clearProcessingMethodSelection() {
        createProcessingMethod = null;
        document.querySelectorAll('.hr-method-card').forEach((card) => {
            card.classList.remove('is-selected');
            card.setAttribute('aria-pressed', 'false');
        });
        const btnContinue = $('hrBtnContinueMethod');
        if (btnContinue) btnContinue.disabled = true;
    }

    function selectProcessingMethod(method) {
        createProcessingMethod = method;
        document.querySelectorAll('.hr-method-card').forEach((card) => {
            const selected = card.dataset.hrMethod === method;
            card.classList.toggle('is-selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        const btnContinue = $('hrBtnContinueMethod');
        if (btnContinue) btnContinue.disabled = false;
        updateStepsForMode();
    }

    function resetFormCapturePhotos() {
        formPages.forEach((page) => {
            const st = formStates[page.id];
            if (!st) return;
            st.included = false;
            st.photoBlob = null;
            st.photoDataUrl = null;

            const includeCb = document.querySelector(`.hr-form-include[data-form-id="${page.id}"]`);
            if (includeCb) includeCb.checked = false;

            const wrap = document.querySelector(`.hr-form-camera[data-form-id="${page.id}"]`);
            const preview = wrap?.querySelector('.hr-photo-preview');
            const camera = wrap?.querySelector('.hr-form-camera-area');
            const input = wrap?.querySelector('.hr-camera-input');
            const uploadInput = wrap?.querySelector('.hr-upload-input');
            if (preview) preview.hidden = true;
            if (camera) camera.hidden = false;
            if (input) input.value = '';
            if (uploadInput) uploadInput.value = '';
        });
        currentFormIndex = 0;
        updateFormNavStatus();
    }

    function syncFormCaptureFromDetails() {
        const selected = new Set(getSelectedRecordTypes('existing'));
        formPages.forEach((page) => {
            const st = formStates[page.id];
            if (!st) return;
            const include = selected.has(page.id);
            st.included = include;
            const includeCb = document.querySelector(`.hr-form-include[data-form-id="${page.id}"]`);
            if (includeCb) includeCb.checked = include;
        });
        updateFormNavStatus();
    }

    function showFormPage(index) {
        if (formPages.length === 0) return;
        currentFormIndex = Math.max(0, Math.min(index, formPages.length - 1));

        document.querySelectorAll('.hr-form-page').forEach((el) => {
            el.hidden = true;
        });
        document.querySelectorAll('.hr-form-nav-item').forEach((el) => {
            el.classList.remove('is-active');
        });

        const page = getFormPage(currentFormIndex);
        if (!page) return;

        const pageEl = $(`hrFormPage-${page.id}`);
        if (pageEl) pageEl.hidden = false;

        const navItem = document.querySelector(`.hr-form-nav-item[data-form-id="${page.id}"]`);
        if (navItem) navItem.classList.add('is-active');

        updateFormNavStatus();
        setPanel('formCapture');
    }

    function updateFormNavStatus() {
        formPages.forEach((page) => {
            const st = formStates[page.id];
            const navItem = document.querySelector(`.hr-form-nav-item[data-form-id="${page.id}"]`);
            if (!navItem) return;

            navItem.classList.remove('completed', 'is-incomplete');
            const statusEl = navItem.querySelector('.hr-form-nav-status');
            if (!st?.included) {
                if (statusEl) statusEl.innerHTML = '<i class="bi bi-circle"></i>';
                return;
            }
            if (st.photoBlob) {
                navItem.classList.add('completed');
                if (statusEl) statusEl.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            } else {
                navItem.classList.add('is-incomplete');
                if (statusEl) statusEl.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i>';
            }
        });
    }

    function handleFormCapturePhoto(formId, file) {
        if (!isImageFile(file)) {
            Swal.fire({ icon: 'error', title: 'Invalid image', text: 'Please choose a valid image file (JPG, PNG, etc.).' });
            return;
        }
        if (file.size > 8 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: 'Photo too large', text: 'Maximum photo size is 8 MB.' });
            return;
        }

        const st = formStates[formId];
        if (!st) return;

        st.included = true;
        st.photoBlob = file;

        const includeCb = document.querySelector(`.hr-form-include[data-form-id="${formId}"]`);
        if (includeCb) includeCb.checked = true;

        const reader = new FileReader();
        reader.onload = () => {
            st.photoDataUrl = reader.result;
            const wrap = document.querySelector(`.hr-form-camera[data-form-id="${formId}"]`);
            const img = wrap?.querySelector('.hr-photo-img');
            const preview = wrap?.querySelector('.hr-photo-preview');
            const camera = wrap?.querySelector('.hr-form-camera-area');
            if (img) img.src = st.photoDataUrl;
            if (preview) preview.hidden = false;
            if (camera) camera.hidden = true;
            updateFormNavStatus();
        };
        reader.readAsDataURL(file);
    }

    function retakeFormCapturePhoto(formId) {
        const st = formStates[formId];
        if (!st) return;
        st.photoBlob = null;
        st.photoDataUrl = null;

        const wrap = document.querySelector(`.hr-form-camera[data-form-id="${formId}"]`);
        const preview = wrap?.querySelector('.hr-photo-preview');
        const camera = wrap?.querySelector('.hr-form-camera-area');
        const input = wrap?.querySelector('.hr-camera-input');
        const uploadInput = wrap?.querySelector('.hr-upload-input');
        if (preview) preview.hidden = true;
        if (camera) camera.hidden = false;
        if (input) input.value = '';
        if (uploadInput) uploadInput.value = '';
        updateFormNavStatus();
    }

    function toggleFormInclude(formId, included) {
        const st = formStates[formId];
        if (!st) return;
        st.included = included;
        if (!included) retakeFormCapturePhoto(formId);
        updateFormNavStatus();
    }

    function validateFormCapture() {
        const pending = includedCaptureFormsPendingPhoto();
        if (pending.length) {
            const labels = pending.map((p) => p.label).join(', ');
            Swal.fire({
                icon: 'warning',
                title: 'Image required',
                html: `Please add an image for each form you marked to add:<br><strong>${escapeHtml(labels)}</strong>`,
            });
            return false;
        }
        if (includedCaptureForms().length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No forms selected',
                text: 'Check at least one form and add an image before continuing.',
            });
            return false;
        }
        if (mode === 'existing' && !patientId) {
            Swal.fire({ icon: 'warning', title: 'Select a patient', text: 'Search and select the patient for these historical records.' });
            return false;
        }
        return true;
    }

    function continueFromFormCapture() {
        if (!validateFormCapture()) return;

        if (mode === 'createPatient') {
            createUsedOcr = false;
            goToCreatePatientPanelFromCapture();
            return;
        }

        saveExistingFormCaptureRecords();
    }

    function goToCreatePatientPanelFromCapture() {
        const forms = includedCaptureForms();
        const first = forms[0];
        if (first && formStates[first.id]?.photoDataUrl) {
            $('hrCreateReviewPhoto').src = formStates[first.id].photoDataUrl;
        }
        if ($('hrRecordDate')?.value && !$('hrCreateRecordDate').value) {
            $('hrCreateRecordDate').value = $('hrRecordDate').value;
        }

        recordTypeItemInputs('create').forEach((cb) => {
            cb.checked = forms.some((page) => page.id === cb.value);
        });
        syncAllFormsCheckbox('create');

        fillCreatePatientFields({});
        forceCreate = false;
        duplicateMatches = [];
        $('hrDuplicateMatches').hidden = true;
        setPanel('createPatient');
        checkDuplicates();
    }

    function continueFromProcessingMethod() {
        if (!createProcessingMethod) {
            Swal.fire({ icon: 'warning', title: 'Choose a method', text: 'Select Soft Copy / OCR or Image Only before continuing.' });
            return;
        }
        createUsedOcr = false;
        updateStepsForMode();

        if (createProcessingMethod === 'imageOnly') {
            resetFormCapturePhotos();
            if (mode === 'existing') {
                syncFormCaptureFromDetails();
            }
            setPanel('formCapture');
            showFormPage(0);
            return;
        }

        retakePhoto();
        setPanel('photo');
    }

    function resetWizard() {
        mode = 'existing';
        currentPanel = 'details';
        patientId = lockPatientPick ? patientId : null;
        patientLabel = lockPatientPick ? patientLabel : '';
        photoBlob = null;
        photoDataUrl = null;
        ocrRawText = '';
        createUsedOcr = false;
        createProcessingMethod = null;
        duplicateMatches = [];
        forceCreate = false;
        saving = false;

        initFormStates();
        resetFormCapturePhotos();

        ['hrRecordDate'].forEach((id) => {
            const el = $(id);
            if (el) el.value = '';
        });
        clearRecordTypeCheckboxes('both');

        ['hrFieldPatientName', 'hrFieldAddress', 'hrFieldDiagnosis', 'hrFieldRecordDate', 'hrFieldNotes'].forEach((id) => {
            const el = $(id);
            if (el) el.value = '';
        });

        clearCreatePatientFields();
        clearProcessingMethodSelection();

        const preview = $('hrPhotoPreview');
        const camera = $('hrCameraArea');
        const ocrStatus = $('hrOcrStatus');
        const dupBox = $('hrDuplicateMatches');
        if (preview) preview.hidden = true;
        if (camera) camera.hidden = false;
        if (ocrStatus) {
            ocrStatus.hidden = true;
            ocrStatus.textContent = '';
        }
        if (dupBox) dupBox.hidden = true;

        const cameraInput = $('hrCameraInput');
        if (cameraInput) cameraInput.value = '';

        updateModeUi();
        setPanel('details');
        updatePatientPickVisibility();
    }

    function clearCreatePatientFields() {
        [
            'hrPatLastName', 'hrPatFirstName', 'hrPatMiddleName', 'hrPatBirthDate',
            'hrPatAge', 'hrPatPhilhealth', 'hrPatContact', 'hrCreateRecordDate',
        ].forEach((id) => {
            const el = $(id);
            if (el) el.value = '';
        });
        clearRecordTypeCheckboxes('create');
        ['hrPatSex', 'hrPatBarangay', 'hrPatCivilStatus'].forEach((id) => {
            const el = $(id);
            if (el) el.value = '';
        });
    }

    function updatePatientPickVisibility() {
        const pick = $('hrPatientPick');
        const selected = $('hrPatientSelected');
        if (!pick || mode === 'createPatient') return;

        if (patientId) {
            pick.hidden = true;
            if (selected) {
                selected.hidden = false;
                selected.innerHTML = `<strong>${escapeHtml(patientLabel)}</strong> <span class="text-muted">(ID: ${patientId})</span>`;
            }
        } else {
            pick.hidden = false;
            if (selected) selected.hidden = true;
        }
    }

    function open(options = {}) {
        if (!modalEl) return;

        lockPatientPick = !!options.patientId;
        resetWizard();

        if (options.patientId) {
            patientId = parseInt(options.patientId, 10);
            patientLabel = options.patientLabel || '';
            updatePatientPickVisibility();
        }

        modal.show();
    }

    function startCreatePatientMode() {
        mode = 'createPatient';
        createUsedOcr = false;
        createProcessingMethod = null;
        updateModeUi();
        clearProcessingMethodSelection();
        setPanel('processingMethod');
    }

    function recordTypeContainer(source = 'existing') {
        return $(source === 'create' ? 'hrCreateRecordTypes' : 'hrRecordTypes');
    }

    function recordTypeItemInputs(source = 'existing') {
        const container = recordTypeContainer(source);
        if (!container) {
            return [];
        }
        return [...container.querySelectorAll('input[data-hr-record-type]')];
    }

    function recordTypeDropdown(source = 'existing') {
        const container = recordTypeContainer(source);
        return container?.closest('.hr-form-type-dropdown') || null;
    }

    function updateFormTypeToggleLabel(source = 'existing') {
        const dropdown = recordTypeDropdown(source);
        const toggleText = dropdown?.querySelector('.hr-form-type-toggle-text');
        if (!toggleText) {
            return;
        }

        const items = recordTypeItemInputs(source);
        const checked = items.filter((cb) => cb.checked);
        const labels = checked.map((cb) => {
            return cb.closest('label')?.querySelector('.hr-form-type-label')?.textContent?.trim() || '';
        }).filter(Boolean);

        if (checked.length === 0) {
            toggleText.textContent = 'Select form type(s)…';
        } else if (checked.length === items.length) {
            toggleText.textContent = 'All Forms';
        } else if (checked.length === 1) {
            toggleText.textContent = labels[0];
        } else if (checked.length === 2) {
            toggleText.textContent = labels.join(', ');
        } else {
            toggleText.textContent = `${checked.length} forms selected`;
        }
    }

    function closeFormTypeDropdowns(exceptSource = null) {
        document.querySelectorAll('.hr-form-type-dropdown.is-open').forEach((dropdown) => {
            const menu = dropdown.querySelector('.hr-form-type-menu');
            const source = menu?.id === 'hrCreateRecordTypes' ? 'create' : 'existing';
            if (exceptSource && source === exceptSource) {
                return;
            }
            dropdown.classList.remove('is-open');
            if (menu) {
                menu.hidden = true;
            }
            const btn = dropdown.querySelector('.hr-form-type-toggle');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function toggleFormTypeDropdown(source = 'existing') {
        const dropdown = recordTypeDropdown(source);
        const menu = recordTypeContainer(source);
        const btn = dropdown?.querySelector('.hr-form-type-toggle');
        if (!dropdown || !menu || !btn) {
            return;
        }

        const willOpen = menu.hidden;
        closeFormTypeDropdowns();
        if (willOpen) {
            dropdown.classList.add('is-open');
            menu.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    function syncAllFormsCheckbox(source = 'existing') {
        const container = recordTypeContainer(source);
        if (!container) {
            return;
        }
        const allCb = container.querySelector('.hr-form-type-all');
        const items = recordTypeItemInputs(source);
        if (!allCb || items.length === 0) {
            return;
        }
        const checkedCount = items.filter((cb) => cb.checked).length;
        allCb.checked = checkedCount === items.length;
        allCb.indeterminate = checkedCount > 0 && checkedCount < items.length;
        updateFormTypeToggleLabel(source);
    }

    function setAllRecordTypes(source, checked) {
        recordTypeItemInputs(source).forEach((cb) => {
            cb.checked = checked;
        });
        syncAllFormsCheckbox(source);
    }

    function getSelectedRecordTypes(source = 'existing') {
        return recordTypeItemInputs(source)
            .filter((cb) => cb.checked)
            .map((cb) => cb.value);
    }

    function clearRecordTypeCheckboxes(source = 'both') {
        const ids = source === 'both' ? ['hrRecordTypes', 'hrCreateRecordTypes'] : [source === 'create' ? 'hrCreateRecordTypes' : 'hrRecordTypes'];
        ids.forEach((id) => {
            const container = $(id);
            if (!container) {
                return;
            }
            container.querySelectorAll('input[data-hr-record-type]').forEach((cb) => {
                cb.checked = id === 'hrCreateRecordTypes' && cb.value === 'pcsf';
            });
            const allCb = container.querySelector('.hr-form-type-all');
            if (allCb) {
                allCb.checked = false;
                allCb.indeterminate = false;
            }
            syncAllFormsCheckbox(id === 'hrCreateRecordTypes' ? 'create' : 'existing');
        });
        closeFormTypeDropdowns();
    }

    function syncRecordTypesFromCreateToExisting() {
        const createTypes = getSelectedRecordTypes('create');
        const existingContainer = $('hrRecordTypes');
        if (!existingContainer || createTypes.length === 0) {
            return;
        }
        recordTypeItemInputs('existing').forEach((cb) => {
            cb.checked = createTypes.includes(cb.value);
        });
        syncAllFormsCheckbox('existing');
    }

    function bindRecordTypeLists() {
        [
            { id: 'hrRecordTypes', toggleId: 'hrRecordTypesToggle', source: 'existing' },
            { id: 'hrCreateRecordTypes', toggleId: 'hrCreateRecordTypesToggle', source: 'create' },
        ].forEach(({ id, toggleId, source }) => {
            const container = $(id);
            if (!container || container.dataset.hrBound === '1') {
                return;
            }
            container.dataset.hrBound = '1';

            $(toggleId)?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleFormTypeDropdown(source);
            });

            container.addEventListener('change', (e) => {
                const target = e.target;
                if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') {
                    return;
                }
                if (target.classList.contains('hr-form-type-all')) {
                    setAllRecordTypes(source, target.checked);
                    return;
                }
                if (target.dataset.hrRecordType !== undefined) {
                    syncAllFormsCheckbox(source);
                }
            });

            updateFormTypeToggleLabel(source);
        });

        if (!document.body.dataset.hrFormDropdownBound) {
            document.body.dataset.hrFormDropdownBound = '1';
            document.addEventListener('click', (e) => {
                if (e.target.closest('.hr-form-type-dropdown')) {
                    return;
                }
                closeFormTypeDropdowns();
            });
        }

        modalEl?.addEventListener('hidden.bs.modal', () => {
            closeFormTypeDropdowns();
        });
    }

    function validateExistingDetails() {
        if (!patientId) {
            Swal.fire({ icon: 'warning', title: 'Select a patient', text: 'Search and select the patient for this historical record.' });
            return false;
        }
        if (getSelectedRecordTypes('existing').length === 0) {
            Swal.fire({ icon: 'warning', title: 'Select form type(s)', text: 'Check at least one form type you are adding.' });
            return false;
        }
        return true;
    }

    function handlePhotoFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            Swal.fire({ icon: 'error', title: 'Invalid photo', text: 'Please capture an image using the camera.' });
            return;
        }

        if (file.size > 8 * 1024 * 1024) {
            Swal.fire({ icon: 'error', title: 'Photo too large', text: 'Maximum photo size is 8 MB.' });
            return;
        }

        photoBlob = file;
        const reader = new FileReader();
        reader.onload = () => {
            photoDataUrl = reader.result;
            $('hrPhotoImg').src = photoDataUrl;
            $('hrPhotoPreview').hidden = false;
            $('hrCameraArea').hidden = true;
            updatePhotoActions();
        };
        reader.readAsDataURL(file);
    }

    function retakePhoto() {
        photoBlob = null;
        photoDataUrl = null;
        ocrRawText = '';
        createUsedOcr = false;
        $('hrPhotoPreview').hidden = true;
        $('hrCameraArea').hidden = false;
        const ocrStatus = $('hrOcrStatus');
        if (ocrStatus) {
            ocrStatus.hidden = true;
            ocrStatus.textContent = '';
        }
        $('hrCameraInput').value = '';
    }

    function loadTesseract() {
        if (tesseractLoaded && window.Tesseract) return Promise.resolve(window.Tesseract);
        if (tesseractLoading) return tesseractLoading;

        tesseractLoading = new Promise((resolve, reject) => {
            if (window.Tesseract) {
                tesseractLoaded = true;
                resolve(window.Tesseract);
                return;
            }
            const script = document.createElement('script');
            script.src = TESSERACT_SRC;
            script.onload = () => {
                tesseractLoaded = true;
                resolve(window.Tesseract);
            };
            script.onerror = () => reject(new Error('Unable to load OCR library.'));
            document.head.appendChild(script);
        });

        return tesseractLoading;
    }

    function findAfter(text, patterns) {
        for (const pattern of patterns) {
            const re = new RegExp(`${pattern}\\s*[:\\-]\\s*(.+)`, 'i');
            const m = text.match(re);
            if (m) return m[1].trim();
        }
        return '';
    }

    function parseOcrHistorical(text) {
        const joined = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean).join('\n');
        return {
            patient_name: findAfter(joined, ['Patient Name', 'Name of Patient', 'Pangalan', 'Name']),
            address: findAfter(joined, ['Address', 'Tirahan', 'Residential Address']),
            diagnosis: findAfter(joined, ['Diagnosis', 'Impression', 'Dx']),
            record_date: findAfter(joined, ['Date', 'Petsa']),
            notes: text.split(/\r?\n/).slice(0, 8).join('\n'),
        };
    }

    function splitFullName(fullName) {
        fullName = (fullName || '').trim();
        if (!fullName) return { last_name: '', first_name: '', middle_name: '' };

        if (fullName.includes(',')) {
            const [last, rest] = fullName.split(',').map((s) => s.trim());
            const parts = rest.split(/\s+/);
            return {
                last_name: last,
                first_name: parts[0] || '',
                middle_name: parts.slice(1).join(' '),
            };
        }

        const parts = fullName.split(/\s+/);
        if (parts.length === 1) return { last_name: parts[0], first_name: '', middle_name: '' };
        if (parts.length === 2) return { last_name: parts[1], first_name: parts[0], middle_name: '' };
        return {
            first_name: parts[0],
            middle_name: parts.slice(1, -1).join(' '),
            last_name: parts[parts.length - 1],
        };
    }

    function parseOcrPatient(text) {
        const joined = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean).join('\n');
        const fullName = findAfter(joined, ['Patient Name', 'Name of Patient', 'Pangalan', 'Name', 'Complete Name']);
        const names = splitFullName(fullName);
        const address = findAfter(joined, ['Address', 'Tirahan', 'Residential Address']);
        const birthRaw = findAfter(joined, ['Birth Date', 'Date of Birth', 'Birthday', 'DOB', 'Petsa ng Kapanganakan']);
        const sexRaw = findAfter(joined, ['Sex', 'Gender', 'Kasarian']);
        const philhealth = findAfter(joined, ['PhilHealth', 'Philhealth No', 'PIN', 'PhilHealth No']);
        const civil = findAfter(joined, ['Civil Status', 'Marital Status']);
        const contact = findAfter(joined, ['Contact', 'Contact Number', 'Mobile', 'Cellphone', 'Phone']);

        let sex = '';
        if (/^m(ale)?$/i.test(sexRaw)) sex = 'Male';
        if (/^f(emale)?$/i.test(sexRaw)) sex = 'Female';

        let barangay = '';
        const barangays = window.HR_BARANGAYS || [];
        barangays.forEach((b) => {
            if (address && address.toLowerCase().includes(b.toLowerCase())) {
                barangay = b;
            }
        });

        let birthDate = '';
        if (birthRaw) {
            const ts = Date.parse(birthRaw);
            if (!Number.isNaN(ts)) {
                birthDate = new Date(ts).toISOString().slice(0, 10);
            }
        }

        return {
            ...names,
            birth_date: birthDate,
            sex,
            address_barangay: barangay,
            philhealth_no: philhealth.replace(/\s+/g, ''),
            civil_status: civil,
            contact_number: contact,
            record_date: findAfter(joined, ['Date', 'Petsa']),
        };
    }

    function fillCreatePatientFields(data = {}) {
        $('hrPatLastName').value = data.last_name || '';
        $('hrPatFirstName').value = data.first_name || '';
        $('hrPatMiddleName').value = data.middle_name || '';
        $('hrPatBirthDate').value = data.birth_date || '';
        $('hrPatSex').value = data.sex || '';
        $('hrPatBarangay').value = data.address_barangay || '';
        $('hrPatPhilhealth').value = data.philhealth_no || '';
        $('hrPatCivilStatus').value = data.civil_status || '';
        $('hrPatContact').value = data.contact_number || '';
        if (data.record_date) $('hrCreateRecordDate').value = data.record_date;
        updateAgeField();
    }

    function updateAgeField() {
        const dob = $('hrPatBirthDate')?.value;
        const ageEl = $('hrPatAge');
        if (!ageEl) return;
        if (!dob) {
            ageEl.value = '';
            return;
        }
        const birth = new Date(dob);
        if (Number.isNaN(birth.getTime())) {
            ageEl.value = '';
            return;
        }
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age -= 1;
        ageEl.value = age >= 0 ? String(age) : '';
    }

    function goToCreatePatientPanel(ocrData) {
        if (photoDataUrl) {
            $('hrCreateReviewPhoto').src = photoDataUrl;
        }
        fillCreatePatientFields(ocrData || {});
        forceCreate = false;
        duplicateMatches = [];
        $('hrDuplicateMatches').hidden = true;
        setPanel('createPatient');
        checkDuplicates();
    }

    async function runOcr() {
        if (!photoBlob) {
            Swal.fire({ icon: 'warning', title: 'No photo', text: 'Take a photo first before running OCR.' });
            return;
        }

        const status = $('hrOcrStatus');
        if (status) {
            status.hidden = false;
            status.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Running OCR…';
        }

        try {
            const Tesseract = await loadTesseract();
            const result = await Tesseract.recognize(photoBlob, 'eng', {
                logger: (m) => {
                    if (status && m.status === 'recognizing text') {
                        status.textContent = `OCR ${Math.round((m.progress || 0) * 100)}%…`;
                    }
                },
            });
            ocrRawText = (result.data?.text || '').trim();

            if (status) status.hidden = true;

            if (mode === 'createPatient') {
                if (createProcessingMethod !== 'ocr') return;
                createUsedOcr = true;
                const parsed = parseOcrPatient(ocrRawText);
                goToCreatePatientPanel(parsed);
                return;
            }

            const fields = parseOcrHistorical(ocrRawText);
            $('hrFieldPatientName').value = fields.patient_name || '';
            $('hrFieldAddress').value = fields.address || '';
            $('hrFieldDiagnosis').value = fields.diagnosis || '';
            $('hrFieldRecordDate').value = fields.record_date || ($('hrRecordDate')?.value || '');
            $('hrFieldNotes').value = fields.notes || '';
            if (photoDataUrl) $('hrReviewPhoto').src = photoDataUrl;
            setPanel('review');
        } catch (err) {
            if (status) status.hidden = false;
            Swal.fire({
                icon: 'error',
                title: 'OCR failed',
                text: err.message || 'Could not extract text. You can still save the original photo.',
            });
        }
    }

    function getCreatePatientData() {
        return {
            last_name: $('hrPatLastName').value.trim(),
            first_name: $('hrPatFirstName').value.trim(),
            middle_name: $('hrPatMiddleName').value.trim(),
            birth_date: $('hrPatBirthDate').value.trim(),
            sex: $('hrPatSex').value.trim(),
            address_barangay: $('hrPatBarangay').value.trim(),
            philhealth_no: $('hrPatPhilhealth').value.trim(),
            civil_status: $('hrPatCivilStatus').value.trim(),
            contact_number: $('hrPatContact').value.trim(),
        };
    }

    function validateCreatePatientFields() {
        const data = getCreatePatientData();
        const missing = [];
        if (getSelectedRecordTypes('create').length === 0 && !isImageOnlyFlow()) {
            Swal.fire({ icon: 'warning', title: 'Select form type(s)', text: 'Check at least one form type you are adding.' });
            return false;
        }
        if (isImageOnlyFlow() && includedCaptureForms().length === 0) {
            Swal.fire({ icon: 'warning', title: 'No forms captured', text: 'Add at least one form photo before creating the patient.' });
            return false;
        }
        return true;
    }

    async function checkDuplicates() {
        const data = getCreatePatientData();
        if (!data.last_name && !data.first_name && !data.philhealth_no) return;

        const params = new URLSearchParams({
            action: 'duplicates',
            last_name: data.last_name,
            first_name: data.first_name,
            birth_date: data.birth_date,
            philhealth_no: data.philhealth_no,
        });

        try {
            const res = await fetch(`${API_URL}?${params}`, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.success) return;
            duplicateMatches = json.duplicates || [];
            renderDuplicateMatches();
        } catch (_) {
            /* non-blocking */
        }
    }

    function renderDuplicateMatches() {
        const box = $('hrDuplicateMatches');
        const list = $('hrDuplicateList');
        if (!box || !list) return;

        if (!duplicateMatches.length) {
            box.hidden = true;
            list.innerHTML = '';
            return;
        }

        box.hidden = false;
        list.innerHTML = duplicateMatches.map((m) => `
            <div class="hr-duplicate-card mb-2 p-2 border rounded">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div class="small">
                        <strong>${escapeHtml(m.full_name)}</strong><br>
                        <span class="text-muted">${escapeHtml(m.patient_id)} · ${escapeHtml(m.birth_display)} · ${escapeHtml(m.sex)}</span><br>
                        <span class="text-warning-emphasis">${escapeHtml(m.match_reason)}</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary hr-use-existing" data-id="${m.id}" data-name="${escapeHtml(m.full_name)}">
                        Use Existing Patient
                    </button>
                </div>
            </div>
        `).join('');

        list.querySelectorAll('.hr-use-existing').forEach((btn) => {
            btn.addEventListener('click', () => {
                useExistingPatient(parseInt(btn.dataset.id, 10), btn.dataset.name);
            });
        });
    }

    function useExistingPatient(id, name) {
        mode = 'existing';
        patientId = id;
        patientLabel = name;
        forceCreate = false;
        updateModeUi();
        updatePatientPickVisibility();

        syncRecordTypesFromCreateToExisting();
        if (!$('hrRecordDate').value && $('hrCreateRecordDate').value) {
            $('hrRecordDate').value = $('hrCreateRecordDate').value;
        }

        Swal.fire({
            icon: 'info',
            title: 'Using existing patient',
            text: 'The photo will be attached to the selected patient. Confirm details and save.',
            timer: 2500,
            showConfirmButton: false,
        });

        if (photoBlob) {
            setPanel('photo');
        } else {
            setPanel('details');
        }
    }

    function buildExistingFormData(includeOcr, recordType, photoBlobArg, options = {}) {
        const fd = new FormData();
        const blob = photoBlobArg || photoBlob;
        fd.append('patient_id', String(patientId));
        fd.append('record_type', recordType);
        fd.append('record_date', String(options.recordDate ?? $('hrRecordDate').value).trim());
        fd.append('photo', blob, blob.name || `historical-${recordType}-${Date.now()}.jpg`);

        if (includeOcr) {
            fd.append('ocr_raw_text', ocrRawText);
            fd.append('reviewed_data', JSON.stringify({
                patient_name: $('hrFieldPatientName').value.trim(),
                address: $('hrFieldAddress').value.trim(),
                diagnosis: $('hrFieldDiagnosis').value.trim(),
                record_date: $('hrFieldRecordDate').value.trim(),
                notes: $('hrFieldNotes').value.trim(),
            }));
        }
        return fd;
    }

    function buildCreateFormData(recordType, photoBlobArg) {
        const fd = new FormData();
        const blob = photoBlobArg || photoBlob;
        fd.append('create_patient', '1');
        fd.append('force_create', forceCreate ? '1' : '0');
        fd.append('record_type', recordType);
        fd.append('record_date', $('hrCreateRecordDate').value.trim());
        fd.append('photo', blob, blob.name || `historical-${recordType}-${Date.now()}.jpg`);
        fd.append('patient_data', JSON.stringify(getCreatePatientData()));

        if (createUsedOcr && ocrRawText) {
            fd.append('ocr_raw_text', ocrRawText);
            fd.append('reviewed_data', JSON.stringify({
                patient_name: [$('hrPatFirstName').value, $('hrPatMiddleName').value, $('hrPatLastName').value].filter(Boolean).join(' '),
                address: $('hrPatBarangay').value,
                record_date: $('hrCreateRecordDate').value.trim(),
                notes: 'Created from photographed old form',
            }));
        }
        return fd;
    }

    async function saveExistingFormCaptureRecords() {
        if (saving || !validateFormCapture()) return;

        saving = true;
        const saveBtn = $('hrBtnSaveRecords');
        const originalHtml = saveBtn?.innerHTML || '';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
        }

        try {
            const forms = includedCaptureForms();
            const recordDate = $('hrRecordDate').value.trim();

            for (const page of forms) {
                const st = formStates[page.id];
                const res = await fetch(API_URL, {
                    method: 'POST',
                    body: buildExistingFormData(false, page.id, st.photoBlob, { recordDate }),
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || `Unable to save ${page.label}.`);
                }
            }

            modal.hide();
            const msg = forms.length === 1
                ? 'Historical record saved.'
                : `${forms.length} historical records saved.`;
            await Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: msg,
                timer: 2200,
                showConfirmButton: false,
            });

            redirectAfterSave(patientId);
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Unable to save', text: err.message || 'Please try again.' });
        } finally {
            saving = false;
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHtml;
            }
        }
    }

    async function saveExistingRecord(includeOcr) {
        if (saving || !photoBlob || !validateExistingDetails()) return;

        saving = true;
        const saveBtn = includeOcr ? $('hrBtnSaveDigital') : $('hrBtnSavePhoto');
        const originalHtml = saveBtn?.innerHTML || '';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
        }

        try {
            const types = getSelectedRecordTypes('existing');
            let savedCount = 0;

            for (const recordType of types) {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    body: buildExistingFormData(includeOcr, recordType),
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Save failed.');
                }
                savedCount += 1;
            }

            modal.hide();
            const typeLabel = savedCount === 1 ? 'Historical record saved.' : `${savedCount} historical records saved.`;
            await Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: includeOcr ? `${typeLabel} Reviewed OCR copy included.` : typeLabel,
                timer: 2200,
                showConfirmButton: false,
            });

            redirectAfterSave(patientId);
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Unable to save', text: err.message || 'Please try again.' });
        } finally {
            saving = false;
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHtml;
            }
        }
    }

    async function saveCreatePatient() {
        const usingCapture = isImageOnlyFlow() && includedCaptureForms().length > 0;
        if (saving || (!photoBlob && !usingCapture) || !validateCreatePatientFields()) return;

        await checkDuplicates();
        if (duplicateMatches.length && !forceCreate) {
            Swal.fire({
                icon: 'warning',
                title: 'Possible duplicate found',
                html: 'A similar patient may already exist. Review the matches below, use an existing patient, or confirm to create anyway.',
                showCancelButton: true,
                confirmButtonText: 'Create anyway',
                cancelButtonText: 'Review matches',
            }).then((result) => {
                if (result.isConfirmed) {
                    forceCreate = true;
                    saveCreatePatient();
                }
            });
            return;
        }

        saving = true;
        const btn = $('hrBtnCreatePatient');
        const originalHtml = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating…';
        }

        try {
            const types = usingCapture
                ? includedCaptureForms().map((page) => page.id)
                : getSelectedRecordTypes('create');
            const firstBlob = usingCapture
                ? formStates[types[0]]?.photoBlob
                : photoBlob;

            const res = await fetch(API_URL, {
                method: 'POST',
                body: buildCreateFormData(types[0], firstBlob),
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Create failed.');

            const pid = data.result?.patient_id;
            if (pid && types.length > 1) {
                const previousPatientId = patientId;
                patientId = pid;
                const createDate = $('hrCreateRecordDate').value.trim();
                for (let i = 1; i < types.length; i += 1) {
                    const blob = usingCapture ? formStates[types[i]]?.photoBlob : photoBlob;
                    const extraRes = await fetch(API_URL, {
                        method: 'POST',
                        body: buildExistingFormData(false, types[i], blob, { recordDate: createDate }),
                        credentials: 'same-origin',
                    });
                    const extraData = await extraRes.json();
                    if (!extraRes.ok || !extraData.success) {
                        throw new Error(extraData.message || `Unable to save ${types[i]}.`);
                    }
                }
                patientId = previousPatientId;
            }

            modal.hide();
            const savedMsg = types.length > 1
                ? `Patient created with ${types.length} historical form records attached.`
                : 'Patient record created and old form attached to Historical Records.';
            await Swal.fire({
                icon: 'success',
                title: 'Patient created',
                text: savedMsg,
                timer: 2400,
                showConfirmButton: false,
            });

            if (data.result?.folder_url) {
                window.location.href = data.result.folder_url;
            } else if (pid) {
                redirectAfterSave(pid);
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Unable to create patient', text: err.message || 'Please try again.' });
        } finally {
            saving = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    function redirectAfterSave(pid) {
        const onSaved = moduleOptions.onSaved || window.HR_ON_SAVED;
        if (typeof onSaved === 'function') {
            onSaved({ patient_id: pid });
        } else if (pid) {
            window.location.href = `${BASE_URL}/patient-folder.php?id=${pid}&tab=historical`;
        }
    }

    function bindEvents() {
        $('hrBtnNext')?.addEventListener('click', () => {
            if (!validateExistingDetails()) return;
            clearProcessingMethodSelection();
            setPanel('processingMethod');
        });

        $('hrBtnBack')?.addEventListener('click', () => {
            if (currentPanel === 'review' || currentPanel === 'createPatient') {
                if (isImageOnlyFlow()) {
                    setPanel('formCapture');
                    showFormPage(formPages.length - 1);
                } else {
                    setPanel('photo');
                }
                return;
            }
            if (currentPanel === 'formCapture') {
                if (currentFormIndex > 0) {
                    showFormPage(currentFormIndex - 1);
                    return;
                }
                setPanel('processingMethod');
                return;
            }
            if (currentPanel === 'photo') {
                retakePhoto();
                setPanel('processingMethod');
                return;
            }
            if (currentPanel === 'processingMethod' && mode === 'existing') {
                setPanel('details');
            }
        });

        $('hrBtnCreateFromForm')?.addEventListener('click', startCreatePatientMode);

        document.querySelectorAll('.hr-method-card').forEach((card) => {
            card.addEventListener('click', () => {
                selectProcessingMethod(card.dataset.hrMethod);
            });
        });
        $('hrBtnContinueMethod')?.addEventListener('click', continueFromProcessingMethod);

        document.querySelectorAll('.hr-btn-take-photo').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelector(`.hr-camera-input[data-form-id="${btn.dataset.formId}"]`)?.click();
            });
        });

        document.querySelectorAll('.hr-btn-choose-image').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelector(`.hr-upload-input[data-form-id="${btn.dataset.formId}"]`)?.click();
            });
        });

        document.querySelectorAll('.hr-camera-input, .hr-upload-input').forEach((input) => {
            input.addEventListener('change', (e) => {
                const file = e.target.files?.[0];
                if (file) handleFormCapturePhoto(input.dataset.formId, file);
            });
        });

        document.querySelectorAll('.hr-btn-retake').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.dataset.formId) {
                    retakeFormCapturePhoto(btn.dataset.formId);
                } else {
                    retakePhoto();
                }
            });
        });

        document.querySelectorAll('.hr-form-include').forEach((cb) => {
            cb.addEventListener('change', () => {
                toggleFormInclude(cb.dataset.formId, cb.checked);
            });
        });

        document.querySelectorAll('.hr-form-nav-item').forEach((btn) => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.formIndex, 10);
                if (!Number.isNaN(index)) showFormPage(index);
            });
        });

        $('hrBtnFormNext')?.addEventListener('click', () => {
            if (currentFormIndex < formPages.length - 1) {
                showFormPage(currentFormIndex + 1);
            } else {
                continueFromFormCapture();
            }
        });

        $('hrBtnSaveRecords')?.addEventListener('click', saveExistingFormCaptureRecords);

        $('hrBtnTakePhoto')?.addEventListener('click', () => $('hrCameraInput')?.click());
        $('hrCameraInput')?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (file) handlePhotoFile(file);
        });

        $('hrBtnOcr')?.addEventListener('click', runOcr);

        $('hrBtnSavePhoto')?.addEventListener('click', () => {
            if (mode === 'createPatient') {
                if (createProcessingMethod !== 'imageOnly') return;
                createUsedOcr = false;
                goToCreatePatientPanel({});
                return;
            }
            saveExistingRecord(false);
        });

        $('hrBtnSaveDigital')?.addEventListener('click', () => saveExistingRecord(true));
        $('hrBtnCreatePatient')?.addEventListener('click', saveCreatePatient);

        $('hrPatBirthDate')?.addEventListener('change', updateAgeField);
        $('hrPatBirthDate')?.addEventListener('input', updateAgeField);

        ['hrPatLastName', 'hrPatFirstName', 'hrPatPhilhealth', 'hrPatBirthDate'].forEach((id) => {
            $(id)?.addEventListener('blur', checkDuplicates);
        });

        modalEl?.addEventListener('hidden.bs.modal', () => {
            lockPatientPick = false;
            resetWizard();
        });
    }

    function init(cfg = {}) {
        moduleOptions = cfg;
        modalEl = $('historicalRecordModal');
        if (!modalEl) return;

        formPages = loadFormPages();
        initFormStates();

        modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bindEvents();
        bindRecordTypeLists();

        window.HistoricalRecordPatientPick = (patient) => {
            if (!patient?.id) return;
            mode = 'existing';
            patientId = parseInt(patient.id, 10);
            patientLabel = patient.formal_name || patient.full_name || patient.patient_id || `Patient #${patientId}`;
            updateModeUi();
            updatePatientPickVisibility();
        };
    }

    return { init, open };
})();

document.addEventListener('DOMContentLoaded', () => {
    HistoricalRecordWizard.init({ onSaved: window.HR_ON_SAVED || null });

    document.getElementById('btnAddExistingRecord')?.addEventListener('click', () => {
        HistoricalRecordWizard.open({});
    });

    document.getElementById('btnAddExistingRecordFolder')?.addEventListener('click', () => {
        const folder = window.PATIENT_FOLDER || {};
        HistoricalRecordWizard.open({
            patientId: folder.patientId,
            patientLabel: folder.patientName || '',
        });
    });
});
