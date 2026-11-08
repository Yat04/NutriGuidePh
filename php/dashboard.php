<?php
require_once __DIR__ . '/auth.php';
secureSessionStart();

checkAccess(['Employee', 'Admin', 'Super Admin']);

$conn = getDB();

// Dashboard totals use each child's latest record, scoped to adviser assignments.
$scope = getAdviserScope($conn);
$scopeParams = [];
$scopeTypes = '';
$scopeCondition = appendAdviserScope($scope, '', $scopeParams, $scopeTypes);
$latestIds = "SELECT MAX(id) FROM stdRecord WHERE 1=1" . $scopeCondition . " GROUP BY std_first_name, std_mid_initial, std_last_name";
$statsStmt = $conn->prepare("SELECT COUNT(*) AS total,
    SUM(classification IN ('Severe Underweight','Underweight')) AS underweight,
    SUM(classification='Normal Weight') AS normal,
    SUM(classification='Overweight') AS overweight,
    SUM(classification='Obese') AS obese,
    SUM(gender='male') AS males,
    SUM(gender='female') AS females
    FROM stdRecord WHERE id IN ($latestIds)");
if (!empty($scopeParams)) $statsStmt->bind_param($scopeTypes, ...$scopeParams);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc() ?: [];
$statsStmt->close();
$total = (int)($stats['total'] ?? 0);
$underweight = (int)($stats['underweight'] ?? 0);
$normal = (int)($stats['normal'] ?? 0);
$overweight = (int)($stats['overweight'] ?? 0);
$obese = (int)($stats['obese'] ?? 0);
$males = (int)($stats['males'] ?? 0);
$females = (int)($stats['females'] ?? 0);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=4">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= generateCsrf() ?>">
</head>
<body class="page-bg">

    <?php $activePage = 'dashboard'; include 'navbar.php'; ?>

    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2" data-aos="fade-down">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-gauge me-2"></i>Dashboard</h4>
                <p class="text-white-50 small mb-0">Overview of student nutrition records</p>
            </div>
            <a href="addrecord.php" class="btn btn-green px-4">
                <i class="fa-solid fa-plus me-2"></i>Add Record
            </a>
        </div>

        <?php $canEdit = isAdmin(); $canExport = isStaff(); ?>

        <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4 dashboard-metrics-grid">
            <div class="col" data-aos="fade-up">
                <a href="records.php?filter=all" class="dashboard-metric-link" aria-label="View all <?= $total ?> students">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon bg-primary-subtle text-primary"><i class="fa-solid fa-users"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number text-primary"><?= $total ?></div>
                                <div class="text-muted small fw-semibold">Total Students</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="50">
                <a href="records.php?filter=underweight" class="dashboard-metric-link" aria-label="View <?= $underweight ?> underweight students">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon" style="background-color:#fdecea;color:#c0392b;"><i class="fa-solid fa-arrow-down"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number" style="color:#c0392b;"><?= $underweight ?></div>
                                <div class="text-muted small fw-semibold">Underweight</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="100">
                <a href="records.php?filter=normal" class="dashboard-metric-link" aria-label="View <?= $normal ?> students with normal BMI">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon bg-success-subtle text-success"><i class="fa-solid fa-check"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number text-success"><?= $normal ?></div>
                                <div class="text-muted small fw-semibold">Normal</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="150">
                <a href="records.php?filter=overweight" class="dashboard-metric-link" aria-label="View <?= $overweight ?> overweight students">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-arrow-up"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number text-warning"><?= $overweight ?></div>
                                <div class="text-muted small fw-semibold">Overweight</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="200">
                <a href="records.php?filter=obese" class="dashboard-metric-link" aria-label="View <?= $obese ?> students with obesity">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon" style="background-color:#f8d7da;color:#721c24;"><i class="fa-solid fa-exclamation"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number" style="color:#721c24;"><?= $obese ?></div>
                                <div class="text-muted small fw-semibold">Obese</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="250">
                <a href="records.php?gender=male" class="dashboard-metric-link" aria-label="View <?= $males ?> male students">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon" style="background-color:#d6eaf8;color:#2471a3;"><i class="fa-solid fa-mars"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number" style="color:#2471a3;"><?= $males ?></div>
                                <div class="text-muted small fw-semibold">Male</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="300">
                <a href="records.php?gender=female" class="dashboard-metric-link" aria-label="View <?= $females ?> female students">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon" style="background-color:#f5d0e0;color:#c2185b;"><i class="fa-solid fa-venus"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number" style="color:#c2185b;"><?= $females ?></div>
                                <div class="text-muted small fw-semibold">Female</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col" data-aos="fade-up" data-aos-delay="350">
                <a href="records.php?filter=at-risk" class="dashboard-metric-link" aria-label="View <?= $underweight + $overweight + $obese ?> at-risk students">
                    <div class="card stat-card shadow-sm">
                        <div class="dashboard-metric-content">
                            <div class="stat-icon" style="background-color:#fdecea;color:#e74c3c;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="dashboard-metric-copy">
                                <div class="stat-number" style="color:#e74c3c;"><?= $underweight + $overweight + $obese ?></div>
                                <div class="text-muted small fw-semibold">At Risk</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-3 g-lg-4 mb-4">
            <div class="col-md-6" data-aos="fade-up">
                <div class="card chart-card shadow-sm" style="height: 450px; width: 450px; margin: 0 auto;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-chart-pie me-2"></i>BMI Distribution</h6>
                        <canvas id="bmiChart" height="260" style="margin: 0 15px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="card chart-card shadow-sm" style="height: 450px; width: 450px; margin: 0 auto;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-venus-mars me-2"></i>Gender Distribution</h6>
                        <canvas id="genderChart" height="260" style="margin: 0 15px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card table-card shadow-sm mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h6 class="fw-bold text-success mb-0"><i class="fa-solid fa-table-list me-2"></i>Recent Records</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if ($canExport): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-success btn-sm dropdown-toggle px-3" type="button" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-download me-1"></i>Download
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item small" href="export_records.php?type=all"><i class="fa-solid fa-table me-2 text-primary"></i>All Records</a></li>
                                <li><a class="dropdown-item small" href="export_records.php?type=underweight"><i class="fa-solid fa-arrow-down me-2 text-danger"></i>Underweight Only</a></li>
                                <li><a class="dropdown-item small" href="export_records.php?type=normal"><i class="fa-solid fa-check me-2 text-success"></i>Normal Only</a></li>
                                <li><a class="dropdown-item small" href="export_records.php?type=overweight"><i class="fa-solid fa-arrow-up me-2 text-warning"></i>Overweight Only</a></li>
                                <li><a class="dropdown-item small" href="export_records.php?type=obese"><i class="fa-solid fa-exclamation me-2 text-danger"></i>Obese Only</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item small" href="export_records.php?type=malnourished"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>All Malnourished</a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2" style="max-width:360px;width:100%;">
                            <input type="date" class="form-control form-control-sm" id="dateFilter" style="max-width:150px;">
                            <input type="text" class="form-control form-control-sm" id="liveSearch" placeholder="Search name, classification, guardian..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold">Name</th>
                                <th class="small fw-semibold">Gender</th>
                                <th class="small fw-semibold">Grade/Section</th>
                                <th class="small fw-semibold">Height / Height-for-Age</th>
                                <th class="small fw-semibold">Weight</th>
                                <th class="small fw-semibold">BMI</th>
                                <th class="small fw-semibold">Classification</th>
                                <th class="small fw-semibold">Guardian</th>
                                <th class="small fw-semibold">Date</th>
                                <?php if ($canEdit): ?><th class="small fw-semibold action-cell">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="recordsBody">
                            <tr><td colspan="<?= $canEdit ? 10 : 9 ?>" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-success"></div> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="paginationContainer" class="mt-3"></div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <div class="modal fade" id="editRecordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">
                    <h6 class="modal-title text-white fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Record</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-success small py-2 d-none" id="editSuccessMsg"><i class="fa-solid fa-circle-check me-1"></i><span id="editSuccessText"></span></div>
                    <div class="alert alert-danger small py-2 d-none" id="editErrorMsg"><i class="fa-solid fa-circle-exclamation me-1"></i><span id="editErrorText"></span></div>
                    <form id="editRecordForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrf() ?>">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3 mb-3">
                            <div class="col-5">
                                <label class="form-label small fw-semibold text-muted">First Name</label>
                                <input type="text" class="form-control form-control-sm" name="fname" id="edit_fn" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold text-muted">Last Name</label>
                                <input type="text" class="form-control form-control-sm" name="lname" id="edit_ln" required>
                            </div>
                            <div class="col-2">
                                <label class="form-label small fw-semibold text-muted">M.I.</label>
                                <input type="text" class="form-control form-control-sm" name="m_initial" id="edit_mid" maxlength="2">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Date of Birth</label>
                            <input type="date" class="form-control form-control-sm" name="birthday" id="edit_birthday" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Gender</label>
                            <select class="form-select form-select-sm" name="gender" id="edit_gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-8">
                                <label class="form-label small fw-semibold text-muted">Height</label>
                                <input type="number" class="form-control form-control-sm" name="height" id="edit_height" step="any" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-semibold text-muted">Unit</label>
                                <select class="form-select form-select-sm" name="height_unit" id="edit_hunit">
                                    <option value="cm">cm</option>
                                    <option value="m">m</option>
                                    <option value="inch">inch</option>
                                    <option value="feet">feet</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-8">
                                <label class="form-label small fw-semibold text-muted">Weight</label>
                                <input type="number" class="form-control form-control-sm" name="weight" id="edit_weight" step="any" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-semibold text-muted">Unit</label>
                                <select class="form-select form-select-sm" name="weight_unit" id="edit_wunit">
                                    <option value="kg">kg</option>
                                    <option value="lbs">lbs</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Guardian Name</label>
                            <input type="text" class="form-control form-control-sm" name="guardian_name" id="edit_guardian">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">Guardian Number</label>
                                <input type="text" class="form-control form-control-sm" name="guardian_number" id="edit_gnum">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">Guardian Email</label>
                                <input type="email" class="form-control form-control-sm" name="guardian_email" id="edit_gemail">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-green w-100 py-2">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Update Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentHistoryModal" tabindex="-1" aria-labelledby="studentHistoryLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">
                    <h6 class="modal-title text-white fw-bold" id="studentHistoryLabel">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i>Student Record History
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="historyModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-success" role="status"></div>
                        <p class="text-muted small mt-2">Loading records...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-section" id="footer">
        <div class="container py-5">
            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="../images/logo.png" alt="Logo" height="36" class="footer-logo">
                        <h5 class="mb-0 text-success-light">NutriPh Guide</h5>
                    </div>
                    <p class="text-muted small">A community-based monitoring and support system for tracking malnourished students at San Antonio Central School.</p>
                </div>
                <div class="col-md-6">
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 700, once: true });
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const nav = document.querySelector('.navbar');
        window.addEventListener('scroll', () => { nav.classList.toggle('navbar-scrolled', window.scrollY > 50); });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const id = 'toast_' + Date.now();
            const icon = type === 'success' ? 'circle-check' : (type === 'error' ? 'circle-exclamation' : 'triangle-exclamation');
            container.insertAdjacentHTML('beforeend', '<div id="'+id+'" class="toast-custom toast-'+type+' mb-2"><div class="toast-body d-flex align-items-center gap-2"><i class="fa-solid fa-'+icon+'"></i> '+message+'</div></div>');
            setTimeout(() => { const el = document.getElementById(id); if (el) el.remove(); }, 4000);
        }

        function esc(s) { if (!s) return ''; const el = document.createElement('span'); el.textContent = s; return el.innerHTML; }
        function formatDate(d) { return new Date(d).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }); }

        new Chart(document.getElementById('bmiChart'), { type:'pie', data:{ labels:['Underweight / Severe','Normal','Overweight','Obese'], datasets:[{ data:[<?= $underweight ?>,<?= $normal ?>,<?= $overweight ?>,<?= $obese ?>], backgroundColor:['#e74c3c','#27ae60','#f39c12','#8e44ad'], borderWidth:2, borderColor:'#fff' }] }, options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ padding:16, usePointStyle:true } } } } });
        new Chart(document.getElementById('genderChart'), { type:'doughnut', data:{ labels:['Male','Female'], datasets:[{ data:[<?= $males ?>,<?= $females ?>], backgroundColor:['#2471a3','#c2185b'], borderWidth:2, borderColor:'#fff' }] }, options:{ responsive:true, cutout:'55%', plugins:{ legend:{ position:'bottom', labels:{ padding:16, usePointStyle:true } } } } });

        const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
        const colSpan = canEdit ? 10 : 9;
        const tbody = document.getElementById('recordsBody');
        const pagContainer = document.getElementById('paginationContainer');
        const searchInput = document.getElementById('liveSearch');
        const dateInput = document.getElementById('dateFilter');
        let searchTimer = null, currentPage = 1;

        searchInput.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { currentPage = 1; loadRecords(); }, 300); });
        dateInput.addEventListener('change', () => { currentPage = 1; loadRecords(); });

        function loadRecords() {
            const q = searchInput.value.trim();
            const selectedDate = dateInput.value;
            tbody.innerHTML = '<tr><td colspan="'+colSpan+'" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-success"></div> Searching...</td></tr>';
            fetch('search_records.php?search=' + encodeURIComponent(q) + '&page=' + currentPage + '&date=' + encodeURIComponent(selectedDate))
                .then(r => r.json())
                .then(data => {
                    if (!data.records || data.records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="'+colSpan+'" class="text-center text-muted py-4"><i class="fa-solid fa-inbox fa-2x mb-2 opacity-25 d-block"></i>'+(q || selectedDate ? 'No records match your filters.' : 'No records yet.')+'</td></tr>';
                        pagContainer.innerHTML = ''; return;
                    }
                    let html = '';
                    data.records.forEach(r => {
                        const cls = r.classification;
                        const badge = {'Severe Underweight':'badge-underweight',Underweight:'badge-underweight','Normal Weight':'badge-normal',Overweight:'badge-overweight',Obese:'badge-obese'}[cls]||'badge-obese';
                        const nameParts = [r.std_first_name, r.std_mid_initial, r.std_last_name].filter(Boolean).map(part => part.trim());
                        const nm = nameParts.map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase()).join(' ');
                        const dt = formatDate(r.created_at);
                        const gradeSection = [r.grade_level, r.section].filter(Boolean).join(' - ') || '<span class="text-muted">—</span>';
                        html += '<tr><td class="small"><a href="#" class="text-decoration-none fw-semibold student-name-link" style="color:#2d5a0e;" data-fn="'+esc(r.std_first_name)+'" data-ln="'+esc(r.std_last_name)+'" data-mid="'+esc(r.std_mid_initial)+'">'+nm+(Number(r.record_count)>1?' <span class="text-muted fw-normal">('+r.record_count+' records)</span>':'')+'</a>';
                        if (cls !== 'Normal Weight') html += ' <i class="fa-solid fa-triangle-exclamation ms-1 text-danger" style="font-size:0.7rem;"></i>';
                        html += '</td><td class="small">'+esc(r.gender)+'</td><td class="small">'+gradeSection+'</td><td class="small">'+esc(r.height)+' '+esc(r.height_unit)+'<div class="text-muted fw-normal" style="font-size:.68rem">'+esc(r.height_classification||'Not assessed')+(r.height_zscore!==null&&r.height_zscore!==''?' · z '+esc(r.height_zscore)+' SD':'')+'</div></td><td class="small">'+esc(r.weight)+' '+esc(r.weight_unit)+'</td><td class="small fw-bold">'+r.bmi+(r.bmi_zscore!==null&&r.bmi_zscore!==''?'<div class="text-muted fw-normal" style="font-size:.68rem">z '+esc(r.bmi_zscore)+' SD</div>':'')+'</td><td><span class="badge rounded-pill '+badge+'">'+cls+'</span></td><td class="small">'+esc(r.guardian_name)+'</td><td class="small text-muted">'+dt+'</td>';
                        if (canEdit) html += '<td class="action-cell"><button class="btn btn-sm btn-outline-success edit-record-btn" data-id="'+r.id+'" data-fn="'+esc(r.std_first_name)+'" data-ln="'+esc(r.std_last_name)+'" data-mid="'+esc(r.std_mid_initial)+'" data-gender="'+esc(r.gender)+'" data-dob="'+esc(r.date_of_birth)+'" data-height="'+r.height+'" data-hunit="'+esc(r.height_unit)+'" data-weight="'+r.weight+'" data-wunit="'+esc(r.weight_unit)+'" data-guardian="'+esc(r.guardian_name)+'" data-gnum="'+esc(r.guardian_number)+'" data-gemail="'+esc(r.guardian_email)+'"><i class="fa-solid fa-pen-to-square"></i></button> <button class="btn btn-sm btn-outline-danger delete-record-btn" data-id="'+r.id+'"><i class="fa-solid fa-trash"></i></button></td>';
                        html += '</tr>';
                    });
                    tbody.innerHTML = html;
                    if (data.totalPages > 1) {
                        let p = '<ul class="pagination pagination-sm justify-content-center mb-0">';
                        p += '<li class="page-item '+(data.page<=1?'disabled':'')+'"><a class="page-link pag-link" href="#" data-p="'+(data.page-1)+'"><i class="fa-solid fa-chevron-left"></i></a></li>';
                        for (let i = Math.max(1,data.page-2); i <= Math.min(data.totalPages,data.page+2); i++) p += '<li class="page-item '+(i===data.page?'active':'')+'"><a class="page-link pag-link" href="#" data-p="'+i+'">'+i+'</a></li>';
                        p += '<li class="page-item '+(data.page>=data.totalPages?'disabled':'')+'"><a class="page-link pag-link" href="#" data-p="'+(data.page+1)+'"><i class="fa-solid fa-chevron-right"></i></a></li></ul>';
                        p += '<p class="text-center text-muted small mt-2 mb-0">Page '+data.page+' of '+data.totalPages+' ('+data.totalRecords+' students)</p>';
                        pagContainer.innerHTML = p;
                        pagContainer.querySelectorAll('.pag-link').forEach(l => l.addEventListener('click', function(e) { e.preventDefault(); const pg = parseInt(this.dataset.p); if (pg >= 1 && pg <= data.totalPages) { currentPage = pg; loadRecords(); } }));
                    } else pagContainer.innerHTML = '';
                    bindNameLinks(); bindEditBtns(); bindDeleteBtns();
                }).catch(() => { tbody.innerHTML = '<tr><td colspan="'+colSpan+'" class="text-center text-danger py-4">Failed to load.</td></tr>'; });
        }
        loadRecords();

        const historyModal = new bootstrap.Modal(document.getElementById('studentHistoryModal'));
        const modalBody = document.getElementById('historyModalBody');
        const modalLabel = document.getElementById('studentHistoryLabel');

        modalBody.addEventListener('change', function(e) {
            const input = e.target.closest('.student-photo-input');
            if (!input || !input.files.length) return;
            const formData = new FormData();
            formData.append('photo', input.files[0]);
            formData.append('student_id', input.dataset.studentId);
            formData.append('csrf_token', csrfToken);
            fetch('upload_photo.php', {method:'POST', body:formData, headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Photo upload failed.');
                    const href = '../uploads/photos/' + encodeURIComponent(data.photo);
                    const link = document.getElementById('studentPhotoLink');
                    link.href = href;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.innerHTML = '<img class="history-profile-photo w-100 h-100" src="'+href+'" alt="Student photo" style="object-fit:cover;">';
                    showToast(data.message);
                })
                .catch(error => showToast(error.message || 'Photo upload failed.', 'error'))
                .finally(() => { input.value = ''; });
        });

        function bindNameLinks() {
            document.querySelectorAll('.student-name-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fn = this.dataset.fn, ln = this.dataset.ln, mid = this.dataset.mid;
                    const fullName = fn + ' ' + mid + ' ' + ln;
                    modalLabel.innerHTML = '<i class="fa-solid fa-clock-rotate-left me-2"></i>' + fullName;
                    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div><p class="text-muted small mt-2">Loading...</p></div>';
                    historyModal.show();
                    fetch('get_student_history.php?fn=' + encodeURIComponent(fn) + '&mid=' + encodeURIComponent(mid) + '&ln=' + encodeURIComponent(ln))
                        .then(r => r.json())
                        .then(data => {
                            if (data.error || !data.records || data.records.length === 0) { modalBody.innerHTML = '<div class="text-center text-muted py-4"><i class="fa-solid fa-inbox fa-2x mb-2 opacity-25"></i><p>No records found.</p></div>'; return; }
                            const recs = data.records, latest = recs[0], isMal = latest.classification !== 'Normal Weight';
                            function getBadge(c) { return {'Severe Underweight':'badge-underweight',Underweight:'badge-underweight','Normal Weight':'badge-normal',Overweight:'badge-overweight',Obese:'badge-obese'}[c]||'badge-obese'; }
                            function getAlert(c) { if(c==='Normal Weight')return''; if(c==='Severe Underweight')return'Severe thinness – urgent health review'; if(c==='Underweight')return'Thinness – malnutrition risk'; if(c==='Overweight')return'Overweight – At Risk'; return'Obese – High Risk'; }
                            let h = '';
                            const photoHref = latest.photo ? '../uploads/photos/' + encodeURIComponent(latest.photo) : '';
                            h += '<div class="d-flex align-items-center gap-3 mb-3"><a id="studentPhotoLink" href="'+(photoHref || '#')+'" '+(photoHref?'target="_blank" rel="noopener"':'onclick="return false;"')+' class="d-flex align-items-center justify-content-center bg-light border rounded-2 overflow-hidden text-success" style="width:96px;height:96px;flex:0 0 96px;">'+(photoHref?'<img class="history-profile-photo w-100 h-100" src="'+photoHref+'" alt="Student photo" style="object-fit:cover;">':'<i class="fa-solid fa-child fa-2x opacity-50"></i>')+'</a><div><div class="fw-semibold">Student photo</div><label class="btn btn-sm btn-outline-success mt-2 mb-1"><i class="fa-solid fa-camera me-1"></i>Upload or update<input type="file" class="student-photo-input d-none" accept="image/jpeg,image/png,image/webp" data-student-id="'+latest.id+'"></label><div class="small text-muted">JPG, PNG, or WebP up to 2 MB</div></div></div>';
                            if (isMal) { const ac = latest.classification==='Severe Underweight'?'#8e2424':(latest.classification==='Underweight'?'#c0392b':(latest.classification==='Overweight'?'#e67e22':'#8e44ad')); const ab = latest.classification==='Severe Underweight'?'#fff5f5':(latest.classification==='Underweight'?'#fff5f5':(latest.classification==='Overweight'?'#fff8f0':'#faf0ff')); h += '<div style="background:'+ab+';border-left:4px solid '+ac+';border-radius:8px;padding:12px 16px;margin-bottom:16px;"><div style="color:'+ac+';font-weight:700;font-size:0.9rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i> '+getAlert(latest.classification)+'</div><div class="text-muted small mt-1">Latest BMI: <b>'+latest.bmi+'</b> — '+formatDate(latest.created_at)+'</div></div>'; }
                            h += '<div class="card border-0 shadow-sm rounded-3 mb-3" style="border-left:4px solid #78bc27 !important;"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold small text-success"><i class="fa-solid fa-star me-1"></i>Latest</span><span class="badge rounded-pill '+getBadge(latest.classification)+'">'+latest.classification+'</span></div>'+buildDet(latest)+'</div></div>';
                            if (recs.length > 1) { h += '<h6 class="fw-bold small text-muted mt-4 mb-3"><i class="fa-solid fa-history me-1"></i>Previous ('+( recs.length-1)+')</h6>'; for (let i=1;i<recs.length;i++) { const r=recs[i],pm=r.classification!=='Normal Weight',bc=pm?'#e74c3c':'#dee2e6'; h+='<div class="card border-0 shadow-sm rounded-3 mb-2" style="border-left:4px solid '+bc+' !important;"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted small">'+formatDate(r.created_at)+'</span><span class="badge rounded-pill '+getBadge(r.classification)+'">'+r.classification+'</span></div>'+buildDet(r)+'</div></div>'; } }
                            else h += '<div class="text-center text-muted small py-3 mt-3" style="background:#f8f9fa;border-radius:8px;"><i class="fa-solid fa-info-circle me-1"></i>Only record for this student.</div>';
                            modalBody.innerHTML = h;
                        }).catch(() => { modalBody.innerHTML = '<div class="text-center text-danger py-4"><i class="fa-solid fa-circle-exclamation fa-2x mb-2"></i><p>Failed to load.</p></div>'; });
                });
            });
        }
        function buildDet(r) { let h='<div class="row g-2 small">'; h+='<div class="col-6 col-md-3"><span class="text-muted">Gender:</span> <b>'+esc(r.gender)+'</b></div>'; h+='<div class="col-6 col-md-3"><span class="text-muted">Height:</span> <b>'+esc(r.height)+' '+esc(r.height_unit)+'</b></div>'; h+='<div class="col-6 col-md-3"><span class="text-muted">Height-for-age:</span> <b>'+esc(r.height_classification||'Not assessed')+(r.height_zscore!==null&&r.height_zscore!==''?' ('+esc(r.height_zscore)+' SD)':'')+'</b></div>'; h+='<div class="col-6 col-md-3"><span class="text-muted">Weight:</span> <b>'+esc(r.weight)+' '+esc(r.weight_unit)+'</b></div>'; h+='<div class="col-6 col-md-3"><span class="text-muted">BMI:</span> <b>'+r.bmi+'</b></div>'; if(r.bmi_zscore!==null&&r.bmi_zscore!=='') h+='<div class="col-6"><span class="text-muted">WHO BMI-for-age:</span> <b>'+esc(r.bmi_zscore)+' SD</b></div>'; if(r.guardian_name) h+='<div class="col-12 mt-1"><span class="text-muted">Guardian:</span> '+esc(r.guardian_name)+'</div>'; h+='</div>'; return h; }

        const editModal = new bootstrap.Modal(document.getElementById('editRecordModal'));
        function bindEditBtns() {
            document.querySelectorAll('.edit-record-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    ['id','fn','ln','mid','gender','dob','height','hunit','weight','wunit','guardian','gnum','gemail'].forEach(k => {
                        const el = document.getElementById('edit_' + (k==='id'?'id':k));
                        if (el) el.value = this.dataset[k] || '';
                    });
                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_fn').value = this.dataset.fn;
                    document.getElementById('edit_ln').value = this.dataset.ln;
                    document.getElementById('edit_mid').value = this.dataset.mid;
                    document.getElementById('edit_gender').value = this.dataset.gender;
                    document.getElementById('edit_birthday').value = this.dataset.dob || '';
                    document.getElementById('edit_height').value = this.dataset.height;
                    document.getElementById('edit_hunit').value = this.dataset.hunit;
                    document.getElementById('edit_weight').value = this.dataset.weight;
                    document.getElementById('edit_wunit').value = this.dataset.wunit;
                    document.getElementById('edit_guardian').value = this.dataset.guardian;
                    document.getElementById('edit_gnum').value = this.dataset.gnum;
                    document.getElementById('edit_gemail').value = this.dataset.gemail;
                    document.getElementById('editSuccessMsg').classList.add('d-none');
                    document.getElementById('editErrorMsg').classList.add('d-none');
                    editModal.show();
                });
            });
        }

        function bindDeleteBtns() {
            document.querySelectorAll('.delete-record-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!confirm('Delete this record? Cannot be undone.')) return;
                    const id = this.dataset.id, row = this.closest('tr');
                    fetch('delete_record.php', { method:'POST', body: new URLSearchParams({id, csrf_token: csrfToken}) })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) { row.style.transition='opacity 0.3s'; row.style.opacity='0'; setTimeout(()=>{row.remove();showToast('Record deleted.');},300); }
                            else showToast(data.error||'Delete failed.','error');
                        }).catch(() => showToast('Network error.','error'));
                });
            });
        }

        document.getElementById('editRecordForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            if (!formData.get('csrf_token')) {
                formData.set('csrf_token', csrfToken);
            }
            fetch('update_record.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        editModal.hide();
                        showToast('Updated! BMI: ' + data.bmi + ' (' + data.classification + '); height-for-age: ' + data.height_classification);
                        setTimeout(() => loadRecords(), 800);
                    } else {
                        document.getElementById('editErrorText').textContent = data.error || 'Failed.';
                        document.getElementById('editErrorMsg').classList.remove('d-none');
                        document.getElementById('editSuccessMsg').classList.add('d-none');
                    }
                })
                .catch(() => {
                    document.getElementById('editErrorText').textContent = 'Network error.';
                    document.getElementById('editErrorMsg').classList.remove('d-none');
                });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
