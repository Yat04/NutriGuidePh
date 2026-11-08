<?php
require_once 'auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit(); }

$conn = getDB();
$email = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_email = ? OR user_email = 'all' ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifs = [];
    if ($result) {
        while ($n = $result->fetch_assoc()) $notifs[] = $n;
    }
    $count = 0;
    $unread = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE (user_email = ? OR user_email = 'all') AND is_read = 0");
    if ($unread) {
        $unread->bind_param("s", $email);
        $unread->execute();
        $uRes = $unread->get_result();
        $count = $uRes ? (int)($uRes->fetch_assoc()['c'] ?? 0) : 0;
        $unread->close();
    }
    echo json_encode(['notifications' => $notifs, 'unread' => $count]);
    $stmt->close();

} elseif ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);

} elseif ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_email = ? OR user_email = 'all'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
}

$conn->close();

// Helper function to create notifications (call from other PHP files)
function createNotification($userEmail, $title, $message, $type = 'info', $link = null) {
    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO notifications (user_email, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $userEmail, $title, $message, $type, $link);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
