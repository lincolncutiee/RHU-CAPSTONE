<?php
/**
 * Extended form fields stored in treatment_records.receptionist_data JSON.
 * No database schema changes required.
 */

class ExtendedFormData
{
    public const JSON_KEY = 'enrollment_extended';

    public static function fromTreatment(?array $treatment): array
    {
        if (empty($treatment['receptionist_data'])) {
            return ['fields' => [], 'form_progress' => 0];
        }

        $data = is_string($treatment['receptionist_data'])
            ? json_decode($treatment['receptionist_data'], true)
            : $treatment['receptionist_data'];

        if (!is_array($data)) {
            return ['fields' => [], 'form_progress' => 0];
        }

        $block = $data[self::JSON_KEY] ?? $data;

        return [
            'fields'        => $block['fields'] ?? $block,
            'form_progress' => (int) ($block['form_progress'] ?? $data['form_progress'] ?? 0),
        ];
    }

    public static function toJson(array $fields, int $formProgress, ?string $existingJson): string
    {
        $data = [];
        if ($existingJson) {
            $decoded = json_decode($existingJson, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $data[self::JSON_KEY] = [
            'fields'        => $fields,
            'form_progress' => $formProgress,
        ];
        $data['form_progress'] = $formProgress;

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function buildResidentialAddress(array $extended, ?string $fallback = null): ?string
    {
        require_once __DIR__ . '/MagallanesAddress.php';

        $extended = MagallanesAddress::applyToExtended($extended);
        $composed = MagallanesAddress::compose($extended['address_barangay'] ?? '');
        if ($composed !== '') {
            return $composed;
        }

        return $fallback ?: null;
    }

    public static function hydrateAddressFields(array $extended, ?string $residentialAddress): array
    {
        require_once __DIR__ . '/MagallanesAddress.php';

        $barangay = MagallanesAddress::normalizeBarangay($extended['address_barangay'] ?? '');
        if ($barangay === '' && $residentialAddress) {
            $barangay = MagallanesAddress::normalizeBarangay($residentialAddress);
            if ($barangay === '' && preg_match('/Brgy\.\s*([^,]+)/i', $residentialAddress, $m)) {
                $barangay = MagallanesAddress::normalizeBarangay($m[1]);
            }
        }

        $extended['address_barangay'] = $barangay;
        return MagallanesAddress::applyToExtended($extended);
    }
}
