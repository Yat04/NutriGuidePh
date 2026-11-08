<?php
require_once __DIR__ . '/auth.php';
secureSessionStart();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isStaff()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getDB();

$search  = trim($_GET['search'] ?? '');
$date    = trim($_GET['date'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = '';
$params = [];
$types = '';
$scope = getAdviserScope($conn);
$scopeParams = [];
$scopeTypes = '';
$scopeCondition = appendAdviserScope($scope, '', $scopeParams, $scopeTypes);
$recordCountParams = [];
$recordCountTypes = '';
$recordCountScope = appendAdviserScope($scope, 'r', $recordCountParams, $recordCountTypes);

if ($search !== '') {
    $like = "%$search%";
    $where .= " AND (s.std_first_name LIKE ? OR s.std_last_name LIKE ? OR s.classification LIKE ? OR s.guardian_name LIKE ? OR s.grade_level LIKE ? OR s.section LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    $types .= 'ssssss';
}

if ($date !== '') {
    $where .= " AND DATE(s.created_at) = ?";
    $params[] = $date;
    $types .= 's';
}

$totalRecords = 0;
$latestIds = "SELECT MAX(id) FROM stdRecord WHERE 1=1" . $scopeCondition . " GROUP BY std_first_name, std_mid_initial, std_last_name";
$countSql = "SELECT COUNT(*) as c FROM stdRecord s WHERE s.id IN ($latestIds)" . $where;
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($scopeParams) || !empty($params)) {
        $countParams = array_merge($scopeParams, $params);
        $countStmt->bind_param($scopeTypes . $types, ...$countParams);
    }
    $countStmt->execute();
    $cRes = $countStmt->get_result();
    $totalRecords = $cRes ? (int)($cRes->fetch_assoc()['c'] ?? 0) : 0;
    $countStmt->close();
}

$sql = "SELECT s.*,
    (SELECT COUNT(*) FROM stdRecord r
     WHERE r.std_first_name = s.std_first_name
             AND r.std_mid_initial <=> s.std_mid_initial
             AND r.std_last_name = s.std_last_name" . $recordCountScope . ") AS record_count
    FROM stdRecord s
    WHERE s.id IN ($latestIds)" . $where . " ORDER BY s.created_at DESC, s.id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$records = [];
if ($stmt) {
    if (!empty($recordCountParams) || !empty($scopeParams) || !empty($params)) {
        $bindParams = array_merge($recordCountParams, $scopeParams, $params, [$perPage, $offset]);
        $bindTypes = $recordCountTypes . $scopeTypes . $types . 'ii';
        $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $heightAssessment = getRecordHeightForAge($row);
            $row['height_zscore'] = $heightAssessment['height_zscore'] ?? null;
            $row['height_classification'] = $heightAssessment['height_classification'] ?? 'Not assessed';
            $records[] = $row;
        }
    }
    $stmt->close();
}

$totalPages = max(1, ceil($totalRecords / $perPage));

echo json_encode([
    'records'      => $records,
    'page'         => $page,
    'totalPages'   => $totalPages,
    'totalRecords' => $totalRecords,
    'search'       => $search,
    'date'         => $date,
]);

$conn->close();
?>
