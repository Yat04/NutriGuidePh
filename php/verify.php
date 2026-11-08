<?php
require_once 'auth.php';
secureSessionStart();

if (!isset($_SESSION['verify_email'])) {
    header("Location: ../pages/signin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/verify.php");
    exit();
}

verifyCsrf();

$code  = trim($_POST['code']);
$email = $_SESSION['verify_email'];

if (empty($code) || strlen($code) !== 6) {
    header("Location: ../pages/verify.php?error=invalid_code");
    exit();
}

$conn = getDB();

$stmt = $conn->prepare("SELECT role, verification_code, code_expires FROM accounts WHERE email = ? AND is_verified = 0");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../pages/verify.php?error=not_found");
    exit();
}

$row = $result->fetch_assoc();

// Check expiry
if (new DateTime() > new DateTime($row['code_expires'])) {
    header("Location: ../pages/verify.php?error=code_expired");
    exit();
}

// Check code
if ($code !== $row['verification_code']) {
    header("Location: ../pages/verify.php?error=wrong_code");
    exit();
}

// Activate verified staff accounts so they can log in
$role = $row['role'];
$status = ($role === 'Parent/Guardian') ? 'active' : 'active';
$upd = $conn->prepare("UPDATE accounts SET is_verified = 1, status = ?, verification_code = NULL, code_expires = NULL WHERE email = ?");
$upd->bind_param("ss", $status, $email);
$upd->execute();

unset($_SESSION['verify_email'], $_SESSION['verify_name']);

$stmt->close();
$upd->close();
$conn->close();

header("Location: ../pages/signin.php?success=registered");
exit();
?>
