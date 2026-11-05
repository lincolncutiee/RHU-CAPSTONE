<?php
/**
 * IT Staff Printing Module — form bundles and access helpers.
 */

require_once __DIR__ . '/Workflow.php';
require_once __DIR__ . '/ExtendedFormData.php';
require_once __DIR__ . '/PaperFormRenderer.php';
require_once __DIR__ . '/WorkflowStatus.php';

class ItPrintForms
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/it-print.php';
        }

        return self::$config;
    }

    public static function isLayoutTestMode(): bool
    {
        return !empty(self::config()['layout_test_mode']);
    }

    public static function bundles(): array
    {
        return self::config()['bundles'] ?? [];
    }

    public static function bundleKeys(): array
    {
        return array_keys(self::bundles());
    }

    public static function resolveFormIds(string $bundleKey): array
    {
        $bundles = self::bundles();
        if (!isset($bundles[$bundleKey])) {
            return [];
        }
        return self::sortFormIds($bundles[$bundleKey]['forms']);
    }

    /**
     * Canonical RHU print order (PCSF → … → EPRESS).
     *
     * @return list<string>
     */
    public static function formPrintOrder(): array
    {
        $order = self::config()['form_print_order'] ?? [
            'pcsf', 'itr', 'enrollment', 'history', 'personal', 'ekas', 'epress',
        ];
        return array_values(array_map('strval', $order));
    }

    /**
     * Sort / filter form IDs into the official print order. Never drops valid IDs
     * that are outside the order list — appends them after.
     *
     * @param list<string> $formIds
     * @return list<string>
     */
    public static function sortFormIds(array $formIds): array
    {
        $order = self::formPrintOrder();
        $rank = array_flip($order);
        $clean = [];
        foreach ($formIds as $id) {
            $id = trim((string) $id);
            if ($id !== '' && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        usort($clean, static function (string $a, string $b) use ($rank): int {
            $ra = $rank[$a] ?? 1000;
            $rb = $rank[$b] ?? 1000;
            return $ra <=> $rb;
        });
        return $clean;
    }

    public static function layoutForForm(string $formId): string
    {
        $map = self::config()['form_layouts'] ?? [];
        return (string) ($map[$formId] ?? 'a4-portrait');
    }

    /**
     * Infer a single preview layout for a form ID list.
     * Mixed paper sizes should be printed as separate official_documents jobs.
     *
     * @param list<string> $formIds
     */
    public static function inferLayout(array $formIds): string
    {
        $ids = self::sortFormIds($formIds);
        if ($ids === ['ekas', 'epress'] || $ids === ['epress', 'ekas']) {
            return 'a4-landscape-half';
        }
        if ($ids === ['pcsf']) {
            return 'legal-portrait';
        }
        if (count($ids) === 1) {
            return self::layoutForForm($ids[0]);
        }
        // Homogeneous A4 portrait stack (e.g. itr+enrollment+history)
        $layouts = array_unique(array_map([self::class, 'layoutForForm'], $ids));
        if ($layouts === ['a4-portrait']) {
            return 'a4-portrait';
        }
        if ($layouts === ['a4-landscape-half']) {
            return 'a4-landscape-half';
        }
        return 'a4-portrait';
    }

    public static function bundleLabel(string $bundleKey): string
    {
        return self::bundles()[$bundleKey]['label'] ?? $bundleKey;
    }

    public static function requireItRole(): void
    {
        requireLogin();
        $user = currentUser();
        if (($user['role'] ?? '') !== ROLE_IT) {
            http_response_code(403);
            die('Access denied. IT Staff only.');
        }
    }

    public static function assertCanView(array $patient): void
    {
        $user = currentUser();
        if (($user['role'] ?? '') !== ROLE_IT) {
            http_response_code(403);
            die('Access denied. IT Staff only.');
        }

        // IT Print archive may open any non-archived patient for print/reprint
        if (!empty($patient['is_archived'])) {
            http_response_code(403);
            die('This patient record is archived and cannot be printed.');
        }
    }

    /**
     * All consultations available in the Print module (one row per visit).
     *
     * @return list<array<string,mixed>>
     */
    public static function listPrintArchive(?mysqli $conn = null): array
    {
        $owned = $conn === null;
        $db = $conn ?? getDBConnection();

        $sql = "SELECT p.id AS patient_db_id, p.patient_id, p.first_name, p.middle_name, p.last_name, p.suffix,
                       p.philhealth_no, p.status AS patient_status, p.updated_at,
                       t.id AS consultation_id, t.date_of_consultation, t.it_data,
                       (
                         SELECT MAX(t2.id) FROM treatment_records t2 WHERE t2.patient_id = p.id
                       ) AS latest_consultation_id
                FROM treatment_records t
                INNER JOIN patients p ON p.id = t.patient_id
                WHERE p.is_archived = 0
                ORDER BY COALESCE(t.date_of_consultation, '1970-01-01') DESC, t.id DESC";

        $result = $db->query($sql);
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if ($owned) {
            $db->close();
        }

        $out = [];
        foreach ($rows as $row) {
            $it = [];
            if (!empty($row['it_data'])) {
                $decoded = json_decode((string) $row['it_data'], true);
                if (is_array($decoded)) {
                    $it = $decoded;
                }
            }
            $history = is_array($it['print_history'] ?? null) ? $it['print_history'] : [];
            $consultStatus = WorkflowStatus::normalize((string) ($it['consultation_status'] ?? ''));
            $patientStatus = WorkflowStatus::normalize((string) ($row['patient_status'] ?? ''));
            $cid = (int) ($row['consultation_id'] ?? 0);
            $isLatest = $cid > 0 && $cid === (int) ($row['latest_consultation_id'] ?? 0);

            $isReady = $isLatest && $patientStatus === WORKFLOW_READY_FOR_PRINT;
            $isPrinted = !empty($history)
                || $consultStatus === WORKFLOW_PRINTED
                || ($isLatest && in_array($patientStatus, [WORKFLOW_PRINTED, WORKFLOW_ARCHIVED], true));

            // Include every dated consultation in the archive; undated empty drafts stay out
            if (!$isReady && !$isPrinted) {
                if (empty($row['date_of_consultation'])) {
                    continue;
                }
                // Historical / in-progress visits remain searchable for reprint
                $isPrinted = true;
            }

            $printStatus = $isReady ? 'ready' : 'printed';
            $out[] = [
                'patient_db_id'        => (int) $row['patient_db_id'],
                'patient_id'           => (string) ($row['patient_id'] ?? ''),
                'first_name'           => (string) ($row['first_name'] ?? ''),
                'middle_name'          => (string) ($row['middle_name'] ?? ''),
                'last_name'            => (string) ($row['last_name'] ?? ''),
                'suffix'               => (string) ($row['suffix'] ?? ''),
                'philhealth_no'        => (string) ($row['philhealth_no'] ?? ''),
                'patient_status'       => $patientStatus,
                'consultation_id'      => $cid,
                'date_of_consultation' => $row['date_of_consultation'] ?? null,
                'print_status'         => $printStatus,
                'print_history_count'  => count($history),
                'is_latest'            => $isLatest,
            ];
        }

        return $out;
    }

    /**
     * One row per patient with all consultations (newest first) for the Print module dropdown.
     *
     * @return list<array<string,mixed>>
     */
    public static function listPrintPatients(?mysqli $conn = null): array
    {
        $archive = self::listPrintArchive($conn);
        $byPatient = [];

        foreach ($archive as $row) {
            $pid = (int) ($row['patient_db_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }

            if (!isset($byPatient[$pid])) {
                $byPatient[$pid] = [
                    'patient_db_id'  => $pid,
                    'patient_id'     => (string) ($row['patient_id'] ?? ''),
                    'first_name'     => (string) ($row['first_name'] ?? ''),
                    'middle_name'    => (string) ($row['middle_name'] ?? ''),
                    'last_name'      => (string) ($row['last_name'] ?? ''),
                    'suffix'         => (string) ($row['suffix'] ?? ''),
                    'philhealth_no'  => (string) ($row['philhealth_no'] ?? ''),
                    'patient_status' => (string) ($row['patient_status'] ?? ''),
                    'consultations'  => [],
                ];
            }

            $iso = !empty($row['date_of_consultation'])
                ? date('Y-m-d', strtotime((string) $row['date_of_consultation']))
                : '';
            $display = $iso !== '' ? date('M d, Y', strtotime($iso)) : 'No date';

            $byPatient[$pid]['consultations'][] = [
                'consultation_id' => (int) ($row['consultation_id'] ?? 0),
                'date_iso'        => $iso,
                'date_display'    => $display,
                'print_status'    => (string) ($row['print_status'] ?? 'printed'),
                'is_latest'       => false,
                'forms_count'     => count(self::officialDocuments()),
            ];
        }

        // Newest consultation first per patient; "(Latest)" follows date then id
        foreach ($byPatient as &$patient) {
            usort($patient['consultations'], static function (array $a, array $b): int {
                $cmp = strcmp((string) ($b['date_iso'] ?? ''), (string) ($a['date_iso'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int) ($b['consultation_id'] ?? 0)) <=> ((int) ($a['consultation_id'] ?? 0));
            });
            if ($patient['consultations'] !== []) {
                $patient['consultations'][0]['is_latest'] = true;
            }
        }
        unset($patient);

        $patients = array_values($byPatient);
        usort($patients, static function (array $a, array $b): int {
            $aDate = $a['consultations'][0]['date_iso'] ?? '';
            $bDate = $b['consultations'][0]['date_iso'] ?? '';
            if ($aDate === $bDate) {
                return ($b['patient_db_id'] ?? 0) <=> ($a['patient_db_id'] ?? 0);
            }
            return strcmp($bDate, $aDate);
        });

        return $patients;
    }

    public static function assertCanViewForPrint(array $patient): void
    {
        self::assertCanView($patient);
    }

    public static function displayStatus(string $status): string
    {
        return $status;
    }

    public static function loadRecord(int $patientDbId, ?int $consultationId = null): array
    {
        $conn = getDBConnection();

        $stmt = $conn->prepare('SELECT * FROM patients WHERE id = ?');
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$patient) {
            $conn->close();
            return [];
        }

        if ($consultationId > 0) {
            $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE id = ? AND patient_id = ?');
            $stmt->bind_param('ii', $consultationId, $patientDbId);
        } else {
            $stmt = $conn->prepare('SELECT * FROM treatment_records WHERE patient_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->bind_param('i', $patientDbId);
        }
        $stmt->execute();
        $treatment = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        if (!empty($treatment['id'])) {
            require_once __DIR__ . '/ClinicalMaster.php';
            ClinicalMaster::ensureSchema($conn);
            $hydrated = ClinicalMaster::hydrateTreatmentClinical($conn, $treatment);
            $treatment = $hydrated['treatment'];
        }

        $profileId = null;
        $stmt = $conn->prepare('SELECT id, receptionist_data FROM treatment_records WHERE patient_id = ? ORDER BY id ASC LIMIT 1');
        $stmt->bind_param('i', $patientDbId);
        $stmt->execute();
        $profileRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!empty($treatment['consultation_types'])) {
            $treatment['consultation_types'] = json_decode($treatment['consultation_types'], true) ?? [];
        }

        $profileBlock = ExtendedFormData::fromTreatment($profileRow ?: null);
        $consultBlock = ExtendedFormData::fromTreatment($treatment);

        require_once __DIR__ . '/PatientProfile.php';
        $mergedExtended = PatientProfile::mergeExtendedForConsultation(
            $profileBlock['fields'],
            $consultBlock['fields']
        );
        $extended = ExtendedFormData::hydrateAddressFields(
            $mergedExtended,
            $patient['residential_address'] ?? null
        );

        return [
            'patient'          => $patient,
            'treatment'        => $treatment,
            'extended'         => $extended,
            'consultation_id'  => !empty($treatment['id']) ? (int) $treatment['id'] : null,
            'profile_record_id'=> $profileRow ? (int) $profileRow['id'] : null,
        ];
    }

    public static function formatPatientName(array $patient): string
    {
        $middle = trim($patient['middle_name'] ?? '');
        return trim(
            ($patient['first_name'] ?? '') . ' '
            . ($middle !== '' ? $middle . ' ' : '')
            . ($patient['last_name'] ?? '')
            . (!empty($patient['suffix']) ? ' ' . $patient['suffix'] : '')
        );
    }

    public static function renderForms(array $formIds, string $role, bool $lockAll = true, string $layout = ''): string
    {
        $previousMode = PaperFormRenderer::isLayoutTestMode();
        PaperFormRenderer::setLayoutTestMode(self::isLayoutTestMode());

        // Always render every requested form (blank if no data). Official order.
        $formIds = self::sortFormIds($formIds);
        if ($layout === '') {
            $layout = self::inferLayout($formIds);
        }

        try {
            if ($layout === 'a4-landscape-half') {
                if (!in_array('ekas', $formIds, true)) {
                    $formIds[] = 'ekas';
                }
                if (!in_array('epress', $formIds, true)) {
                    $formIds[] = 'epress';
                }
                $formIds = self::sortFormIds(array_values(array_intersect($formIds, ['ekas', 'epress'])));
                if (count($formIds) < 2) {
                    $formIds = ['ekas', 'epress'];
                }

                // One physical A4 landscape sheet — both slips, never split
                $html = '<div class="print-page print-page--landscape-half it-print-sheet it-print-sheet--half-half">';
                foreach ($formIds as $i => $formId) {
                    $side = $i === 0 ? 'left' : 'right';
                    $html .= '<div class="it-print-half it-print-half--' . $side
                        . ' it-print-form-block form-container" id="form-' . htmlspecialchars($formId)
                        . '" data-form-id="' . htmlspecialchars($formId) . '">';
                    $html .= PaperFormRenderer::renderPage($formId, $role, $lockAll);
                    $html .= '</div>';
                }
                $html .= '</div>';
                return $html;
            }

            $pageClass = ($layout === 'legal-portrait')
                ? 'print-page print-page--legal'
                : 'print-page print-page--a4';

            // Each form = one fixed paper sheet (never split across pages)
            $html = '';
            foreach ($formIds as $formId) {
                $html .= '<div class="' . $pageClass . ' it-print-form-block form-container"'
                    . ' id="form-' . htmlspecialchars($formId) . '"'
                    . ' data-form-id="' . htmlspecialchars($formId) . '">';
                $html .= PaperFormRenderer::renderPage($formId, $role, $lockAll);
                $html .= '</div>';
            }

            return $html;
        } finally {
            PaperFormRenderer::setLayoutTestMode($previousMode);
        }
    }

    /**
     * Official Print-module documents in canonical form order
     * (PCSF → ITR → Enrollment → History → Personal → EKAS+EPRESS).
     *
     * @return list<array<string,mixed>>
     */
    public static function officialDocuments(): array
    {
        $docs = self::config()['official_documents'] ?? [];
        $out = [];
        foreach ($docs as $id => $doc) {
            $out[] = [
                'id'          => (string) $id,
                'label'       => (string) ($doc['label'] ?? $id),
                'hint'        => (string) ($doc['hint'] ?? ''),
                'forms'       => self::sortFormIds(array_values($doc['forms'] ?? [])),
                'layout'      => (string) ($doc['layout'] ?? 'a4-portrait'),
                'page_size'   => (string) ($doc['page_size'] ?? 'A4'),
                'orientation' => (string) ($doc['orientation'] ?? 'portrait'),
                'duplex'      => !empty($doc['duplex']),
                'duplex_hint' => (string) ($doc['duplex_hint'] ?? ''),
            ];
        }
        return $out;
    }

    public static function officialDocument(string $id): ?array
    {
        foreach (self::officialDocuments() as $doc) {
            if ($doc['id'] === $id) {
                return $doc;
            }
        }
        return null;
    }

    /**
     * @param list<string> $ids
     * @return list<array<string,mixed>>
     */
    public static function sanitizeOfficialDocumentIds(array $ids): array
    {
        $allowed = array_column(self::officialDocuments(), 'id');
        $clean = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && in_array($id, $allowed, true) && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        return $clean;
    }

    public static function layoutForBundle(string $bundleKey): string
    {
        $bundles = self::bundles();
        return (string) ($bundles[$bundleKey]['layout'] ?? 'a4-portrait');
    }

    /**
     * Individual printable forms (excludes composite bundles like consultation/all).
     *
     * @return list<array{id:string,label:string,title:string}>
     */
    public static function printableForms(): array
    {
        $out = [];
        foreach (self::bundles() as $key => $bundle) {
            $forms = $bundle['forms'] ?? [];
            if (count($forms) !== 1 || ($forms[0] ?? '') !== $key) {
                continue;
            }
            $out[] = [
                'id'    => $key,
                'label' => (string) ($bundle['title'] ?? $bundle['label'] ?? $key),
                'title' => (string) ($bundle['title'] ?? $bundle['label'] ?? $key),
            ];
        }
        return $out;
    }

    /**
     * Sanitize a list of form ids against known printable forms.
     * Always returns IDs in the official RHU print order.
     *
     * @param list<string> $formIds
     * @return list<string>
     */
    public static function sanitizeFormIds(array $formIds): array
    {
        $allowed = self::formPrintOrder();
        foreach (self::printableForms() as $f) {
            if (!in_array($f['id'], $allowed, true)) {
                $allowed[] = $f['id'];
            }
        }
        $clean = [];
        foreach ($formIds as $id) {
            $id = trim((string) $id);
            if ($id !== '' && in_array($id, $allowed, true) && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        return self::sortFormIds($clean);
    }

    /** Count consultations currently Ready for Printing (nav badge). */
    public static function readyToPrintCount(?mysqli $conn = null): int
    {
        $owned = $conn === null;
        $db = $conn ?? getDBConnection();
        $status = WORKFLOW_READY_FOR_PRINT;
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM patients WHERE status = ? AND is_archived = 0');
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        if ($owned) {
            $db->close();
        }
        return $count;
    }
}
