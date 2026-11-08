<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Admin', 'Super Admin']);

$conn = getDB();
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meal'])) {
    verifyCsrf();
    $date = $_POST['plan_date'];
    $type = sanitize($_POST['meal_type']);
    $menu = sanitize($_POST['menu']);
    $cal = intval($_POST['calories'] ?? 0) ?: null;
    $protein = floatval($_POST['protein_g'] ?? 0) ?: null;
    $notes = sanitize($_POST['notes'] ?? '');
    $by = $_SESSION['firstName'];

    $stmt = $conn->prepare("INSERT INTO meal_plans (plan_date, meal_type, menu, calories, protein_g, notes, created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssisss", $date, $type, $menu, $cal, $protein, $notes, $by);
    if ($stmt->execute()) { $msg = 'Meal plan saved!'; $msgType = 'success'; auditLog('add_meal_plan', 'meal_plans', $conn->insert_id); }
    else { $msg = 'Error saving.'; $msgType = 'danger'; }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meal']) && isAdmin()) {
    verifyCsrf();
    $id = intval($_POST['meal_id']);
    $conn->query("DELETE FROM meal_plans WHERE id=$id");
    $msg = 'Meal plan deleted.'; $msgType = 'success';
}

$weekStart = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$plans = $conn->query("SELECT * FROM meal_plans WHERE plan_date BETWEEN '$weekStart' AND '$weekEnd' ORDER BY plan_date, meal_type");
$plansByDay = [];
if ($plans && $plans instanceof mysqli_result) {
    while ($p = $plans->fetch_assoc()) $plansByDay[$p['plan_date']][] = $p;
}
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide – Meal Planning</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= generateCsrf() ?>">
</head>
<body class="page-bg">
    <?php $activePage = 'feeding'; include 'navbar.php'; ?>

    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-calendar-week me-2"></i>Meal Planning</h4>
                <p class="text-white-50 small mb-0">Week of <?= date('M d', strtotime($weekStart)) ?> – <?= date('M d, Y', strtotime($weekEnd)) ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="?week=<?= date('Y-m-d', strtotime($weekStart . ' -7 days')) ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="?week=<?= date('Y-m-d', strtotime('monday this week')) ?>" class="btn btn-outline-light btn-sm">This Week</a>
                <a href="?week=<?= date('Y-m-d', strtotime($weekStart . ' +7 days')) ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?> alert-dismissible fade show small py-2"><?= $msg ?><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-3">
            <?php for ($d = 0; $d < 5; $d++):
                $date = date('Y-m-d', strtotime($weekStart . " +$d days"));
                $dayName = $days[$d];
                $dayPlans = $plansByDay[$date] ?? [];
            ?>
            <div class="col-lg">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-success text-white py-2 text-center rounded-top-3">
                        <div class="fw-bold small"><?= $dayName ?></div>
                        <div style="font-size:0.7rem;"><?= date('M d', strtotime($date)) ?></div>
                    </div>
                    <div class="card-body p-2" style="min-height:150px;">
                        <?php if (!empty($dayPlans)): ?>
                            <?php foreach ($dayPlans as $mp): ?>
                            <div class="p-2 mb-1 rounded-2" style="background:#f8f9fa;font-size:0.78rem;">
                                <div class="fw-bold text-success"><?= htmlspecialchars($mp['meal_type']) ?></div>
                                <div><?= htmlspecialchars($mp['menu']) ?></div>
                                <?php if ($mp['calories']): ?><div class="text-muted" style="font-size:0.7rem;"><?= $mp['calories'] ?> kcal<?= $mp['protein_g'] ? ' · '.$mp['protein_g'].'g protein' : '' ?></div><?php endif; ?>
                                <?php if (isAdmin()): ?>
                                <form method="POST" class="d-inline"><input type="hidden" name="delete_meal" value="1"><input type="hidden" name="meal_id" value="<?= $mp['id'] ?>"><?= csrfField() ?><button class="btn btn-link btn-sm text-danger p-0" style="font-size:0.65rem;" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></button></form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-3" style="font-size:0.75rem;">No meals planned</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent p-2 text-center">
                        <button class="btn btn-sm btn-outline-success py-0 px-2 add-meal-btn" data-date="<?= $date ?>" data-day="<?= $dayName ?>" style="font-size:0.72rem;"><i class="fa-solid fa-plus me-1"></i>Add</button>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Add Meal Modal -->
    <div class="modal fade" id="mealModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">
                    <h6 class="modal-title text-white fw-bold" id="mealModalLabel"><i class="fa-solid fa-utensils me-2"></i>Add Meal</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST">
                        <input type="hidden" name="add_meal" value="1">
                        <?= csrfField() ?>
                        <input type="hidden" name="plan_date" id="meal_date">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Meal Type</label>
                            <select class="form-select form-select-sm" name="meal_type" required>
                                <option value="Breakfast">Breakfast</option>
                                <option value="Lunch" selected>Lunch</option>
                                <option value="Snack">Snack</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Menu</label>
                            <textarea class="form-control form-control-sm" name="menu" rows="2" placeholder="e.g. Rice, chicken adobo, kangkong" required></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">Calories (optional)</label>
                                <input type="number" class="form-control form-control-sm" name="calories" placeholder="kcal">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">Protein (optional)</label>
                                <input type="number" class="form-control form-control-sm" name="protein_g" step="0.1" placeholder="grams">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Notes</label>
                            <input type="text" class="form-control form-control-sm" name="notes" placeholder="Optional notes">
                        </div>
                        <button type="submit" class="btn btn-green w-100 py-2"><i class="fa-solid fa-check me-2"></i>Save Meal Plan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const mealModal = new bootstrap.Modal(document.getElementById('mealModal'));
        document.querySelectorAll('.add-meal-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('meal_date').value = this.dataset.date;
                document.getElementById('mealModalLabel').innerHTML = '<i class="fa-solid fa-utensils me-2"></i>' + this.dataset.day + ' Meal';
                mealModal.show();
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
