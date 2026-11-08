<?php
require_once __DIR__ . '/auth.php';
secureSessionStart();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/signin.php");
    exit();
}

if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDB();
if ($conn->connect_error) { die("Connection failed"); }

$filename = 'NutriPh_Feeding_Program_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Sheet 1: Enrolled underweight students
$enrolled = $conn->query("
    SELECT s.* FROM stdRecord s
    INNER JOIN (
        SELECT std_first_name, std_mid_initial, std_last_name, MAX(id) as latest_id
        FROM stdRecord GROUP BY std_first_name, std_mid_initial, std_last_name
    ) lr ON s.id = lr.latest_id
    WHERE s.classification IN ('Severe Underweight','Underweight')
    ORDER BY s.std_last_name, s.std_first_name
");

// Sheet data: Feeding history
$feedings = $conn->query("
    SELECT f.*, s.std_first_name, s.std_mid_initial, s.std_last_name, s.bmi
    FROM feedingRecord f
    JOIN stdRecord s ON f.student_id = s.student_id
    ORDER BY f.feed_date DESC, f.feed_time DESC, f.created_at DESC
");

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="UTF-8"></head><body>';

// Table 1: Enrolled Students
echo '<h3>Feeding Program – Enrolled Students (Thinness)</h3>';
echo '<table border="1">';
echo '<tr style="background-color:#c0392b;color:#fff;font-weight:bold;">';
echo '<td>No.</td><td>Last Name</td><td>First Name</td><td>M.I.</td><td>Gender</td>';
echo '<td>BMI</td><td>Weight</td><td>Weight Unit</td><td>Guardian Name</td>';
echo '<td>Guardian Number</td><td>Guardian Email</td>';
echo '</tr>';

$n = 1;
while ($row = $enrolled->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . $n++ . '</td>';
    echo '<td>' . htmlspecialchars($row['std_last_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['std_first_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['std_mid_initial']) . '</td>';
    echo '<td>' . htmlspecialchars($row['gender']) . '</td>';
    echo '<td>' . $row['bmi'] . '</td>';
    echo '<td>' . $row['weight'] . '</td>';
    echo '<td>' . htmlspecialchars($row['weight_unit']) . '</td>';
    echo '<td>' . htmlspecialchars($row['guardian_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['guardian_number']) . '</td>';
    echo '<td>' . htmlspecialchars($row['guardian_email']) . '</td>';
    echo '</tr>';
}
echo '</table><br><br>';

// Table 2: Feeding History
echo '<h3>Feeding History Log</h3>';
echo '<table border="1">';
echo '<tr style="background-color:#3d6b0f;color:#fff;font-weight:bold;">';
echo '<td>No.</td><td>Student Name</td><td>BMI</td><td>Feed Date</td><td>Feed Time</td>';
echo '<td>Meal Type</td><td>Notes</td><td>Recorded By</td>';
echo '</tr>';

$n = 1;
while ($f = $feedings->fetch_assoc()) {
    $name = $f['std_first_name'] . ' ' . $f['std_mid_initial'] . ' ' . $f['std_last_name'];
    $feedTimeDisplay = (!empty($f['feed_time']) && $f['feed_time'] !== '00:00:00') ? date('h:i A', strtotime($f['feed_time'])) : '—';
    echo '<tr>';
    echo '<td>' . $n++ . '</td>';
    echo '<td>' . htmlspecialchars($name) . '</td>';
    echo '<td>' . $f['bmi'] . '</td>';
    echo '<td>' . date('M d, Y', strtotime($f['feed_date'])) . '</td>';
    echo '<td>' . htmlspecialchars($feedTimeDisplay) . '</td>';
    echo '<td>' . htmlspecialchars($f['meal_type']) . '</td>';
    echo '<td>' . htmlspecialchars($f['notes']) . '</td>';
    echo '<td>' . htmlspecialchars($f['recorded_by']) . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '</body></html>';
$conn->close();
?>
