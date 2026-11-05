<?php
/**
 * Download bytes 0..584515583 of HDX shp zip in chunks (more reliable than one long transfer).
 */
declare(strict_types=1);

$url = 'https://data.humdata.org/dataset/caf116df-f984-4deb-85ca-41b349d3f313/resource/4e4067d6-3fc8-4d2c-91ea-330c3d4f8e8e/download/phl_admin_boundaries.shp.zip';
$out = dirname(__DIR__) . '/assets/geo/_hdx_shp_prefix.bin';
$prefixEnd = 584_515_583;
$chunk = 100_000_000;

if (is_file($out) && filesize($out) >= $prefixEnd + 1) {
    echo "Prefix already complete\n";
    exit(0);
}

$whereOut = trim((string) shell_exec('where curl.exe 2>nul'));
$curl = $whereOut !== '' ? trim(explode("\n", str_replace("\r", '', $whereOut))[0]) : 'curl.exe';

for ($start = 0; $start <= $prefixEnd; $start += $chunk) {
    $end = min($prefixEnd, $start + $chunk - 1);
    $need = $end - $start + 1;
    $part = sprintf('%s.%08d-%08d.part', $out, $start, $end);
    if (is_file($part) && filesize($part) >= $need) {
        echo "Have chunk {$start}-{$end}\n";
        continue;
    }
    echo "Downloading bytes {$start}-{$end}...\n";
    $cmd = escapeshellarg($curl)
        . ' -L --fail --retry 5 --retry-delay 3'
        . ' -r ' . $start . '-' . $end
        . ' -o ' . escapeshellarg($part)
        . ' -A ' . escapeshellarg('RHUWEB-HDX-Download/1.0')
        . ' ' . escapeshellarg($url);
    passthru($cmd, $code);
    if ($code !== 0 || !is_file($part) || filesize($part) < $need) {
        fwrite(STDERR, "Chunk failed {$start}-{$end}\n");
        exit(1);
    }
}

$fp = fopen($out, 'wb');
for ($start = 0; $start <= $prefixEnd; $start += $chunk) {
    $end = min($prefixEnd, $start + $chunk - 1);
    $part = sprintf('%s.%08d-%08d.part', $out, $start, $end);
    $in = fopen($part, 'rb');
    stream_copy_to_stream($in, $fp);
    fclose($in);
}
fclose($fp);

echo 'Prefix size ' . filesize($out) . "\n";
exit(0);
