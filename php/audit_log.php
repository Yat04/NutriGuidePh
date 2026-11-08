<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Super Admin']);

$conn = getDB();

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;
$filter = $_GET['filter'] ?? '';

$where = '';
if ($filter) {
    $where = "WHERE action LIKE '%" . $conn->real_escape_string($filter) . "%' OR user_name LIKE '%" . $conn->real_escape_string($filter) . "%' OR details LIKE '%" . $conn->real_escape_string($filter) . "%'";
}

$cRes = $conn->query("SELECT COUNT(*) as c FROM audit_log $where");
$totalLogs = ($cRes && $cRes instanceof mysqli_result) ? (int)($cRes->fetch_assoc()['c'] ?? 0) : 0;
$totalPages = max(1, ceil($totalLogs / $perPage));
$logs = $conn->query("SELECT * FROM audit_log $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide â€“ Audit Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body class="page-bg">
    <?php $activePage = 'audit'; include 'navbar.php'; ?>


    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">
        <?php if (isset($_GET['success']) && $_GET['success'] === 'cleared'): ?>
            <div class="alert alert-success alert-dismissible fade show small py-2 mb-3">
                <i class="fa-solid fa-circle-check me-1"></i>Audit log has been cleared successfully.
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-clipboard-list me-2"></i>Audit Log</h4>
                <p class="text-white-50 small mb-0"><?= $totalLogs ?> total entries</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <form method="GET" class="d-flex gap-2" style="max-width:300px;width:100%;">
                    <input type="text" class="form-control form-control-sm" name="filter" placeholder="Filter by action, user, details..." value="<?= htmlspecialchars($filter) ?>">
                    <button class="btn btn-green btn-sm px-3"><i class="fa-solid fa-search"></i></button>
                </form>
                <?php if ($totalLogs > 0): ?>
                <form method="POST" action="clear_audit_log.php" onsubmit="return confirm('Are you sure you want to permanently clear all audit logs?');" class="d-inline">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm text-nowrap"><i class="fa-solid fa-trash me-1"></i>Clear Log</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card table-card shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold">Time</th>
                                <th class="small fw-semibold">User</th>
                                <th class="small fw-semibold">Action</th>
                                <th class="small fw-semibold">Target</th>
                                <th class="small fw-semibold">Details</th>
                                <th class="small fw-semibold">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs->num_rows > 0): ?>
                                <?php while ($l = $logs->fetch_assoc()):
                                    $actionColor = match(true) {
                                        str_contains($l['action'], 'delete') => 'text-danger',
                                        str_contains($l['action'], 'create') || str_contains($l['action'], 'add') => 'text-success',
                                        str_contains($l['action'], 'update') || str_contains($l['action'], 'edit') => 'text-primary',
                                        str_contains($l['action'], 'login') => 'text-info',
                                        default => 'text-muted'
                                    };
                                ?>
                                <tr>
                                    <td class="small text-muted text-nowrap"><?= date('M d, g:ia', strtotime($l['created_at'])) ?></td>
                                    <td class="small fw-semibold"><?= htmlspecialchars($l['user_name']) ?></td>
                                    <td class="small <?= $actionColor ?> fw-semibold"><?= htmlspecialchars($l['action']) ?></td>
                                    <td class="small"><?= htmlspecialchars($l['target_type'] ?? '') ?><?= $l['target_id'] ? ' #'.$l['target_id'] : '' ?></td>
                                    <td class="small text-muted" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($l['details'] ?? '') ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($l['ip_address']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="fa-solid fa-clipboard fa-2x mb-2 opacity-25 d-block"></i>No log entries found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page-1 ?>&filter=<?= urlencode($filter) ?>"><i class="fa-solid fa-chevron-left"></i></a></li>
                        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                            <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page+1 ?>&filter=<?= urlencode($filter) ?>"><i class="fa-solid fa-chevron-right"></i></a></li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
