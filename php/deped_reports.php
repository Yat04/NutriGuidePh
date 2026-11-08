<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Admin', 'Super Admin']);

$conn = getDB();
$type = $_GET['type'] ?? 'sbfp';
$schoolYear = $_GET['sy'] ?? '';

$syWhere = $schoolYear ? " AND school_year = '" . $conn->real_escape_string($schoolYear) . "'" : '';
$resSY = $conn->query("SELECT DISTINCT school_year FROM stdRecord WHERE school_year IS NOT NULL ORDER BY school_year DESC");
$schoolYears = ($resSY && $resSY instanceof mysqli_result) ? $resSY->fetch_all(MYSQLI_ASSOC) : [];

// Get underweight students for SBFP safely
$resSBFP = $conn->query("
    SELECT s.* FROM stdRecord s
    INNER JOIN (SELECT std_first_name, std_mid_initial, std_last_name, MAX(id) as lid FROM stdRecord GROUP BY std_first_name, std_mid_initial, std_last_name) l
    ON s.id = l.lid
    WHERE s.classification IN ('Severe Underweight','Underweight') $syWhere
    ORDER BY s.grade_level, s.std_last_name
");
$sbfpStudents = ($resSBFP && $resSBFP instanceof mysqli_result) ? $resSBFP->fetch_all(MYSQLI_ASSOC) : [];

// OPT data - all students grouped by grade and gender safely
$resOPT = $conn->query("
    SELECT s.grade_level, s.gender, s.classification, COUNT(*) as cnt FROM stdRecord s
    INNER JOIN (SELECT std_first_name, std_mid_initial, std_last_name, MAX(id) as lid FROM stdRecord GROUP BY std_first_name, std_mid_initial, std_last_name) l
    ON s.id = l.lid
    WHERE s.grade_level IS NOT NULL $syWhere
    GROUP BY s.grade_level, s.gender, s.classification
    ORDER BY s.grade_level, s.gender
");
$optData = ($resOPT && $resOPT instanceof mysqli_result) ? $resOPT->fetch_all(MYSQLI_ASSOC) : [];

$optMap = [];
foreach ($optData as $o) {
    $optMap[$o['grade_level']][$o['gender']][$o['classification']] = $o['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – DepEd Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <style>
        @media print { .no-print { display: none !important; } body { background: #fff !important; font-size: 10pt; } .card { box-shadow: none !important; border: 1px solid #ddd !important; } }
        .deped-header { text-align: center; margin-bottom: 15px; }
        .deped-header img { height: 50px; }
        .deped-table th { background: #3d6b0f !important; color: #fff !important; font-size: 0.75rem; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .deped-table td { font-size: 0.78rem; }
    </style>
</head>
<body class="page-bg">
    <?php $activePage = 'reports'; include 'navbar.php'; ?>

    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2 no-print">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-file-lines me-2"></i>DepEd Reports</h4>
                <p class="text-white-50 small mb-0">SBFP and OPT (Operation Timbang Plus) report formats</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
            <div class="card-body p-3">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm" name="type" style="max-width:200px;">
                        <option value="sbfp" <?= $type==='sbfp'?'selected':'' ?>>SBFP Masterlist</option>
                        <option value="opt" <?= $type==='opt'?'selected':'' ?>>OPT Summary (Operation Timbang)</option>
                        <option value="quarterly" <?= $type==='quarterly'?'selected':'' ?>>Quarterly Nutritional Status</option>
                    </select>
                    <select class="form-select form-select-sm" name="sy" style="max-width:150px;">
                        <option value="">All Years</option>
                        <?php foreach ($schoolYears as $sy): ?>
                            <option value="<?= $sy['school_year'] ?>" <?= $schoolYear===$sy['school_year']?'selected':'' ?>><?= $sy['school_year'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-green btn-sm px-3"><i class="fa-solid fa-filter me-1"></i>Generate</button>
                </form>
            </div>
        </div>

        <?php if ($type === 'sbfp'): ?>
        <!-- SBFP Masterlist -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="deped-header">
                    <p class="mb-0 small">Republic of the Philippines · Department of Education</p>
                    <h5 class="fw-bold">SCHOOL-BASED FEEDING PROGRAM (SBFP)</h5>
                    <h6>MASTERLIST OF BENEFICIARIES</h6>
                    <p class="small">San Antonio Central School<?= $schoolYear ? " · S.Y. $schoolYear" : '' ?></p>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm deped-table mb-0">
                        <thead>
                            <tr><th>#</th><th>Last Name</th><th>First Name</th><th>M.I.</th><th>Gender</th><th>Grade</th><th>Section</th><th>Height (cm)</th><th>Weight (kg)</th><th>BMI</th><th>Guardian</th><th>Contact</th></tr>
                        </thead>
                        <tbody>
                            <?php $n=1; foreach ($sbfpStudents as $s): ?>
                            <tr>
                                <td><?= $n++ ?></td>
                                <td><?= htmlspecialchars($s['std_last_name']) ?></td>
                                <td><?= htmlspecialchars($s['std_first_name']) ?></td>
                                <td><?= htmlspecialchars($s['std_mid_initial']) ?></td>
                                <td><?= $s['gender'] ?></td>
                                <td><?= htmlspecialchars($s['grade_level']) ?></td>
                                <td><?= htmlspecialchars($s['section']) ?></td>
                                <td><?= $s['height'] ?></td>
                                <td><?= $s['weight'] ?></td>
                                <td><?= $s['bmi'] ?></td>
                                <td><?= htmlspecialchars($s['guardian_name']) ?></td>
                                <td><?= htmlspecialchars($s['guardian_number']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sbfpStudents)): ?><tr><td colspan="12" class="text-center text-muted py-3">No underweight students found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2">Total SBFP Beneficiaries: <b><?= count($sbfpStudents) ?></b></p>
                <div class="row mt-4 pt-3" style="border-top:1px solid #ddd;">
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>School Feeding Coordinator</small></div>
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>School Nurse</small></div>
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>School Principal</small></div>
                </div>
            </div>
        </div>

        <?php elseif ($type === 'opt'): ?>
        <!-- OPT Summary -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="deped-header">
                    <p class="mb-0 small">Republic of the Philippines · Department of Education</p>
                    <h5 class="fw-bold">OPERATION TIMBANG PLUS (OPT+)</h5>
                    <h6>NUTRITIONAL STATUS SUMMARY</h6>
                    <p class="small">San Antonio Central School<?= $schoolYear ? " · S.Y. $schoolYear" : '' ?></p>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm deped-table text-center mb-0">
                        <thead>
                            <tr>
                                <th rowspan="2">Grade Level</th>
                                <th rowspan="2">Gender</th>
                                <th rowspan="2">Total Weighed</th>
                                <th colspan="2">Underweight</th>
                                <th colspan="2">Normal</th>
                                <th colspan="2">Overweight</th>
                                <th colspan="2">Obese</th>
                            </tr>
                            <tr><th>No.</th><th>%</th><th>No.</th><th>%</th><th>No.</th><th>%</th><th>No.</th><th>%</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($optMap as $grade => $genders):
                                foreach ($genders as $gender => $counts):
                                    $total = array_sum($counts);
                                    $uw = $counts['Underweight'] ?? 0;
                                    $nw = $counts['Normal Weight'] ?? 0;
                                    $ow = $counts['Overweight'] ?? 0;
                                    $ob = $counts['Obese'] ?? 0;
                            ?>
                            <tr>
                                <td class="text-start"><?= htmlspecialchars($grade) ?></td>
                                <td><?= $gender ?></td>
                                <td><b><?= $total ?></b></td>
                                <td><?= $uw ?></td><td><?= $total ? round($uw/$total*100,1) : 0 ?>%</td>
                                <td><?= $nw ?></td><td><?= $total ? round($nw/$total*100,1) : 0 ?>%</td>
                                <td><?= $ow ?></td><td><?= $total ? round($ow/$total*100,1) : 0 ?>%</td>
                                <td><?= $ob ?></td><td><?= $total ? round($ob/$total*100,1) : 0 ?>%</td>
                            </tr>
                            <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row mt-4 pt-3" style="border-top:1px solid #ddd;">
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>OPT Coordinator</small></div>
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>School Nurse</small></div>
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>School Principal</small></div>
                </div>
            </div>
        </div>

        <?php elseif ($type === 'quarterly'): ?>
        <!-- Quarterly Nutritional Status Report -->
        <?php
        $allStats = $conn->query("
            SELECT s.classification, COUNT(*) as cnt FROM stdRecord s
            INNER JOIN (SELECT std_first_name, std_last_name, MAX(student_id) as lid FROM stdRecord GROUP BY std_first_name, std_last_name) l ON s.student_id = l.lid
            WHERE 1=1 $syWhere GROUP BY s.classification
        ")->fetch_all(MYSQLI_ASSOC);
        $sm = []; foreach ($allStats as $a) $sm[$a['classification']] = $a['cnt'];
        $tot = array_sum(array_column($allStats, 'cnt'));
        ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="deped-header">
                    <p class="mb-0 small">Republic of the Philippines · Department of Education</p>
                    <h5 class="fw-bold">QUARTERLY NUTRITIONAL STATUS REPORT</h5>
                    <p class="small">San Antonio Central School<?= $schoolYear ? " · S.Y. $schoolYear" : '' ?> · <?= date('F Y') ?></p>
                </div>
                <table class="table table-bordered table-sm deped-table text-center mb-3">
                    <thead><tr><th>Classification</th><th>Count</th><th>Percentage</th><th>Remarks</th></tr></thead>
                    <tbody>
                        <tr><td class="text-start">Underweight (Wasted/Severely Wasted)</td><td><?= $sm['Underweight']??0 ?></td><td><?= $tot?round(($sm['Underweight']??0)/$tot*100,1):0 ?>%</td><td class="text-start small">For SBFP enrollment</td></tr>
                        <tr><td class="text-start">Normal Weight</td><td><?= $sm['Normal Weight']??0 ?></td><td><?= $tot?round(($sm['Normal Weight']??0)/$tot*100,1):0 ?>%</td><td class="text-start small">Maintain balanced diet</td></tr>
                        <tr><td class="text-start">Overweight</td><td><?= $sm['Overweight']??0 ?></td><td><?= $tot?round(($sm['Overweight']??0)/$tot*100,1):0 ?>%</td><td class="text-start small">Monitor and counsel</td></tr>
                        <tr><td class="text-start">Obese</td><td><?= $sm['Obese']??0 ?></td><td><?= $tot?round(($sm['Obese']??0)/$tot*100,1):0 ?>%</td><td class="text-start small">Refer to health center</td></tr>
                        <tr class="table-light"><td class="text-start fw-bold">TOTAL</td><td class="fw-bold"><?= $tot ?></td><td>100%</td><td></td></tr>
                    </tbody>
                </table>
                <div class="row mt-4 pt-3" style="border-top:1px solid #ddd;">
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>Prepared by</small></div>
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>Noted by</small></div>
                    <div class="col-4 text-center"><div style="border-bottom:1px solid #333;height:40px;"></div><small>Approved by</small></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
