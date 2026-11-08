<?php
require_once 'auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit(); }

$conn = getDB();
$email = strtolower(trim((string) $_SESSION['user_id']));
$stmt = $conn->prepare("SELECT firstName, lastName, email, role, consent_agreed, consent_date, COALESCE(created_at, Created) as created_at FROM accounts WHERE LOWER(email)=LOWER(?) LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    echo json_encode(['success' => true, 'user' => $result->fetch_assoc()]);
} else {
    echo json_encode(['error' => 'User not found']);
}
$stmt->close();
$conn->close();
?>
