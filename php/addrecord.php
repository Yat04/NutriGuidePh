<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Employee', 'Admin', 'Super Admin']);

$conn = getDB();
$adviserScope = getAdviserScope($conn);
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');
$defaultSchoolYear = ($currentMonth >= 6) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
$growthBenchmarks = [
    'Kindergarten' => ['age'=>'4.5-5.5 years (entry: 5)', 'age_range'=>[4.5,5.5], 'boys'=>['height'=>[105,115],'weight'=>[16,21],'bmi'=>[13.8,16.8]], 'girls'=>['height'=>[104,114],'weight'=>[15,21],'bmi'=>[13.5,16.8]]],
    'Grade 1' => ['age'=>'6.0-6.9 years', 'age_range'=>[6.0,6.9], 'boys'=>['height'=>[111,122],'weight'=>[18,25],'bmi'=>[13.7,16.9]], 'girls'=>['height'=>[110,121],'weight'=>[17,24],'bmi'=>[13.4,16.8]]],
    'Grade 2' => ['age'=>'7.0-7.9 years', 'age_range'=>[7.0,7.9], 'boys'=>['height'=>[116,128],'weight'=>[20,28],'bmi'=>[13.7,17.2]], 'girls'=>['height'=>[115,127],'weight'=>[19,28],'bmi'=>[13.4,17.1]]],
    'Grade 3' => ['age'=>'8.0-8.9 years', 'age_range'=>[8.0,8.9], 'boys'=>['height'=>[121,134],'weight'=>[22,32],'bmi'=>[13.8,17.7]], 'girls'=>['height'=>[120,133],'weight'=>[21,32],'bmi'=>[13.5,17.7]]],
    'Grade 4' => ['age'=>'9.0-9.9 years', 'age_range'=>[9.0,9.9], 'boys'=>['height'=>[126,140],'weight'=>[24,37],'bmi'=>[14.0,18.3]], 'girls'=>['height'=>[126,140],'weight'=>[24,38],'bmi'=>[13.7,18.4]]],
    'Grade 5' => ['age'=>'10.0-10.9 years', 'age_range'=>[10.0,10.9], 'boys'=>['height'=>[131,146],'weight'=>[27,43],'bmi'=>[14.3,19.1]], 'girls'=>['height'=>[132,147],'weight'=>[27,44],'bmi'=>[14.0,19.3]]],
    'Grade 6' => ['age'=>'11.0-11.9 years', 'age_range'=>[11.0,11.9], 'boys'=>['height'=>[136,152],'weight'=>[30,49],'bmi'=>[14.7,20.1]], 'girls'=>['height'=>[138,154],'weight'=>[30,51],'bmi'=>[14.4,20.5]]],
];

$save_msg = "";
$save_type = "";

// â”€â”€ Save Record â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (isset($_POST['save_record'])) {
    verifyCsrf();
    $ln             = $_POST["lname"];
    $fn             = $_POST["fname"];
    $mid            = $_POST["m_initial"];
    $gender         = strtolower(trim($_POST["gender"] ?? 'male'));
    $grade          = $adviserScope === null ? trim($_POST["grade_level"] ?? '') : $adviserScope['grade_level'];
    $section        = trim($_POST["section"] ?? '');
    $school_year    = trim($_POST["school_year"] ?? '');
    $bday           = trim($_POST['birthday'] ?? '');
    $height         = $_POST["height"];
    $h_unit         = strtolower(trim($_POST["measurement_unit"] ?? 'cm'));
    $weight         = $_POST["weight"];
    $w_unit         = strtolower(trim($_POST["weight_unit"] ?? 'kg'));
    $pname          = $_POST["p_name"];
    $contact        = $_POST["number"];
    $email          = $_POST["email"];

    if ($grade === '' || $section === '' || ($adviserScope !== null && !in_array($section, $adviserScope['sections'], true))) {
        $save_msg = "Grade level and section are required before saving.";
        $save_type = "error";
    } elseif (!isValidSchoolYear($school_year)) {
        $save_msg = 'Enter a valid school year in YYYY-YYYY format.';
        $save_type = 'error';
    } elseif (!validateHeight($height, $h_unit) || !validateWeight($weight, $w_unit)) {
        $save_msg = 'Enter a valid height and weight before saving.';
        $save_type = 'error';
    } else {
        $calculation = calculatePediatricBmi($height, $h_unit, $weight, $w_unit, $bday, $gender);
        if (isset($calculation['error'])) {
            $save_msg = $calculation['error'];
            $save_type = 'error';
        } else {
            $bmi = $calculation['bmi'];
            $bmiZscore = $calculation['bmi_zscore'];
            $classification = $calculation['classification'];
            $heightZscore = $calculation['height_zscore'];
            $heightClassification = $calculation['height_classification'];
            $stmt = $conn->prepare("INSERT INTO stdRecord (std_last_name, std_first_name, std_mid_initial, gender, grade_level, section, school_year, date_of_birth, height, height_unit, weight, weight_unit, classification, bmi, bmi_zscore, height_zscore, height_classification, guardian_name, guardian_number, guardian_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log('Student record insert preparation failed: ' . $conn->error);
                $save_msg = "Error saving record. Please try again.";
                $save_type = "error";
            } else {
                $stmt->bind_param("ssssssssssssssssssss", $ln, $fn, $mid, $gender, $grade, $section, $school_year, $bday, $height, $h_unit, $weight, $w_unit, $classification, $bmi, $bmiZscore, $heightZscore, $heightClassification, $pname, $contact, $email);
                $conn->begin_transaction();
            }

            if ($stmt && $stmt->execute() && syncInsertedStudentRecordIdentifier($conn, $conn->insert_id) && $conn->commit()) {
                $save_msg  = "Record saved successfully!";
                $save_type = "success";
                $newStudentId = (int)$conn->insert_id;
                $photoStmt = $conn->prepare("SELECT photo FROM stdRecord WHERE std_first_name = ? AND std_mid_initial <=> ? AND std_last_name = ? AND id <> ? AND photo IS NOT NULL AND photo <> '' ORDER BY id DESC LIMIT 1");
                $photoStmt->bind_param('sssi', $fn, $mid, $ln, $newStudentId);
                $photoStmt->execute();
                $existingPhoto = $photoStmt->get_result()->fetch_assoc()['photo'] ?? '';
                $photoStmt->close();
                if ($existingPhoto !== '') {
                    $copyPhoto = $conn->prepare("UPDATE stdRecord SET photo = ? WHERE id = ?");
                    $copyPhoto->bind_param('si', $existingPhoto, $newStudentId);
                    $copyPhoto->execute();
                    $copyPhoto->close();
                }
                auditLog('add_record', 'stdRecord', $newStudentId, "$fn $mid $ln - BMI: $bmi (z: $bmiZscore, $classification)");

                if (in_array($classification, ['Severe Underweight', 'Underweight', 'Overweight', 'Obese'], true) && !empty($email)) {
                    require_once 'mailer.php';
                    $studentName = "$fn $mid $ln";
                    sendMalnourishmentEmail($email, $pname, $studentName, $bmi, $height, $h_unit, $weight, $w_unit, $classification);
                }
            } elseif ($stmt) {
                $conn->rollback();
                $save_msg  = "Error saving record. Please try again.";
                $save_type = "error";
            }
            if ($stmt) $stmt->close();
        }
    }
}

// â”€â”€ Calculate BMI on Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$preview = null;
$validation_error = '';
if (isset($_POST['submit_form'])) {
    verifyCsrf();
    $fn     = normalizeNameCase($_POST["fname"] ?? '');
    $mid    = normalizeInitialCase($_POST["m_initial"] ?? '');
    $ln     = normalizeNameCase($_POST["lname"] ?? '');
    $bday   = trim($_POST["birthday"] ?? '');
    $gender = strtolower(trim($_POST["gender"] ?? 'male'));
    $grade  = $adviserScope === null ? trim($_POST["grade_level"] ?? '') : $adviserScope['grade_level'];
    $section = trim($_POST["section"] ?? '');
    $school_year = trim($_POST["school_year"] ?? '');
    $weight = floatval($_POST["weight"]);
    $w_unit = strtolower(trim($_POST["weight_unit"] ?? 'kg'));
    $height = floatval($_POST["height"]);
    $h_unit = strtolower(trim($_POST["measurement_unit"] ?? 'cm'));
    $pname  = sanitize($_POST["p_name"]);
    $contact= sanitize($_POST["number"]);
    $email  = trim($_POST["email"]);

    if ($grade === '' || $section === '' || ($adviserScope !== null && !in_array($section, $adviserScope['sections'], true))) {
        $validation_error = 'Grade level and section are required before previewing or saving a record.';
    } elseif (!isValidSchoolYear($school_year)) {
        $validation_error = 'Enter a valid school year in YYYY-YYYY format.';
    } elseif (!validateHeight($height, $h_unit)) {
        $validation_error = 'Invalid height value for the selected unit.';
    } elseif (!validateWeight($weight, $w_unit)) {
        $validation_error = 'Invalid weight value for the selected unit.';
    } else {
        $calculation = calculatePediatricBmi($height, $h_unit, $weight, $w_unit, $bday, $gender);
        if (isset($calculation['error'])) {
            $validation_error = $calculation['error'];
        } else {
            $bmi = $calculation['bmi'];
            $bmi_zscore = $calculation['bmi_zscore'];
            $age_months = $calculation['age_months'];
            $classification = $calculation['classification'];
            $height_zscore = $calculation['height_zscore'];
            $height_classification = $calculation['height_classification'];
            $benchmarkWarnings = [];
            if (isset($growthBenchmarks[$grade])) {
                $sexBenchmarks = $growthBenchmarks[$grade][strtolower($gender) === 'male' ? 'boys' : 'girls'];
                $heightCm = $height * (['cm'=>1,'m'=>100,'inch'=>2.54,'feet'=>30.48][$h_unit] ?? 1);
                $weightKg = $weight * ($w_unit === 'lbs' ? 0.45359237 : 1);
                $ageYears = $age_months / 12;
                if ($ageYears < $growthBenchmarks[$grade]['age_range'][0] || $ageYears > $growthBenchmarks[$grade]['age_range'][1]) $benchmarkWarnings[] = 'Age is outside the typical range for this grade.';
                if ($heightCm < $sexBenchmarks['height'][0] || $heightCm > $sexBenchmarks['height'][1]) $benchmarkWarnings[] = 'Height is outside the typical range for this grade and sex.';
                if ($weightKg < $sexBenchmarks['weight'][0] || $weightKg > $sexBenchmarks['weight'][1]) $benchmarkWarnings[] = 'Weight is outside the typical range for this grade and sex.';
                if ($bmi < $sexBenchmarks['bmi'][0] || $bmi > $sexBenchmarks['bmi'][1]) $benchmarkWarnings[] = 'BMI is outside the typical range for this grade and sex; WHO BMI-for-age determines classification.';
            }
            $preview = compact('fn','mid','ln','bday','gender','grade','section','school_year','weight','w_unit','height','h_unit','bmi','bmi_zscore','height_zscore','height_classification','age_months','classification','pname','contact','email','benchmarkWarnings');
        }
    }
}

// â”€â”€ Student Name Suggestions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$studentSuggestions = [];
$suggestionParams = [];
$suggestionTypes = '';
$suggestionScope = appendAdviserScope($adviserScope, '', $suggestionParams, $suggestionTypes);
$suggestionSql = "SELECT student_id, id, std_first_name, std_mid_initial, std_last_name, gender, grade_level, section, school_year, date_of_birth, guardian_name, guardian_number, guardian_email FROM stdRecord WHERE (std_first_name <> '' OR std_last_name <> '')" . $suggestionScope . " ORDER BY created_at DESC LIMIT 200";
$suggestionQuery = $conn->prepare($suggestionSql);
if ($suggestionQuery) {
    if (!empty($suggestionParams)) $suggestionQuery->bind_param($suggestionTypes, ...$suggestionParams);
    $suggestionQuery->execute();
    $suggestionQuery = $suggestionQuery->get_result();
}
if ($suggestionQuery) {
    $studentSuggestions = $suggestionQuery->fetch_all(MYSQLI_ASSOC);
}
$studentSuggestionsJson = json_encode($studentSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide â€“ Add Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=5">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body class="page-bg">

    <!-- Navbar -->
    <?php $activePage = 'addrecord'; include 'navbar.php'; ?>


    <!-- Main Content -->
    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="row g-3 g-lg-4">

            <!-- Form Card -->
            <div class="col-lg-6" data-aos="fade-right">
                <div class="card border-0 shadow-sm rounded-4" style="border-top: 5px solid #78bc27 !important;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success mb-4">
                            <i class="fa-solid fa-clipboard-list me-2"></i>Student Record Entry
                        </h5>

                        <?php if ($validation_error): ?>
                            <div class="alert alert-danger small py-2"><i class="fa-solid fa-circle-exclamation me-1"></i><?= $validation_error ?></div>
                        <?php endif; ?>

                        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
                            <?= csrfField() ?>
                            <p class="section-label mb-3">Student Information</p>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Select previous student to update</label>
                                <input type="text" id="student-search" class="form-control" placeholder="Type a student name here..." autocomplete="off">
                                <div id="student-suggestions" class="list-group mt-2 shadow-sm" style="max-height: 210px; overflow-y: auto; display: none;"></div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted">First Name *</label>
                                    <input type="text" class="form-control" name="fname" value="<?= htmlspecialchars($preview['fn'] ?? '') ?>" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted">Last Name *</label>
                                    <input type="text" class="form-control" name="lname" value="<?= htmlspecialchars($preview['ln'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-4 col-sm-3">
                                    <label class="form-label small fw-semibold text-muted">M.I.</label>
                                    <input type="text" class="form-control" name="m_initial" maxlength="2" value="<?= htmlspecialchars($preview['mid'] ?? '') ?>">
                                </div>
                                <div class="col-8 col-sm-5">
                                    <label class="form-label small fw-semibold text-muted">Date of Birth *</label>
                                    <input type="date" class="form-control" name="birthday" value="<?= htmlspecialchars($preview['bday'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label small fw-semibold text-muted">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="male" <?= (strtolower((string)($preview['gender'] ?? 'male')) == 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= (strtolower((string)($preview['gender'] ?? 'female')) == 'female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted">Grade Level</label>
                                    <?php if ($adviserScope !== null): ?>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($adviserScope['grade_level']) ?>" readonly required>
                                        <input type="hidden" name="grade_level" value="<?= htmlspecialchars($adviserScope['grade_level']) ?>">
                                    <?php else: ?>
                                        <select class="form-select" name="grade_level" required>
                                            <option value="">Select...</option>
                                            <?php foreach (array_merge(['Kindergarten'], array_map(fn($g) => "Grade $g", range(1, 6))) as $gradeOption): ?>
                                                <option value="<?= htmlspecialchars($gradeOption) ?>" <?= (($preview['grade'] ?? '') === $gradeOption) ? 'selected' : '' ?>><?= htmlspecialchars($gradeOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted">Section</label>
                                    <?php if ($adviserScope !== null): ?>
                                        <select class="form-select" name="section" required>
                                            <option value="">Select...</option>
                                            <?php foreach ($adviserScope['sections'] as $assignedSection): ?>
                                                <option value="<?= htmlspecialchars($assignedSection) ?>" <?= (($preview['section'] ?? '') === $assignedSection) ? 'selected' : '' ?>><?= htmlspecialchars($assignedSection) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="section" placeholder="e.g. Sampaguita" value="<?= htmlspecialchars($preview['section'] ?? '') ?>" required>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">School Year</label>
                                <input type="text" class="form-control" name="school_year" placeholder="YYYY-YYYY" pattern="[0-9]{4}-[0-9]{4}" maxlength="9" value="<?= htmlspecialchars($preview['school_year'] ?? $defaultSchoolYear) ?>" required>
                            </div>

                            <p class="section-label mb-3 mt-4">Physical Measurements</p>
                            <div class="alert alert-info small py-2"><i class="fa-solid fa-circle-info me-1"></i>BMI-for-age and height-for-age use WHO age- and sex-specific references. Height-for-age is assessed from 2 through 18 years (stunting below −2 SD; severe stunting below −3 SD; tall above +2 SD); younger children are marked not assessed. These classifications are screening measures, not diagnoses.</div>
                            <details class="mb-3 growth-ranges">
                                <summary class="growth-ranges-trigger small fw-semibold text-success" role="button" aria-expanded="false" aria-controls="growth-ranges-content">Typical growth ranges by grade</summary>
                                <div id="growth-ranges-content">
                                <div class="table-responsive mt-2 growth-ranges-table-wrapper" role="region" aria-label="Typical growth ranges by grade. Scroll horizontally to view all columns." tabindex="0">
                                    <table class="table table-sm table-bordered small align-middle mb-1 growth-ranges-table">
                                        <thead class="table-light"><tr><th>Grade</th><th>Age</th><th>Boys: height / weight / BMI</th><th>Girls: height / weight / BMI</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($growthBenchmarks as $benchmarkGrade => $benchmark): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($benchmarkGrade) ?></td>
                                                <td><?= htmlspecialchars($benchmark['age']) ?></td>
                                                <td><?= $benchmark['boys']['height'][0] ?>-<?= $benchmark['boys']['height'][1] ?> cm / <?= $benchmark['boys']['weight'][0] ?>-<?= $benchmark['boys']['weight'][1] ?> kg / BMI <?= $benchmark['boys']['bmi'][0] ?>-<?= $benchmark['boys']['bmi'][1] ?></td>
                                                <td><?= $benchmark['girls']['height'][0] ?>-<?= $benchmark['girls']['height'][1] ?> cm / <?= $benchmark['girls']['weight'][0] ?>-<?= $benchmark['girls']['weight'][1] ?> kg / BMI <?= $benchmark['girls']['bmi'][0] ?>-<?= $benchmark['girls']['bmi'][1] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">Typical ranges are a guide only; the WHO BMI-for-age z-score determines the classification.</div>
                            </details>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Height *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="height" step="any" placeholder="e.g. 120" value="<?= $preview['height'] ?? '' ?>" required>
                                    <select class="form-select" name="measurement_unit" style="max-width: 100px;">
                                        <option value="cm" <?= (($preview['h_unit'] ?? 'cm') == 'cm') ? 'selected' : '' ?>>cm</option>
                                        <option value="m" <?= (($preview['h_unit'] ?? '') == 'm') ? 'selected' : '' ?>>m</option>
                                        <option value="inch" <?= (($preview['h_unit'] ?? '') == 'inch') ? 'selected' : '' ?>>inch</option>
                                        <option value="feet" <?= (($preview['h_unit'] ?? '') == 'feet') ? 'selected' : '' ?>>feet</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Weight *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="weight" step="any" placeholder="e.g. 35" value="<?= $preview['weight'] ?? '' ?>" required>
                                    <select class="form-select" name="weight_unit" style="max-width: 100px;">
                                        <option value="kg" <?= (($preview['w_unit'] ?? 'kg') == 'kg') ? 'selected' : '' ?>>kg</option>
                                        <option value="lbs" <?= (($preview['w_unit'] ?? '') == 'lbs') ? 'selected' : '' ?>>lbs</option>
                                    </select>
                                </div>
                            </div>

                            <p class="section-label mb-3 mt-4">Guardian Information</p>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted">Guardian Name</label>
                                <input type="text" class="form-control" name="p_name" placeholder="Full name" value="<?= htmlspecialchars($preview['pname'] ?? '') ?>">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted">Contact Number</label>
                                    <input type="text" class="form-control" name="number" placeholder="+63 900 000 0000" value="<?= htmlspecialchars($preview['contact'] ?? '') ?>">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted">Guardian Email</label>
                                    <input type="email" class="form-control" name="email" placeholder="email@example.com" value="<?= htmlspecialchars($preview['email'] ?? '') ?>">
                                </div>
                            </div>

                            <button type="submit" name="submit_form" class="btn btn-green w-100 py-2 mt-2">
                                <i class="fa-solid fa-calculator me-2"></i>Evaluate Growth & Preview
                            </button>
                        </form>

                        <!-- Photo Upload (separate form, after record is saved) -->
                        <?php if ($save_type === 'success' && $conn->insert_id): ?>
                        <form action="upload_photo.php" method="POST" enctype="multipart/form-data" class="mt-3 p-3 rounded-3" style="background:#f8fff0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="student_id" value="<?= $conn->insert_id ?>">
                            <label class="form-label small fw-semibold text-success"><i class="fa-solid fa-camera me-1"></i>Upload Student Photo (optional)</label>
                            <div class="d-flex gap-2">
                                <input type="file" class="form-control form-control-sm" name="photo" accept="image/jpeg,image/png,image/webp">
                                <button class="btn btn-sm btn-outline-success px-3"><i class="fa-solid fa-upload"></i></button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Preview Card -->
            <div class="col-lg-6" data-aos="fade-left">
                <div class="card border-0 shadow-sm rounded-4" style="border-top: 5px solid #78bc27 !important;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success mb-4">
                            <i class="fa-solid fa-chart-column me-2"></i>Record Preview
                        </h5>

                        <?php if ($save_msg): ?>
                            <div class="alert alert-<?= $save_type === 'success' ? 'success' : 'danger' ?> small py-2">
                                <i class="fa-solid fa-<?= $save_type === 'success' ? 'circle-check' : 'circle-exclamation' ?> me-1"></i>
                                <?= htmlspecialchars($save_msg) ?>
                                <?php if ($save_type === 'success' && in_array($preview['classification'] ?? '', ['Underweight', 'Overweight', 'Obese'])): ?>
                                    <br><small><i class="fa-solid fa-envelope me-1"></i>A health alert has been sent to the guardian's email.</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($preview): ?>
                            <?php
                                $cls = $preview['classification'];
                                $badgeClass = match($cls) {
                                    'Underweight'  => 'badge-underweight',
                                    'Normal Weight'=> 'badge-normal',
                                    'Overweight'   => 'badge-overweight',
                                    default        => 'badge-obese'
                                };
                            ?>

                            <?php if ($cls !== 'Normal Weight'): ?>
                                <?php
                                    $alertMsg = match($cls) {
                                        'Underweight' => 'This student is classified as <b>Underweight (Malnourished)</b>.',
                                        'Overweight'  => 'This student is classified as <b>Overweight (At Risk)</b>.',
                                        'Obese'       => 'This student is classified as <b>Obese (High Risk)</b>.',
                                        default       => ''
                                    };
                                    $alertType = ($cls === 'Underweight' || $cls === 'Obese') ? 'danger' : 'warning';
                                ?>
                                <div class="alert alert-<?= $alertType ?> small py-2">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    <strong>Health Alert Detected</strong><br>
                                    <?= $alertMsg ?> Saving will send a health alert to the guardian's email.
                                </div>
                            <?php endif; ?>

                            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="fname" value="<?= htmlspecialchars($preview['fn']) ?>">
                                <input type="hidden" name="m_initial" value="<?= htmlspecialchars($preview['mid']) ?>">
                                <input type="hidden" name="lname" value="<?= htmlspecialchars($preview['ln']) ?>">
                                <input type="hidden" name="gender" value="<?= htmlspecialchars($preview['gender']) ?>">
                                <input type="hidden" name="grade_level" value="<?= htmlspecialchars($preview['grade'] ?? '') ?>">
                                <input type="hidden" name="section" value="<?= htmlspecialchars($preview['section'] ?? '') ?>">
                                <input type="hidden" name="school_year" value="<?= htmlspecialchars($preview['school_year'] ?? '') ?>">
                                <input type="hidden" name="birthday" value="<?= htmlspecialchars($preview['bday']) ?>">
                                <input type="hidden" name="height" value="<?= htmlspecialchars($preview['height']) ?>">
                                <input type="hidden" name="measurement_unit" value="<?= htmlspecialchars($preview['h_unit']) ?>">
                                <input type="hidden" name="weight" value="<?= htmlspecialchars($preview['weight']) ?>">
                                <input type="hidden" name="weight_unit" value="<?= htmlspecialchars($preview['w_unit']) ?>">
                                <input type="hidden" name="bmi" value="<?= htmlspecialchars($preview['bmi']) ?>">
                                <input type="hidden" name="classification" value="<?= htmlspecialchars($cls) ?>">
                                <input type="hidden" name="p_name" value="<?= htmlspecialchars($preview['pname']) ?>">
                                <input type="hidden" name="number" value="<?= htmlspecialchars($preview['contact']) ?>">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($preview['email']) ?>">

                                <?php if (!empty($preview['benchmarkWarnings'])): ?>
                                <div class="alert alert-warning small py-2">
                                    <div class="fw-semibold mb-1">Typical growth-range check</div>
                                    <?php foreach ($preview['benchmarkWarnings'] as $warning): ?><div><?= htmlspecialchars($warning) ?></div><?php endforeach; ?>
                                    <div class="mt-1">These ranges are advisory; the WHO BMI-for-age result determines nutritional classification.</div>
                                </div>
                                <?php endif; ?>

                                <table class="table table-sm table-borderless mb-3">
                                    <tr><th class="text-muted small" style="width:140px">Student Name</th><td><?= htmlspecialchars("{$preview['fn']} {$preview['mid']} {$preview['ln']}") ?></td></tr>
                                    <tr><th class="text-muted small">Date of Birth</th><td><?= htmlspecialchars($preview['bday']) ?></td></tr>
                                    <tr><th class="text-muted small">Age</th><td><?= number_format($preview['age_months'] / 12, 1) ?> years</td></tr>
                                    <tr><th class="text-muted small">Gender</th><td><?= htmlspecialchars($preview['gender']) ?></td></tr>
                                    <tr><th class="text-muted small">Height</th><td><?= htmlspecialchars("{$preview['height']} {$preview['h_unit']}") ?></td></tr>
                                    <tr><th class="text-muted small">Weight</th><td><?= htmlspecialchars("{$preview['weight']} {$preview['w_unit']}") ?></td></tr>
                                    <tr><th class="text-muted small">BMI-for-age</th><td><strong><?= $preview['bmi'] ?> kg/m²</strong> <span class="text-muted small">(z-score <?= $preview['bmi_zscore'] ?> SD)</span></td></tr>
                                    <tr><th class="text-muted small">Classification</th><td><span class="badge rounded-pill <?= $badgeClass ?>"><?= $cls ?></span></td></tr>
                                    <tr><th class="text-muted small">Height-for-age</th><td><strong><?= htmlspecialchars($preview['height_classification']) ?></strong><?= $preview['height_zscore'] !== null ? ' <span class="text-muted small">(z-score ' . htmlspecialchars((string)$preview['height_zscore']) . ' SD)</span>' : '' ?></td></tr>
                                    <tr><th class="text-muted small">Guardian</th><td><?= htmlspecialchars($preview['pname']) ?></td></tr>
                                    <tr><th class="text-muted small">Contact</th><td><?= htmlspecialchars($preview['contact']) ?></td></tr>
                                    <tr><th class="text-muted small">Email</th><td><?= htmlspecialchars($preview['email']) ?></td></tr>
                                </table>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="save_record" class="btn btn-green flex-fill">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>Save
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary flex-fill" onclick="window.scrollTo({top:0,behavior:'smooth'})">
                                        <i class="fa-solid fa-pen me-1"></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger flex-fill" onclick="if(confirm('Clear this record?')) location.href='addrecord.php'">
                                        <i class="fa-solid fa-trash me-1"></i>Discard
                                    </button>
                                </div>
                            </form>

                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fa-solid fa-file-pen fa-3x mb-3 opacity-25"></i>
                                <p>Fill out the form and click<br><strong>Evaluate Growth & Preview</strong> to see the results here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-section mt-4" id="footer">
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
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ duration: 700, once: true });</script>

    <!-- Student Search and Autofill Script -->
    <script>
        const studentSuggestions = <?= $studentSuggestionsJson ?>;
        const growthRanges = document.querySelector('.growth-ranges');
        const growthRangesTrigger = growthRanges ? growthRanges.querySelector('.growth-ranges-trigger') : null;
        if (growthRanges && growthRangesTrigger) {
            growthRanges.addEventListener('toggle', () => {
                growthRangesTrigger.setAttribute('aria-expanded', growthRanges.open ? 'true' : 'false');
            });
        }
        const growthRangesTableWrapper = document.querySelector('.growth-ranges-table-wrapper');
        if (growthRangesTableWrapper) {
            const updateGrowthRangesScrollHint = () => {
                const hasMoreContent = growthRangesTableWrapper.scrollWidth > growthRangesTableWrapper.clientWidth + 1;
                const atEnd = growthRangesTableWrapper.scrollLeft + growthRangesTableWrapper.clientWidth >= growthRangesTableWrapper.scrollWidth - 1;
                growthRangesTableWrapper.classList.toggle('has-more-content', hasMoreContent);
                growthRangesTableWrapper.classList.toggle('at-end', atEnd);
            };
            growthRangesTableWrapper.addEventListener('scroll', updateGrowthRangesScrollHint, { passive: true });
            window.addEventListener('resize', updateGrowthRangesScrollHint);
            if (growthRanges) growthRanges.addEventListener('toggle', updateGrowthRangesScrollHint);
            updateGrowthRangesScrollHint();
        }

        const searchInput = document.getElementById('student-search');
        const suggestionsBox = document.getElementById('student-suggestions');

        function setFieldValue(name, value) {
            const field = document.querySelector('[name="' + name + '"]');
            if (field) field.value = value ?? '';
        }

        function titleCaseName(value) {
            const normalized = String(value || '')
                .replace(/[^ 00-\p{L}\p{N}\s.'-]/gu, '')
                .replace(/\s+/g, ' ');

            if (!normalized) return '';

            return normalized
                .split(' ')
                .map(part => {
                    if (part === '') return '';
                    if (part.length <= 1) return part.toUpperCase();
                    return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
                })
                .join(' ');
        }

        function capitaliseStudentFields() {
            const first = document.querySelector('[name="fname"]');
            const last = document.querySelector('[name="lname"]');
            const mid = document.querySelector('[name="m_initial"]');
            if (first) first.addEventListener('input', () => first.value = titleCaseName(first.value));
            if (last) last.addEventListener('input', () => last.value = titleCaseName(last.value));
            if (mid) mid.addEventListener('input', () => mid.value = mid.value.replace(/\s+/g, '').slice(0, 2).toUpperCase());
        }

        capitaliseStudentFields();

        function applySelectedStudent(student) {
            const gradeSelect = document.querySelector('select[name="grade_level"]');
            const schoolYearSelect = document.querySelector('select[name="school_year"]');
            const genderSelect = document.querySelector('select[name="gender"]');

            setFieldValue('fname', student.std_first_name || '');
            setFieldValue('lname', student.std_last_name || '');
            setFieldValue('m_initial', student.std_mid_initial || '');
            setFieldValue('birthday', student.date_of_birth || '');
            setFieldValue('section', student.section || '');
            if (genderSelect) genderSelect.value = student.gender || 'Male';
            if (gradeSelect && student.grade_level) gradeSelect.value = student.grade_level;
            if (schoolYearSelect && student.school_year) schoolYearSelect.value = student.school_year;
            setFieldValue('p_name', student.guardian_name || '');
            setFieldValue('number', student.guardian_number || '');
            setFieldValue('email', student.guardian_email || '');
            setFieldValue('height', '');
            setFieldValue('weight', '');
        }

        function renderSuggestions(list) {
            if (!suggestionsBox || !searchInput) return;

            suggestionsBox.innerHTML = '';
            if (!list.length) {
                suggestionsBox.style.display = 'none';
                return;
            }

            list.forEach(student => {
                const label = [student.std_first_name, student.std_mid_initial, student.std_last_name].filter(Boolean).join(' ');
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = label + (student.grade_level ? ' • ' + student.grade_level : '') + (student.section ? ' • ' + student.section : '');
                item.addEventListener('click', () => {
                    applySelectedStudent(student);
                    searchInput.value = '';
                    suggestionsBox.style.display = 'none';
                });
                suggestionsBox.appendChild(item);
            });
            suggestionsBox.style.display = 'block';
        }

        if (searchInput && suggestionsBox) {
            searchInput.addEventListener('input', function() {
                const q = this.value.trim().toLowerCase();
                if (!q) {
                    suggestionsBox.style.display = 'none';
                    return;
                }
                const matches = studentSuggestions.filter(student => {
                    const fullName = [student.std_first_name, student.std_mid_initial, student.std_last_name].join(' ').toLowerCase();
                    return fullName.includes(q) || (student.guardian_name || '').toLowerCase().includes(q) || (student.grade_level || '').toLowerCase().includes(q);
                }).slice(0, 8);
                renderSuggestions(matches);
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                    suggestionsBox.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
