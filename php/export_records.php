<?php
require_once 'auth.php';
secureSessionStart();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/signin.php");
    exit();
}

require_once 'auth.php';
if (!isStaff()) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDB();
if ($conn->connect_error) { die("Connection failed"); }

$type = $_GET['type'] ?? 'all';
$scope = getAdviserScope($conn);

// Set headers for Excel download
$filename = 'NutriPh_Records_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Build query based on type
$where = ' WHERE 1=1';
if ($type === 'underweight')  $where .= " AND classification IN ('Severe Underweight','Underweight')";
elseif ($type === 'normal')   $where .= " AND classification='Normal Weight'";
elseif ($type === 'overweight') $where .= " AND classification='Overweight'";
elseif ($type === 'obese')    $where .= " AND classification='Obese'";
elseif ($type === 'malnourished') $where .= " AND classification IN ('Severe Underweight','Underweight','Overweight','Obese')";
$scopeParams = [];
$scopeTypes = '';
$where .= appendAdviserScope($scope, '', $scopeParams, $scopeTypes);

$stmt = $conn->prepare("SELECT * FROM stdRecord$where ORDER BY created_at DESC");
if (!empty($scopeParams)) $stmt->bind_param($scopeTypes, ...$scopeParams);
$stmt->execute();
$result = $stmt->get_result();

// Output as HTML table (Excel reads this natively)
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
echo '<x:Name>Student Records</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
echo '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
echo '<table border="1">';
echo '<tr style="background-color:#3d6b0f;color:#fff;font-weight:bold;">';
echo '<td>No.</td><td>Last Name</td><td>First Name</td><td>M.I.</td><td>Gender</td><td>Date of Birth</td>';
echo '<td>Height</td><td>Height Unit</td><td>Weight</td><td>Weight Unit</td>';
echo '<td>BMI</td><td>WHO BMI-for-age z-score</td><td>Height-for-age status</td><td>WHO height-for-age z-score</td><td>Classification</td><td>Guardian Name</td><td>Guardian Number</td>';
echo '<td>Guardian Email</td><td>Date Recorded</td>';
echo '</tr>';

$n = 1;
while ($row = $result->fetch_assoc()) {
    $heightAssessment = getRecordHeightForAge($row);
    echo '<tr>';
    echo '<td>' . $n++ . '</td>';
    echo '<td>' . htmlspecialchars($row['std_last_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['std_first_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['std_mid_initial']) . '</td>';
    echo '<td>' . htmlspecialchars($row['gender']) . '</td>';
    echo '<td>' . htmlspecialchars($row['date_of_birth'] ?? '') . '</td>';
    echo '<td>' . $row['height'] . '</td>';
    echo '<td>' . htmlspecialchars($row['height_unit']) . '</td>';
    echo '<td>' . $row['weight'] . '</td>';
    echo '<td>' . htmlspecialchars($row['weight_unit']) . '</td>';
    echo '<td>' . $row['bmi'] . '</td>';
    echo '<td>' . htmlspecialchars((string)($row['bmi_zscore'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars($heightAssessment['height_classification'] ?? 'Not assessed') . '</td>';
    echo '<td>' . htmlspecialchars((string)($heightAssessment['height_zscore'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars($row['classification']) . '</td>';
    echo '<td>' . htmlspecialchars($row['guardian_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['guardian_number']) . '</td>';
    echo '<td>' . htmlspecialchars($row['guardian_email']) . '</td>';
    echo '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</table></body></html>';

$conn->close();
?>
