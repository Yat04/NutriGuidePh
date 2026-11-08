<?php
require_once 'auth.php';
secureSessionStart();
if (isset($_SESSION['user_id'])) {
    auditLog('logout', 'account');
}
session_unset();
session_destroy();
header("Location: ../index.php");
exit();
?>
