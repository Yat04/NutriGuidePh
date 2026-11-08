<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(45,90,14,0.88), rgba(61,107,15,0.78)),
                        url('../images/happy.jpg') center/cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', Tahoma, sans-serif; padding: 1rem;
        }
        .error-card { max-width: 440px; width: 100%; border-radius: 16px; border-top: 5px solid #e74c3c; }
    </style>
</head>
<body>
    <div class="card error-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5 text-center">
            <i class="fa-solid fa-shield-halved fa-3x text-danger mb-3 opacity-50"></i>
            <h5 class="fw-bold text-danger mb-2">Access Denied</h5>
            <p class="text-muted small mb-4">You don't have permission to access this page, or your session has expired.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="../index.php" class="btn btn-outline-secondary btn-sm px-3"><i class="fa-solid fa-home me-1"></i>Home</a>
                <a href="signin.html" class="btn btn-success btn-sm px-3"><i class="fa-solid fa-right-to-bracket me-1"></i>Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
