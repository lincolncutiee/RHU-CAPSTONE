<?php
/**
 * Prefer local vendor files so the app works without internet (XAMPP / RHU LAN).
 */

function rhu_vendor_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/vendor-assets.php';
    }
    return $cfg;
}

function rhu_vendor_path(string $key): string
{
    $cfg = rhu_vendor_config();
    return $cfg[$key] ?? '';
}

function rhu_asset_url(string $vendorKey, string $cdnFallback): string
{
    $rel = rhu_vendor_path($vendorKey);
    if ($rel !== '') {
        $full = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($full)) {
            return rtrim(BASE_URL, '/') . $rel;
        }
    }
    return $cdnFallback;
}
