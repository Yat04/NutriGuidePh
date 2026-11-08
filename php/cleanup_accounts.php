<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Super Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed. This maintenance action requires a valid POST request.");
}

verifyCsrf();

$conn = getDB();

// Delete all accounts EXCEPT Admin and Super Admin accounts
$deleteAccounts = $conn->query("DELETE FROM accounts WHERE role NOT IN ('Admin', 'Super Admin')");

// Clear audit_log table
$clearAudit = $conn->query("TRUNCATE TABLE audit_log");

if ($deleteAccounts && $clearAudit) {
    $remainingAdmins = $conn->query("SELECT COUNT(*) as count FROM accounts WHERE role IN ('Admin', 'Super Admin')");
    $result = $remainingAdmins->fetch_assoc();
    $adminCount = $result['count'];
    
    auditLog('cleanup_accounts', 'accounts', null, 'Maintenance cleanup performed');
    echo "✅ Cleanup Successful!<br>";
    echo "• Deleted non-admin accounts<br>";
    echo "• Cleared audit_log table<br>";
    echo "• Remaining Administrator accounts: <b>$adminCount</b><br>";
} else {
    echo "❌ Error: " . $conn->error;
}

$conn->close();
?>
