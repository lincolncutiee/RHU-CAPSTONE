<?php
declare(strict_types=1);

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php probe-zip.php <zip>\n");
    exit(1);
}

$z = new ZipArchive();
$r = $z->open($path);
echo "open={$r} size=" . filesize($path) . " numFiles=" . ($r === true ? $z->numFiles : 0) . "\n";
if ($r === true) {
    for ($i = 0; $i < min(15, $z->numFiles); $i++) {
        echo $z->getNameIndex($i) . "\n";
    }
    $z->close();
}
