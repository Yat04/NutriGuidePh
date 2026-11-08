<?php
require_once 'auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isAdmin()) { echo json_encode(['error' => 'Permission denied']); exit(); }
verifyCsrf();

$id = intval($_POST['id'] ?? 0);
if ($id === 0) { echo json_encode(['error' => 'Invalid ID']); exit(); }

$conn = getDB();
$stmt = $conn->prepare("DELETE FROM feedingRecord WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    auditLog('delete_feeding', 'feedingRecord', $id);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Not found']);
}
$stmt->close();
$conn->close();
?>
