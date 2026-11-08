<?php
require_once 'auth.php';
secureSessionStart();

if (!isset($_SESSION['verify_email'])) {
    header("Location: ../pages/signin.php");
    exit();
}

$email = $_SESSION['verify_email'];
$name  = $_SESSION['verify_name'] ?? 'User';

$conn = getDB();

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$stmt = $conn->prepare("UPDATE accounts SET verification_code = ?, code_expires = ? WHERE email = ? AND is_verified = 0");
$stmt->bind_param("sss", $code, $expires, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    header("Location: ../pages/verify.php?error=not_found");
    exit();
}

$stmt->close();
$conn->close();

require_once 'mailer.php';
$sent = sendVerificationEmail($email, $name, $code);

if ($sent) {
    header("Location: ../pages/verify.php?success=resent");
} else {
    header("Location: ../pages/verify.php?error=email_failed");
}
exit();
?>
