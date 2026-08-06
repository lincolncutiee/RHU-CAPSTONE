<?php
/**
 * Controlled residential address for RHU Magallanes.
 */

class MagallanesAddress
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../config/magallanes-address.php';
        }
        return self::$config;
    }

    public static function province(): string
    {
        return self::config()['province'];
    }

    public static function municipality(): string
    {
        return self::config()['municipality'];
    }

    /** @return list<string> */
    public static function barangays(): array
    {
        return self::config()['barangays'];
    }

    public static function isValidBarangay(?string $barangay): bool
    {
        $barangay = trim((string) $barangay);
        if ($barangay === '') {
            return false;
        }
        foreach (self::barangays() as $official) {
            if (strcasecmp($official, $barangay) === 0) {
                return true;
            }
        }
        return false;
    }

    /** Normalize to official spelling, or empty if unknown. */
    public static function normalizeBarangay(?string $barangay): string
    {
        $barangay = trim((string) $barangay);
        if ($barangay === '') {
            return '';
        }
        foreach (self::barangays() as $official) {
            if (strcasecmp($official, $barangay) === 0) {
                return $official;
            }
        }
        // Legacy free-text may contain the barangay name
        foreach (self::barangays() as $official) {
            if (stripos($barangay, $official) !== false) {
                return $official;
            }
        }
        return '';
    }

    public static function compose(?string $barangay): string
    {
        $barangay = self::normalizeBarangay($barangay);
        if ($barangay === '') {
            return '';
        }
        return 'Brgy. ' . $barangay . ', ' . self::municipality() . ', ' . self::province();
    }

    /**
     * Force Magallanes municipality/province and composed street from barangay.
     *
     * @param array<string, mixed> $extended
     * @return array<string, mixed>
     */
    public static function applyToExtended(array $extended): array
    {
        $barangay = self::normalizeBarangay($extended['address_barangay'] ?? '');
        $extended['address_barangay'] = $barangay;
        $extended['address_municipality'] = self::municipality();
        $extended['address_province'] = self::province();
        $extended['address_street'] = self::compose($barangay);
        return $extended;
    }

    /**
     * Barangay &lt;select&gt; options: '' => placeholder, then official list.
     *
     * @return array<string, string>
     */
    public static function barangayOptions(): array
    {
        $opts = ['' => '— Select barangay —'];
        foreach (self::barangays() as $b) {
            $opts[$b] = $b;
        }
        return $opts;
    }
}
