<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/HeatMap.php';

$from = $argv[1] ?? '2026-09-21';
$to = $argv[2] ?? '2026-10-20';

$conn = getDBConnection();

$sql = "SELECT dm.diagnosis_name, COUNT(*) AS c
        FROM treatment_records tr
        INNER JOIN consultation_diagnoses cd ON cd.consultation_id = tr.id
        INNER JOIN diagnosis_master dm ON dm.id = cd.diagnosis_id AND dm.status = 'active'
        INNER JOIN patients p ON p.id = tr.patient_id AND p.is_archived = 0
        WHERE COALESCE(tr.date_of_consultation, DATE(tr.created_at)) BETWEEN ? AND ?
        GROUP BY dm.id, dm.diagnosis_name
        ORDER BY c DESC
        LIMIT 15";
$st = $conn->prepare($sql);
$st->bind_param('ss', $from, $to);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

echo "Diagnoses in period (all recorded):\n";
foreach ($rows as $row) {
    echo '  ' . $row['diagnosis_name'] . ' => ' . $row['c'] . "\n";
}

echo "\nHeatMap surveillance diseases:\n";
$h = HeatMap::build($conn, $from, $to);
foreach ($h['diseases'] as $d) {
    echo '  ' . $d['name'] . ' => ' . $d['count'] . "\n";
}

$conn->close();
