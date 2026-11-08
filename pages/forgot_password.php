<?php require_once '../php/auth.php'; secureSessionStart(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPH - Forgot Password</title>
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
        .btn-green:hover { background-color: var(--green-hover); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(120,188,39,0.3); }
        .form-control { border-radius: 8px; font-size: 0.9rem; padding: 0.55rem 0.85rem; }
        .form-control:focus { border-color: var(--green-accent); box-shadow: 0 0 0 0.2rem rgba(120,188,39,0.12); }
        .input-group-text { border-radius: 8px 0 0 8px; font-size: 0.85rem; }
        .link-green { color: var(--green-accent); font-weight: 700; text-decoration: none; }
        .link-green:hover { text-decoration: underline; color: var(--green-hover); }
        @media (min-width: 576px) { .auth-card .card-body { padding: 2.5rem; } .auth-card img { height: 64px; } }
        @media (max-width: 400px) { .auth-card .card-body { padding: 1.5rem; } .auth-card img { height: 48px; } }
    </style>
</head>
<body>
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body text-center">
            <img src="../images/logo.png" alt="NutriPH Logo" class="mb-3">
            <h5 class="fw-bold text-success mb-2">FORGOT PASSWORD</h5>
            <p class="text-muted small mb-4">Enter your email and we'll send a reset code.</p>

            <div class="alert alert-danger small py-2 d-none" id="errorMsg"><i class="fa-solid fa-circle-exclamation me-1"></i><span id="errorText"></span></div>
            <div class="alert alert-success small py-2 d-none" id="successMsg"><i class="fa-solid fa-circle-check me-1"></i><span id="successText"></span></div>

            <form action="../php/forgot_password.php" method="post">
                <?= csrfField() ?>
                <div class="mb-4 text-start">
                    <label class="form-label small fw-semibold text-muted">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-green w-100 py-2 mb-3">
                    <i class="fa-solid fa-paper-plane me-2"></i>Send Reset Code
                </button>
            </form>

            <p class="small mb-0"><a href="signin.php" class="text-muted text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Back to Sign In</a></p>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const error = params.get('error');
        const success = params.get('success');
        const msgs = {
            no_account: 'No account found with that email.',
            db_error: 'Database error. Try again later.',
            rate_limited: 'Too many attempts. Wait 5 minutes.',
            email_failed: 'Could not send email. Try again.'
        };
        if (error && msgs[error]) { document.getElementById('errorText').textContent = msgs[error]; document.getElementById('errorMsg').classList.remove('d-none'); }
        if (success === 'sent') { document.getElementById('successText').textContent = 'Reset code sent! Check your email.'; document.getElementById('successMsg').classList.remove('d-none'); }
    </script>
</body>
</html>
