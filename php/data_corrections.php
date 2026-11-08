<?php
require_once 'auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit(); }

$conn = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Parent submits correction request
if ($action === 'submit' && isParent()) {
    verifyCsrf();
    $studentId = intval($_POST['student_id'] ?? 0);
    $field = sanitize($_POST['field_name'] ?? '');
    $current = sanitize($_POST['current_value'] ?? '');
    $requested = sanitize($_POST['requested_value'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $email = $_SESSION['user_id'];

    if ($studentId && $field && $requested) {
        $stmt = $conn->prepare("INSERT INTO data_corrections (parent_email, student_id, field_name, current_value, requested_value, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $email, $studentId, $field, $current, $requested, $reason);
        $stmt->execute();
        auditLog('correction_request', 'data_corrections', $conn->insert_id, "$field: $current → $requested");
        echo json_encode(['success' => true]);
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Missing fields']);
    }

// Admin reviews correction
} elseif ($action === 'review' && isAdmin()) {
    verifyCsrf();
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id && in_array($status, ['approved', 'rejected'])) {
        $reviewer = $_SESSION['user_id'];
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE data_corrections SET status=?, reviewed_by=?, reviewed_at=? WHERE id=?");
        $stmt->bind_param("sssi", $status, $reviewer, $now, $id);
        $stmt->execute();

        // If approved, apply the correction
        if ($status === 'approved') {
            $corr = $conn->query("SELECT * FROM data_corrections WHERE id=$id")->fetch_assoc();
            if ($corr) {
                $field = $corr['field_name'];
                $allowed = ['std_first_name','std_last_name','std_mid_initial','gender','guardian_name','guardian_number','guardian_email'];
                if (in_array($field, $allowed)) {
                    $upd = $conn->prepare("UPDATE stdRecord SET $field = ? WHERE student_id = ? OR id = ?");
                    $val = $corr['requested_value'];
                    $sid = $corr['student_id'];
                    $upd->bind_param("sii", $val, $sid, $sid);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
        auditLog("correction_$status", 'data_corrections', $id);
        echo json_encode(['success' => true]);
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Invalid request']);
    }

// List pending corrections (admin)
} elseif ($action === 'list' && isAdmin()) {
    $result = $conn->query("SELECT dc.*, s.std_first_name, s.std_last_name FROM data_corrections dc LEFT JOIN stdRecord s ON (dc.student_id = s.student_id OR dc.student_id = s.id) ORDER BY dc.created_at DESC LIMIT 50");
    $corrections = [];
    if ($result) {
        while ($r = $result->fetch_assoc()) $corrections[] = $r;
    }
    echo json_encode(['corrections' => $corrections]);

} else {
    echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>
