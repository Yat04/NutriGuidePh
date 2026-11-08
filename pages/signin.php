<?php
require_once '../php/auth.php';
secureSessionStart();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPH - Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green-accent: #78bc27; --green-hover: #5e9a1d; --green-dark: #2d6a07; --radius: 12px; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(45,90,14,0.88), rgba(61,107,15,0.78)),
                        url('../images/happy.jpg') center/cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif; padding: 1rem;
        }
        .auth-card { border-top: 5px solid var(--green-accent); max-width: 430px; width: 100%; border-radius: 16px; }
        .auth-card .card-body { padding: 2rem; }
        .auth-card img { height: 60px; }
        .auth-card h5 { font-size: 1.1rem; letter-spacing: 0.5px; }
        .btn-green { background-color: var(--green-accent); border: none; color: #fff; font-weight: 700; letter-spacing: 0.3px; border-radius: var(--radius); transition: all 0.25s ease; }
        .btn-green:hover { background-color: var(--green-hover); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(120,188,39,0.3); }
        .form-control { border-radius: 8px; font-size: 0.9rem; padding: 0.55rem 0.85rem; }
        .form-control:focus { border-color: var(--green-accent); box-shadow: 0 0 0 0.2rem rgba(120,188,39,0.12); }
        .input-group-text { border-radius: 8px 0 0 8px; font-size: 0.85rem; }
        .toggle-pass { cursor: pointer; color: var(--green-accent); font-size: 0.8rem; font-weight: 700; user-select: none; border-radius: 0 8px 8px 0; }
        .link-green { color: var(--green-accent); font-weight: 700; text-decoration: none; }
        .link-green:hover { text-decoration: underline; color: var(--green-hover); }
        .form-label { font-size: 0.82rem; }
        .divider { display: flex; align-items: center; gap: 0.75rem; margin: 1rem 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #dee2e6; }
        .divider span { font-size: 0.75rem; color: #aaa; font-weight: 500; }
        @media (min-width: 576px) { .auth-card .card-body { padding: 2.5rem; } .auth-card img { height: 68px; } }
        @media (max-width: 400px) { .auth-card .card-body { padding: 1.5rem; } .auth-card img { height: 52px; } .auth-card h5 { font-size: 1rem; } }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body">
            <div class="text-center mb-4">
                <img src="../images/logo.png" alt="NutriPH Logo" class="mb-3">
                <h5 class="fw-bold text-success">NUTRIPH SIGN IN</h5>
            </div>

            <div class="alert alert-danger small py-2 d-none" id="errorMsg" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i><span id="errorText"></span>
            </div>
            <div class="alert alert-success small py-2 d-none" id="successMsg" role="alert">
                <i class="fa-solid fa-circle-check me-1"></i><span id="successText"></span>
            </div>

            <form action="../php/signin.php" method="post" id="signinForm">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label for="Email" class="form-label fw-semibold text-muted">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <input type="email" class="form-control" id="Email" name="Email" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label fw-semibold text-muted mb-0">Password</label>
                <a href="forgot_password.php" class="link-green" style="font-size:0.76rem;">Forgot password?</a>
                    </div>
                    <div class="input-group mt-1">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        <span class="input-group-text bg-light toggle-pass" onclick="togglePassword()">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-green w-100 py-2 mt-3 mb-3" id="submitBtn">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                </button>
            </form>

            <div class="divider"><span>parent?</span></div>

            <p class="text-center text-muted small mb-0">
                <a href="parent_signup.php" class="link-green"><i class="fa-solid fa-user-group me-1"></i>Sign Up as Parent/Guardian</a>
            </p>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const error = params.get('error');
        const success = params.get('success');

        const errorMessages = {
            wrong_password: 'Incorrect password. Please try again.',
            no_account: 'No account found with that email.',
            empty_fields: 'Please fill in all fields.',
            not_verified: 'Your email is not verified yet. Check your inbox.',
            rate_limited: 'Too many login attempts. Please wait 5 minutes.',
            pending_approval: 'Your account is pending admin approval. Please wait for activation.',
            deactivated: 'Your account has been deactivated. Contact the administrator.',
            db_error: 'Database error. Please try again later.'
        };

        if (error && errorMessages[error]) {
            document.getElementById('errorText').textContent = errorMessages[error];
            document.getElementById('errorMsg').classList.remove('d-none');
        }

        if (success === 'registered') {
            document.getElementById('successText').textContent = 'Account created! You can now sign in.';
            document.getElementById('successMsg').classList.remove('d-none');
        }
        if (success === 'password_reset') {
            document.getElementById('successText').textContent = 'Password reset successful! Sign in with your new password.';
            document.getElementById('successMsg').classList.remove('d-none');
        }

        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye'); }
        }

        // Loading spinner on submit
        document.getElementById('signinForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
