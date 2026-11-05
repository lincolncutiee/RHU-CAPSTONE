<?php
/**
 * Shared helpers for HDX COD-AB boundary downloads (Philippines ADM0–4).
 */
declare(strict_types=1);

function hdx_verify_zip(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    $zip = new ZipArchive();
    $open = $zip->open($path);
    if ($open !== true) {
        return false;
    }
    $hasAdm4 = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (is_string($name) && preg_match('/(?:adm4|admin4).*\.shp$/i', $name)) {
            $hasAdm4 = true;
            break;
        }
    }
    $zip->close();

    return $hasAdm4;
}

/**
 * @return array{ok: bool, code: int, bytes: int, message: string}
 */
function hdx_download_resumable(string $url, string $dest, int $minBytes, string $label): array
{
    if (is_file($dest) && filesize($dest) >= $minBytes && hdx_verify_zip($dest)) {
        return [
            'ok'      => true,
            'code'    => 0,
            'bytes'   => (int) filesize($dest),
            'message' => "{$label} already present (" . filesize($dest) . " bytes).",
        ];
    }

    if (is_file($dest) && filesize($dest) >= $minBytes && !hdx_verify_zip($dest)) {
        unlink($dest);
    }

    $part = $dest . '.part';
    $existing = is_file($part) ? (int) filesize($part) : 0;
    if ($existing > 0) {
        echo "Resuming {$label} at byte {$existing}\n";
    }

    $whereOut = trim((string) shell_exec('where curl.exe 2>nul'));
    $curlCli = '';
    if ($whereOut !== '') {
        $curlCli = trim(explode("\n", str_replace("\r", '', $whereOut))[0]);
    }
    if ($curlCli !== '' && is_file($curlCli)) {
        $cmd = escapeshellarg($curlCli)
            . ' -L --fail --retry 3 --retry-delay 5'
            . ' -C - -o ' . escapeshellarg($part)
            . ' -A ' . escapeshellarg('RHUWEB-HDX-Download/1.0')
            . ' ' . escapeshellarg($url);
        passthru($cmd, $curlExit);
        $bytes = is_file($part) ? (int) filesize($part) : 0;
        if ($curlExit !== 0) {
            return [
                'ok'      => false,
                'code'    => 1,
                'bytes'   => $bytes,
                'message' => "{$label} curl.exe download failed (exit {$curlExit}, {$bytes} bytes in .part).",
            ];
        }
    } else {
        $fp = fopen($part, 'cb');
        if ($fp === false) {
            return ['ok' => false, 'code' => 1, 'bytes' => 0, 'message' => "Cannot open {$part}"];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_USERAGENT      => 'RHUWEB-HDX-Download/1.0',
            CURLOPT_FAILONERROR    => false,
        ]);
        if ($existing > 0) {
            curl_setopt($ch, CURLOPT_RANGE, "{$existing}-");
        }

        $ok = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        $bytes = is_file($part) ? (int) filesize($part) : 0;
        if (!$ok || ($http !== 200 && $http !== 206)) {
            return [
                'ok'      => false,
                'code'    => 1,
                'bytes'   => $bytes,
                'message' => "{$label} download failed HTTP {$http} ({$bytes} bytes in .part).",
            ];
        }
    }

    $bytes = is_file($part) ? (int) filesize($part) : 0;

    if ($bytes < $minBytes) {
        return [
            'ok'      => false,
            'code'    => 1,
            'bytes'   => $bytes,
            'message' => "{$label} incomplete: {$bytes} bytes (need >= {$minBytes}). Keeping {$part}.",
        ];
    }

    if (!rename($part, $dest)) {
        return ['ok' => false, 'code' => 1, 'bytes' => $bytes, 'message' => "Could not rename {$part} to {$dest}"];
    }

    if (!hdx_verify_zip($dest)) {
        unlink($dest);
        if (is_file($part)) {
            unlink($part);
        }

        return [
            'ok'      => false,
            'code'    => 1,
            'bytes'   => 0,
            'message' => "{$label} zip failed integrity check after download.",
        ];
    }

    return [
        'ok'      => true,
        'code'    => 0,
        'bytes'   => (int) filesize($dest),
        'message' => "Saved {$dest} (" . filesize($dest) . " bytes).",
    ];
}
