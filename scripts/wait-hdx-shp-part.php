<?php
declare(strict_types=1);

$part = dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.shp.zip.part';
$min = 920_000_000;
$deadline = time() + (int) ($argv[1] ?? 3600);

while (time() < $deadline) {
    if (!is_file($part)) {
        echo "missing part\n";
        sleep(15);
        continue;
    }
    $n = (int) filesize($part);
    echo $n . "\n";
    if ($n >= $min) {
        exit(0);
    }
    sleep(30);
}

echo "timeout at " . (is_file($part) ? filesize($part) : 0) . "\n";
exit(1);
