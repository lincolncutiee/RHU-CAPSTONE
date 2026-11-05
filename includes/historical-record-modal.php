<?php
/**
 * Add Existing Record — modal wizard (camera photo + optional OCR).
 * Requires HistoricalRecord and $canAddHistorical (bool).
 */
require_once __DIR__ . '/../core/HistoricalRecord.php';
require_once __DIR__ . '/../core/MagallanesAddress.php';

if (empty($canAddHistorical)) {
    return;
}

$hrRecordTypes = HistoricalRecord::recordTypeOptions();
$hrFormPages = HistoricalRecord::officialFormPages();
$hrBarangays = MagallanesAddress::barangays();
$hrCivilStatuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced', 'Annulled'];
$hrRequiredPatientFields = array_keys(HistoricalRecord::requiredPatientVerifyFields());
$hrFieldLabel = static function (string $text, string $fieldKey) use ($hrRequiredPatientFields): string {
    $safe = htmlspecialchars($text);
    if (in_array($fieldKey, $hrRequiredPatientFields, true)) {
        return $safe . ' <span class="text-danger">*</span>';
    }
    return $safe . ' <span class="text-muted fw-normal">(optional)</span>';
};
?>
<div class="modal fade" id="historicalRecordModal" tabindex="-1" aria-labelledby="historicalRecordModalLabel" aria-hidden="true" data-bs-backdrop="static" data-form-pages="<?= htmlspecialchars(json_encode($hrFormPages, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" id="hrModalDialog">
        <div class="modal-content hr-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold" id="historicalRecordModalLabel">
                    <i class="bi bi-camera me-2 text-primary"></i> <span id="hrModalTitleText">Add Existing Record</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="hr-steps" id="hrSteps" aria-hidden="true">
                    <span class="hr-step is-active" data-hr-step="details">1. Details</span>
                    <span class="hr-step" data-hr-step="photo">2. Photo</span>
                    <span class="hr-step" data-hr-step="review">3. Review</span>
                </div>

                <!-- Step: existing patient details -->
                <div class="hr-panel" id="hrPanelDetails">
                    <div class="hr-patient-pick mb-3" id="hrPatientPick">
                        <label class="form-label fw-semibold small">Patient</label>
                        <div data-patient-search data-patient-search-variant="historical-pick">
                            <div class="patient-search-widget">
                                <div class="patient-search-input-wrap">
                                    <i class="bi bi-search"></i>
                                    <input type="search" class="patient-search-input" placeholder="Search patient name or ID…" autocomplete="off" aria-label="Search patient">
                                    <button type="button" class="patient-search-clear d-none" aria-label="Clear search"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="patient-search-dropdown">
                                    <div class="patient-search-statusbar" hidden></div>
                                    <div class="patient-search-loading" hidden><span class="spinner-border spinner-border-sm text-primary"></span> Searching…</div>
                                    <ul class="patient-search-results" role="listbox"></ul>
                                    <div class="patient-search-empty" hidden>Type to search patients…</div>
                                </div>
                            </div>
                        </div>
                        <div class="hr-patient-selected mt-2" id="hrPatientSelected" hidden></div>
                    </div>

                    <div class="hr-not-encoded mt-3 pt-3 border-top" id="hrNotEncoded">
                        <p class="small text-muted mb-2 mb-0">Patient not yet encoded?</p>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="hrBtnCreateFromForm">
                            <i class="bi bi-plus-lg"></i> Create Patient from Existing Form
                        </button>
                    </div>

                    <div id="hrExistingDetails" class="mt-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small" for="hrRecordTypesToggle">Form Type(s) to Add</label>
                            <p class="form-text mb-2">Open the dropdown and check each old form you are adding.</p>
                            <?php
                            $hrListId = 'hrRecordTypes';
                            $hrToggleId = 'hrRecordTypesToggle';
                            $hrInputName = 'hr_record_types[]';
                            $hrDefaultOldPatient = false;
                            include __DIR__ . '/partials/hr-form-type-dropdown.php';
                            ?>
                        </div>

                        <div class="mb-2">
                            <label for="hrRecordDate" class="form-label fw-semibold small">Date / Year</label>
                            <input type="text" class="form-control form-control-sm" id="hrRecordDate" placeholder="e.g. 2018 or 2019-05-12" list="hrYearSuggestions">
                            <datalist id="hrYearSuggestions">
                                <?php for ($y = (int) date('Y'); $y >= 1990; $y--): ?>
                                    <option value="<?= $y ?>"></option>
                                <?php endfor; ?>
                            </datalist>
                            <div class="form-text">Enter a year or full date for the old form.</div>
                        </div>
                    </div>
                </div>

                <!-- Step: create patient — choose processing method -->
                <div class="hr-panel" id="hrPanelProcessingMethod" hidden>
                    <h6 class="fw-semibold mb-1">How do you want to save this old form?</h6>
                    <p class="text-muted small mb-3" id="hrProcessingMethodIntro">Choose one option before continuing.</p>
                    <div class="hr-method-options">
                        <button type="button" class="hr-method-card" data-hr-method="ocr" aria-pressed="false">
                            <span class="hr-method-icon">📄</span>
                            <span class="hr-method-title">Soft Copy / OCR</span>
                            <span class="hr-method-desc">Convert the old form into a digital soft copy using OCR. Use this only when the form is readable enough.</span>
                        </button>
                        <button type="button" class="hr-method-card" data-hr-method="imageOnly" aria-pressed="false">
                            <span class="hr-method-icon">🖼️</span>
                            <span class="hr-method-title">Image Only</span>
                            <span class="hr-method-desc">Save the old form as an image file. Take a photo or upload any image (scanned copy, screenshot, etc.). No OCR — the original image is kept as-is.</span>
                        </button>
                    </div>
                </div>

                <!-- Step: Image Only — 7 official form photo capture -->
                <div class="hr-panel" id="hrPanelFormCapture" hidden>
                    <p class="text-muted small mb-3" id="hrFormCaptureIntro">For each form you add, take a photo or choose any image file (JPG, PNG, etc.) from your device. Soft Copy / OCR is not used in this mode.</p>
                    <div class="hr-wizard-layout">
                        <aside class="hr-form-nav" id="hrFormNav" aria-label="Official RHU forms">
                            <div class="hr-form-nav-title"><i class="bi bi-folder2-open me-1"></i> Official RHU Forms</div>
                            <?php foreach ($hrFormPages as $index => $page): ?>
                                <button type="button"
                                        class="hr-form-nav-item<?= $index === 0 ? ' is-active' : '' ?>"
                                        data-form-id="<?= htmlspecialchars($page['id']) ?>"
                                        data-form-index="<?= (int) $index ?>"
                                        title="<?= htmlspecialchars($page['label']) ?>">
                                    <span class="hr-form-nav-num"><?= $index + 1 ?></span>
                                    <span class="hr-form-nav-label"><?= htmlspecialchars($page['label']) ?></span>
                                    <span class="hr-form-nav-status" aria-hidden="true"><i class="bi bi-circle"></i></span>
                                </button>
                            <?php endforeach; ?>
                        </aside>

                        <div class="hr-form-main">
                            <?php foreach ($hrFormPages as $index => $page): ?>
                                <div class="hr-form-page" id="hrFormPage-<?= htmlspecialchars($page['id']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
                                    <h6 class="hr-form-page-title"><?= htmlspecialchars($page['label']) ?></h6>
                                    <?php if (!empty($page['subtitle'])): ?>
                                        <p class="text-muted small mb-3"><?= htmlspecialchars($page['subtitle']) ?></p>
                                    <?php endif; ?>

                                    <label class="hr-include-form mb-3">
                                        <input type="checkbox" class="hr-form-include" data-form-id="<?= htmlspecialchars($page['id']) ?>">
                                        <span>Add this form for this patient</span>
                                    </label>

                                    <div class="hr-form-camera" data-form-id="<?= htmlspecialchars($page['id']) ?>">
                                        <input type="file"
                                               class="hr-camera-input"
                                               data-form-id="<?= htmlspecialchars($page['id']) ?>"
                                               accept="image/*"
                                               capture="environment"
                                               hidden>
                                        <input type="file"
                                               class="hr-upload-input"
                                               data-form-id="<?= htmlspecialchars($page['id']) ?>"
                                               accept="image/*"
                                               hidden>
                                        <div class="hr-form-camera-area">
                                            <div class="hr-form-image-actions">
                                                <button type="button" class="btn btn-primary btn-sm hr-btn-take-photo" data-form-id="<?= htmlspecialchars($page['id']) ?>">
                                                    <i class="bi bi-camera"></i> Take Photo
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-sm hr-btn-choose-image" data-form-id="<?= htmlspecialchars($page['id']) ?>">
                                                    <i class="bi bi-image"></i> Choose Image
                                                </button>
                                            </div>
                                        </div>
                                        <div class="hr-photo-preview" hidden>
                                            <img class="hr-photo-img" alt="Form image preview">
                                            <div class="hr-photo-actions mt-2">
                                                <button type="button" class="btn btn-outline-secondary btn-sm hr-btn-retake" data-form-id="<?= htmlspecialchars($page['id']) ?>">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Replace Image
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Step: photo (OCR / single photo) -->
                <div class="hr-panel" id="hrPanelPhoto" hidden>
                    <p class="text-muted small mb-3" id="hrPhotoIntro">Photograph the old physical form using your device camera. The original photo is always preserved.</p>

                    <div class="hr-camera-area" id="hrCameraArea">
                        <input type="file" id="hrCameraInput" accept="image/*" capture="environment" hidden>
                        <button type="button" class="btn btn-primary" id="hrBtnTakePhoto">
                            <i class="bi bi-camera"></i> Take Photo
                        </button>
                    </div>

                    <div class="hr-photo-preview" id="hrPhotoPreview" hidden>
                        <img id="hrPhotoImg" alt="Photographed old form preview">
                        <div class="hr-photo-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="hrBtnRetake">
                                <i class="bi bi-arrow-counterclockwise"></i> Retake Photo
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="hrBtnOcr">
                                <i class="bi bi-text-paragraph"></i> OCR &amp; Convert
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="hrBtnSavePhoto">
                                <i class="bi bi-check-lg"></i> <span id="hrBtnSavePhotoLabel">Save Record</span>
                            </button>
                        </div>
                        <div class="hr-ocr-status text-muted small mt-2" id="hrOcrStatus" hidden></div>
                    </div>
                </div>

                <!-- Step: existing patient OCR review -->
                <div class="hr-panel" id="hrPanelReview" hidden>
                    <p class="text-muted small mb-3">Review and correct the extracted information before saving. OCR results are not official until you confirm.</p>
                    <div class="hr-review-grid">
                        <div class="hr-review-photo">
                            <span class="form-label fw-semibold small d-block mb-2">Original Form</span>
                            <img id="hrReviewPhoto" alt="Original photographed form">
                        </div>
                        <div class="hr-review-fields">
                            <span class="form-label fw-semibold small d-block mb-2">Extracted Information</span>
                            <div class="mb-2">
                                <label for="hrFieldPatientName" class="form-label small mb-1"><?= $hrFieldLabel('Patient Name', 'patient_name') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrFieldPatientName">
                            </div>
                            <div class="mb-2">
                                <label for="hrFieldAddress" class="form-label small mb-1"><?= $hrFieldLabel('Address', 'address_barangay') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrFieldAddress">
                            </div>
                            <div class="mb-2">
                                <label for="hrFieldDiagnosis" class="form-label small mb-1"><?= $hrFieldLabel('Diagnosis', 'diagnosis') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrFieldDiagnosis">
                            </div>
                            <div class="mb-2">
                                <label for="hrFieldRecordDate" class="form-label small mb-1"><?= $hrFieldLabel('Date', 'record_date') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrFieldRecordDate">
                            </div>
                            <div class="mb-2">
                                <label for="hrFieldNotes" class="form-label small mb-1"><?= $hrFieldLabel('Additional Notes', 'notes') ?></label>
                                <textarea class="form-control form-control-sm" id="hrFieldNotes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step: create patient from form — verify patient info -->
                <div class="hr-panel" id="hrPanelCreatePatient" hidden>
                    <p class="text-muted small mb-3">Verify the required patient information before creating the record. OCR suggestions must be reviewed and corrected.</p>
                    <div class="hr-review-grid mb-3">
                        <div class="hr-review-photo">
                            <span class="form-label fw-semibold small d-block mb-2">Original Form</span>
                            <img id="hrCreateReviewPhoto" alt="Original photographed form">
                        </div>
                        <div>
                            <span class="form-label fw-semibold small d-block mb-2">Historical Form Details</span>
                            <div class="mb-3">
                                <label class="form-label small mb-1" for="hrCreateRecordTypesToggle">Form Type(s) to Add</label>
                                <?php
                                $hrListId = 'hrCreateRecordTypes';
                                $hrToggleId = 'hrCreateRecordTypesToggle';
                                $hrInputName = 'hr_create_record_types[]';
                                $hrDefaultOldPatient = true;
                                include __DIR__ . '/partials/hr-form-type-dropdown.php';
                                ?>
                            </div>
                            <div class="mb-2">
                                <label for="hrCreateRecordDate" class="form-label small mb-1">Form Date / Year</label>
                                <input type="text" class="form-control form-control-sm" id="hrCreateRecordDate" placeholder="e.g. 2018" list="hrYearSuggestions">
                            </div>
                        </div>
                    </div>

                    <div class="hr-create-patient-fields">
                        <span class="form-label fw-semibold small d-block mb-2">Patient Information</span>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="hrPatLastName" class="form-label small mb-1"><?= $hrFieldLabel('Last Name', 'last_name') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrPatLastName" required>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatFirstName" class="form-label small mb-1"><?= $hrFieldLabel('First Name', 'first_name') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrPatFirstName" required>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatMiddleName" class="form-label small mb-1"><?= $hrFieldLabel('Middle Name', 'middle_name') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrPatMiddleName">
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatBirthDate" class="form-label small mb-1"><?= $hrFieldLabel('Date of Birth', 'birth_date') ?></label>
                                <input type="date" class="form-control form-control-sm" id="hrPatBirthDate" required>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatAge" class="form-label small mb-1"><?= $hrFieldLabel('Age', 'age') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrPatAge" readonly placeholder="Auto-calculated">
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatSex" class="form-label small mb-1"><?= $hrFieldLabel('Sex', 'sex') ?></label>
                                <select class="form-select form-select-sm" id="hrPatSex" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatBarangay" class="form-label small mb-1"><?= $hrFieldLabel('Barangay', 'address_barangay') ?></label>
                                <select class="form-select form-select-sm" id="hrPatBarangay" required>
                                    <option value="">Select barangay</option>
                                    <?php foreach ($hrBarangays as $brgy): ?>
                                        <option value="<?= htmlspecialchars($brgy) ?>"><?= htmlspecialchars($brgy) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatPhilhealth" class="form-label small mb-1"><?= $hrFieldLabel('PhilHealth Number', 'philhealth_no') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrPatPhilhealth" required>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatCivilStatus" class="form-label small mb-1"><?= $hrFieldLabel('Civil Status', 'civil_status') ?></label>
                                <select class="form-select form-select-sm" id="hrPatCivilStatus" required>
                                    <option value="">Select</option>
                                    <?php foreach ($hrCivilStatuses as $cs): ?>
                                        <option value="<?= htmlspecialchars($cs) ?>"><?= htmlspecialchars($cs) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="hrPatContact" class="form-label small mb-1"><?= $hrFieldLabel('Contact Number', 'contact_number') ?></label>
                                <input type="text" class="form-control form-control-sm" id="hrPatContact" required>
                            </div>
                        </div>
                    </div>

                    <div class="hr-duplicate-matches mt-3" id="hrDuplicateMatches" hidden>
                        <div class="alert alert-warning py-2 px-3 mb-2 small">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Possible existing patient(s) found. Use an existing record instead of creating a duplicate.
                        </div>
                        <div id="hrDuplicateList"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" id="hrBtnCancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="hrBtnBack" hidden>Back</button>
                <button type="button" class="btn btn-primary btn-sm" id="hrBtnNext">Continue</button>
                <button type="button" class="btn btn-primary btn-sm" id="hrBtnContinueMethod" hidden>Continue</button>
                <button type="button" class="btn btn-primary btn-sm" id="hrBtnFormNext" hidden>Next</button>
                <button type="button" class="btn btn-success btn-sm" id="hrBtnSaveRecords" hidden>
                    <i class="bi bi-check-lg"></i> Save Records
                </button>
                <button type="button" class="btn btn-success btn-sm" id="hrBtnSaveDigital" hidden>
                    <i class="bi bi-check-lg"></i> Save Digital Copy
                </button>
                <button type="button" class="btn btn-success btn-sm" id="hrBtnCreatePatient" hidden>
                    <i class="bi bi-person-plus"></i> Create Patient &amp; Save Record
                </button>
            </div>
        </div>
    </div>
</div>
