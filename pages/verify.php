<?php
require_once '../php/auth.php';
secureSessionStart();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPH - Verify Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --green-accent: #78bc27; --green-hover: #5e9a1d; --green-dark: #2d6a07; --radius: 12px; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(45,90,14,0.88), rgba(61,107,15,0.78)),
                        url('../images/happy.jpg') center/cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif; padding: 1rem;
        }
        .auth-card { border-top: 5px solid var(--green-accent); max-width: 460px; width: 100%; border-radius: 16px; }
        .auth-card .card-body { padding: 2rem; }
        .auth-card img { height: 56px; }
        .btn-green { background-color: var(--green-accent); border: none; color: #fff; font-weight: 700; border-radius: var(--radius); transition: all 0.25s ease; }
        .btn-green:hover { background-color: var(--green-hover); color: #fff; }
        .btn-green:disabled { background-color: #c1df86; }
        .otp-wrapper { display: flex; justify-content: center; gap: 0.5rem; }
        .otp-digit {
            width: 48px; height: 56px; text-align: center; font-size: 1.4rem; font-weight: 700;
            border: 2px solid #dee2e6; border-radius: 10px; outline: none;
            transition: border-color 0.2s, box-shadow 0.2s; color: var(--green-dark); flex-shrink: 0;
        }
        .otp-digit:focus { border-color: var(--green-accent); box-shadow: 0 0 0 0.2rem rgba(120,188,39,0.15); background-color: #f8fff0; }
        .link-green { color: var(--green-accent); font-weight: 700; text-decoration: none; }
        .link-green:hover { text-decoration: underline; color: var(--green-hover); }
        @media (min-width: 576px) { .auth-card .card-body { padding: 2.5rem; } .auth-card img { height: 64px; } .otp-digit { width: 52px; height: 60px; font-size: 1.5rem; } .otp-wrapper { gap: 0.6rem; } }
        @media (max-width: 380px) { .auth-card .card-body { padding: 1.5rem; } .otp-digit { width: 40px; height: 48px; font-size: 1.2rem; } .otp-wrapper { gap: 0.35rem; } }
    </style>
</head>
<body>
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body text-center">
            <div class="mb-4">
                <img src="../images/logo.png" alt="NutriPH Logo" class="mb-3">
                <h5 class="fw-bold text-success">VERIFY YOUR EMAIL</h5>
            </div>

            <p class="text-muted mb-1" style="font-size:0.9rem;">A 6-digit verification code was sent to your email.</p>
            <p class="text-muted small mb-4">Enter the code below to activate your account.</p>

            <div class="alert alert-danger small py-2 d-none" id="errorMsg"><i class="fa-solid fa-circle-exclamation me-1"></i><span id="errorText"></span></div>
            <div class="alert alert-success small py-2 d-none" id="successMsg"><i class="fa-solid fa-circle-check me-1"></i><span id="successText"></span></div>
            <form action="../php/verify.php" method="post" onsubmit="collectCode()">
                <?= csrfField() ?>
                <input type="hidden" name="code" id="codeInput">
                <div class="otp-wrapper mb-4">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>
                <button type="submit" class="btn btn-green w-100 py-2 mb-3" id="verifyBtn" disabled>
                    <i class="fa-solid fa-shield-check me-2"></i>Verify Email
                </button>
            </form>

            <p class="text-muted small mb-2">
                Didn't receive a code? <a href="../php/resend.php" class="link-green">Resend code</a>
            </p>
            <p class="small mb-0">
                <a href="signin.php" class="text-muted text-decoration-none">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Sign In
                </a>
            </p>
        </div>
    </div>

    <script>
        const digits = document.querySelectorAll('.otp-digit');
        const codeInput = document.getElementById('codeInput');
        const verifyBtn = document.getElementById('verifyBtn');

        digits.forEach((input, i) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '');
                if (input.value && i < digits.length - 1) digits[i + 1].focus();
                verifyBtn.disabled = ![...digits].every(d => d.value !== '');
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && i > 0) digits[i - 1].focus();
            });
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((ch, j) => { if (digits[j]) digits[j].value = ch; });
                if (digits[pasted.length - 1]) digits[pasted.length - 1].focus();
                verifyBtn.disabled = ![...digits].every(d => d.value !== '');
            });
        });

        function collectCode() { codeInput.value = [...digits].map(d => d.value).join(''); }

        const params = new URLSearchParams(window.location.search);
        const error = params.get('error');
        const success = params.get('success');

        const errorMessages = {
            wrong_code: 'Incorrect code. Please try again.',
            code_expired: 'The code has expired. Please request a new one.',
            invalid_code: 'Please enter the full 6-digit code.',
            not_found: 'Account not found or already verified.',
            not_verified: 'Please verify your email to continue.',
            email_failed: 'Email could not be sent. Use Resend code.',
            db_error: 'Database error. Please try again later.'
        };

        if (error && errorMessages[error]) {
            document.getElementById('errorText').textContent = errorMessages[error];
            document.getElementById('errorMsg').classList.remove('d-none');
        }
        if (success === 'resent') {
            document.getElementById('successText').textContent = 'A new code has been sent to your email.';
            document.getElementById('successMsg').classList.remove('d-none');
        }

        digits[0].focus();
    </script>
</body>
</html>
