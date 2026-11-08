<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Super Admin']);

$conn = getDB();
$tables = ['accounts', 'stdRecord', 'feedingRecord', 'feedingSchedule', 'audit_log', 'notifications', 'data_corrections', 'meal_plans', 'scheduled_reports'];

$filename = 'NutriPh_Backup_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "-- NutriPh Guide Database Backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- ────────────────────────────────────\n\n";

foreach ($tables as $table) {
    $result = $conn->query("SELECT * FROM $table");
    if (!$result || $result->num_rows === 0) continue;

    echo "-- Table: $table\n";
    $fields = $result->fetch_fields();
    $cols = implode(', ', array_map(fn($f) => "`{$f->name}`", $fields));

    while ($row = $result->fetch_assoc()) {
        $vals = [];
        foreach ($row as $v) {
            $vals[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
        }
        echo "INSERT INTO `$table` ($cols) VALUES (" . implode(', ', $vals) . ");\n";
    }
    echo "\n";
}

auditLog('database_backup', 'system', null, $filename);
$conn->close();
?>
