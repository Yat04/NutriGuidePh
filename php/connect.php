<?php
require_once 'auth.php';
secureSessionStart();

$firstName        = sanitize($_POST['firstName'] ?? '');
$lastName         = sanitize($_POST['lastName'] ?? '');

verifyCsrf();
$role             = $_POST['role'] ?? '';
$email            = strtolower(trim($_POST['Email'] ?? ''));
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$consent          = isset($_POST['consent_agreed']) ? 1 : 0;

$redirect_page = 'parent_signup.php';

// Only Parent/Guardian can self-register. Staff accounts are created by Super Admin.
if ($role !== 'Parent/Guardian') {
    header("Location: ../pages/signin.php");
    exit();
}

if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
    header("Location: ../pages/$redirect_page?error=empty_fields");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../pages/$redirect_page?error=invalid_email");
    exit();
}

if (strlen($password) < 6) {
    header("Location: ../pages/$redirect_page?error=weak_password");
    exit();
}

if ($password !== $confirm_password) {
    header("Location: ../pages/$redirect_page?error=password_mismatch");
    exit();
}

if ($role === 'Parent/Guardian' && !$consent) {
    header("Location: ../pages/$redirect_page?error=consent_required");
    exit();
}

// Rate limit signups
if (!checkRateLimit('signup', 3, 300)) {
    header("Location: ../pages/$redirect_page?error=rate_limited");
    exit();
}

$conn = getDB();

mysqli_report(MYSQLI_REPORT_OFF);

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$consent_date = $consent ? date('Y-m-d H:i:s') : null;
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$status = ($role === 'Parent/Guardian') ? 'active' : 'pending';

// Force Employee role for staff signups (only Super Admin can promote)
if ($role !== 'Parent/Guardian') {
    $role = 'Employee';
}

$stmt = $conn->prepare("
    INSERT INTO accounts (firstName, lastName, role, email, password, verification_code, code_expires, is_verified, consent_agreed, consent_date, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
");
$stmt->bind_param("sssssssiss", $firstName, $lastName, $role, $email, $hashed_password, $code, $expires, $consent, $consent_date, $status);

if ($stmt->execute()) {
    require_once 'mailer.php';
    $sent = sendVerificationEmail($email, $firstName, $code);

    $_SESSION['verify_email'] = $email;
    $_SESSION['verify_name']  = $firstName;

    header("Location: ../pages/verify.php" . ($sent ? '' : '?error=email_failed'));
    exit();
} else {
    if ($conn->errno == 1062) {
        header("Location: ../pages/$redirect_page?error=email_exists");
    } else {
        header("Location: ../pages/$redirect_page?error=db_error");
    }
    exit();
}

$stmt->close();
$conn->close();
?>
