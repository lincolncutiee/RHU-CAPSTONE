<?php
$api = 'https://data.humdata.org/api/3/action/package_show?id=cod-ab-phl';
$json = file_get_contents($api);
$data = json_decode($json, true);
foreach ($data['result']['resources'] ?? [] as $r) {
    echo ($r['url'] ?? '') . "\t" . ($r['name'] ?? '') . "\t" . ($r['size'] ?? '') . "\n";
}
