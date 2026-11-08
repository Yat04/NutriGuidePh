<?php
require_once __DIR__ . '/auth.php';
secureSessionStart();
checkAccess(['Employee', 'Admin', 'Super Admin']);

$conn = getDB();
$scope = getAdviserScope($conn);

$search = trim($_GET['search'] ?? '');
$grade = trim($_GET['grade'] ?? '');
$date = trim($_GET['date'] ?? '');
$filterLabels = [
    'all' => 'All Students',
    'underweight' => 'Underweight',
    'normal' => 'Normal',
    'overweight' => 'Overweight',
    'obese' => 'Obese',
    'at-risk' => 'At Risk',
];
$filter = strtolower(trim($_GET['filter'] ?? 'all'));
if (!isset($filterLabels[$filter])) $filter = 'all';
$genderFilter = strtolower(trim($_GET['gender'] ?? ''));
if (!in_array($genderFilter, ['male', 'female'], true)) $genderFilter = '';

// Get only grades visible to the current adviser.
$gradeParams = [];
$gradeTypes = '';
$gradeScope = appendAdviserScope($scope, '', $gradeParams, $gradeTypes);
$gradesStmt = $conn->prepare("SELECT DISTINCT grade_level FROM stdRecord WHERE grade_level IS NOT NULL AND grade_level != ''" . $gradeScope . " ORDER BY grade_level");
if (!empty($gradeParams)) $gradesStmt->bind_param($gradeTypes, ...$gradeParams);
$gradesStmt->execute();
$gradesResult = $gradesStmt->get_result();

// Build query for unique students (latest record only)
$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $like = "%$search%";
    $conditions[] = "(s.std_first_name LIKE ? OR s.std_last_name LIKE ? OR s.guardian_name LIKE ? OR s.classification LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}
if ($grade !== '') {
    $conditions[] = "s.grade_level = ?";
    $params[] = $grade;
    $types .= 's';
}
if ($date !== '') {
    $conditions[] = "DATE(s.created_at) = ?";
    $params[] = $date;
    $types .= 's';
}
if ($filter === 'underweight') {
    $conditions[] = "LOWER(s.classification) IN ('severe underweight', 'underweight')";
} elseif ($filter === 'normal') {
    $conditions[] = "LOWER(s.classification) = 'normal weight'";
} elseif ($filter === 'overweight') {
    $conditions[] = "LOWER(s.classification) = 'overweight'";
} elseif ($filter === 'obese') {
    $conditions[] = "LOWER(s.classification) = 'obese'";
} elseif ($filter === 'at-risk') {
    $conditions[] = "LOWER(s.classification) IN ('severe underweight', 'underweight', 'overweight', 'obese')";
}
if ($genderFilter !== '') {
    $conditions[] = "LOWER(s.gender) = ?";
    $params[] = $genderFilter;
    $types .= 's';
}

$recordCountParams = [];
$recordCountTypes = '';
$recordCountScope = appendAdviserScope($scope, 'r', $recordCountParams, $recordCountTypes);
$latestParams = [];
$latestTypes = '';
$latestScope = appendAdviserScope($scope, '', $latestParams, $latestTypes);
$latestIds = "SELECT MAX(id) FROM stdRecord WHERE 1=1" . $latestScope . " GROUP BY std_first_name, std_mid_initial, std_last_name";
$sql = "SELECT s.*, (SELECT COUNT(*) FROM stdRecord r WHERE r.std_first_name = s.std_first_name AND r.std_mid_initial <=> s.std_mid_initial AND r.std_last_name = s.std_last_name" . $recordCountScope . ") as record_count
    FROM stdRecord s
    WHERE s.id IN ($latestIds)";

if (!empty($conditions)) {
    $sql .= " AND " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY s.std_last_name, s.std_first_name";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $bindParams = array_merge($recordCountParams, $latestParams, $params);
        $stmt->bind_param($recordCountTypes . $latestTypes . $types, ...$bindParams);
        $stmt->execute();
        $students = $stmt->get_result();
    } else {
        $students = false;
    }
} else {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $bindParams = array_merge($recordCountParams, $latestParams);
        $bindTypes = $recordCountTypes . $latestTypes;
        if (!empty($bindParams)) $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $students = $stmt->get_result();
    } else {
        $students = false;
    }
}

$totalStudents = ($students && $students instanceof mysqli_result) ? $students->num_rows : 0;
$canEdit = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Student Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= generateCsrf() ?>">
</head>
<body class="page-bg">

    <?php $activePage = 'records'; include 'navbar.php'; ?>


    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2" data-aos="fade-down">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-folder-open me-2"></i>Student Records</h4>
                <p class="text-white-50 small mb-0"><?= $totalStudents ?> <?= $filter === 'all' && $genderFilter === '' ? 'unique students' : 'matching students' ?> — click a name to view full history</p>
            </div>
            <a href="addrecord.php" class="btn btn-green px-4"><i class="fa-solid fa-plus me-2"></i>Add Record</a>
        </div>

        <div class="card table-card shadow-sm mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h6 class="fw-bold text-success mb-0"><i class="fa-solid fa-table-list me-2"></i><?= htmlspecialchars($filterLabels[$filter]) ?><?= $genderFilter !== '' ? ' · ' . ucfirst($genderFilter) : '' ?> (Latest Record)</h6>
                    <form id="recordsFilterForm" method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                        <select class="form-select form-select-sm" name="filter" style="max-width:155px;">
                            <?php foreach ($filterLabels as $filterValue => $filterLabel): ?>
                                <option value="<?= htmlspecialchars($filterValue) ?>" <?= $filter === $filterValue ? 'selected' : '' ?>><?= htmlspecialchars($filterLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm" name="gender" style="max-width:120px;">
                            <option value="">All Genders</option>
                            <option value="male" <?= $genderFilter === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $genderFilter === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                        <input type="date" id="dateFilter" class="form-control form-control-sm" name="date" value="<?= htmlspecialchars($date) ?>" style="max-width:160px;">
                        <input type="text" id="liveSearch" class="form-control form-control-sm" name="search" placeholder="Search name, guardian, classification..." value="<?= htmlspecialchars($search) ?>" style="max-width:220px;">
                        <select class="form-select form-select-sm" name="grade" style="max-width:140px;">
                            <option value="">All Grades</option>
                            <?php if ($gradesResult && $gradesResult instanceof mysqli_result): ?>
                                <?php while ($g = $gradesResult->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($g['grade_level']) ?>" <?= $grade === $g['grade_level'] ? 'selected' : '' ?>><?= htmlspecialchars($g['grade_level']) ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="btn btn-green btn-sm px-3"><i class="fa-solid fa-search"></i></button>
                        <?php if ($search || $grade || $date || $filter !== 'all' || $genderFilter !== ''): ?>
                            <a href="records.php" class="btn btn-outline-secondary btn-sm px-2"><i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold" style="width:1%;">#</th>
                                <th class="small fw-semibold">Name</th>
                                <th class="small fw-semibold">Gender</th>
                                <th class="small fw-semibold">Grade / Section</th>
                                <th class="small fw-semibold">Height</th>
                                <th class="small fw-semibold">Height-for-Age</th>
                                <th class="small fw-semibold">Weight</th>
                                <th class="small fw-semibold">BMI</th>
                                <th class="small fw-semibold">Classification</th>
                                <th class="small fw-semibold">Guardian</th>
                                <th class="small fw-semibold" style="width:1%;text-align:center;">Records</th>
                                <th class="small fw-semibold">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($totalStudents > 0): ?>
                                <?php $n = 1; while ($s = $students->fetch_assoc()):
                                    $cls = $s['classification'];
                                    $badge = match($cls) { 'Severe Underweight', 'Underweight'=>'badge-underweight', 'Normal Weight'=>'badge-normal', 'Overweight'=>'badge-overweight', default=>'badge-obese' };
                                    $studentName = formatStudentFullName($s['std_first_name'] ?? '', $s['std_mid_initial'] ?? '', $s['std_last_name'] ?? '');
                                    $heightAssessment = getRecordHeightForAge($s);
                                ?>
                                <tr>
                                    <td class="small text-muted"><?= $n++ ?></td>
                                    <td class="small">
                                        <a href="#" class="text-decoration-none fw-semibold student-name-link" style="color:#2d5a0e;"
                                           data-fn="<?= htmlspecialchars($s['std_first_name']) ?>"
                                           data-ln="<?= htmlspecialchars($s['std_last_name']) ?>"
                                           data-mid="<?= htmlspecialchars($s['std_mid_initial']) ?>">
                                            <?= htmlspecialchars($studentName) ?>
                                        </a>
                                        <?php if ($cls !== 'Normal Weight'): ?>
                                            <i class="fa-solid fa-triangle-exclamation ms-1 text-danger" style="font-size:0.65rem;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($s['gender']) ?></td>
                                    <td class="small" style="white-space:normal;"><?= htmlspecialchars(($s['grade_level'] ?? '') . ($s['section'] ? ' - ' . $s['section'] : '')) ?: '<span class="text-muted">—</span>' ?></td>
                                    <td class="small"><?= $s['height'] ?> <?= $s['height_unit'] ?></td>
                                    <td class="small"><?= htmlspecialchars($heightAssessment['height_classification'] ?? 'Not assessed') ?><?php if (($heightAssessment['height_zscore'] ?? null) !== null): ?><div class="text-muted" style="font-size:.68rem;">z <?= htmlspecialchars((string)$heightAssessment['height_zscore']) ?> SD</div><?php endif; ?></td>
                                    <td class="small"><?= $s['weight'] ?> <?= $s['weight_unit'] ?></td>
                                    <td class="small fw-bold"><?= $s['bmi'] ?><?php if ($s['bmi_zscore'] !== null): ?><div class="text-muted fw-normal" style="font-size:.68rem;">z <?= htmlspecialchars($s['bmi_zscore']) ?> SD</div><?php endif; ?></td>
                                    <td><span class="badge rounded-pill <?= $badge ?>"><?= $cls ?></span></td>
                                    <td class="small" style="white-space:normal;"><?= htmlspecialchars($s['guardian_name'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                                    <td class="small text-center"><?= $s['record_count'] ?></td>
                                    <td class="small text-muted"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">
                                        <i class="fa-solid fa-folder-open fa-2x mb-2 opacity-25 d-block"></i>
                                        <?= ($search || $grade || $date || $filter !== 'all' || $genderFilter !== '') ? 'No records found for this category and filter combination.' : 'No student records yet.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">
                    <h6 class="modal-title text-white fw-bold" id="historyLabel"><i class="fa-solid fa-clock-rotate-left me-2"></i>Record History</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="historyBody">
                    <div class="text-center py-5"><div class="spinner-border text-success"></div></div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-section mt-4">
        <div class="container py-5">
            <div class="row g-4 align-items-center">
                <div class="col-md-6"><div class="d-flex align-items-center gap-2 mb-3"><img src="../images/logo.png" alt="Logo" height="36" class="footer-logo"><h5 class="mb-0 text-success-light">NutriPh Guide</h5></div><p class="text-muted small">Community-based monitoring system for San Antonio Central School.</p></div>
                <div class="col-md-6"><h6 class="text-success-light text-uppercase small fw-bold mb-3">Contact</h6><ul class="list-unstyled text-muted small"><li class="mb-2"><i class="fa-solid fa-envelope me-2"></i>abcdefg@gmail.com</li><li class="mb-2"><i class="fa-solid fa-location-dot me-2"></i>San Antonio Central School, Philippines</li></ul></div>
            </div>
        </div>
        <div class="text-center py-3 border-top border-secondary small text-muted footer-bottom">&copy; <?= date('Y') ?> NutriPh Guide</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 700, once: true });

        const filterForm = document.getElementById('recordsFilterForm');
        const liveSearch = document.getElementById('liveSearch');
        const dateFilter = document.getElementById('dateFilter');
        const gradeSelect = filterForm ? filterForm.querySelector('select[name="grade"]') : null;
        const categorySelect = filterForm ? filterForm.querySelector('select[name="filter"]') : null;
        const genderSelect = filterForm ? filterForm.querySelector('select[name="gender"]') : null;
        let searchTimer = null;

        function submitFilter() {
            if (filterForm) {
                filterForm.submit();
            }
        }

        if (liveSearch) {
            liveSearch.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(submitFilter, 350);
            });
        }

        if (dateFilter) {
            dateFilter.addEventListener('change', submitFilter);
        }

        if (gradeSelect) {
            gradeSelect.addEventListener('change', submitFilter);
        }
        if (categorySelect) {
            categorySelect.addEventListener('change', submitFilter);
        }
        if (genderSelect) {
            genderSelect.addEventListener('change', submitFilter);
        }

        function esc(s) { if(!s)return''; const el=document.createElement('span'); el.textContent=s; return el.innerHTML; }
        function fmtDate(d) { return new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}); }

        const hModalEl = document.getElementById('historyModal');
        const hBody = document.getElementById('historyBody');
        const hLabel = document.getElementById('historyLabel');
        const hModal = hModalEl ? new bootstrap.Modal(hModalEl) : null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        hBody.addEventListener('change', function(e) {
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
                })
                .catch(error => { hBody.insertAdjacentHTML('afterbegin', '<div class="alert alert-danger small py-2">'+esc(error.message || 'Photo upload failed.')+'</div>'); })
                .finally(() => { input.value = ''; });
        });

        document.querySelectorAll('.student-name-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (!hModal || !hBody || !hLabel) return;

                const fn = this.dataset.fn, ln = this.dataset.ln, mid = this.dataset.mid;
                const fullName = esc(fn) + ' ' + esc(mid) + ' ' + esc(ln);
                hLabel.innerHTML = '<i class="fa-solid fa-clock-rotate-left me-2"></i>' + fullName;
                hBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-success"></div><p class="text-muted small mt-2">Loading records...</p></div>';
                hModal.show();

                fetch('get_student_history.php?fn='+encodeURIComponent(fn)+'&mid='+encodeURIComponent(mid)+'&ln='+encodeURIComponent(ln))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.records || data.records.length === 0) {
                            hBody.innerHTML = '<div class="text-center text-muted py-4"><i class="fa-solid fa-inbox fa-2x mb-2 opacity-25"></i><p>No records found.</p></div>';
                            return;
                        }

                        const recs = data.records;
                        const latest = recs[0];
                        let h = '';
                        const photoHref = latest.photo ? '../uploads/photos/' + encodeURIComponent(latest.photo) : '';
                        h += '<div class="d-flex align-items-center gap-3 mb-3"><a id="studentPhotoLink" href="'+(photoHref || '#')+'" '+(photoHref?'target="_blank" rel="noopener"':'onclick="return false;"')+' class="d-flex align-items-center justify-content-center bg-light border rounded-2 overflow-hidden text-success" style="width:96px;height:96px;flex:0 0 96px;">'+(photoHref?'<img class="history-profile-photo w-100 h-100" src="'+photoHref+'" alt="Student photo" style="object-fit:cover;">':'<i class="fa-solid fa-child fa-2x opacity-50"></i>')+'</a><div><div class="fw-semibold">Student photo</div><label class="btn btn-sm btn-outline-success mt-2 mb-1"><i class="fa-solid fa-camera me-1"></i>Upload or update<input type="file" class="student-photo-input d-none" accept="image/jpeg,image/png,image/webp" data-student-id="'+latest.id+'"></label><div class="small text-muted">JPG, PNG, or WebP up to 2 MB</div></div></div>';

                        if (recs.length > 1) {
                            const bmis = recs.map(r => parseFloat(r.bmi)).reverse();
                            const maxB = Math.max(...bmis, 30);
                            h += '<div class="card border-0 shadow-sm rounded-3 mb-3"><div class="card-body p-3">';
                            h += '<h6 class="fw-bold small text-success mb-2"><i class="fa-solid fa-chart-line me-1"></i>BMI Trend</h6>';
                            h += '<div class="d-flex align-items-end gap-1" style="height:50px;">';
                            bmis.forEach(b => {
                                const pct = Math.max(12, (b/maxB)*100);
                                const clr = b<18.5?'#e74c3c':(b<=24.9?'#27ae60':(b<=29.9?'#f39c12':'#8e44ad'));
                                h += '<div style="flex:1;background:'+clr+';height:'+pct+'%;border-radius:3px 3px 0 0;min-width:6px;" title="BMI: '+b+'"></div>';
                            });
                            h += '</div><div class="d-flex justify-content-between mt-1" style="font-size:0.65rem;color:#aaa;"><span>Oldest</span><span>Latest</span></div>';
                            h += '</div></div>';
                        }

                        recs.forEach((r, i) => {
                            const cls = r.classification;
                            const isLatest = i === 0;
                            const bc = {'Severe Underweight':'#8e2424',Underweight:'#c0392b','Normal Weight':'#27ae60',Overweight:'#e67e22',Obese:'#8e44ad'}[cls] || '#8e44ad';
                            const bg = {'Severe Underweight':'badge-underweight',Underweight:'badge-underweight','Normal Weight':'badge-normal',Overweight:'badge-overweight',Obese:'badge-obese'}[cls] || 'badge-obese';

                            h += '<div class="card border-0 shadow-sm rounded-3 mb-2" style="border-left:4px solid '+bc+' !important;">';
                            h += '<div class="card-body p-3">';
                            h += '<div class="d-flex justify-content-between align-items-center mb-2">';
                            if (isLatest) {
                                h += '<span class="fw-bold small text-success"><i class="fa-solid fa-star me-1"></i>Latest Record</span>';
                            } else {
                                h += '<span class="text-muted small"><i class="fa-solid fa-calendar me-1"></i>' + fmtDate(r.created_at) + '</span>';
                            }
                            h += '<div class="d-flex align-items-center gap-2">';
                            h += '<span class="badge rounded-pill '+bg+'">'+cls+'</span>';
                            if (cls !== 'Normal Weight') h += '<i class="fa-solid fa-triangle-exclamation text-danger" style="font-size:0.65rem;"></i>';
                            h += '</div></div>';

                            h += '<div class="row g-2" style="font-size:0.82rem;">';
                            h += '<div class="col-6 col-md-3"><span class="text-muted">BMI:</span> <b>'+r.bmi+'</b></div>';
                            if (r.bmi_zscore !== null && r.bmi_zscore !== '') h += '<div class="col-6 col-md-3"><span class="text-muted">WHO BMI-for-age:</span> <b>'+esc(r.bmi_zscore)+' SD</b></div>';
                            h += '<div class="col-6 col-md-3"><span class="text-muted">Height:</span> '+esc(r.height)+' '+esc(r.height_unit)+'</div>';
                            h += '<div class="col-6 col-md-3"><span class="text-muted">Weight:</span> '+esc(r.weight)+' '+esc(r.weight_unit)+'</div>';
                            h += '<div class="col-6 col-md-3"><span class="text-muted">Gender:</span> '+esc(r.gender)+'</div>';
                            if (r.grade_level) h += '<div class="col-6"><span class="text-muted">Grade:</span> '+esc(r.grade_level)+(r.section?' - '+esc(r.section):'')+'</div>';
                            if (r.guardian_name) h += '<div class="col-6"><span class="text-muted">Guardian:</span> '+esc(r.guardian_name)+'</div>';
                            h += '</div>';
                            h += '</div></div>';
                        });

                        hBody.innerHTML = h;
                    })
                    .catch(() => {
                        hBody.innerHTML = '<div class="text-center text-danger py-4"><i class="fa-solid fa-circle-exclamation fa-2x mb-2"></i><p>Failed to load.</p></div>';
                    });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
