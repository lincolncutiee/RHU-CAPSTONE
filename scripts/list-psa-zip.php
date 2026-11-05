<?php
$z = new ZipArchive();
$p = dirname(__DIR__) . '/assets/geo/_psa-barangay.zip';
if ($z->open($p) !== true) {
    echo "no zip\n";
    exit(1);
}
for ($i = 0; $i < $z->numFiles; $i++) {
    echo $z->getNameIndex($i), "\n";
}
$z->close();
