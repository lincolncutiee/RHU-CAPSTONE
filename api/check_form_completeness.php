<?php
/**
 * Check RHU form completeness before IT marks a record Ready for Printing.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/FormCompleteness.php';
require_once __DIR__ . '/../core/Permissions.php';
require_once __DIR__ . '/../core/Workflow.php';
requireLogin();

$user = currentUser();
$role = Permissions::normalizeRole($user['role'] ?? '');
if (!in_array($role, [ROLE_IT, ROLE_RECORDS, ROLE_DOCTOR, ROLE_PHARMACY], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$scope = trim((string) ($_GET['scope'] ?? $_POST['scope'] ?? 'display'));
$scope = $scope === 'submit' ? 'submit' : 'display';

$patientId = (int) ($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
    exit;
}

$conn = getDBConnection();
$result = FormCompleteness::validatePatientId($conn, $patientId, $role, $scope);
$conn->close();

echo json_encode([
    'success'  => true,
    'scope'    => $scope,
    'complete' => $result['complete'],
    'issues'   => $result['issues'],
    'message'  => $result['complete']
        ? 'All required RHU forms are complete.'
        : FormCompleteness::formatIssuesMessage($result['issues']),
]);
