<?php
/**
 * Rebuild HDX shp zip from a verified prefix + HTTP range tail (no overlapping curl append).
 */
declare(strict_types=1);

require_once __DIR__ . '/hdx-boundary-download.php';

$geo = dirname(__DIR__) . '/assets/geo';
$src = $geo . '/_hdx_phl_admin_boundaries.shp.zip';
$tail = $geo . '/_hdx_phl_admin_boundaries.shp.zip.part.tmp';
$dest = $geo . '/_hdx_phl_admin_boundaries.shp.zip';
$tmp = $dest . '.rebuild';

$prefixLen = 584_515_584;
$expected = 928_515_157;
$tailLen = 343_999_573;

if (!is_file($src) && !is_file($geo . '/_hdx_phl_admin_boundaries.shp.zip.part')) {
    fwrite(STDERR, "Need source zip or .part for prefix\n");
    exit(1);
}

$prefixBin = $geo . '/_hdx_shp_prefix.bin';
$prefixSource = is_file($prefixBin) && filesize($prefixBin) >= $prefixLen
    ? $prefixBin
    : (is_file($src) ? $src : $geo . '/_hdx_phl_admin_boundaries.shp.zip.part');
if (!is_file($tail) || filesize($tail) < $tailLen) {
    fwrite(STDERR, "Tail file missing or wrong size\n");
    exit(1);
}

if (is_file($tmp)) {
    unlink($tmp);
}

$in = fopen($prefixSource, 'rb');
$out = fopen($tmp, 'wb');
if ($in === false || $out === false) {
    exit(1);
}

$left = $prefixLen;
while ($left > 0) {
    $chunk = fread($in, min(8192 * 1024, $left));
    if ($chunk === false || $chunk === '') {
        break;
    }
    fwrite($out, $chunk);
    $left -= strlen($chunk);
}
fclose($in);

$tailIn = fopen($tail, 'rb');
while (true) {
    $chunk = fread($tailIn, 8192 * 1024);
    if ($chunk === false || $chunk === '') {
        break;
    }
    fwrite($out, $chunk);
}
fclose($tailIn);
fclose($out);

$size = (int) filesize($tmp);
echo "Rebuilt size {$size}\n";
if ($size !== $expected) {
    fwrite(STDERR, "Unexpected size\n");
    exit(1);
}

if (!hdx_verify_zip($tmp)) {
    fwrite(STDERR, "Zip open failed\n");
    exit(1);
}

// Deep check: list entries and try reading one central entry
$z = new ZipArchive();
$z->open($tmp);
$adm4 = null;
for ($i = 0; $i < $z->numFiles; $i++) {
    $name = $z->getNameIndex($i);
    if ($name !== false && preg_match('/adm4.*\.shp$/i', $name)) {
        $adm4 = $name;
        break;
    }
}
if ($adm4 === null) {
    fwrite(STDERR, "No adm4 shp in zip listing\n");
    exit(1);
}
$stream = $z->getStream($adm4);
if ($stream === false) {
    fwrite(STDERR, "Cannot read {$adm4} from zip\n");
    exit(1);
}
fclose($stream);
$z->close();

if (is_file($dest)) {
    unlink($dest);
}
rename($tmp, $dest);
echo "OK {$dest}\n";
