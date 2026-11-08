<?php
require_once 'auth.php';
secureSessionStart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/forgot_password.php");
    exit();
}

verifyCsrf();

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    header("Location: ../pages/forgot_password.php?error=no_account");
    exit();
}

if (!checkRateLimit('forgot_' . $email, 3, 300)) {
    header("Location: ../pages/forgot_password.php?error=rate_limited");
    exit();
}

$conn = getDB();

$stmt = $conn->prepare("SELECT firstName FROM accounts WHERE email = ? AND is_verified = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../pages/forgot_password.php?error=no_account");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$upd = $conn->prepare("UPDATE accounts SET verification_code = ?, code_expires = ? WHERE email = ?");
$upd->bind_param("sss", $code, $expires, $email);
$upd->execute();
$upd->close();
$conn->close();

require_once 'mailer.php';
$sent = sendVerificationEmail($email, $user['firstName'], $code);

$_SESSION['reset_email'] = $email;
$_SESSION['dev_reset_code'] = (!$sent && !IS_PRODUCTION) ? $code : null;

header("Location: ../pages/reset_password.php" . ($sent ? '?success=sent' : '?error=email_failed'));
exit();
?>
