<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Employee', 'Admin', 'Super Admin']);

$conn = getDB();
$scope = getAdviserScope($conn);
$scopeParams = [];
$scopeTypes = '';
$scopeCondition = appendAdviserScope($scope, '', $scopeParams, $scopeTypes);
$latestSql = "SELECT s.* FROM stdRecord s WHERE s.id IN (
    SELECT MAX(id) FROM stdRecord WHERE 1=1" . $scopeCondition . "
    GROUP BY std_first_name, std_mid_initial, std_last_name
) ORDER BY s.std_last_name, s.std_first_name";
$latestStmt = $conn->prepare($latestSql);
if (!$latestStmt) {
    throw new RuntimeException('Could not prepare the latest-student report query: ' . $conn->error);
}
if ($scopeParams) $latestStmt->bind_param($scopeTypes, ...$scopeParams);
$latestStmt->execute();
$latestResult = $latestStmt->get_result();
$allStudents = $latestResult ? $latestResult->fetch_all(MYSQLI_ASSOC) : [];
$latestStmt->close();

$schoolYears = array_values(array_unique(array_filter(array_column($allStudents, 'school_year'), fn($year) => $year !== null && $year !== '')));
rsort($schoolYears, SORT_NATURAL);
$allowedGrades = array_merge(['Kindergarten'], array_map(fn($grade) => 'Grade ' . $grade, range(1, 6)));
if ($scope !== null) {
    $allowedGrades = in_array($scope['grade_level'] ?? '', $allowedGrades, true) ? [$scope['grade_level']] : [];
}
$schoolYear = trim($_GET['sy'] ?? '');
$gradeFilter = trim($_GET['grade'] ?? '');
if (!in_array($schoolYear, $schoolYears, true)) $schoolYear = '';
if (!in_array($gradeFilter, $allowedGrades, true)) $gradeFilter = '';

$students = array_values(array_filter($allStudents, function ($student) use ($schoolYear, $gradeFilter) {
    return ($schoolYear === '' || ($student['school_year'] ?? '') === $schoolYear)
        && ($gradeFilter === '' || ($student['grade_level'] ?? '') === $gradeFilter);
}));

$classificationOrder = ['Severe Underweight', 'Underweight', 'Normal Weight', 'Overweight', 'Obese'];
$statMap = array_fill_keys($classificationOrder, 0);
$genderData = [
    'Male' => array_fill_keys($classificationOrder, 0),
    'Female' => array_fill_keys($classificationOrder, 0),
];
$gradeData = [];
foreach (array_map(fn($grade) => 'Grade ' . $grade, range(1, 6)) as $grade) {
    $gradeData[$grade] = array_fill_keys($classificationOrder, 0);
}
foreach ($students as &$student) {
    $heightAssessment = getRecordHeightForAge($student);
    $student['height_zscore'] = $heightAssessment['height_zscore'] ?? null;
    $student['height_classification'] = $heightAssessment['height_classification'] ?? 'Not assessed';
    $classification = $student['classification'] ?? '';
    if (isset($statMap[$classification])) $statMap[$classification]++;
    $gender = ucfirst(strtolower((string)($student['gender'] ?? 'Unknown')));
    if (!isset($genderData[$gender])) $genderData[$gender] = array_fill_keys($classificationOrder, 0);
    if (isset($genderData[$gender][$classification])) $genderData[$gender][$classification]++;
    $grade = (string)($student['grade_level'] ?? '');
    if (isset($gradeData[$grade][$classification])) $gradeData[$grade][$classification]++;
}
unset($student);
$total = count($students);
$genderStats = [];
foreach ($genderData as $gender => $counts) {
    $genderStats[] = ['gender' => $gender, 'counts' => $counts];
}

$isPrint = isset($_GET['print']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Reports<?= $isPrint ? ' (Print)' : '' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <?php if (!$isPrint): ?>
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <?php endif; ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; color: #000 !important; font-size: 11pt; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .table th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { border: 1px solid #999; }
            .report-table { font-size: 8pt; }
            .report-table thead { display: table-header-group; }
            .report-table tr { break-inside: avoid; }
        }
        @page { size: A4 landscape; margin: 12mm; }
        .report-header { text-align: center; margin-bottom: 20px; }
        .report-header h4 { margin: 0; font-weight: 800; }
        .report-header p { margin: 2px 0; font-size: 0.9rem; color: #555; }
    </style>
</head>
<body class="<?= $isPrint ? '' : 'page-bg' ?>" <?= $isPrint ? 'style="background:#fff;"' : '' ?>>

    <?php if (!$isPrint): ?>
    <?php $activePage = 'reports'; include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="<?= $isPrint ? 'container' : 'container-fluid px-3 px-lg-5' ?> py-3 py-lg-4">

        <?php if (!$isPrint): ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2 no-print">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-print me-2"></i>Reports</h4>
                <p class="text-white-50 small mb-0">Generate and print nutritional status reports</p>
            </div>
            <div class="d-flex gap-2">
                <a href="reports.php?<?= http_build_query(array_filter(['sy' => $schoolYear, 'grade' => $gradeFilter])) ?>&print=1" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-file-lines me-1"></i>Print Layout</a>
                <button onclick="window.print()" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
            <div class="card-body p-3">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm" name="sy" style="max-width:150px;">
                        <option value="">All Years</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?= htmlspecialchars($sy) ?>" <?= $schoolYear === $sy ? 'selected' : '' ?>><?= htmlspecialchars($sy) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" name="grade" style="max-width:150px;">
                        <option value="">All Grades</option>
                        <?php foreach ($allowedGrades as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= $gradeFilter === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-green btn-sm px-3"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                    <?php if ($schoolYear || $gradeFilter): ?>
                        <a href="reports.php" class="btn btn-outline-secondary btn-sm px-2"><i class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Header -->
        <div class="report-header">
            <p>Republic of the Philippines</p>
            <h4>San Antonio Central School</h4>
            <p>Official Student Health and Nutritional Status Report<?= $schoolYear ? " — S.Y. " . htmlspecialchars($schoolYear) : '' ?><?= $gradeFilter ? " — " . htmlspecialchars($gradeFilter) : '' ?></p>
            <p style="font-size:0.8rem;">Prepared for <?= isAdmin() ? 'School Administration' : 'Assigned Adviser Scope' ?> · Generated: <?= date('F d, Y') ?></p>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-3">
                <h6 class="fw-bold mb-3">Table 1. Student Health Cases and Classifications</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle report-table mb-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Student</th><th>Gender</th><th>Grade / Section</th><th>Date of Birth</th><th>Height</th><th>Height-for-Age</th><th>BMI</th><th>Nutritional Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($students): ?>
                                <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars(formatStudentFullName($student['std_first_name'] ?? '', $student['std_mid_initial'] ?? '', $student['std_last_name'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(ucfirst(strtolower((string)$student['gender']))) ?></td>
                                    <td><?= htmlspecialchars(trim(($student['grade_level'] ?? '') . (!empty($student['section']) ? ' / ' . $student['section'] : '')) ?: '—') ?></td>
                                    <td><?= htmlspecialchars($student['date_of_birth'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars((string)$student['height'] . ' ' . (string)$student['height_unit']) ?></td>
                                    <td><?= htmlspecialchars($student['height_classification']) ?><?= $student['height_zscore'] !== null ? ' (' . htmlspecialchars((string)$student['height_zscore']) . ' SD)' : '' ?></td>
                                    <td><?= htmlspecialchars((string)$student['bmi']) ?><?= $student['bmi_zscore'] !== null ? ' (' . htmlspecialchars((string)$student['bmi_zscore']) . ' SD)' : '' ?></td>
                                    <td><?= htmlspecialchars($student['classification']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center text-muted">No student records match the selected filters and access scope.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Table 2. Nutritional Status Summary</h6>
                <table class="table table-bordered table-sm text-center mb-0">
                    <thead class="table-light">
                        <tr><th>Total</th><th>Severe Thinness</th><th>Thinness</th><th>Normal</th><th>Overweight</th><th>Obese</th><th>At Risk</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><b><?= $total ?></b></td>
                            <td><?= $statMap['Severe Underweight'] ?? 0 ?></td>
                            <td><?= $statMap['Underweight'] ?? 0 ?></td>
                            <td><?= $statMap['Normal Weight'] ?? 0 ?></td>
                            <td><?= $statMap['Overweight'] ?? 0 ?></td>
                            <td><?= $statMap['Obese'] ?? 0 ?></td>
                            <td><b><?= ($statMap['Severe Underweight'] ?? 0) + ($statMap['Underweight'] ?? 0) + ($statMap['Overweight'] ?? 0) + ($statMap['Obese'] ?? 0) ?></b></td>
                        </tr>
                        <?php if ($total > 0): ?>
                        <tr class="text-muted small">
                            <td>100%</td>
                            <td><?= round((($statMap['Severe Underweight'] ?? 0) / $total) * 100, 1) ?>%</td>
                            <td><?= round((($statMap['Underweight'] ?? 0) / $total) * 100, 1) ?>%</td>
                            <td><?= round((($statMap['Normal Weight'] ?? 0) / $total) * 100, 1) ?>%</td>
                            <td><?= round((($statMap['Overweight'] ?? 0) / $total) * 100, 1) ?>%</td>
                            <td><?= round((($statMap['Obese'] ?? 0) / $total) * 100, 1) ?>%</td>
                            <td><?= round(((($statMap['Severe Underweight'] ?? 0) + ($statMap['Underweight'] ?? 0) + ($statMap['Overweight'] ?? 0) + ($statMap['Obese'] ?? 0)) / $total) * 100, 1) ?>%</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <h6 class="fw-bold mt-4 mb-3">Breakdown by Gender</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center mb-0">
                        <thead class="table-light">
                            <tr><th>Gender</th><th>Total</th><th>Severe Thinness</th><th>Thinness</th><th>Normal</th><th>Overweight</th><th>Obese</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($genderStats as $genderRow):
                                $counts = $genderRow['counts'];
                            ?>
                            <tr>
                                <td class="text-start"><b><?= htmlspecialchars($genderRow['gender']) ?></b></td>
                                <td><?= array_sum($counts) ?></td>
                                <td><?= $counts['Severe Underweight'] ?? 0 ?></td>
                                <td><?= $counts['Underweight'] ?? 0 ?></td>
                                <td><?= $counts['Normal Weight'] ?? 0 ?></td>
                                <td><?= $counts['Overweight'] ?? 0 ?></td>
                                <td><?= $counts['Obese'] ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- By Grade Level -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Table 3. Breakdown by Grade Level (Grades 1–6)</h6>
                <table class="table table-bordered table-sm text-center mb-0">
                    <thead class="table-light">
                        <tr><th>Grade</th><th>Total</th><th>Severe Thinness</th><th>Thinness</th><th>Normal</th><th>Overweight</th><th>Obese</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gradeData as $grade => $counts):
                            $gt = array_sum($counts);
                        ?>
                        <tr>
                            <td class="text-start"><b><?= htmlspecialchars($grade) ?></b></td>
                            <td><?= $gt ?></td>
                            <td><?= $counts['Severe Underweight'] ?? 0 ?></td>
                            <td><?= $counts['Underweight'] ?? 0 ?></td>
                            <td><?= $counts['Normal Weight'] ?? 0 ?></td>
                            <td><?= $counts['Overweight'] ?? 0 ?></td>
                            <td><?= $counts['Obese'] ?? 0 ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Signature Lines -->
        <div class="row mt-5 pt-4" style="border-top:1px solid #ddd;">
            <div class="col-4 text-center">
                <div style="border-bottom:1px solid #333;margin-bottom:4px;height:40px;"></div>
                <small>Prepared by</small>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom:1px solid #333;margin-bottom:4px;height:40px;"></div>
                <small>Noted by</small>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom:1px solid #333;margin-bottom:4px;height:40px;"></div>
                <small>Approved by</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($isPrint): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>
<?php $conn->close(); ?>
