<?php require_once '../php/auth.php'; secureSessionStart(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPH - Parent/Guardian Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-accent: #78bc27;
            --green-hover: #5e9a1d;
            --green-dark: #2d6a07;
            --radius: 12px;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(45,90,14,0.88), rgba(61,107,15,0.78)),
                        url('../images/happy.jpg') center/cover no-repeat fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif; padding: 1rem;
        }
        .auth-card {
            border-top: 5px solid var(--green-accent);
            max-width: 580px;
            width: 100%;
            border-radius: 16px;
        }
        .auth-card .card-body { padding: 2rem; }
        .auth-card img { height: 56px; }
        .auth-card h5 { font-size: 1.05rem; letter-spacing: 0.5px; }
        .btn-green {
            background-color: var(--green-accent);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: var(--radius);
            transition: all 0.25s ease;
        }
        .btn-green:hover {
            background-color: var(--green-hover);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(120,188,39,0.3);
        }
        .btn-green:disabled { background-color: #c1df86; transform: none; box-shadow: none; }
        .form-control, .form-select {
            border-radius: 8px;
            font-size: 0.9rem;
            padding: 0.55rem 0.85rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--green-accent);
            box-shadow: 0 0 0 0.2rem rgba(120,188,39,0.12);
        }
        .input-group-text { border-radius: 8px 0 0 8px; font-size: 0.85rem; }
        .toggle-pass {
            cursor: pointer;
            color: var(--green-accent);
            font-size: 0.8rem;
            font-weight: 700;
            user-select: none;
            border-radius: 0 8px 8px 0;
        }
        .section-divider {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--green-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dde8c8;
            padding-bottom: 6px;
        }
        .link-green { color: var(--green-accent); font-weight: 700; text-decoration: none; }
        .link-green:hover { text-decoration: underline; color: var(--green-hover); }
        .form-label { font-size: 0.82rem; }
        .consent-box {
            max-height: 220px;
            overflow-y: auto;
            background: #f8fff0;
            border: 2px solid #dde8c8;
            border-radius: var(--radius);
            padding: 1rem;
            font-size: 0.82rem;
            line-height: 1.7;
            color: #444;
        }
        .consent-box h6 { color: var(--green-dark); font-size: 0.85rem; }
        .consent-box ul { padding-left: 1.2rem; }
        .consent-box li { margin-bottom: 4px; }
        .form-check-input:checked {
            background-color: var(--green-accent);
            border-color: var(--green-accent);
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(120,188,39,0.2);
        }

        @media (min-width: 576px) {
            .auth-card .card-body { padding: 2.5rem; }
            .auth-card img { height: 64px; }
        }
        @media (max-width: 400px) {
            .auth-card .card-body { padding: 1.5rem; }
            .auth-card img { height: 48px; }
            .auth-card h5 { font-size: 0.95rem; }
            .consent-box { max-height: 180px; font-size: 0.78rem; }
        }
    </style>
</head>
<body>
    <div class="card auth-card shadow-lg border-0">
        <div class="card-body">
            <div class="text-center mb-4">
                <img src="../images/logo.png" alt="NutriPH Logo" class="mb-3">
                <h5 class="fw-bold text-success">PARENT / GUARDIAN SIGN UP</h5>
                <p class="text-muted small mb-0">Create your account and provide consent for your child's data</p>
            </div>

            <div class="alert alert-danger small py-2 d-none" id="errorMsg" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i><span id="errorText"></span>
            </div>

            <form action="../php/connect.php" method="post" id="parentForm">
                <?= csrfField() ?>
                <input type="hidden" name="role" value="Parent/Guardian">

                <p class="section-divider mb-3">Personal Information</p>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold text-muted">First Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" class="form-control" name="firstName" placeholder="First name" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold text-muted">Last Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" class="form-control" name="lastName" placeholder="Last name" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted">Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-envelope text-muted"></i></span>
                        <input type="email" class="form-control" name="Email" placeholder="Enter your email" required>
                    </div>
                </div>

                <p class="section-divider mb-3 mt-4">Security</p>

                <div class="mb-2">
                    <label class="form-label fw-semibold text-muted">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Create a strong password" required minlength="6">
                        <span class="input-group-text bg-light toggle-pass" onclick="togglePassword('password','eyeIcon1')">
                            <i class="fa-solid fa-eye" id="eyeIcon1"></i>
                        </span>
                    </div>
                    <div style="height:4px;border-radius:2px;background:#eee;margin-top:6px;overflow:hidden;"><div id="strengthBar" style="height:100%;border-radius:2px;transition:width 0.3s,background 0.3s;width:0;"></div></div>
                    <div id="strengthText" style="font-size:0.72rem;font-weight:600;margin-top:3px;"></div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" id="password1" name="confirm_password" placeholder="Confirm your password" required minlength="6">
                        <span class="input-group-text bg-light toggle-pass" onclick="togglePassword('password1','eyeIcon2')">
                            <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                        </span>
                    </div>
                    <div class="text-danger small mt-1 d-none" id="matchError"><i class="fa-solid fa-xmark me-1"></i>Passwords do not match</div>
                    <div class="text-success small mt-1 d-none" id="matchOk"><i class="fa-solid fa-check me-1"></i>Passwords match</div>
                </div>

                <p class="section-divider mb-3 mt-4"><i class="fa-solid fa-file-shield me-1"></i>Data Privacy Consent</p>

                <div class="consent-box mb-3">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-shield-halved me-1"></i> Parental/Guardian Consent Form</h6>
                    <p class="mb-2">By signing up and checking the box below, I, as the parent or legal guardian, voluntarily give my consent to <b>NutriPh Guide – San Antonio Central School</b> to collect, store, and process the following data of my child:</p>
                    <ul>
                        <li>Full name, gender, and date of birth</li>
                        <li>Height, weight, and BMI classification</li>
                        <li>Guardian/parent contact information (name, phone, email)</li>
                        <li>Nutritional status and feeding program records</li>
                    </ul>

                    <h6 class="fw-bold mb-2 mt-3">Purpose of Data Collection</h6>
                    <p class="mb-2">The data will be used solely for the following purposes:</p>
                    <ul>
                        <li>Monitoring and tracking the nutritional health status of students</li>
                        <li>Identifying malnourished (underweight, overweight, obese) students for intervention</li>
                        <li>Enrolling qualified students in the school's Feeding Program</li>
                        <li>Sending health alerts and nutritional guidance to parents/guardians via email</li>
                        <li>Generating reports for school health records and compliance</li>
                    </ul>

                    <h6 class="fw-bold mb-2 mt-3">Data Protection</h6>
                    <ul>
                        <li>All data is stored securely and will <b>not</b> be shared with third parties without your explicit consent</li>
                        <li>Only authorized school personnel and administrators can access the data</li>
                        <li>You may request to view, update, or delete your child's data at any time by contacting the school</li>
                        <li>Data will be retained only for the duration of your child's enrollment at San Antonio Central School</li>
                    </ul>

                    <h6 class="fw-bold mb-2 mt-3">Your Rights</h6>
                    <ul>
                        <li>Right to access your child's records</li>
                        <li>Right to request correction of inaccurate data</li>
                        <li>Right to withdraw consent at any time (note: this may affect your child's participation in the feeding program)</li>
                        <li>Right to file a complaint regarding data handling</li>
                    </ul>

                    <p class="mb-0 mt-3" style="color:#2d6a07;font-weight:600;">This consent is in compliance with the <b>Data Privacy Act of 2012 (Republic Act No. 10173)</b> of the Philippines.</p>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="consentCheck" name="consent_agreed" value="1">
                    <label class="form-check-label small" for="consentCheck">
                        I have read and understood the above consent form. I <b>voluntarily agree</b> to allow NutriPh Guide to collect and process my child's data for the stated purposes.
                    </label>
                </div>

                <button type="submit" class="btn btn-green w-100 py-2 mb-3" id="submitBtn" disabled>
                    <i class="fa-solid fa-user-plus me-2"></i>Create Parent Account
                </button>
            </form>

            <p class="text-center text-muted small mb-2">
                Already have an account? <a href="signin.php" class="link-green">Sign In</a>
            </p>
            <p class="text-center text-muted small mb-0">
                Are you a staff member? <a href="Signup.html" class="link-green">Staff Sign Up</a>
            </p>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const error = params.get('error');

        const errorMessages = {
            password_mismatch: 'Passwords do not match. Please try again.',
            email_exists: 'An account with that email already exists.',
            empty_fields: 'Please fill in all required fields.',
            consent_required: 'You must agree to the consent form to create an account.',
            db_error: 'Database error. Please try again later.'
        };

        if (error && errorMessages[error]) {
            document.getElementById('errorText').textContent = errorMessages[error];
            document.getElementById('errorMsg').classList.remove('d-none');
        }

        const consentCheck = document.getElementById('consentCheck');
        const submitBtn = document.getElementById('submitBtn');

        // Password strength
        const pw = document.getElementById('password');
        const pw2 = document.getElementById('password1');
        const bar = document.getElementById('strengthBar');
        const txt = document.getElementById('strengthText');
        let pwValid = false;

        pw.addEventListener('input', function() {
            const v = pw.value;
            const has6 = v.length >= 6, hasUp = /[A-Z]/.test(v), hasLo = /[a-z]/.test(v), hasNum = /[0-9]/.test(v);
            let score = [has6, hasUp, hasLo, hasNum].filter(Boolean).length;
            if (v.length >= 10) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            const pct = Math.min(100, (score / 6) * 100);
            bar.style.width = pct + '%';
            if (score <= 2) { bar.style.background = '#e74c3c'; txt.textContent = 'Weak'; txt.style.color = '#e74c3c'; }
            else if (score <= 4) { bar.style.background = '#f39c12'; txt.textContent = 'Medium'; txt.style.color = '#f39c12'; }
            else { bar.style.background = '#27ae60'; txt.textContent = 'Strong'; txt.style.color = '#27ae60'; }
            if (!v) { bar.style.width = '0'; txt.textContent = ''; }
            pwValid = has6;
            checkMatch();
        });

        pw2.addEventListener('input', checkMatch);

        function checkMatch() {
            const match = pw.value === pw2.value && pw2.value.length > 0;
            document.getElementById('matchError').classList.toggle('d-none', match || pw2.value.length === 0);
            document.getElementById('matchOk').classList.toggle('d-none', !match);
            updateBtn();
        }

        consentCheck.addEventListener('change', updateBtn);

        function updateBtn() {
            const match = pw.value === pw2.value && pw2.value.length > 0;
            submitBtn.disabled = !(pwValid && match && consentCheck.checked);
        }

        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        // Loading spinner
        document.getElementById('parentForm').addEventListener('submit', function(e) {
            if (submitBtn.disabled) { e.preventDefault(); return; }
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating account...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
