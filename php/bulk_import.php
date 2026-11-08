<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Admin', 'Super Admin']);

$conn = getDB();
$msg = ''; $msgType = ''; $imported = 0; $skipped = 0; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    verifyCsrf();
    $file = $_FILES['csv_file'];
    $schoolYear = sanitize($_POST['school_year'] ?? '');

    if ($file['error'] === UPLOAD_ERR_OK && pathinfo($file['name'], PATHINFO_EXTENSION) === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // Skip header row
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < 8) { $errors[] = "Row $row: Not enough columns"; $skipped++; continue; }

            $ln = trim($data[0]); $fn = trim($data[1]); $mid = trim($data[2] ?? '');
            $gender = trim($data[3]); $grade = trim($data[4] ?? ''); $section = trim($data[5] ?? '');
            $height = floatval($data[6]); $weight = floatval($data[7]);
            $h_unit = trim($data[8] ?? 'cm'); $w_unit = trim($data[9] ?? 'kg');
            $guardian = trim($data[10] ?? ''); $gnum = trim($data[11] ?? ''); $gemail = trim($data[12] ?? '');
            $dateOfBirth = trim($data[13] ?? '');

            if (empty($fn) || empty($ln) || $height <= 0 || $weight <= 0 || $dateOfBirth === '') { $errors[] = "Row $row: Name, valid height/weight, and date of birth are required"; $skipped++; continue; }

            $calculation = calculatePediatricBmi($height, $h_unit, $weight, $w_unit, $dateOfBirth, strtolower($gender));
            if (isset($calculation['error'])) { $errors[] = "Row $row: " . $calculation['error']; $skipped++; continue; }
            $bmi = $calculation['bmi'];
            $bmiZscore = $calculation['bmi_zscore'];
            $heightZscore = $calculation['height_zscore'];
            $heightClassification = $calculation['height_classification'];
            $cls = $calculation['classification'];

            $stmt = $conn->prepare("INSERT INTO stdRecord (std_last_name, std_first_name, std_mid_initial, gender, grade_level, section, school_year, date_of_birth, height, height_unit, weight, weight_unit, classification, bmi, bmi_zscore, height_zscore, height_classification, guardian_name, guardian_number, guardian_email) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if (!$stmt) {
                error_log('Bulk student record insert preparation failed: ' . $conn->error);
                $errors[] = "Row $row: Could not prepare the student record.";
                $skipped++;
                continue;
            }
            $stmt->bind_param("ssssssssssssssssssss", $ln, $fn, $mid, $gender, $grade, $section, $schoolYear, $dateOfBirth, $height, $h_unit, $weight, $w_unit, $cls, $bmi, $bmiZscore, $heightZscore, $heightClassification, $guardian, $gnum, $gemail);
            $conn->begin_transaction();
            if ($stmt->execute() && syncInsertedStudentRecordIdentifier($conn, $conn->insert_id) && $conn->commit()) {
                $imported++;
            } else {
                $conn->rollback();
                $errors[] = "Row $row: Could not save or link the student record.";
                $skipped++;
            }
            $stmt->close();
        }
        fclose($handle);
        auditLog('bulk_import', 'stdRecord', null, "Imported: $imported, Skipped: $skipped");
        $msg = "Import complete! $imported records imported, $skipped skipped.";
        $msgType = $skipped > 0 ? 'warning' : 'success';
    } else {
        $msg = 'Please upload a valid CSV file.'; $msgType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Bulk Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body class="page-bg">
    <?php $activePage = 'records'; include 'navbar.php'; ?>

    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="mb-4">
            <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-file-import me-2"></i>Bulk CSV Import</h4>
            <p class="text-white-50 small mb-0">Upload a CSV file to import multiple student records at once</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> alert-dismissible fade show small py-2">
                <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : ($msgType === 'warning' ? 'triangle-exclamation' : 'circle-exclamation') ?> me-1"></i>
                <?= $msg ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small py-2">
                    <b>Errors:</b><br>
                    <?php foreach (array_slice($errors, 0, 10) as $e): ?>
                        <?= htmlspecialchars($e) ?><br>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 10): ?>...and <?= count($errors) - 10 ?> more<?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4" style="border-top:5px solid #78bc27 !important;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-upload me-2"></i>Upload CSV</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">School Year</label>
                                <select class="form-select form-select-sm" name="school_year">
                                    <?php
                                    $cy = date('Y'); $cm = date('n');
                                    $sy = ($cm >= 6) ? $cy.'-'.($cy+1) : ($cy-1).'-'.$cy;
                                    for ($y = $cy+1; $y >= $cy-3; $y--) {
                                        $v = ($y-1).'-'.$y;
                                        echo "<option value='$v' ".($v===$sy?'selected':'').">$v</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">CSV File</label>
                                <input type="file" class="form-control form-control-sm" name="csv_file" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-green w-100 py-2">
                                <i class="fa-solid fa-file-import me-2"></i>Import Records
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4" style="border-top:5px solid #78bc27 !important;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-info-circle me-2"></i>CSV Format</h6>
                        <p class="small text-muted mb-2">Your CSV must have these columns in order:</p>
                        <div class="table-responsive">
                            <table class="table table-sm small mb-3">
                                <thead class="table-light"><tr><th>#</th><th>Column</th><th>Required</th><th>Example</th></tr></thead>
                                <tbody>
                                    <tr><td>1</td><td>Last Name</td><td>✅</td><td>Dela Cruz</td></tr>
                                    <tr><td>2</td><td>First Name</td><td>✅</td><td>Juan</td></tr>
                                    <tr><td>3</td><td>M.I.</td><td></td><td>P</td></tr>
                                    <tr><td>4</td><td>Gender</td><td>✅</td><td>Male</td></tr>
                                    <tr><td>5</td><td>Grade Level</td><td></td><td>Grade 3</td></tr>
                                    <tr><td>6</td><td>Section</td><td></td><td>Sampaguita</td></tr>
                                    <tr><td>7</td><td>Height</td><td>✅</td><td>120</td></tr>
                                    <tr><td>8</td><td>Weight</td><td>✅</td><td>25</td></tr>
                                    <tr><td>9</td><td>Height Unit</td><td></td><td>cm</td></tr>
                                    <tr><td>10</td><td>Weight Unit</td><td></td><td>kg</td></tr>
                                    <tr><td>11</td><td>Guardian Name</td><td></td><td>Maria Dela Cruz</td></tr>
                                    <tr><td>12</td><td>Guardian Number</td><td></td><td>09171234567</td></tr>
                                    <tr><td>13</td><td>Guardian Email</td><td></td><td>maria@email.com</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <a href="download_template.php" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-download me-2"></i>Download Template CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
