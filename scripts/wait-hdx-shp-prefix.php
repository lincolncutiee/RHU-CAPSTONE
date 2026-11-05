<?php
declare(strict_types=1);

$prefix = dirname(__DIR__) . '/assets/geo/_hdx_shp_prefix.bin';
$need = 584_515_584;
$deadline = time() + (int) ($argv[1] ?? 7200);

while (time() < $deadline) {
    $n = is_file($prefix) ? (int) filesize($prefix) : 0;
    echo $n . "\n";
    if ($n >= $need) {
        exit(0);
    }
    sleep(45);
}
exit(1);
