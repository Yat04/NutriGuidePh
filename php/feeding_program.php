<?php
require_once __DIR__ . '/auth.php';
secureSessionStart();
checkAccess(['Admin', 'Super Admin']);

// Ensure timezone is set to Manila (PST / UTC+8)
date_default_timezone_set('Asia/Manila');

$conn = getDB();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";
$msg_type = "";
$today = date('Y-m-d');

// Ensure the schedule table exists and the record table has feed_time.
$conn->query("CREATE TABLE IF NOT EXISTS feedingSchedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_date DATE,
    meal_type VARCHAR(30),
    food_description TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS feedingRecord (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    feed_date DATE NOT NULL,
    feed_time TIME DEFAULT NULL,
    meal_type VARCHAR(30),
    notes TEXT,
    recorded_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$checkFeedTime = $conn->query("SHOW COLUMNS FROM feedingRecord LIKE 'feed_time'");
if ($checkFeedTime->num_rows === 0) {
    $conn->query("ALTER TABLE feedingRecord ADD COLUMN feed_time TIME DEFAULT NULL AFTER feed_date");
}

// ── Auto-correct historical records to ensure accurate meal types and time in Feeding History ──
// Fix records wrongly saved as Breakfast during Lunch hours (e.g., 12:53:10)
$conn->query("UPDATE feedingRecord SET meal_type = 'Lunch' WHERE meal_type = 'Breakfast' AND feed_time >= '12:00:00' AND feed_time <= '13:30:00'");
// Populate missing historical feed_time records with their proper time range
$conn->query("UPDATE feedingRecord SET feed_time = '19:00:00' WHERE (feed_time IS NULL OR feed_time = '00:00:00') AND meal_type = 'Dinner'");
$conn->query("UPDATE feedingRecord SET feed_time = '12:30:00' WHERE (feed_time IS NULL OR feed_time = '00:00:00') AND meal_type = 'Lunch'");
$conn->query("UPDATE feedingRecord SET feed_time = '07:30:00' WHERE (feed_time IS NULL OR feed_time = '00:00:00') AND meal_type = 'Breakfast'");
$conn->query("UPDATE feedingRecord SET feed_time = '10:30:00' WHERE (feed_time IS NULL OR feed_time = '00:00:00') AND meal_type = 'Morning Snack'");
$conn->query("UPDATE feedingRecord SET feed_time = '16:00:00' WHERE (feed_time IS NULL OR feed_time = '00:00:00') AND meal_type = 'Afternoon Snack'");
$conn->query("UPDATE feedingRecord SET feed_time = '22:30:00' WHERE (feed_time IS NULL OR feed_time = '00:00:00') AND meal_type = 'Midnight Snack'");

// Save or update the menu schedule for a chosen date.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    if (!isAdmin()) {
        $msg = "Only an administrator can manage the feeding schedule.";
        $msg_type = "danger";
    } else {
        $schedule_date = $_POST['schedule_date'] ?? $today;
        $meal_type = trim($_POST['meal_type'] ?? '');
        $food_description = trim($_POST['food_description'] ?? '');
        $created_by = $_SESSION['firstName'] ?? 'Super Admin';

        if ($schedule_date === '' || $meal_type === '' || $food_description === '') {
            $msg = "Please select a date, meal type, and menu items before saving.";
            $msg_type = "danger";
        } else {
            $existing = $conn->prepare("SELECT id FROM feedingSchedule WHERE schedule_date = ? ORDER BY created_at DESC LIMIT 1");
            $existing->bind_param('s', $schedule_date);
            $existing->execute();
            $existingResult = $existing->get_result();

            if ($existingResult->num_rows > 0) {
                $row = $existingResult->fetch_assoc();
                $stmt = $conn->prepare("UPDATE feedingSchedule SET meal_type = ?, food_description = ?, created_by = ? WHERE id = ?");
                $stmt->bind_param('sssi', $meal_type, $food_description, $created_by, $row['id']);
            } else {
                $stmt = $conn->prepare("INSERT INTO feedingSchedule (schedule_date, meal_type, food_description, created_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('ssss', $schedule_date, $meal_type, $food_description, $created_by);
            }

            if ($stmt->execute()) {
                $msg = "Feeding schedule saved successfully.";
                $msg_type = "success";
            } else {
                $msg = "Unable to save feeding schedule.";
                $msg_type = "danger";
            }

            $stmt->close();
            $existing->close();
        }
    }
}

// Log feed submission (supports both log_feed and quick_feed for backward compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['log_feed']) || isset($_POST['quick_feed']))) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $feed_date = trim($_POST['feed_date'] ?? '');
    if (!$feed_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $feed_date)) {
        $feed_date = date('Y-m-d');
    }

    $feed_time = trim($_POST['feed_time'] ?? '');
    if (!$feed_time) {
        $feed_time = date('H:i:s');
    }

    $meal_type = trim($_POST['meal_type'] ?? '');
    if ($meal_type === '') {
        $meal_type = 'Lunch';
    }

    $food_description = trim($_POST['food_description'] ?? '');
    if ($food_description === '') {
        // Fallback to active schedule food description if available
        $schedStmt = $conn->prepare("SELECT food_description FROM feedingSchedule WHERE schedule_date = ? ORDER BY created_at DESC LIMIT 1");
        $schedStmt->bind_param('s', $feed_date);
        $schedStmt->execute();
        $schedRes = $schedStmt->get_result();
        if ($schedRes && $schedRes->num_rows > 0) {
            $food_description = $schedRes->fetch_assoc()['food_description'];
        } else {
            $food_description = 'Standard School Feeding Meal';
        }
        $schedStmt->close();
    }

    $recorded_by = $_SESSION['firstName'] ?? ($_SESSION['user_id'] ?? 'Staff');

    if ($student_id <= 0) {
        $msg = "Student record not found.";
        $msg_type = "danger";
    } else {
        $insertStmt = $conn->prepare("INSERT INTO feedingRecord (student_id, feed_date, feed_time, meal_type, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param('isssss', $student_id, $feed_date, $feed_time, $meal_type, $food_description, $recorded_by);

        if ($insertStmt->execute()) {
            $displayTime = date('h:i:s A', strtotime($feed_time));
            $msg = "Feeding logged successfully for <strong>" . htmlspecialchars($meal_type) . "</strong> at <strong>" . htmlspecialchars($displayTime) . "</strong>.";
            $msg_type = "success";
        } else {
            $msg = "Could not save the feeding record.";
            $msg_type = "danger";
        }

        $insertStmt->close();
    }
}

// Current active menu.
$todaySchedule = $conn->query("SELECT * FROM feedingSchedule WHERE schedule_date = CURDATE() ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

// Get all underweight students (latest record per student)
$underweight_students = $conn->query("SELECT s.* FROM stdRecord s
    INNER JOIN (
        SELECT std_first_name, std_mid_initial, std_last_name, MAX(id) as latest_id
        FROM stdRecord GROUP BY std_first_name, std_mid_initial, std_last_name
    ) latest_rec ON s.id = latest_rec.latest_id
    WHERE s.classification IN ('Severe Underweight', 'Underweight')
    ORDER BY s.std_last_name, s.std_first_name");

// Get recent feeding records
$feeding_records = $conn->query("SELECT f.*, s.std_first_name, s.std_mid_initial, s.std_last_name, s.bmi, s.classification
    FROM feedingRecord f
    JOIN stdRecord s ON f.student_id = s.student_id
    ORDER BY f.feed_date DESC, f.feed_time DESC, f.created_at DESC
    LIMIT 50");

$total_enrolled = $underweight_students->num_rows;
$total_feedings = $conn->query("SELECT COUNT(*) as c FROM feedingRecord")->fetch_assoc()['c'];
$today_feedings = $conn->query("SELECT COUNT(*) as c FROM feedingRecord WHERE feed_date = CURDATE()")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Feeding Program</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <style>
        .feeding-history-scroll {
            max-height: 450px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
            scroll-behavior: smooth;
        }
        .feeding-history-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .feeding-history-scroll::-webkit-scrollbar-track {
            background: #f1f5eb;
            border-radius: 6px;
        }
        .feeding-history-scroll::-webkit-scrollbar-thumb {
            background: #a3c978;
            border-radius: 6px;
        }
        .feeding-history-scroll::-webkit-scrollbar-thumb:hover {
            background: #3d6b0f;
        }
        .feeding-history-item {
            transition: background-color 0.2s ease, opacity 0.25s ease, transform 0.25s ease;
        }
        .feeding-history-item:hover {
            background-color: #f1f4f0 !important;
        }
        .feeding-history-item.selected {
            background-color: #f1f8ea !important;
            border-left-color: #3d6b0f !important;
            box-shadow: 0 1px 3px rgba(61, 107, 15, 0.15);
        }
        .feed-select-checkbox {
            cursor: pointer;
            width: 1.05rem;
            height: 1.05rem;
        }
        .feed-select-checkbox:checked,
        #selectAllHistory:checked {
            background-color: #3d6b0f;
            border-color: #3d6b0f;
        }
    </style>
</head>
<body class="page-bg">

    <!-- Navbar -->
    <?php $activePage = 'feeding'; include 'navbar.php'; ?>

    <!-- Content -->
    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">

        <!-- Header -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2" data-aos="fade-down">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-utensils me-2"></i>Feeding Program</h4>
                <p class="text-white-50 small mb-0">Only <b>Underweight</b> students are enrolled. Other classifications are not included.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if (isAdmin()): ?>
                    <button class="btn btn-outline-light btn-sm px-3" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="fa-solid fa-calendar-check me-2"></i>Set Menu
                    </button>
                <?php endif; ?>
                <a href="export_feeding.php" class="btn btn-outline-light btn-sm px-3">
                    <i class="fa-solid fa-download me-2"></i>Download Excel
                </a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show small py-2" data-aos="fade-down">
                <i class="fa-solid fa-<?= $msg_type === 'success' ? 'circle-check' : ($msg_type === 'warning' ? 'triangle-exclamation' : 'circle-exclamation') ?> me-1"></i>
                <?= $msg ?>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($todaySchedule): ?>
            <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4" data-aos="fade-up">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid fa-utensils text-success"></i>
                    <strong class="text-success">Today's Active Menu</strong>
                </div>
                <div class="small text-success-emphasis">
                    <span class="fw-semibold"><?= htmlspecialchars($todaySchedule['meal_type']) ?></span> —
                    <?= htmlspecialchars($todaySchedule['food_description']) ?>
                </div>
            </div>
        <?php elseif (isAdmin()): ?>
            <div class="alert alert-warning border-0 rounded-4 shadow-sm mb-4" data-aos="fade-up">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                    <span class="small fw-semibold text-warning-emphasis">No feeding menu is set for today yet. Use “Set Menu” to create it.</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-2 g-lg-3 mb-4">
            <div class="col-6 col-lg-4" data-aos="fade-up">
                <div class="card stat-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="stat-icon" style="background-color:#fdecea;color:#c0392b;">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div>
                            <div class="stat-number" style="color:#c0392b;"><?= $total_enrolled ?></div>
                            <div class="text-muted small fw-semibold">Enrolled Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4" data-aos="fade-up" data-aos-delay="50">
                <div class="card stat-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="stat-icon bg-success-subtle text-success">
                            <i class="fa-solid fa-bowl-food"></i>
                        </div>
                        <div>
                            <div class="stat-number text-success"><?= $total_feedings ?></div>
                            <div class="text-muted small fw-semibold">Total Feedings</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card stat-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="stat-icon bg-warning-subtle text-warning">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                        <div>
                            <div class="stat-number text-warning"><?= $today_feedings ?></div>
                            <div class="text-muted small fw-semibold">Fed Today</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-lg-4">

            <!-- Enrolled Students -->
            <div class="col-lg-7" data-aos="fade-up">
                <div class="card table-card shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-clipboard-list me-2"></i>Enrolled Students (Underweight Only)</h6>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small fw-semibold">Name</th>
                                        <th class="small fw-semibold">Gender</th>
                                        <th class="small fw-semibold">BMI</th>
                                        <th class="small fw-semibold">Weight</th>
                                        <th class="small fw-semibold">Guardian</th>
                                        <th class="small fw-semibold text-center">Feed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($underweight_students->num_rows > 0): ?>
                                        <?php while ($s = $underweight_students->fetch_assoc()): ?>
                                        <tr>
                                            <td class="small fw-semibold">
                                                <?= htmlspecialchars($s['std_first_name'] . ' ' . $s['std_mid_initial'] . ' ' . $s['std_last_name']) ?>
                                                <i class="fa-solid fa-triangle-exclamation ms-1 text-danger" style="font-size:0.65rem;" title="Underweight"></i>
                                            </td>
                                            <td class="small"><?= htmlspecialchars($s['gender']) ?></td>
                                            <td class="small"><span class="badge rounded-pill badge-underweight"><?= $s['bmi'] ?></span></td>
                                            <td class="small"><?= htmlspecialchars($s['weight'] . ' ' . $s['weight_unit']) ?></td>
                                            <td class="small"><?= htmlspecialchars($s['guardian_name']) ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-green py-0 px-2 log-feed-btn"
                                                    data-id="<?= $s['student_id'] ?>"
                                                    data-name="<?= htmlspecialchars(formatStudentFullName($s['std_first_name'] ?? '', $s['std_mid_initial'] ?? '', $s['std_last_name'] ?? '')) ?>"
                                                    title="Log Feeding for <?= htmlspecialchars($s['std_first_name']) ?>">
                                                    <i class="fa-solid fa-plus" style="font-size:0.7rem;"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-face-smile fa-2x mb-2 opacity-25 d-block"></i>
                                                No underweight students found. All students are healthy!
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feeding History -->
            <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
                <div class="card table-card shadow-sm">
                    <div class="card-body p-4">
                        <!-- Header & Display Controls -->
                        <div class="d-flex flex-column gap-2 mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="fw-bold text-success mb-0">
                                        <i class="fa-solid fa-clock-rotate-left me-1"></i>Feeding History
                                    </h6>
                                    <span class="badge rounded-pill bg-success-subtle text-success small" id="historyCounter" title="Visible entries">
                                        <?= $feeding_records->num_rows ?>
                                    </span>
                                </div>

                                <!-- Select All / Deselect All Toggle -->
                                <?php if ($feeding_records->num_rows > 0): ?>
                                <div class="form-check m-0 d-flex align-items-center gap-1">
                                    <input class="form-check-input m-0" type="checkbox" id="selectAllHistory" title="Select / Deselect all visible entries">
                                    <label class="form-check-label text-muted small user-select-none" for="selectAllHistory" style="font-size:0.75rem;cursor:pointer;">
                                        Select All
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Client-Side UI Action Bar (Database Safe) -->
                            <?php if ($feeding_records->num_rows > 0): ?>
                            <div class="d-flex align-items-center justify-content-between gap-2 pt-1 border-top" id="historyActionBar">
                                <div class="small text-muted d-flex align-items-center" style="font-size:0.7rem;">
                                    <i class="fa-solid fa-shield-halved text-success me-1"></i>
                                    <span>Display only (DB safe)</span>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill" id="btnClearSelected" title="Remove checked entries from view only" style="font-size:0.7rem;display:none;">
                                        <i class="fa-solid fa-eye-slash me-1"></i>Clear Selected (<span id="selectedCount">0</span>)
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill" id="btnClearAll" title="Clear all entries from screen view only" style="font-size:0.7rem;">
                                        <i class="fa-solid fa-broom me-1"></i>Clear All
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 rounded-pill" id="btnRestoreAll" title="Restore all entries back to view" style="font-size:0.7rem;display:none;">
                                        <i class="fa-solid fa-rotate-left me-1"></i>Restore
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($feeding_records->num_rows > 0): ?>
                            <!-- Scrollable Container -->
                            <div class="feeding-history-scroll" id="feedingHistoryContainer">
                            <?php while ($f = $feeding_records->fetch_assoc()):
                                $feedingName = formatStudentFullName($f['std_first_name'] ?? '', $f['std_mid_initial'] ?? '', $f['std_last_name'] ?? '');
                                $feedTimeDisplay = (!empty($f['feed_time']) && $f['feed_time'] !== '00:00:00') ? date('h:i:s A', strtotime($f['feed_time'])) : '—';
                            ?>
                                <div class="d-flex align-items-start gap-2 mb-2 p-2 rounded-3 feeding-history-item" data-id="<?= $f['id'] ?>" style="background:#f8f9fa;border-left:3px solid #78bc27;">
                                    <!-- Individual Selection Checkbox -->
                                    <div class="pt-1 pe-1">
                                        <input class="form-check-input feed-select-checkbox m-0" type="checkbox" value="<?= $f['id'] ?>" title="Select to clear from screen">
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small fw-semibold"><?= htmlspecialchars($feedingName) ?></div>
                                        <div class="text-muted" style="font-size:0.75rem;">
                                            <i class="fa-solid fa-calendar me-1"></i><?= date('M d, Y', strtotime($f['feed_date'])) ?>
                                            <span class="mx-1">·</span>
                                            <i class="fa-solid fa-clock me-1 text-success"></i><?= htmlspecialchars($feedTimeDisplay) ?>
                                            <span class="mx-1">·</span>
                                            <i class="fa-solid fa-utensils me-1 text-success"></i><span class="fw-semibold text-dark"><?= htmlspecialchars($f['meal_type']) ?></span>
                                            <?php if ($f['notes']): ?>
                                                <span class="mx-1">·</span>
                                                <?= htmlspecialchars($f['notes']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted" style="font-size:0.7rem;">By: <?= htmlspecialchars($f['recorded_by']) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            </div>

                            <!-- Empty View State (Displayed when all items are hidden from screen) -->
                            <div id="historyClearedState" class="text-center text-muted py-4 rounded-3 border border-dashed mt-2" style="display:none;background:#fafcf8;">
                                <i class="fa-solid fa-eye-slash fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                <p class="small mb-1 fw-semibold text-secondary">All entries hidden from screen view.</p>
                                <p class="text-muted mb-2" style="font-size:0.72rem;">Database records remain safe and untouched. Parents can still view the feeding logs.</p>
                                <button type="button" class="btn btn-sm btn-green px-3 py-1" id="btnRestoreFromEmpty">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Restore Feeding History View
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fa-solid fa-bowl-food fa-2x mb-2 opacity-25 d-block"></i>
                                <p class="small">No feeding records yet. Use the <b>+</b> button to log a feeding.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php if (isAdmin()): ?>
    <!-- Set Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">
                    <h6 class="modal-title text-white fw-bold"><i class="fa-solid fa-calendar-check me-2"></i>Set Feeding Schedule</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST">
                        <input type="hidden" name="save_schedule" value="1">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Date</label>
                            <input type="date" class="form-control form-control-sm" name="schedule_date" value="<?= htmlspecialchars($today) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Meal Type</label>
                            <select class="form-select form-select-sm" name="meal_type" required>
                                <option value="Breakfast" <?= ($todaySchedule['meal_type'] ?? '') === 'Breakfast' ? 'selected' : '' ?>>Breakfast</option>
                                <option value="Morning Snack" <?= ($todaySchedule['meal_type'] ?? '') === 'Morning Snack' ? 'selected' : '' ?>>Morning Snack</option>
                                <option value="Lunch" <?= ($todaySchedule['meal_type'] ?? '') === 'Lunch' ? 'selected' : '' ?>>Lunch</option>
                                <option value="Afternoon Snack" <?= ($todaySchedule['meal_type'] ?? '') === 'Afternoon Snack' ? 'selected' : '' ?>>Afternoon Snack</option>
                                <option value="Dinner" <?= ($todaySchedule['meal_type'] ?? '') === 'Dinner' ? 'selected' : '' ?>>Dinner</option>
                                <option value="Midnight Snack" <?= ($todaySchedule['meal_type'] ?? '') === 'Midnight Snack' ? 'selected' : '' ?>>Midnight Snack</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Food Items / Description</label>
                            <textarea class="form-control form-control-sm" name="food_description" rows="4" placeholder="e.g. Rice, chicken, vegetable soup" required><?= htmlspecialchars($todaySchedule['food_description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-green w-100 py-2">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Save Menu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Log Feeding Modal with Date, Time, Auto-Category, & Food Fields -->
    <div class="modal fade" id="feedModal" tabindex="-1" aria-labelledby="feedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow overflow-hidden">
                <div class="modal-header border-0 py-3 px-4 text-white" style="background: linear-gradient(135deg, #3d6b0f 0%, #2e530b 100%);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center shadow-sm" style="width:34px;height:34px;">
                            <i class="fa-solid fa-bowl-food" style="color:#3d6b0f;"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-white mb-0" id="feedModalLabel">Log Student Feeding</h6>
                            <small class="text-white-50" style="font-size:0.75rem;">NutriGuide PH Feeding Program</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <form method="POST" id="feedRecordForm">
                        <input type="hidden" name="log_feed" value="1">
                        <input type="hidden" name="student_id" id="feed_student_id">

                        <!-- Student Information (Read-only) -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted d-flex align-items-center justify-content-between">
                                <span><i class="fa-solid fa-user me-1 text-success"></i>Student Name</span>
                                <span class="badge bg-danger-subtle text-danger" style="font-size:0.7rem;">Underweight</span>
                            </label>
                            <input type="text" class="form-control form-control-sm fw-bold text-dark" id="feed_student_name" readonly style="background:#f8f9fa;">
                        </div>

                        <!-- Date and Time Row -->
                        <div class="row g-2 mb-3">
                            <!-- 1. Auto-populated Date (Local Timezone) -->
                            <div class="col-6">
                                <label for="feed_date" class="form-label small fw-semibold text-muted">
                                    <i class="fa-solid fa-calendar-day me-1 text-success"></i>Date
                                </label>
                                <input type="date" class="form-control form-control-sm" id="feed_date" name="feed_date" required>
                                <div class="text-muted mt-1 fw-semibold" id="feed_date_display" style="font-size:0.72rem;"></div>
                            </div>

                            <!-- 2. Auto-populated Time (Exact Button Click Time) -->
                            <div class="col-6">
                                <label for="feed_time" class="form-label small fw-semibold text-muted">
                                    <i class="fa-solid fa-clock me-1 text-success"></i>Time
                                </label>
                                <input type="time" step="1" class="form-control form-control-sm font-monospace" id="feed_time" name="feed_time" required>
                                <div class="text-muted mt-1 fw-semibold" id="feed_time_display" style="font-size:0.72rem;"></div>
                            </div>
                        </div>

                        <!-- 3. Auto-detected Category -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="feed_meal_type" class="form-label small fw-semibold text-muted mb-0">
                                    <i class="fa-solid fa-tag me-1 text-success"></i>Meal Category
                                </label>
                                <span class="badge rounded-pill bg-success-subtle text-success px-2 py-1" id="feed_category_badge" style="font-size:0.7rem;">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-detected
                                </span>
                            </div>
                            <select class="form-select form-select-sm" id="feed_meal_type" name="meal_type" required>
                                <option value="Breakfast">Breakfast (6:30 AM – 8:30 AM)</option>
                                <option value="Morning Snack">Morning Snack (10:00 AM – 11:00 AM)</option>
                                <option value="Lunch">Lunch (12:00 PM – 1:30 PM)</option>
                                <option value="Afternoon Snack">Afternoon Snack (3:30 PM – 4:30 PM)</option>
                                <option value="Dinner">Dinner (6:30 PM – 8:00 PM)</option>
                                <option value="Midnight Snack">Midnight Snack (10:00 PM – 11:30 PM / Late Night)</option>
                            </select>
                            <div class="form-text text-muted" id="feed_category_hint" style="font-size:0.72rem;">
                                Pre-selected based on captured time. You can manually change it anytime.
                            </div>
                        </div>

                        <!-- 4. Food Input Field -->
                        <div class="mb-3">
                            <label for="feed_food_description" class="form-label small fw-semibold text-muted">
                                <i class="fa-solid fa-bowl-rice me-1 text-success"></i>Food Item(s) Consumed
                            </label>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="feed_food_description"
                                   name="food_description"
                                   list="foodDatalist"
                                   placeholder="e.g. Rice, vegetable soup, banana, milk"
                                   required>
                            <datalist id="foodDatalist">
                                <option value="Rice, Chicken Adobo, and Banana">
                                <option value="Rice, Monggo Soup with Malunggay, and Papaya">
                                <option value="Boiled Egg, Fortified Milk, and Apple">
                                <option value="Arroz Caldo (Chicken Porridge) with Boiled Egg">
                                <option value="Champorado with Milk">
                                <option value="Rice, Sinigang na Bangus, and Orange">
                                <option value="Pandesal, Peanut Butter, and Milk">
                                <option value="Apple, Banana, Grapes, and Bread">
                            </datalist>

                            <!-- Scheduled menu helper pill if active -->
                            <?php if ($todaySchedule && !empty($todaySchedule['food_description'])): ?>
                                <div class="mt-2 p-2 rounded-2 border" style="background:#f4f9ed;font-size:0.75rem;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-success fw-semibold">
                                            <i class="fa-solid fa-clipboard-check me-1"></i>Today's Menu (<?= htmlspecialchars($todaySchedule['meal_type']) ?>):
                                        </span>
                                        <button type="button" class="btn btn-xs btn-outline-success py-0 px-2" id="btnUseScheduledMenu" style="font-size:0.7rem;">
                                            Use this menu
                                        </button>
                                    </div>
                                    <div class="text-muted mt-1" id="scheduledMenuText"><?= htmlspecialchars($todaySchedule['food_description']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 pt-2">
                            <button type="button" class="btn btn-light btn-sm w-50 py-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-green btn-sm w-50 py-2">
                                <i class="fa-solid fa-check me-1"></i>Confirm &amp; Log
                            </button>
                        </div>
                    </form>
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
    <script>
        AOS.init({ duration: 700, once: true });

        // Store active menu data from PHP
        window.NUTRIGUIDE_ACTIVE_MENU = <?= json_encode($todaySchedule ?: null) ?>;

        /**
         * Auto-detects the meal category according to the following ranges:
         * - Breakfast: 6:30 AM – 8:30 AM (including Morning Snack: 10:00 AM – 11:00 AM if applicable)
         * - Lunch: 12:00 PM – 1:30 PM
         * - Afternoon Snack: 3:30 PM – 4:30 PM
         * - Dinner: 6:30 PM – 8:00 PM
         * - Midnight Snack: 10:00 PM – 11:30 PM (and other late-night hours)
         *
         * @param {number} hours - 0 to 23
         * @param {number} minutes - 0 to 59
         * @returns {string} The detected meal category name
         */
        function detectMealCategory(hours, minutes) {
            const totalMinutes = hours * 60 + minutes;

            // 1. Breakfast: 6:30 AM (390m) – 8:30 AM (510m)
            if (totalMinutes >= 390 && totalMinutes <= 510) {
                return 'Breakfast';
            }

            // Morning Snack: 10:00 AM (600m) – 11:00 AM (660m)
            if (totalMinutes >= 600 && totalMinutes <= 660) {
                return 'Morning Snack';
            }

            // 2. Lunch: 12:00 PM (720m) – 1:30 PM (810m)
            if (totalMinutes >= 720 && totalMinutes <= 810) {
                return 'Lunch';
            }

            // 3. Afternoon Snack: 3:30 PM (930m) – 4:30 PM (990m)
            if (totalMinutes >= 930 && totalMinutes <= 990) {
                return 'Afternoon Snack';
            }

            // 4. Dinner: 6:30 PM (1110m) – 8:00 PM (1200m)
            if (totalMinutes >= 1110 && totalMinutes <= 1200) {
                return 'Dinner';
            }

            // 5. Midnight Snack: 10:00 PM (1320m) – 11:30 PM (1410m) and other late-night/early hours
            if ((totalMinutes >= 1320 && totalMinutes <= 1440) || totalMinutes < 390) {
                return 'Midnight Snack';
            }

            // Intermediate transition hours (mapping intelligently to the closest meal)
            if (totalMinutes > 510 && totalMinutes < 600) {        // 8:31 AM - 9:59 AM
                return totalMinutes <= 555 ? 'Breakfast' : 'Morning Snack';
            }
            if (totalMinutes > 660 && totalMinutes < 720) {        // 11:01 AM - 11:59 AM
                return totalMinutes <= 690 ? 'Morning Snack' : 'Lunch';
            }
            if (totalMinutes > 810 && totalMinutes < 930) {        // 1:31 PM - 3:29 PM
                return totalMinutes <= 870 ? 'Lunch' : 'Afternoon Snack';
            }
            if (totalMinutes > 990 && totalMinutes < 1110) {       // 4:31 PM - 6:29 PM
                return totalMinutes <= 1050 ? 'Afternoon Snack' : 'Dinner';
            }
            if (totalMinutes > 1200 && totalMinutes < 1320) {      // 8:01 PM - 9:59 PM
                return totalMinutes <= 1260 ? 'Dinner' : 'Midnight Snack';
            }

            return 'Breakfast';
        }

        /**
         * Formats a Date object into readable local date (e.g., "November 3, 2026")
         */
        function formatReadableDate(d) {
            const months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            return `${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
        }

        /**
         * Formats 24h hours, minutes, seconds into readable 12-hour AM/PM string (e.g., "01:04:15 PM")
         */
        function formatReadableTime(hours, minutes, seconds = 0) {
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const h12 = hours % 12 || 12;
            return `${String(h12).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} ${ampm}`;
        }

        /**
         * Populates and opens the Feeding Modal when a student's (+) button is clicked
         */
        function openFeedingModal(studentId, studentName) {
            const now = new Date();

            // 1. Auto-populated Date: Set in local timezone (YYYY-MM-DD)
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const localDateYmd = `${year}-${month}-${day}`;

            const dateInput = document.getElementById('feed_date');
            const dateDisplay = document.getElementById('feed_date_display');
            if (dateInput) dateInput.value = localDateYmd;
            if (dateDisplay) dateDisplay.textContent = formatReadableDate(now);

            // 2. Auto-populated Time: Exact time button was clicked (HH:mm:ss)
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            const timeHms = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

            const timeInput = document.getElementById('feed_time');
            const timeDisplay = document.getElementById('feed_time_display');
            if (timeInput) timeInput.value = timeHms;
            if (timeDisplay) timeDisplay.textContent = formatReadableTime(hours, minutes, seconds);

            // 3. Auto-detected Category: Based on captured time
            const detectedCategory = detectMealCategory(hours, minutes);
            const categorySelect = document.getElementById('feed_meal_type');
            const categoryBadge = document.getElementById('feed_category_badge');
            const categoryHint = document.getElementById('feed_category_hint');

            if (categorySelect) {
                categorySelect.dataset.manualOverride = ''; // reset manual flag
                categorySelect.value = detectedCategory;
            }
            if (categoryBadge) {
                categoryBadge.className = 'badge rounded-pill bg-success-subtle text-success px-2 py-1';
                categoryBadge.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-detected';
            }
            if (categoryHint) {
                categoryHint.textContent = `Pre-selected ${detectedCategory} based on captured time (${formatReadableTime(hours, minutes, seconds)}).`;
            }

            // Set Student Details
            document.getElementById('feed_student_id').value = studentId;
            document.getElementById('feed_student_name').value = studentName;

            // 4. Food Input Field: Pre-fill if today's menu matches category, or leave ready for input
            const foodInput = document.getElementById('feed_food_description');
            const activeMenu = window.NUTRIGUIDE_ACTIVE_MENU;
            if (foodInput) {
                if (activeMenu && activeMenu.food_description) {
                    foodInput.value = activeMenu.food_description;
                } else {
                    foodInput.value = '';
                }
            }

            // Show the modal
            const feedModalEl = document.getElementById('feedModal');
            const modalInstance = bootstrap.Modal.getInstance(feedModalEl) || new bootstrap.Modal(feedModalEl);
            modalInstance.show();
        }

        // Attach event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Attach click handler to all plus (+) buttons in the Enrolled Students table
            document.querySelectorAll('.log-feed-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    openFeedingModal(this.dataset.id, this.dataset.name);
                });
            });

            // Date input change event
            const dateInput = document.getElementById('feed_date');
            const dateDisplay = document.getElementById('feed_date_display');
            if (dateInput && dateDisplay) {
                dateInput.addEventListener('change', function() {
                    if (!this.value) return;
                    const parts = this.value.split('-');
                    const picked = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
                    dateDisplay.textContent = formatReadableDate(picked);
                });
            }

            // Time input change event: update readable time & auto-adjust category if not manually overridden
            const timeInput = document.getElementById('feed_time');
            const timeDisplay = document.getElementById('feed_time_display');
            const categorySelect = document.getElementById('feed_meal_type');
            const categoryBadge = document.getElementById('feed_category_badge');
            const categoryHint = document.getElementById('feed_category_hint');

            if (timeInput) {
                const handleTimeChange = function() {
                    if (!this.value) return;
                    const parts = this.value.split(':');
                    const h = parseInt(parts[0], 10) || 0;
                    const m = parseInt(parts[1], 10) || 0;
                    const s = parseInt(parts[2], 10) || 0;
                    if (timeDisplay) timeDisplay.textContent = formatReadableTime(h, m, s);

                    // If user has not explicitly locked in a manual selection, re-detect
                    if (categorySelect && !categorySelect.dataset.manualOverride) {
                        const newCat = detectMealCategory(h, m);
                        categorySelect.value = newCat;
                        if (categoryBadge) {
                            categoryBadge.className = 'badge rounded-pill bg-success-subtle text-success px-2 py-1';
                            categoryBadge.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto-detected';
                        }
                        if (categoryHint) {
                            categoryHint.textContent = `Pre-selected ${newCat} based on updated time (${formatReadableTime(h, m, s)}).`;
                        }
                    }
                };
                timeInput.addEventListener('input', handleTimeChange);
                timeInput.addEventListener('change', handleTimeChange);
            }

            // Manual Category Change Event: let user manually change and remember
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    this.dataset.manualOverride = '1';
                    if (categoryBadge) {
                        categoryBadge.className = 'badge rounded-pill bg-secondary-subtle text-secondary px-2 py-1';
                        categoryBadge.innerHTML = '<i class="fa-solid fa-hand me-1"></i>Manual selection';
                    }
                    if (categoryHint) {
                        categoryHint.textContent = `Manually selected: ${this.value}.`;
                    }
                });
            }

            // "Use this menu" quick button
            const btnUseMenu = document.getElementById('btnUseScheduledMenu');
            if (btnUseMenu) {
                btnUseMenu.addEventListener('click', function() {
                    const scheduledText = document.getElementById('scheduledMenuText');
                    const foodInput = document.getElementById('feed_food_description');
                    if (scheduledText && foodInput) {
                        foodInput.value = scheduledText.textContent.trim();
                        foodInput.focus();
                    }
                });
            }

            // ── Feeding History Client-Side Display Controller (Strictly UI-Only / Database Safe) ──
            const feedingHistoryContainer = document.getElementById('feedingHistoryContainer');
            const selectAllHistory = document.getElementById('selectAllHistory');
            const btnClearSelected = document.getElementById('btnClearSelected');
            const btnClearAll = document.getElementById('btnClearAll');
            const btnRestoreAll = document.getElementById('btnRestoreAll');
            const btnRestoreFromEmpty = document.getElementById('btnRestoreFromEmpty');
            const selectedCountSpan = document.getElementById('selectedCount');
            const historyCounter = document.getElementById('historyCounter');
            const historyClearedState = document.getElementById('historyClearedState');

            function getVisibleItems() {
                return Array.from(document.querySelectorAll('.feeding-history-item')).filter(item => item.style.display !== 'none');
            }

            function getCheckedVisibleBoxes() {
                const checked = [];
                document.querySelectorAll('.feed-select-checkbox').forEach(cb => {
                    const item = cb.closest('.feeding-history-item');
                    if (item && item.style.display !== 'none' && cb.checked) {
                        checked.push(cb);
                    }
                });
                return checked;
            }

            function updateHistoryControls() {
                const visibleItems = getVisibleItems();
                const checkedBoxes = getCheckedVisibleBoxes();
                const totalVisible = visibleItems.length;
                const totalChecked = checkedBoxes.length;

                // Update visible count badge
                if (historyCounter) {
                    historyCounter.textContent = totalVisible;
                }

                // Update Clear Selected button & count
                if (btnClearSelected && selectedCountSpan) {
                    selectedCountSpan.textContent = totalChecked;
                    btnClearSelected.style.display = totalChecked > 0 ? 'inline-block' : 'none';
                }

                // Update Select All master checkbox state
                if (selectAllHistory) {
                    if (totalVisible === 0) {
                        selectAllHistory.checked = false;
                        selectAllHistory.indeterminate = false;
                        selectAllHistory.disabled = true;
                    } else {
                        selectAllHistory.disabled = false;
                        if (totalChecked === totalVisible) {
                            selectAllHistory.checked = true;
                            selectAllHistory.indeterminate = false;
                        } else if (totalChecked > 0) {
                            selectAllHistory.checked = false;
                            selectAllHistory.indeterminate = true;
                        } else {
                            selectAllHistory.checked = false;
                            selectAllHistory.indeterminate = false;
                        }
                    }
                }

                // Handle Empty / Restore View visibility
                const allItems = document.querySelectorAll('.feeding-history-item');
                if (allItems.length > 0) {
                    if (totalVisible === 0) {
                        if (feedingHistoryContainer) feedingHistoryContainer.style.display = 'none';
                        if (historyClearedState) historyClearedState.style.display = 'block';
                        if (btnRestoreAll) btnRestoreAll.style.display = 'inline-block';
                        if (btnClearAll) btnClearAll.style.display = 'none';
                    } else {
                        if (feedingHistoryContainer) feedingHistoryContainer.style.display = 'block';
                        if (historyClearedState) historyClearedState.style.display = 'none';
                        if (btnClearAll) btnClearAll.style.display = 'inline-block';
                        if (btnRestoreAll) {
                            btnRestoreAll.style.display = totalVisible < allItems.length ? 'inline-block' : 'none';
                        }
                    }
                }
            }

            // 1. Master Toggle: Select All / Deselect All visible items
            if (selectAllHistory) {
                selectAllHistory.addEventListener('change', function() {
                    const isChecked = this.checked;
                    getVisibleItems().forEach(item => {
                        const cb = item.querySelector('.feed-select-checkbox');
                        if (cb) {
                            cb.checked = isChecked;
                            if (isChecked) {
                                item.classList.add('selected');
                            } else {
                                item.classList.remove('selected');
                            }
                        }
                    });
                    updateHistoryControls();
                });
            }

            // 2. Individual Checkbox Selection & Visual Highlight
            document.querySelectorAll('.feed-select-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    const item = this.closest('.feeding-history-item');
                    if (item) {
                        if (this.checked) {
                            item.classList.add('selected');
                        } else {
                            item.classList.remove('selected');
                        }
                    }
                    updateHistoryControls();
                });
            });

            // 3. Clear Selected (Strictly Client-Side UI DOM Manipulation)
            if (btnClearSelected) {
                btnClearSelected.addEventListener('click', function() {
                    const checkedBoxes = getCheckedVisibleBoxes();
                    if (checkedBoxes.length === 0) return;

                    checkedBoxes.forEach(cb => {
                        const item = cb.closest('.feeding-history-item');
                        if (item) {
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(15px)';
                            setTimeout(() => {
                                item.style.display = 'none';
                                cb.checked = false;
                                item.classList.remove('selected');
                                updateHistoryControls();
                            }, 180);
                        }
                    });
                });
            }

            // 4. Clear All (Strictly Client-Side UI DOM Manipulation)
            if (btnClearAll) {
                btnClearAll.addEventListener('click', function() {
                    const items = getVisibleItems();
                    if (items.length === 0) return;

                    items.forEach(item => {
                        item.style.opacity = '0';
                        setTimeout(() => {
                            item.style.display = 'none';
                            const cb = item.querySelector('.feed-select-checkbox');
                            if (cb) cb.checked = false;
                            item.classList.remove('selected');
                            updateHistoryControls();
                        }, 150);
                    });
                });
            }

            // 5. Restore All Entries to Screen View (Strictly Client-Side UI)
            function restoreAllHistoryView() {
                document.querySelectorAll('.feeding-history-item').forEach(item => {
                    item.style.display = 'flex';
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'none';
                    }, 10);
                    const cb = item.querySelector('.feed-select-checkbox');
                    if (cb) cb.checked = false;
                    item.classList.remove('selected');
                });
                if (selectAllHistory) {
                    selectAllHistory.checked = false;
                    selectAllHistory.indeterminate = false;
                }
                updateHistoryControls();
            }

            if (btnRestoreAll) {
                btnRestoreAll.addEventListener('click', restoreAllHistoryView);
            }
            if (btnRestoreFromEmpty) {
                btnRestoreFromEmpty.addEventListener('click', restoreAllHistoryView);
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
