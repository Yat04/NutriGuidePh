<?php
require_once __DIR__ . '/auth.php';
secureSessionStart();
checkAccess(['Parent/Guardian']);

$conn = getDB();
$email = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT DISTINCT std_first_name, std_mid_initial, std_last_name FROM stdRecord WHERE guardian_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$linkedChildren = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$allRecords = [];
if (!empty($linkedChildren)) {
    $identityConditions = [];
    $identityParams = [];
    $identityTypes = '';
    foreach ($linkedChildren as $child) {
        $identityConditions[] = "(std_first_name = ? AND std_mid_initial <=> ? AND std_last_name = ?)";
        $identityParams[] = $child['std_first_name'];
        $identityParams[] = $child['std_mid_initial'];
        $identityParams[] = $child['std_last_name'];
        $identityTypes .= 'sss';
    }
    $historyStmt = $conn->prepare("SELECT * FROM stdRecord WHERE " . implode(' OR ', $identityConditions) . " ORDER BY created_at DESC, student_id DESC");
    $historyStmt->bind_param($identityTypes, ...$identityParams);
    $historyStmt->execute();
    $allRecords = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $historyStmt->close();
}

// Group by logical child, not by each new row id. Each medical update belongs to the same child.
$students = [];
foreach ($allRecords as $r) {
    $studentKey = normalizeChildKey($r['std_first_name'] ?? '', $r['std_last_name'] ?? '', $r['std_mid_initial'] ?? '');
    $students[$studentKey][] = $r;
}

// Feeding records for the parent’s children.
$stmt2 = $conn->prepare("SELECT f.*, s.student_id, s.std_first_name, s.std_mid_initial, s.std_last_name FROM feedingRecord f JOIN stdRecord s ON f.student_id = s.student_id WHERE s.guardian_email = ? ORDER BY f.feed_date DESC, f.feed_time DESC LIMIT 30");
$stmt2->bind_param("s", $email);
$stmt2->execute();
$feedings = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$feedingsByChild = [];
foreach ($feedings as $feeding) {
    $studentKey = normalizeChildKey($feeding['std_first_name'] ?? '', $feeding['std_last_name'] ?? '', $feeding['std_mid_initial'] ?? '');
    $feedingsByChild[$studentKey][] = $feeding;
}

function normalizeChildKey($first, $last, $mid = '') {
    $parts = [trim((string)$first), trim((string)$last), trim((string)$mid)];
    $parts = array_filter($parts, function ($value) { return $value !== ''; });
    $value = strtolower(implode('|', $parts));
    $value = preg_replace('/\s+/', '', $value);
    return $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Parent Dashboard</title>
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

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid px-3 px-lg-5">
            <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
                <img src="../images/logo.png" alt="Logo" height="40"><span class="fw-bold brand-text">NutriPh Guide</span>
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto gap-1 align-items-center">
                    <li class="nav-item"><a class="nav-link active" href="parent_dashboard.php">My Child's Records</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-success-light fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-circle-user me-1"></i><?= htmlspecialchars($_SESSION['firstName']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                            <li class="px-3 py-2"><div class="small fw-bold"><?= htmlspecialchars($_SESSION['firstName']) ?></div><div class="text-muted" style="font-size:0.75rem;">Parent/Guardian</div></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item small text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="mb-4">
            <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-child me-2"></i>My Child's Health Dashboard</h4>
            <p class="text-white-50 small mb-0">View health records, BMI trends, and feeding program history</p>
        </div>

        <?php if (empty($students)): ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center py-5">
                    <i class="fa-solid fa-child fa-3x mb-3 text-muted opacity-25"></i>
                    <h6 class="fw-bold text-muted">No Records Found</h6>
                    <p class="text-muted small">No student records are linked to your email (<b><?= htmlspecialchars($email) ?></b>).</p>
                </div>
            </div>
        <?php else: ?>

        <?php foreach ($students as $studentKey => $records):
            $recordsAsc = array_reverse($records);
            $latest = $records[0];
            if (empty($latest['photo'])) {
                foreach ($records as $historicalRecord) {
                    if (!empty($historicalRecord['photo'])) {
                        $latest['photo'] = $historicalRecord['photo'];
                        break;
                    }
                }
            }
            $fullName = formatStudentFullName($latest['std_first_name'] ?? '', $latest['std_mid_initial'] ?? '', $latest['std_last_name'] ?? '');
            $latestHeightAssessment = getRecordHeightForAge($latest);
            $cls = $latest['classification'];
            $badge = match($cls) { 'Severe Underweight', 'Underweight'=>'badge-underweight', 'Normal Weight'=>'badge-normal', 'Overweight'=>'badge-overweight', default=>'badge-obese' };
            $childFeedings = $feedingsByChild[$studentKey] ?? [];

            $bmiDelta = null;
            if (count($records) > 1) {
                $prevBmi = (float) $records[1]['bmi'];
                $bmiDelta = round((float) $latest['bmi'] - $prevBmi, 1);
            }

            $weightChange = '';
            if (count($records) > 1) {
                $prev = $records[1];
                $currW = ($latest['weight_unit'] === 'lbs') ? $latest['weight'] * 0.453592 : $latest['weight'];
                $prevW = ($prev['weight_unit'] === 'lbs') ? $prev['weight'] * 0.453592 : $prev['weight'];
                $diff = round($currW - $prevW, 1);
                if ($diff > 0) $weightChange = '<span class="text-success small fw-bold">+' . $diff . ' kg</span>';
                elseif ($diff < 0) $weightChange = '<span class="text-danger small fw-bold">' . $diff . ' kg</span>';
                else $weightChange = '<span class="text-muted small">No change</span>';
            }

            $chartLabels = [];
            $chartBmi = [];
            $chartUsesZscores = count(array_filter($records, fn($record) => isset($record['bmi_zscore']))) === count($records);
            foreach ($recordsAsc as $r) {
                $chartLabels[] = date('M d', strtotime($r['created_at']));
                $chartBmi[] = $chartUsesZscores ? (float)$r['bmi_zscore'] : (float)$r['bmi'];
            }
        ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <a href="<?= !empty($latest['photo']) ? '../uploads/photos/' . rawurlencode($latest['photo']) : '#' ?>" <?= !empty($latest['photo']) ? 'target="_blank" rel="noopener"' : 'onclick="return false;"' ?> class="d-flex align-items-center justify-content-center bg-light border rounded-2 overflow-hidden text-success" style="width:88px;height:88px;flex:0 0 88px;" aria-label="View <?= htmlspecialchars($fullName) ?> profile photo">
                            <?php if (!empty($latest['photo'])): ?>
                                <img src="../uploads/photos/<?= htmlspecialchars($latest['photo']) ?>" alt="<?= htmlspecialchars($fullName) ?>" class="w-100 h-100" style="object-fit:cover;">
                            <?php else: ?>
                                <i class="fa-solid fa-child fa-2x opacity-50"></i>
                            <?php endif; ?>
                        </a>
                        <div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($fullName) ?></h5>
                            <div class="text-muted small">
                                <?= htmlspecialchars($latest['gender']) ?>
                                <?php if ($latest['grade_level']): ?> · <?= htmlspecialchars($latest['grade_level']) ?><?php endif; ?>
                                <?php if ($latest['section']): ?> - <?= htmlspecialchars($latest['section']) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge rounded-pill <?= $badge ?>"><?= $cls ?></span>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="p-2 rounded-3 text-center" style="background:#f8f9fa;">
                                <div class="fw-bold" style="font-size:1.2rem;"><?= $latest['bmi'] ?>
                                <?php if ($bmiDelta !== null): ?>
                                    <span class="small ms-1 <?= $bmiDelta >= 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $bmiDelta > 0 ? '+' : '' ?><?= $bmiDelta ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:0.7rem;">BMI-for-age<?= $latest['bmi_zscore'] !== null ? ' · ' . htmlspecialchars($latest['bmi_zscore']) . ' SD' : '' ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2 rounded-3 text-center" style="background:#f8f9fa;">
                            <div class="fw-bold" style="font-size:1.2rem;"><?= $latest['weight'] ?> <?= $latest['weight_unit'] ?></div>
                            <div class="text-muted" style="font-size:0.7rem;">Weight <?= $weightChange ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2 rounded-3 text-center" style="background:#f8f9fa;">
                            <div class="fw-bold" style="font-size:1.2rem;"><?= $latest['height'] ?> <?= $latest['height_unit'] ?></div>
                            <div class="text-muted" style="font-size:0.7rem;">Height · <?= htmlspecialchars($latestHeightAssessment['height_classification'] ?? 'Not assessed') ?><?= ($latestHeightAssessment['height_zscore'] ?? null) !== null ? ' · ' . htmlspecialchars((string)$latestHeightAssessment['height_zscore']) . ' SD' : '' ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2 rounded-3 text-center" style="background:#f8f9fa;">
                            <div class="fw-bold" style="font-size:1.2rem;"><?= count($records) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;">Total Records</div>
                        </div>
                    </div>
                </div>

                <?php if (count($records) > 1): ?>
                <div class="mb-3">
                    <h6 class="fw-bold small text-success mb-2"><i class="fa-solid fa-chart-line me-1"></i><?= $chartUsesZscores ? 'WHO BMI-for-age z-score' : 'BMI history (older records without DOB)' ?></h6>
                    <canvas class="parent-bmi-chart" data-labels='<?= json_encode($chartLabels) ?>' data-values='<?= json_encode($chartBmi) ?>' data-value-label="<?= $chartUsesZscores ? 'WHO BMI-for-age z-score' : 'BMI' ?>" height="120"></canvas>
                </div>
                <?php endif; ?>

                <?php if ($cls !== 'Normal Weight'): ?>
                <div class="alert alert-<?= ($cls === 'Overweight') ? 'warning' : 'danger' ?> small py-2 mb-3">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <b>Health Alert:</b>
                    <?php if ($cls === 'Severe Underweight'): ?>
                        The WHO BMI-for-age result indicates severe thinness. Please arrange a prompt assessment with a qualified health professional.
                    <?php elseif ($cls === 'Underweight'): ?>
                        The WHO BMI-for-age result indicates thinness. Discuss your child's growth with a qualified health professional.
                    <?php elseif ($cls === 'Overweight'): ?>
                        Your child is overweight. Reduce sugary drinks and junk food. Encourage 60 minutes of physical activity daily.
                    <?php else: ?>
                        Your child is classified as obese. Please consult a doctor immediately for a dietary and exercise plan.
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <h6 class="fw-bold small text-muted mb-2"><i class="fa-solid fa-history me-1"></i>Record History</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover small mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>School Year</th><th>Grade / Section</th><th>Height</th><th>Height-for-Age</th><th>Weight</th><th>BMI</th><th>WHO z-score</th><th>Status</th><th>Change</th></tr></thead>
                        <tbody>
                        <?php foreach ($records as $i => $r):
                            $b = match($r['classification']) { 'Severe Underweight', 'Underweight'=>'badge-underweight', 'Normal Weight'=>'badge-normal', 'Overweight'=>'badge-overweight', default=>'badge-obese' };
                            $heightAssessment = getRecordHeightForAge($r);
                            $wc = '';
                            if ($i < count($records) - 1) {
                                $next = $records[$i + 1];
                                $cw = ($r['weight_unit']==='lbs') ? $r['weight']*0.453592 : $r['weight'];
                                $pw = ($next['weight_unit']==='lbs') ? $next['weight']*0.453592 : $next['weight'];
                                $d = round($cw - $pw, 1);
                                $wc = $d > 0 ? '<span class="text-success">+'.$d.' kg</span>' : ($d < 0 ? '<span class="text-danger">'.$d.' kg</span>' : '—');
                            }
                        ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td><?= htmlspecialchars($r['school_year'] ?? '—') ?></td>
                            <td><?= htmlspecialchars(trim(($r['grade_level'] ?? '') . (!empty($r['section']) ? ' - ' . $r['section'] : '')) ?: '—') ?></td>
                            <td><?= $r['height'] ?> <?= $r['height_unit'] ?></td>
                            <td><?= htmlspecialchars($heightAssessment['height_classification'] ?? 'Not assessed') ?><?= ($heightAssessment['height_zscore'] ?? null) !== null ? '<div class="text-muted">' . htmlspecialchars((string)$heightAssessment['height_zscore']) . ' SD</div>' : '' ?></td>
                            <td><?= $r['weight'] ?> <?= $r['weight_unit'] ?></td>
                            <td class="fw-bold"><?= $r['bmi'] ?></td>
                            <td><?= $r['bmi_zscore'] !== null ? htmlspecialchars($r['bmi_zscore']) . ' SD' : '—' ?></td>
                            <td><span class="badge rounded-pill <?= $b ?>"><?= $r['classification'] ?></span></td>
                            <td><?= $wc ?: '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="border-top pt-3 mt-2">
                    <h6 class="fw-bold small text-success mb-2"><i class="fa-solid fa-utensils me-1"></i>Feeding Program</h6>
                    <?php if (!empty($childFeedings)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Meal</th>
                                        <th>Menu/Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($childFeedings as $f): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($f['feed_date'])) ?></td>
                                        <td><?= $f['feed_time'] ? date('h:i A', strtotime($f['feed_time'])) : '—' ?></td>
                                        <td><?= htmlspecialchars($f['meal_type'] ?: '—') ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($f['notes'] ?: '—') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="small text-muted">No feeding record has been logged for this child yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.parent-bmi-chart').forEach(function (canvas) {
                const labels = JSON.parse(canvas.dataset.labels || '[]');
                const values = JSON.parse(canvas.dataset.values || '[]');
                if (!Array.isArray(labels) || !Array.isArray(values) || labels.length === 0 || values.length < 2) return;

                const existingChart = Chart.getChart(canvas);
                if (existingChart) {
                    existingChart.destroy();
                }

                const wrapper = canvas.parentElement;
                if (wrapper) {
                    wrapper.style.height = '180px';
                    wrapper.style.maxHeight = '180px';
                    wrapper.style.overflow = 'hidden';
                }

                canvas.style.width = '100%';
                canvas.style.height = '180px';
                canvas.style.maxHeight = '180px';
                canvas.width = canvas.clientWidth || 500;
                canvas.height = 180;

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: canvas.dataset.valueLabel || 'BMI',
                            data: values,
                            borderColor: '#2d8a4b',
                            backgroundColor: 'rgba(45, 138, 75, 0.14)',
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#2d8a4b',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 1,
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 250
                        },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: false,
                                ticks: { precision: 1 },
                                grid: { color: 'rgba(0,0,0,0.06)' }
                            }
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
