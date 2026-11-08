<?php require_once '../php/auth.php'; secureSessionStart(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPH - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green-accent: #78bc27; --green-hover: #5e9a1d; --radius: 12px; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(45,90,14,0.88), rgba(61,107,15,0.78)),
                        url('../images/happy.jpg') center/cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', Tahoma, sans-serif; padding: 1rem;
        }
        .auth-card { border-top: 5px solid var(--green-accent); max-width: 430px; width: 100%; border-radius: 16px; }
        .auth-card .card-body { padding: 2rem; }
        .auth-card img { height: 56px; }
        .btn-green { background-color: var(--green-accent); border: none; color: #fff; font-weight: 700; border-radius: var(--radius); transition: all 0.25s ease; }
        .btn-green:hover { background-color: var(--green-hover); color: #fff; }
        .form-control { border-radius: 8px; font-size: 0.9rem; padding: 0.55rem 0.85rem; }
        .form-control:focus { border-color: var(--green-accent); box-shadow: 0 0 0 0.2rem rgba(120,188,39,0.12); }
        .input-group-text { border-radius: 8px 0 0 8px; font-size: 0.85rem; }
        .link-green { color: var(--green-accent); font-weight: 700; text-decoration: none; }
        @media (min-width: 576px) { .auth-card .card-body { padding: 2.5rem; } }
        @media (max-width: 400px) { .auth-card .card-body { padding: 1.5rem; } }
    </style>
</head>
<body>
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body">
            <div class="text-center mb-4">
                <img src="../images/logo.png" alt="NutriPH Logo" class="mb-3">
                <h5 class="fw-bold text-success">RESET PASSWORD</h5>
                <p class="text-muted small">Enter the code sent to your email and your new password.</p>
            </div>

            <div class="alert alert-danger small py-2 d-none" id="errorMsg"><i class="fa-solid fa-circle-exclamation me-1"></i><span id="errorText"></span></div>
            <div class="alert alert-success small py-2 d-none" id="successMsg"><i class="fa-solid fa-circle-check me-1"></i><span id="successText"></span></div>

            <?php if (!empty($_SESSION['dev_reset_code'])): ?>
                <div class="alert alert-warning small py-2">
                    Local XAMPP code: <strong><?= htmlspecialchars($_SESSION['dev_reset_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>

            <form action="../php/reset_password.php" method="post">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Reset Code</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-hashtag text-muted"></i></span>
                        <input type="text" class="form-control" name="code" placeholder="6-digit code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" name="new_password" placeholder="Min 6 characters" minlength="6" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold text-muted">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" minlength="6" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-green w-100 py-2 mb-3">
                    <i class="fa-solid fa-key me-2"></i>Reset Password
                </button>
            </form>
            <p class="text-center small mb-0"><a href="forgot_password.php" class="text-muted text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Resend code</a></p>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const error = params.get('error');
        const msgs = {
            invalid_code: 'Enter the 6-digit reset code.',
            wrong_code: 'Incorrect code. Try again.',
            code_expired: 'Code expired. Request a new one.',
            mismatch: 'Passwords do not match.',
            weak: 'Password must be at least 6 characters.',
            no_session: 'Session expired. Request a new code.',
            email_failed: 'The reset email could not be sent. Check the local code above if available, or try again later.',
            db_error: 'Database error. Try again.'
        };
        if (error && msgs[error]) { document.getElementById('errorText').textContent = msgs[error]; document.getElementById('errorMsg').classList.remove('d-none'); }
        if (params.get('success') === 'sent') {
            document.getElementById('successText').textContent = 'Reset code sent. Check your email.';
            document.getElementById('successMsg').classList.remove('d-none');
        }
    </script>
</body>
</html>
