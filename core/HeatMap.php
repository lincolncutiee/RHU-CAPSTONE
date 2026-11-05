<?php
/**
 * Disease surveillance heat map — aggregates active cases by barangay, purok, and disease.
 *
 * Purok is parsed only from address_street in receptionist_data JSON.
 * Barangay boundaries are loaded from config geojson_path (replaceable without logic changes).
 */

require_once __DIR__ . '/ClinicalMaster.php';
require_once __DIR__ . '/ExtendedFormData.php';
require_once __DIR__ . '/MagallanesAddress.php';
require_once __DIR__ . '/MagallanesGeoJson.php';
require_once __DIR__ . '/PatientSearch.php';

class HeatMap
{
    public const PUROK_NOT_SPECIFIED = 'Purok Not Specified';

    /** @var array<string, mixed> */
    private static array $config;

    /** @return array<string, mixed> */
    private static function config(): array
    {
        if (!isset(self::$config)) {
            self::$config = require __DIR__ . '/../config/heat-map.php';
        }
        return self::$config;
    }

    public static function geoJsonPath(): string
    {
        return (string) (self::config()['geojson_path'] ?? '/assets/geo/magallanes-barangays.geojson');
    }

    public static function monitoringDays(string $diagnosisName, ?string $icdCode): int
    {
        $cfg = self::config();
        $rules = $cfg['monitoring_days'] ?? [];
        $nameLower = strtolower($diagnosisName);

        foreach ($rules as $key => $days) {
            $keyStr = (string) $key;
            if ($icdCode !== null && preg_match('/^[A-Z]\d/i', $keyStr) && stripos($icdCode, $keyStr) === 0) {
                return (int) $days;
            }
            if (str_contains($nameLower, strtolower($keyStr))) {
                return (int) $days;
            }
        }

        return (int) ($cfg['default_monitoring_days'] ?? 90);
    }

    public static function intensityForCount(int $count): array
    {
        $tiers = self::config()['intensity_tiers'] ?? [];
        usort($tiers, static fn ($a, $b) => ($b['min'] ?? 0) <=> ($a['min'] ?? 0));

        foreach ($tiers as $tier) {
            if ($count >= (int) ($tier['min'] ?? 0)) {
                return $tier;
            }
        }

        return ['level' => 'base', 'label' => 'No active cases', 'color' => '#22c55e'];
    }

    /**
     * Extract purok label from address_street only.
     * Returns null when no purok can be identified — never invent data.
     */
    public static function extractPurok(?string $addressStreet): ?string
    {
        if ($addressStreet === null || trim($addressStreet) === '') {
            return null;
        }

        $text = trim($addressStreet);

        // "Purok 1, Brgy. Poblacion" / "Purok 2, Poblacion" / "Purok 3 - Brgy. Guiasan"
        if (preg_match('/\b(?:Purok|Prk\.?|P\.)\s*(\d+|[IVXLC]+)\b/i', $text, $m)) {
            return 'Purok ' . trim($m[1]);
        }

        return null;
    }

    /**
     * Suggest a default date range from existing consultation/diagnosis records.
     *
     * @return array{from: string, to: string, has_records: bool}
     */
    public static function suggestDateRange(mysqli $conn): array
    {
        ClinicalMaster::ensureSchema($conn);
        $today = date('Y-m-d');
        $fallbackDays = (int) (self::config()['fallback_date_window_days'] ?? 90);

        $sql = "SELECT
                    MIN(COALESCE(tr.date_of_consultation, DATE(tr.created_at))) AS min_date,
                    MAX(COALESCE(tr.date_of_consultation, DATE(tr.created_at))) AS max_date,
                    COUNT(DISTINCT tr.id) AS consult_count
                FROM treatment_records tr
                INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
                LEFT JOIN consultation_diagnoses cd ON cd.consultation_id = tr.id
                WHERE cd.id IS NOT NULL
                   OR (tr.diagnosis IS NOT NULL AND TRIM(tr.diagnosis) <> '')";

        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : null;

        $minDate = $row['min_date'] ?? null;
        $consultCount = (int) ($row['consult_count'] ?? 0);

        if ($consultCount > 0 && $minDate) {
            return [
                'from'         => (string) $minDate,
                'to'           => $today,
                'has_records'  => true,
            ];
        }

        return [
            'from'        => date('Y-m-d', strtotime('-' . $fallbackDays . ' days')),
            'to'          => $today,
            'has_records' => false,
        ];
    }

    private static function resolveBarangay(string $residentialAddress, ?string $receptionistJson): string
    {
        if ($receptionistJson) {
            $ext = ExtendedFormData::fromTreatment(['receptionist_data' => $receptionistJson]);
            $barangay = MagallanesGeoJson::normalizeBarangayName($ext['fields']['address_barangay'] ?? '');
            if ($barangay !== '') {
                return $barangay;
            }
        }

        $fromAddress = PatientSearch::extractBarangay($residentialAddress);
        if ($fromAddress !== '' && $fromAddress !== '—') {
            return MagallanesGeoJson::normalizeBarangayName($fromAddress);
        }

        return '';
    }

    private static function resolvePurok(?string $receptionistJson): string
    {
        if (!$receptionistJson) {
            return self::PUROK_NOT_SPECIFIED;
        }

        $ext = ExtendedFormData::fromTreatment(['receptionist_data' => $receptionistJson]);
        $street = trim((string) ($ext['fields']['address_street'] ?? ''));

        return self::extractPurok($street) ?? self::PUROK_NOT_SPECIFIED;
    }

    /**
     * @return array{
     *   summary: array,
     *   diseases: list<array{id:int,name:string,count:int}>,
     *   barangays: list<array>,
     *   legend: list<array>,
     *   date_from: string,
     *   date_to: string,
     *   has_active_cases: bool
     * }
     */
    public static function build(
        mysqli $conn,
        string $dateFrom,
        string $dateTo,
        ?int $diagnosisId = null,
        ?ReportFilters $filters = null,
    ): array {
        require_once __DIR__ . '/ReportFilters.php';
        $dateFrom = self::normalizeDate($dateFrom);
        $dateTo = self::normalizeDate($dateTo);
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $allCases = self::fetchActiveCases($conn, $dateFrom, $dateTo, $filters);
        $allPayload = self::aggregate($allCases, $dateFrom, $dateTo, null);

        if ($diagnosisId !== null && $diagnosisId > 0) {
            $filteredCases = array_values(array_filter(
                $allCases,
                static fn (array $c) => (int) $c['diagnosis_id'] === $diagnosisId
            ));
            $filteredPayload = self::aggregate($filteredCases, $dateFrom, $dateTo, $diagnosisId);
            $filteredPayload['diseases'] = $allPayload['diseases'];
            return $filteredPayload;
        }

        return $allPayload;
    }

    /**
     * @return list<array>
     */
    private static function fetchActiveCases(
        mysqli $conn,
        string $dateFrom,
        string $dateTo,
        ?ReportFilters $filters = null,
    ): array {
        ClinicalMaster::ensureSchema($conn);
        require_once __DIR__ . '/ReportFilters.php';
        $filters ??= new ReportFilters();

        $today = date('Y-m-d');
        $referenceEnd = $dateTo < $today ? $dateTo : $today;

        $types = ['ss'];
        $params = [$dateFrom, $dateTo];
        $demo = $filters->appendSql('p', $types, $params);

        $sql = "SELECT
                    p.id AS patient_id,
                    p.residential_address,
                    tr.id AS consultation_id,
                    tr.diagnosis AS legacy_diagnosis,
                    tr.receptionist_data,
                    COALESCE(tr.date_of_consultation, DATE(tr.created_at)) AS consult_date,
                    dm.id AS diagnosis_id,
                    dm.diagnosis_name,
                    dm.icd_code
                FROM patients p
                INNER JOIN treatment_records tr ON tr.patient_id = p.id
                LEFT JOIN consultation_diagnoses cd ON cd.consultation_id = tr.id
                LEFT JOIN diagnosis_master dm ON dm.id = cd.diagnosis_id AND dm.status = 'active'
                WHERE p.is_archived = 0
                  AND COALESCE(tr.date_of_consultation, DATE(tr.created_at)) BETWEEN ? AND ?{$demo}";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(implode('', $types), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        /** @var list<array> $rawCases */
        $rawCases = [];
        $seenNormalized = [];

        while ($row = $result->fetch_assoc()) {
            $consultDate = (string) $row['consult_date'];
            $barangay = self::resolveBarangay(
                (string) ($row['residential_address'] ?? ''),
                $row['receptionist_data'] ?? null
            );
            $purok = self::resolvePurok($row['receptionist_data'] ?? null);

            $diagnoses = [];
            if (!empty($row['diagnosis_id'])) {
                $diagnoses[] = [
                    'id'   => (int) $row['diagnosis_id'],
                    'name' => (string) $row['diagnosis_name'],
                    'icd'  => $row['icd_code'] ?? null,
                ];
            } elseif (!empty($row['legacy_diagnosis'])) {
                foreach (ClinicalMaster::resolveDiagnosesFromLabels($conn, (string) $row['legacy_diagnosis']) as $dx) {
                    if ((int) ($dx['id'] ?? 0) > 0) {
                        $diagnoses[] = [
                            'id'   => (int) $dx['id'],
                            'name' => (string) ($dx['diagnosis_name'] ?? $dx['label']),
                            'icd'  => $dx['icd_code'] ?? null,
                        ];
                    }
                }
            }

            foreach ($diagnoses as $dx) {
                $monitorDays = self::monitoringDays($dx['name'], $dx['icd']);
                $activeUntil = date('Y-m-d', strtotime($consultDate . ' +' . $monitorDays . ' days'));
                if ($activeUntil < $referenceEnd) {
                    continue;
                }

                $dedupeKey = $row['patient_id'] . '|' . $dx['id'] . '|' . $consultDate;
                if (isset($seenNormalized[$dedupeKey])) {
                    continue;
                }
                $seenNormalized[$dedupeKey] = true;

                $rawCases[] = [
                    'patient_id'      => (int) $row['patient_id'],
                    'consultation_id' => (int) $row['consultation_id'],
                    'consult_date'    => $consultDate,
                    'diagnosis_id'    => $dx['id'],
                    'diagnosis_name'  => $dx['name'],
                    'icd_code'        => $dx['icd'],
                    'barangay'        => $barangay,
                    'purok'           => $purok,
                ];
            }
        }
        $stmt->close();

        return $rawCases;
    }

    /**
     * @param list<array> $cases
     * @return array
     */
    private static function aggregate(array $cases, string $dateFrom, string $dateTo, ?int $diagnosisId): array
    {
        $officialBarangays = MagallanesGeoJson::barangayNames();

        /** @var array<string, array{total:int,diseases:array<int,int>,puroks:array<string,int>}> $byBarangay */
        $byBarangay = [];
        foreach ($officialBarangays as $name) {
            $byBarangay[$name] = ['total' => 0, 'diseases' => [], 'puroks' => []];
        }

        /** @var array<int, array{id:int,name:string,count:int}> $diseaseCounts */
        $diseaseCounts = [];
        $consultationIds = [];
        $puroksWithCases = 0;

        foreach ($cases as $case) {
            $bg = $case['barangay'];
            if ($bg === '' || !isset($byBarangay[$bg])) {
                continue;
            }

            $byBarangay[$bg]['total']++;
            $dxId = (int) $case['diagnosis_id'];
            $byBarangay[$bg]['diseases'][$dxId] = ($byBarangay[$bg]['diseases'][$dxId] ?? 0) + 1;

            $purok = $case['purok'] ?? self::PUROK_NOT_SPECIFIED;
            $byBarangay[$bg]['puroks'][$purok] = ($byBarangay[$bg]['puroks'][$purok] ?? 0) + 1;

            if ($purok !== self::PUROK_NOT_SPECIFIED) {
                $puroksWithCases++;
            }

            if (!isset($diseaseCounts[$dxId])) {
                $diseaseCounts[$dxId] = [
                    'id'    => $dxId,
                    'name'  => $case['diagnosis_name'],
                    'count' => 0,
                ];
            }
            $diseaseCounts[$dxId]['count']++;

            $consultationIds[(int) $case['consultation_id']] = true;
        }

        uasort($diseaseCounts, static fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));

        $barangayPayload = [];
        $affectedBarangays = 0;
        $totalCases = 0;

        foreach ($officialBarangays as $name) {
            $data = $byBarangay[$name];
            $count = (int) $data['total'];
            $intensity = self::intensityForCount($count);
            if ($count > 0) {
                $affectedBarangays++;
            }
            $totalCases += $count;

            $diseaseBreakdown = [];
            foreach ($data['diseases'] as $dxId => $dxCount) {
                $label = '';
                foreach ($diseaseCounts as $dc) {
                    if ($dc['id'] === $dxId) {
                        $label = $dc['name'];
                        break;
                    }
                }
                if ($label === '') {
                    foreach ($cases as $c) {
                        if ((int) $c['diagnosis_id'] === $dxId) {
                            $label = $c['diagnosis_name'];
                            break;
                        }
                    }
                }
                $diseaseBreakdown[] = [
                    'id'    => $dxId,
                    'name'  => $label,
                    'count' => $dxCount,
                ];
            }
            usort($diseaseBreakdown, static fn ($a, $b) => $b['count'] <=> $a['count']);

            $purokBreakdown = [];
            foreach ($data['puroks'] as $purokName => $purokCount) {
                $purokBreakdown[] = ['name' => $purokName, 'count' => $purokCount];
            }
            usort($purokBreakdown, static fn ($a, $b) => self::comparePurokRows($a, $b));

            $barangayPayload[] = [
                'name'              => $name,
                'total_cases'       => $count,
                'intensity'         => $intensity['level'],
                'intensity_label'   => $intensity['label'],
                'color'             => $intensity['color'],
                'anomaly_status'    => self::anomalyStatusForIntensity((string) $intensity['level'], $count),
                'disease_breakdown' => $diseaseBreakdown,
                'purok_breakdown'   => $purokBreakdown,
            ];
        }

        return [
            'summary' => [
                'total_active_cases'  => $totalCases,
                'affected_barangays'  => $affectedBarangays,
                'total_barangays'     => count($officialBarangays),
                'puroks_with_cases'   => $puroksWithCases,
                'total_consultations' => count($consultationIds),
            ],
            'diseases'          => array_values($diseaseCounts),
            'barangays'         => $barangayPayload,
            'legend'            => self::config()['intensity_tiers'] ?? [],
            'date_from'         => $dateFrom,
            'date_to'           => $dateTo,
            'diagnosis_id'      => $diagnosisId,
            'has_active_cases'  => $totalCases > 0,
        ];
    }

    public static function anomalyStatusForIntensity(string $level, int $count): string
    {
        if ($count <= 0) {
            return 'Normal';
        }

        return match ($level) {
            'very_high' => 'Anomalous',
            'high'      => 'High',
            'moderate'  => 'Moderate',
            default     => 'Normal',
        };
    }

    /** @param array{name:string,count:int} $a @param array{name:string,count:int} $b */
    private static function comparePurokRows(array $a, array $b): int
    {
        if ($a['name'] === self::PUROK_NOT_SPECIFIED && $b['name'] !== self::PUROK_NOT_SPECIFIED) {
            return 1;
        }
        if ($b['name'] === self::PUROK_NOT_SPECIFIED && $a['name'] !== self::PUROK_NOT_SPECIFIED) {
            return -1;
        }
        $countCmp = $b['count'] <=> $a['count'];
        return $countCmp !== 0 ? $countCmp : strnatcasecmp($a['name'], $b['name']);
    }

    private static function normalizeDate(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return date('Y-m-01');
        }
        return date('Y-m-d', $ts);
    }
}
