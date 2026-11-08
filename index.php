<?php require_once 'php/auth.php'; secureSessionStart(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Community-Based Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="images/logo.png" alt="Logo" height="40">
                <span class="fw-bold brand-text">NutriPh Guide</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto gap-1 align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="php/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="php/feeding_program.php">Feeding Program</a></li>
                        <li class="nav-item">
                            <span class="nav-link text-success-light fw-semibold"><i class="fa-solid fa-user me-1"></i>Hi, <?= htmlspecialchars($_SESSION['firstName']) ?></span>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="php/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="index.php" data-section="home">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="#how-it-works" data-section="how-it-works">How It Works</a></li>
                        <li class="nav-item"><a class="nav-link" href="#nutrition" data-section="nutrition">Nutrition Guide</a></li>
                        <li class="nav-item"><a class="nav-link" href="#bmi" data-section="bmi">BMI Info</a></li>
                        <li class="nav-item"><a class="nav-link" href="#footer" data-section="footer">About Us</a></li>
                        <li class="nav-item"><a class="nav-link btn btn-success btn-sm text-white px-3 ms-2" href="pages/signin.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mobile Offcanvas -->
    <div class="offcanvas offcanvas-end text-bg-dark" id="mobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title brand-text">NutriPh Guide</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="navbar-nav gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="php/dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="php/feeding_program.php"><i class="fa-solid fa-utensils me-2"></i>Feeding Program</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="#footer"><i class="fa-solid fa-circle-info me-2"></i>About Us</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="php/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link text-white" href="#how-it-works" data-section="how-it-works"><i class="fa-solid fa-list-ol me-2"></i>How It Works</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="#nutrition" data-section="nutrition"><i class="fa-solid fa-apple-whole me-2"></i>Nutrition Guide</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="#bmi" data-section="bmi"><i class="fa-solid fa-weight-scale me-2"></i>BMI Info</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="#footer" data-section="footer"><i class="fa-solid fa-circle-info me-2"></i>About Us</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="pages/signin.php"><i class="fa-solid fa-right-to-bracket me-2"></i>Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center py-4 py-lg-5">
                <div class="col-lg-7 text-center text-lg-start mb-4 mb-lg-0" data-aos="fade-right">
                    <span class="badge bg-white bg-opacity-25 text-white px-3 py-2 mb-3 rounded-pill" style="font-size:0.78rem;">
                        <i class="fa-solid fa-heart-pulse me-1"></i> San Antonio Central School
                    </span>
                    <h1 class="fw-bold text-white mb-3">Community-Based<br>Nutrition Monitoring</h1>
                    <p class="lead text-white-50 mb-4">Track and support student health through BMI monitoring, nutritional guidance, and feeding programs — all in one place.</p>
                    <div class="d-flex gap-2 justify-content-center justify-content-lg-start flex-wrap">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="pages/signin.php" class="btn btn-light btn-lg px-4 fw-semibold"><i class="fa-solid fa-right-to-bracket me-2"></i>Get Started</a>
                            <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4 fw-semibold"><i class="fa-solid fa-circle-play me-2"></i>Learn More</a>
                        <?php else: ?>
                            <a href="php/dashboard.php" class="btn btn-light btn-lg px-4 fw-semibold"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5 text-center mt-3 mt-lg-0" data-aos="fade-left">
                    <div class="notice-card">
                        <h5><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Notice</h5>
                        <p class="mb-0"><strong>It's not us who will give prescription or suggestions that you will receive from the doctor.</strong> We made this website to ease the manual process of input of students' data of their BMI status and their classification.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 bg-white" id="how-it-works">
        <div class="container">
            <h2 class="text-center fw-bold text-success-dark mb-2" data-aos="fade-up">How It Works</h2>
            <p class="text-center text-muted mb-5" data-aos="fade-up">Simple steps to monitor student nutrition</p>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="step-number">1</div>
                        <div>
                            <h6 class="fw-bold mb-1">Record Student Data</h6>
                            <p class="text-muted small mb-0">Enter height, weight, and personal details. BMI is calculated automatically.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="step-number">2</div>
                        <div>
                            <h6 class="fw-bold mb-1">Classify & Detect</h6>
                            <p class="text-muted small mb-0">Students are classified as Underweight, Normal, Overweight, or Obese with health alerts.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="step-number">3</div>
                        <div>
                            <h6 class="fw-bold mb-1">Notify Guardians</h6>
                            <p class="text-muted small mb-0">Guardians receive email alerts with dietary recommendations for at-risk students.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="step-number">4</div>
                        <div>
                            <h6 class="fw-bold mb-1">Feeding Program</h6>
                            <p class="text-muted small mb-0">Underweight students are enrolled in the feeding program with tracked meal sessions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Counter -->
    <section class="counter-section py-4">
        <div class="container">
            <div class="row g-3 text-center">
                <div class="col-6 col-lg-3" data-aos="zoom-in">
                    <div class="counter-value"><i class="fa-solid fa-clipboard-check me-1" style="font-size:0.7em;"></i> BMI</div>
                    <div class="counter-label">Tracking System</div>
                </div>
                <div class="col-6 col-lg-3" data-aos="zoom-in" data-aos-delay="100">
                    <div class="counter-value"><i class="fa-solid fa-envelope me-1" style="font-size:0.7em;"></i> Email</div>
                    <div class="counter-label">Health Alerts</div>
                </div>
                <div class="col-6 col-lg-3" data-aos="zoom-in" data-aos-delay="200">
                    <div class="counter-value"><i class="fa-solid fa-utensils me-1" style="font-size:0.7em;"></i> Feeding</div>
                    <div class="counter-label">Program Tracking</div>
                </div>
                <div class="col-6 col-lg-3" data-aos="zoom-in" data-aos-delay="300">
                    <div class="counter-value"><i class="fa-solid fa-file-excel me-1" style="font-size:0.7em;"></i> Export</div>
                    <div class="counter-label">Excel Reports</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Nutrition Guide Section -->
    <section class="py-5 bg-light" id="nutrition">
        <div class="container">
            <h2 class="text-center fw-bold text-success-dark mb-2" data-aos="fade-up">Nutrition Guide</h2>
            <p class="text-center text-muted mb-5" data-aos="fade-up">Essential nutrition tips for growing children</p>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6" data-aos="fade-up">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-success-subtle text-success mx-auto mb-3"><i class="fa-solid fa-carrot fa-lg"></i></div>
                            <h6 class="fw-bold">Eat Balanced Meals</h6>
                            <p class="text-muted small mb-0">Include fruits, vegetables, lean protein, and whole grains in every meal to support healthy growth.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-primary-subtle text-primary mx-auto mb-3"><i class="fa-solid fa-glass-water fa-lg"></i></div>
                            <h6 class="fw-bold">Stay Hydrated</h6>
                            <p class="text-muted small mb-0">Children should drink at least 6–8 glasses of water daily. Avoid sugary drinks with empty calories.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-warning-subtle text-warning mx-auto mb-3"><i class="fa-solid fa-bowl-rice fa-lg"></i></div>
                            <h6 class="fw-bold">Proper Calorie Intake</h6>
                            <p class="text-muted small mb-0">School-age children need 1,200–2,000 calories daily depending on age, sex, and activity level.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 border-0 shadow-sm card-hover">
                        <div class="card-body text-center p-4">
                            <div class="icon-circle bg-danger-subtle text-danger mx-auto mb-3"><i class="fa-solid fa-person-running fa-lg"></i></div>
                            <h6 class="fw-bold">Active Lifestyle</h6>
                            <p class="text-muted small mb-0">Encourage at least 60 minutes of physical activity daily to complement proper nutrition.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BMI Info Section -->
    <section class="py-5 bg-white" id="bmi">
        <div class="container">
            <h2 class="text-center fw-bold text-success-dark mb-2" data-aos="fade-up">BMI Classification</h2>
            <p class="text-center text-muted mb-5" data-aos="fade-up">Body Mass Index (BMI) is used to assess whether a child's weight is healthy for their height.</p>
            <div class="row justify-content-center" data-aos="fade-up">
                <div class="col-lg-9">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle shadow-sm rounded-3 overflow-hidden mb-0">
                            <thead class="table-success-dark text-white">
                                <tr><th>Classification</th><th>BMI Range</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                                <tr class="table-warning">
                                    <td><i class="fa-solid fa-arrow-down me-1 text-warning"></i>Underweight</td>
                                    <td>Below 18.5</td><td>May indicate malnourishment. Needs dietary intervention.</td>
                                </tr>
                                <tr class="table-success">
                                    <td><i class="fa-solid fa-check me-1 text-success"></i>Normal Weight</td>
                                    <td>18.5 – 24.9</td><td>Healthy range. Maintain balanced diet and activity.</td>
                                </tr>
                                <tr style="background-color: #fde8c8;">
                                    <td><i class="fa-solid fa-arrow-up me-1 text-orange"></i>Overweight</td>
                                    <td>25.0 – 29.9</td><td>At risk. Encourage healthier eating and exercise.</td>
                                </tr>
                                <tr class="table-danger">
                                    <td><i class="fa-solid fa-exclamation me-1 text-danger"></i>Obese</td>
                                    <td>30.0 and above</td><td>High health risk. Medical consultation recommended.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-section" id="footer">
        <div class="container py-5">
            <div class="row g-4 align-items-center">
                <div class="col-md-6" data-aos="fade-up">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="images/logo.png" alt="Logo" height="36" class="footer-logo">
                        <h5 class="mb-0 text-success-light">NutriPh Guide</h5>
                    </div>
                    <p class="text-muted small">A community-based monitoring and support system for tracking malnourished students at San Antonio Central School.</p>
                </div>
                <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <h6 class="text-success-light text-uppercase small fw-bold mb-3">Contact & Address</h6>
                    <ul class="list-unstyled text-muted small">
                        <li class="mb-2"><i class="fa-solid fa-envelope me-2"></i>abcdefg@gmail.com</li>
                        <li class="mb-2"><i class="fa-solid fa-location-dot me-2"></i>San Antonio Central School, Philippines</li>
                        <li class="mb-2"><i class="fa-solid fa-phone me-2"></i>+63 900 000 0000</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="text-center py-3 border-top border-secondary small text-muted footer-bottom">
            &copy; <?= date('Y') ?> NutriPh Guide &mdash; San Antonio Central School. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 700, once: true });
        if ('serviceWorker' in navigator) { navigator.serviceWorker.register('sw.js'); }
        const nav = document.getElementById('mainNav');
        const navLinks = document.querySelectorAll('.navbar .nav-link[data-section], .offcanvas .nav-link[data-section]');
        const sections = document.querySelectorAll('section[id], footer[id]');

        function setActiveNav(sectionId) {
            navLinks.forEach(link => {
                const isActive = link.dataset.section === sectionId;
                link.classList.toggle('active', isActive);
            });
        }

        function updateActiveNav() {
            let activeSection = 'home';

            sections.forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top <= 170 && rect.bottom >= 170) {
                    activeSection = section.id;
                }
            });

            if (window.scrollY < 120) {
                activeSection = 'home';
            }

            setActiveNav(activeSection);
        }

        window.addEventListener('scroll', updateActiveNav, { passive: true });
        window.addEventListener('load', updateActiveNav);
        document.querySelectorAll('a[href^="#"]').forEach(link => {
            link.addEventListener('click', () => {
                const id = link.getAttribute('href').replace('#', '');
                if (id) {
                    setTimeout(updateActiveNav, 60);
                }
            });
        });

        window.addEventListener('scroll', () => {
            nav.classList.toggle('navbar-scrolled', window.scrollY > 50);
        });
    </script>
</body>
</html>
