<?php
declare(strict_types=1);
$path = $argv[1] ?? (dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.gdb.zip');
if (!is_file($path)) {
    $path = dirname(__DIR__) . '/assets/geo/_hdx_phl_admin_boundaries.gdb.zip.part';
}
if (!is_file($path)) {
    echo "missing\n";
    exit(1);
}
echo basename($path), ' ', filesize($path), "\n";
$z = new ZipArchive();
if ($z->open($path) !== true) {
    echo "zip open failed\n";
    exit(1);
}
for ($i = 0; $i < min($z->numFiles, 80); $i++) {
    echo $z->getNameIndex($i), "\n";
}
if ($z->numFiles > 80) {
    echo '... ', $z->numFiles - 80, " more\n";
}
$z->close();
