<?php
/**
 * Complete a partial HDX shp zip when curl -C - stalled but a range tail (.part.tmp) exists.
 *
 * Usage: php scripts/finish-hdx-shp-merge.php
 */
declare(strict_types=1);

require_once __DIR__ . '/hdx-boundary-download.php';

$geo = dirname(__DIR__) . '/assets/geo';
$part = $geo . '/_hdx_phl_admin_boundaries.shp.zip.part';
$tail = $part . '.tmp';
$dest = $geo . '/_hdx_phl_admin_boundaries.shp.zip';
$expected = 928_515_157;
$rangeStart = 584_515_584;

if (!is_file($part)) {
    fwrite(STDERR, "Missing {$part}\n");
    exit(1);
}

$partSize = (int) filesize($part);
if ($partSize >= 920_000_000 && hdx_verify_zip($part)) {
    if (!is_file($dest)) {
        rename($part, $dest);
    }
    echo "Part already complete ({$partSize} bytes).\n";
    exit(0);
}

if (!is_file($tail)) {
    fwrite(STDERR, "No tail file {$tail}; run download-hdx-shp.php\n");
    exit(1);
}

$tailSize = (int) filesize($tail);
if ($partSize <= $rangeStart) {
    fwrite(STDERR, "Part size {$partSize} is not past range start {$rangeStart}\n");
    exit(1);
}

$offsetInTail = $partSize - $rangeStart;
$need = $expected - $partSize;

if ($offsetInTail + $need > $tailSize) {
    fwrite(STDERR, "Tail too small: part={$partSize} offsetInTail={$offsetInTail} need={$need} tail={$tailSize}\n");
    exit(1);
}

$in = fopen($tail, 'rb');
$out = fopen($part, 'ab');
if ($in === false || $out === false) {
    fwrite(STDERR, "Cannot open files for merge\n");
    exit(1);
}

if (fseek($in, $offsetInTail) !== 0) {
    fwrite(STDERR, "fseek failed\n");
    exit(1);
}

$remaining = $need;
while ($remaining > 0) {
    $chunk = fread($in, min(8192 * 1024, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    fwrite($out, $chunk);
    $remaining -= strlen($chunk);
}
fclose($in);
fclose($out);

$final = (int) filesize($part);
echo "Merged to {$final} bytes\n";

if ($final < 920_000_000 || !hdx_verify_zip($part)) {
    fwrite(STDERR, "Zip still invalid after merge\n");
    exit(1);
}

if (is_file($dest)) {
    unlink($dest);
}
rename($part, $dest);
if (is_file($tail)) {
    unlink($tail);
}

echo "Wrote {$dest}\n";
exit(0);
