<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Employee', 'Admin', 'Super Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['photo'])) {
    header("Location: addrecord.php");
    exit();
}

verifyCsrf();
$expectsJson = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$respond = static function ($success, $message, $filename = '') use ($expectsJson) {
    if ($expectsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message, 'photo' => $filename]);
        exit();
    }
    header('Location: addrecord.php?photo=' . ($success ? 'updated' : 'error'));
    exit();
};

$studentId = intval($_POST['student_id'] ?? 0);
$file = $_FILES['photo'];

if ($studentId === 0 || $file['error'] !== UPLOAD_ERR_OK) {
    $respond(false, 'Choose a valid photo to upload.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime]) || $file['size'] > 2 * 1024 * 1024 || @getimagesize($file['tmp_name']) === false) {
    $respond(false, 'Use a JPG, PNG, or WebP image up to 2 MB.');
}

$ext = $allowed[$mime];
$filename = 'student_' . $studentId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
$dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

$conn = getDB();
$lookup = $conn->prepare("SELECT std_first_name, std_mid_initial, std_last_name, grade_level, section FROM stdRecord WHERE id = ? LIMIT 1");
$lookup->bind_param('i', $studentId);
$lookup->execute();
$student = $lookup->get_result()->fetch_assoc();
$lookup->close();
if (!$student) {
    $conn->close();
    $respond(false, 'Student record not found.');
}

$scope = getAdviserScope($conn);
if ($scope !== null && ($student['grade_level'] !== $scope['grade_level'] || !in_array($student['section'], $scope['sections'], true))) {
    $conn->close();
    http_response_code(403);
    $respond(false, 'This student is outside your assigned grade and section.');
}

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $conn->close();
    $respond(false, 'The photo could not be saved.');
}

$stmt = $conn->prepare("UPDATE stdRecord SET photo = ? WHERE std_first_name = ? AND std_mid_initial <=> ? AND std_last_name = ?");
$stmt->bind_param('ssss', $filename, $student['std_first_name'], $student['std_mid_initial'], $student['std_last_name']);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if (!$success) {
    @unlink($dest);
    $respond(false, 'The photo could not be attached to the student.');
}

auditLog('upload_photo', 'stdRecord', $studentId, $filename);
$respond(true, 'Student photo updated.', $filename);
?>
