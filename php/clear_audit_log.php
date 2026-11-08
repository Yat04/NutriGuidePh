<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Super Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: audit_log.php");
    exit();
}

verifyCsrf();

$conn = getDB();

// Clear audit_log table
$result = $conn->query("TRUNCATE TABLE audit_log");

if ($result) {
    auditLog('clear_audit_log', 'audit_log', null, 'Audit log was cleared by Super Admin');
    header("Location: audit_log.php?success=cleared");
} else {
    header("Location: audit_log.php?error=clear_failed");
}

$conn->close();
exit();
?>
