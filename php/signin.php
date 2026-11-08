<?php
require_once 'auth.php';
secureSessionStart();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrf();
    $email    = strtolower(trim($_POST['Email']));
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header("Location: ../pages/signin.php?error=empty_fields");
        exit();
    }

    // Rate limit: 5 attempts per 5 minutes
    if (!checkRateLimit('login_' . $email, 5, 300)) {
        header("Location: ../pages/signin.php?error=rate_limited");
        exit();
    }

    $conn = getDB();

    $stmt = $conn->prepare("SELECT firstName, password, is_verified, role, status FROM accounts WHERE LOWER(email) = LOWER(?)");
    if ($stmt === false) {
        $conn->close();
        header("Location: ../pages/signin.php?error=db_error");
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user['role'] = normalizeRole($user['role'] ?? '');

        if (password_verify($password, $user['password']) || $password === $user['password']) {
            if (!$user['is_verified']) {
                $_SESSION['verify_email'] = $email;
                $_SESSION['verify_name']  = $user['firstName'];
                header("Location: ../pages/verify.php?error=not_verified");
                exit();
            }

            $isStaffRole = in_array($user['role'], ['Employee', 'Admin', 'Super Admin']);
            $isPendingStaff = ($user['status'] ?? 'pending') === 'pending' && $isStaffRole;
            if ($isPendingStaff) {
                $conn->query("UPDATE accounts SET status='active' WHERE LOWER(email)=LOWER('" . $conn->real_escape_string($email) . "')");
            }

            if (($user['status'] ?? '') === 'deactivated') {
                header("Location: ../pages/signin.php?error=deactivated");
                exit();
            }

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']   = $email;
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['role']      = $user['role'];

            auditLog('login', 'account', null, $user['role']);

            unset($_SESSION['rate_login_' . $email]);

            if ($user['role'] === 'Parent/Guardian') {
                header("Location: parent_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            header("Location: ../pages/signin.php?error=wrong_password");
            exit();
        }
    } else {
        header("Location: ../pages/signin.php?error=no_account");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
