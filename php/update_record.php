<?php
require_once 'auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isAdmin()) { echo json_encode(['error' => 'Permission denied']); exit(); }
verifyCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit();
}

$id      = intval($_POST['id'] ?? 0);
$fn      = normalizeNameCase($_POST['fname'] ?? '');
$ln      = normalizeNameCase($_POST['lname'] ?? '');
$mid     = normalizeInitialCase($_POST['m_initial'] ?? '');
$gender  = strtolower(trim($_POST['gender'] ?? 'male'));
$height  = floatval($_POST['height'] ?? 0);
$h_unit  = $_POST['height_unit'] ?? 'cm';
$weight  = floatval($_POST['weight'] ?? 0);
$w_unit  = $_POST['weight_unit'] ?? 'kg';
$dateOfBirth = trim($_POST['birthday'] ?? '');
$guardian = trim($_POST['guardian_name'] ?? '');
$g_num   = trim($_POST['guardian_number'] ?? '');
$g_email = trim($_POST['guardian_email'] ?? '');

if ($id === 0 || empty($fn) || empty($ln) || $height <= 0 || $weight <= 0) {
    echo json_encode(['error' => 'Missing required fields']); exit();
}
if (!validateHeight($height, $h_unit)) { echo json_encode(['error' => 'Invalid height']); exit(); }
if (!validateWeight($weight, $w_unit)) { echo json_encode(['error' => 'Invalid weight']); exit(); }

$conn = getDB();
$dateStmt = $conn->prepare("SELECT DATE(created_at) AS measurement_date FROM stdRecord WHERE id = ? LIMIT 1");
$dateStmt->bind_param('i', $id);
$dateStmt->execute();
$measurementDate = $dateStmt->get_result()->fetch_assoc()['measurement_date'] ?? null;
$dateStmt->close();
if (!$measurementDate) { $conn->close(); echo json_encode(['error' => 'Record not found']); exit(); }
$calculation = calculatePediatricBmi($height, $h_unit, $weight, $w_unit, $dateOfBirth, $gender, $measurementDate);
if (isset($calculation['error'])) { $conn->close(); echo json_encode(['error' => $calculation['error']]); exit(); }
$bmi = $calculation['bmi'];
$bmiZscore = $calculation['bmi_zscore'];
$heightZscore = $calculation['height_zscore'];
$heightClassification = $calculation['height_classification'];
$classification = $calculation['classification'];

$stmt = $conn->prepare("UPDATE stdRecord SET std_first_name=?, std_last_name=?, std_mid_initial=?, gender=?, height=?, height_unit=?, weight=?, weight_unit=?, date_of_birth=?, bmi=?, bmi_zscore=?, height_zscore=?, height_classification=?, classification=?, guardian_name=?, guardian_number=?, guardian_email=? WHERE id=?");
$stmt->bind_param(str_repeat('s', 17) . 'i', $fn, $ln, $mid, $gender, $height, $h_unit, $weight, $w_unit, $dateOfBirth, $bmi, $bmiZscore, $heightZscore, $heightClassification, $classification, $guardian, $g_num, $g_email, $id);

if ($stmt->execute()) {
    auditLog('update_record', 'stdRecord', $id, "$fn $ln - BMI: $bmi ($classification)");
    echo json_encode(['success' => true, 'bmi' => $bmi, 'bmi_zscore' => $bmiZscore, 'classification' => $classification, 'height_zscore' => $heightZscore, 'height_classification' => $heightClassification]);
} else {
    echo json_encode(['error' => 'Update failed']);
}
$stmt->close();
$conn->close();
?>
