<?php
declare(strict_types=1);

require_once __DIR__ . '/hdx-boundary-download.php';

$dl = dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.shp.zip.dl';
$dest = dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.shp.zip';
$min = 920_000_000;
$deadline = time() + (int) ($argv[1] ?? 7200);

while (time() < $deadline) {
    $n = is_file($dl) ? (int) filesize($dl) : 0;
    echo $n . "\n";
    if ($n >= $min && hdx_verify_zip($dl)) {
        if (is_file($dest)) {
            unlink($dest);
        }
        rename($dl, $dest);
        echo "Ready {$dest}\n";
        exit(0);
    }
    sleep(60);
}
exit(1);
