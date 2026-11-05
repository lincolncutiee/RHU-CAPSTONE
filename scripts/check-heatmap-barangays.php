<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$payload = HeatMap::build([]);
$barangays = $payload['barangays'] ?? [];
foreach ($barangays as $b) {
    $name = $b['name'] ?? '';
    if (stripos($name, 'Ni') !== false || stripos($name, 'Santo') !== false) {
        echo $name . ' | cases=' . ($b['total_cases'] ?? '?') . ' | color=' . ($b['color'] ?? '?') . PHP_EOL;
    }
}
echo '--- all ---' . PHP_EOL;
foreach ($barangays as $b) {
    echo ($b['name'] ?? '') . ' | ' . ($b['total_cases'] ?? 0) . PHP_EOL;
}
