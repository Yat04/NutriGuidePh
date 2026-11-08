<?php
require_once 'auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isStaff()) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit(); }

$fn = trim($_GET['fn'] ?? '');
$ln = trim($_GET['ln'] ?? '');
$mid = trim($_GET['mid'] ?? '');
if (empty($fn) || empty($ln)) { echo json_encode(['error' => 'Missing parameters']); exit(); }

$conn = getDB();
$scope = getAdviserScope($conn);
$params = [$fn, $mid, $ln];
$types = 'sss';
$scopeCondition = appendAdviserScope($scope, '', $params, $types);
$stmt = $conn->prepare("SELECT * FROM stdRecord WHERE std_first_name = ? AND std_mid_initial <=> ? AND std_last_name = ?" . $scopeCondition . " ORDER BY created_at DESC, id DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $heightAssessment = getRecordHeightForAge($row);
    $row['height_zscore'] = $heightAssessment['height_zscore'] ?? null;
    $row['height_classification'] = $heightAssessment['height_classification'] ?? 'Not assessed';
    $records[] = $row;
}
$profilePhoto = '';
foreach ($records as $row) {
	if (!empty($row['photo'])) {
		$profilePhoto = $row['photo'];
		break;
	}
}
if ($profilePhoto !== '') {
	foreach ($records as &$row) $row['photo'] = $profilePhoto;
	unset($row);
}

echo json_encode(['records' => $records]);
$stmt->close();
$conn->close();
?>
