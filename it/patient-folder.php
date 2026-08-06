<?php
/**
 * IT Staff — Patient folder (legacy URL → EMR patient folder).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$patientDbId = (int) ($_GET['id'] ?? 0);
if ($patientDbId <= 0) {
    die('Invalid patient ID.');
}

$consultationId = (int) ($_GET['consultation'] ?? 0);
$tab = $_GET['tab'] ?? 'consultations';

if ($consultationId > 0) {
    header('Location: ' . BASE_URL . '/consultation.php?id=' . $patientDbId . '&consultation=' . $consultationId);
    exit;
}

header('Location: ' . BASE_URL . '/patient-folder.php?id=' . $patientDbId . '&tab=' . urlencode($tab));
exit;
