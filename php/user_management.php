<?php
require_once 'auth.php';
secureSessionStart();
checkAccess(['Super Admin']);

$conn = getDB();
if ($conn->connect_error) { die("Connection failed"); }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    mysqli_report(MYSQLI_REPORT_OFF);
    $uid = intval($_POST['uid'] ?? 0);
    $action = $_POST['action'];

    if ($uid > 0) {
        if ($action === 'approve') {
            $conn->query("UPDATE accounts SET status='active' WHERE id=$uid OR account_id=$uid");
            auditLog('approve_user', 'account', $uid);
        } elseif ($action === 'deactivate') {
            $conn->query("UPDATE accounts SET status='deactivated' WHERE (id=$uid OR account_id=$uid) AND email != '{$_SESSION['user_id']}'");
            auditLog('deactivate_user', 'account', $uid);
        } elseif ($action === 'activate') {
            $conn->query("UPDATE accounts SET status='active' WHERE id=$uid OR account_id=$uid");
            auditLog('activate_user', 'account', $uid);
        } elseif ($action === 'change_role') {
            $newRole = $_POST['new_role'] ?? '';
            $allowed = ['Employee', 'Admin', 'Super Admin'];
            if (in_array($newRole, $allowed)) {
                $me = $_SESSION['user_id'];
                if ($newRole === 'Employee') {
                    $grade = trim($_POST['adviser_grade_level'] ?? '');
                    $sections = array_values(array_unique(array_filter(array_map('trim', explode(',', $_POST['adviser_sections'] ?? '')))));
                    $validGrades = array_merge(['Kindergarten'], array_map(fn($g) => "Grade $g", range(1, 6)));
                    $sectionList = implode(',', $sections);
                    if (in_array($grade, $validGrades, true) && $sectionList !== '' && strlen($sectionList) <= 255) {
                        $stmt = $conn->prepare("UPDATE accounts SET role=?, adviser_grade_level=?, adviser_sections=? WHERE (id=? OR account_id=?) AND email != ?");
                        $stmt->bind_param("sssiss", $newRole, $grade, $sectionList, $uid, $uid, $me);
                        $stmt->execute();
                        auditLog('change_role', 'account', $uid, "New role: Adviser ($grade / $sectionList)");
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE accounts SET role=?, adviser_grade_level=NULL, adviser_sections=NULL WHERE (id=? OR account_id=?) AND email != ?");
                    $stmt->bind_param("siis", $newRole, $uid, $uid, $me);
                    $stmt->execute();
                    auditLog('change_role', 'account', $uid, "New role: $newRole");
                    $stmt->close();
                }
            }
        } elseif ($action === 'delete') {
            $conn->query("DELETE FROM accounts WHERE (id=$uid OR account_id=$uid) AND email != '{$_SESSION['user_id']}'");
            auditLog('delete_user', 'account', $uid);
        }
    }

    // Create staff account
    if ($action === 'create_staff') {
        $fn = trim($_POST['new_fn'] ?? '');
        $ln = trim($_POST['new_ln'] ?? '');
        $em = trim($_POST['new_email'] ?? '');
        $pw = $_POST['new_password'] ?? '';
        $rl = $_POST['new_role'] ?? 'Employee';
        $allowed = ['Employee', 'Admin', 'Super Admin'];
        $grade = trim($_POST['adviser_grade_level'] ?? '');
        $sections = array_values(array_unique(array_filter(array_map('trim', explode(',', $_POST['adviser_sections'] ?? '')))));
        $sectionList = implode(',', $sections);
        $validGrades = array_merge(['Kindergarten'], array_map(fn($g) => "Grade $g", range(1, 6)));
        $assignmentValid = $rl !== 'Employee' || (in_array($grade, $validGrades, true) && $sectionList !== '' && strlen($sectionList) <= 255);

        if (!empty($fn) && !empty($ln) && !empty($em) && strlen($pw) >= 6 && in_array($rl, $allowed) && $assignmentValid) {
            $hashed = password_hash($pw, PASSWORD_DEFAULT);
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $adviserGrade = $rl === 'Employee' ? $grade : null;
            $adviserSections = $rl === 'Employee' ? $sectionList : null;
            $stmt = $conn->prepare("INSERT INTO accounts (firstName, lastName, role, email, password, is_verified, status, verification_code, code_expires, adviser_grade_level, adviser_sections) VALUES (?, ?, ?, ?, ?, 0, 'active', ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $fn, $ln, $rl, $em, $hashed, $code, $expires, $adviserGrade, $adviserSections);
            if ($stmt->execute()) {
                require_once 'mailer.php';
                sendVerificationEmail($em, $fn, $code);
                auditLog('create_staff', 'account', $conn->insert_id, "$fn $ln ($rl)");
            }
            $stmt->close();
        }
    }

    header("Location: user_management.php");
    exit();
}

// Get all accounts safely
$pending = $conn->query("SELECT * FROM accounts WHERE status='pending' AND role != 'Parent/Guardian' ORDER BY firstName ASC");
$active = $conn->query("SELECT * FROM accounts WHERE status='active' ORDER BY role DESC, firstName ASC");
$deactivated = $conn->query("SELECT * FROM accounts WHERE status='deactivated' ORDER BY firstName ASC");

$pendingCount = ($pending && $pending instanceof mysqli_result) ? $pending->num_rows : 0;
$activeCount = ($active && $active instanceof mysqli_result) ? $active->num_rows : 0;
$deactivatedCount = ($deactivated && $deactivated instanceof mysqli_result) ? $deactivated->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('nutriph_dark')==='1')document.documentElement.setAttribute('data-theme','dark');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriPh Guide â€“ User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css?v=3">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#3d6b0f">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body class="page-bg">

    <!-- Navbar -->
    <?php $activePage = 'users'; include 'navbar.php'; ?>


    <div class="container-fluid px-3 px-lg-5 py-3 py-lg-4">

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2" data-aos="fade-down">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-users-gear me-2"></i>User Management</h4>
                <p class="text-white-50 small mb-0">Create, approve, promote, and manage user accounts</p>
            </div>
            <button class="btn btn-green px-4" data-bs-toggle="modal" data-bs-target="#createStaffModal">
                <i class="fa-solid fa-user-plus me-2"></i>Create Staff
            </button>
        </div>

        <!-- Stats -->
        <div class="row g-2 g-lg-3 mb-4">
            <div class="col-4" data-aos="fade-up">
                <div class="card stat-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="stat-icon bg-warning-subtle text-warning"><i class="fa-solid fa-clock"></i></div>
                        <div><div class="stat-number text-warning"><?= $pendingCount ?></div><div class="text-muted small fw-semibold">Pending</div></div>
                    </div>
                </div>
            </div>
            <div class="col-4" data-aos="fade-up" data-aos-delay="50">
                <div class="card stat-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="stat-icon bg-success-subtle text-success"><i class="fa-solid fa-user-check"></i></div>
                        <div><div class="stat-number text-success"><?= $activeCount ?></div><div class="text-muted small fw-semibold">Active</div></div>
                    </div>
                </div>
            </div>
            <div class="col-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card stat-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 p-3">
                        <div class="stat-icon" style="background-color:#f8d7da;color:#721c24;"><i class="fa-solid fa-user-slash"></i></div>
                        <div><div class="stat-number" style="color:#721c24;"><?= $deactivatedCount ?></div><div class="text-muted small fw-semibold">Deactivated</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <?php if ($pendingCount > 0): ?>
        <div class="card table-card shadow-sm mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3" style="color:#e67e22;"><i class="fa-solid fa-clock me-2"></i>Pending Approval (<?= $pendingCount ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold">Name</th>
                                <th class="small fw-semibold">Email</th>
                                <th class="small fw-semibold">Registered</th>
                                <th class="small fw-semibold text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $pending->fetch_assoc()): ?>
                            <tr>
                                <td class="small fw-semibold"><?= htmlspecialchars($u['firstName'] . ' ' . $u['lastName']) ?></td>
                                <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="small text-muted"><?php $regDate = !empty($u['created_at']) ? $u['created_at'] : (!empty($u['Created']) ? $u['Created'] : 'now'); echo date('M d, Y', strtotime($regDate)); ?></td>
                                <td class="text-center text-nowrap">
                                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="uid" value="<?= $u['id'] ?? $u['account_id'] ?>"><input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success py-0 px-2"><i class="fa-solid fa-check me-1" style="font-size:0.7rem;"></i>Approve</button>
                                    </form>
                                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="uid" value="<?= $u['id'] ?? $u['account_id'] ?>"><input type="hidden" name="action" value="delete">
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Delete this account?')"><i class="fa-solid fa-trash" style="font-size:0.7rem;"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Users -->
        <div class="card table-card shadow-sm mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-user-check me-2"></i>Active Users (<?= $activeCount ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold">Name</th>
                                <th class="small fw-semibold">Email</th>
                                <th class="small fw-semibold">Role</th>
                                <th class="small fw-semibold">Joined</th>
                                <th class="small fw-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $active->fetch_assoc()):
                                $isMe = ($u['email'] === $_SESSION['user_id']);
                                $roleBadge = match($u['role']) {
                                    'Super Admin' => 'bg-danger-subtle text-danger',
                                    'Admin' => 'bg-primary-subtle text-primary',
                                    'Employee' => 'bg-success-subtle text-success',
                                    default => 'bg-secondary-subtle text-secondary'
                                };
                            ?>
                            <tr>
                                <td class="small fw-semibold">
                                    <?= htmlspecialchars($u['firstName'] . ' ' . $u['lastName']) ?>
                                    <?php if ($isMe): ?><span class="badge bg-dark rounded-pill ms-1" style="font-size:0.6rem;">You</span><?php endif; ?>
                                    <?php if (!$u['is_verified']): ?><span class="badge bg-warning-subtle text-warning rounded-pill ms-1" style="font-size:0.6rem;">Unverified</span><?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php if (!$isMe && $u['role'] !== 'Parent/Guardian'): ?>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="uid" value="<?= $u['id'] ?? $u['account_id'] ?>">
                                        <input type="hidden" name="action" value="change_role">
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <select name="new_role" class="form-select form-select-sm" style="width:auto;font-size:0.78rem;">
                                            <option value="Employee" <?= $u['role']==='Employee'?'selected':'' ?>>Adviser (Employee)</option>
                                            <option value="Admin" <?= $u['role']==='Admin'?'selected':'' ?>>Admin</option>
                                            <option value="Super Admin" <?= $u['role']==='Super Admin'?'selected':'' ?>>Super Admin</option>
                                        </select>
                                        <select name="adviser_grade_level" class="form-select form-select-sm" style="width:auto;font-size:0.72rem;" aria-label="Adviser grade">
                                            <option value="">Grade</option>
                                            <?php foreach (array_merge(['Kindergarten'], array_map(fn($g) => "Grade $g", range(1, 6))) as $gradeOption): ?>
                                                <option value="<?= htmlspecialchars($gradeOption) ?>" <?= ($u['adviser_grade_level'] ?? '') === $gradeOption ? 'selected' : '' ?>><?= htmlspecialchars($gradeOption) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input name="adviser_sections" class="form-control form-control-sm" style="width:110px;font-size:0.72rem;" placeholder="Sections" value="<?= htmlspecialchars($u['adviser_sections'] ?? '') ?>" aria-label="Adviser sections">
                                        <button class="btn btn-sm btn-outline-success py-0" title="Save role and adviser assignment"><i class="fa-solid fa-check"></i></button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                        <span class="badge rounded-pill <?= $roleBadge ?>" style="font-size:0.75rem;"><?= normalizeRole($u['role']) === 'Employee' ? 'Adviser' : htmlspecialchars($u['role']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php $regDate = !empty($u['created_at']) ? $u['created_at'] : (!empty($u['Created']) ? $u['Created'] : 'now'); echo date('M d, Y', strtotime($regDate)); ?></td>
                                <td class="text-center">
                                    <?php if (!$isMe): ?>
                                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="uid" value="<?= $u['id'] ?? $u['account_id'] ?>"><input type="hidden" name="action" value="deactivate">
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Deactivate this account?')" title="Deactivate"><i class="fa-solid fa-user-slash" style="font-size:0.7rem;"></i></button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Deactivated -->
        <?php if ($deactivatedCount > 0): ?>
        <div class="card table-card shadow-sm mb-4" data-aos="fade-up">
            <div class="card-body p-4">
                <h6 class="fw-bold text-danger mb-3"><i class="fa-solid fa-user-slash me-2"></i>Deactivated (<?= $deactivatedCount ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small fw-semibold">Name</th>
                                <th class="small fw-semibold">Email</th>
                                <th class="small fw-semibold">Role</th>
                                <th class="small fw-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $deactivated->fetch_assoc()): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($u['firstName'] . ' ' . $u['lastName']) ?></td>
                                <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                                <td class="small"><?= $u['role'] ?></td>
                                <td class="text-center text-nowrap">
                                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="uid" value="<?= $u['id'] ?? $u['account_id'] ?>"><input type="hidden" name="action" value="activate">
                                        <button class="btn btn-sm btn-outline-success py-0 px-2"><i class="fa-solid fa-user-check me-1" style="font-size:0.7rem;"></i>Reactivate</button>
                                    </form>
                                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="uid" value="<?= $u['id'] ?? $u['account_id'] ?>"><input type="hidden" name="action" value="delete">
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Permanently delete this account?')"><i class="fa-solid fa-trash" style="font-size:0.7rem;"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Create Staff Modal -->
    <div class="modal fade" id="createStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">
                    <h6 class="modal-title text-white fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Create Staff Account</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" id="createStaffForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_staff">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">First Name</label>
                                <input type="text" class="form-control form-control-sm" name="new_fn" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">Last Name</label>
                                <input type="text" class="form-control form-control-sm" name="new_ln" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Email</label>
                            <input type="email" class="form-control form-control-sm" name="new_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Role</label>
                            <select class="form-select form-select-sm" name="new_role" id="newStaffRole">
                                <option value="Employee">Adviser (Employee)</option>
                                <option value="Admin">Admin</option>
                                <option value="Super Admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3" id="newAdviserAssignment">
                            <div class="col-5">
                                <label class="form-label small fw-semibold text-muted">Grade Level</label>
                                <select class="form-select form-select-sm" name="adviser_grade_level" id="newAdviserGrade">
                                    <option value="">Select grade</option>
                                    <?php foreach (array_merge(['Kindergarten'], array_map(fn($g) => "Grade $g", range(1, 6))) as $gradeOption): ?>
                                        <option value="<?= htmlspecialchars($gradeOption) ?>"><?= htmlspecialchars($gradeOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-7">
                                <label class="form-label small fw-semibold text-muted">Section(s)</label>
                                <input type="text" class="form-control form-control-sm" name="adviser_sections" id="newAdviserSections" placeholder="e.g. Sampaguita, Rosal">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control form-control-sm" name="new_password" id="staffPassword" required minlength="6" placeholder="Min 6 characters">
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-toggle-password="staffPassword" data-toggle-icon="toggleStaffPasswordIcon"><i class="fa-solid fa-eye" id="toggleStaffPasswordIcon"></i></button>
                            </div>
                            <div class="progress mt-2" style="height:4px;">
                                <div class="progress-bar" id="staffStrengthBar" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <div class="small text-muted" id="staffStrengthText"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Confirm Password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control form-control-sm" name="confirm_password" id="staffPasswordConfirm" required minlength="6" placeholder="Re-enter password">
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-toggle-password="staffPasswordConfirm" data-toggle-icon="toggleStaffPasswordConfirmIcon"><i class="fa-solid fa-eye" id="toggleStaffPasswordConfirmIcon"></i></button>
                            </div>
                            <div class="small text-danger d-none" id="staffMatchError">Passwords do not match</div>
                            <div class="small text-success d-none" id="staffMatchOk">Passwords match</div>
                        </div>
                        <p class="text-muted small mb-3"><i class="fa-solid fa-info-circle me-1"></i>A verification code will be sent to their email. They must verify before logging in.</p>
                        <button type="submit" class="btn btn-green w-100 py-2" id="createStaffSubmitBtn" disabled>
                            <i class="fa-solid fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-section" id="footer">
        <div class="container py-5">
            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="../images/logo.png" alt="Logo" height="36" class="footer-logo">
                        <h5 class="mb-0 text-success-light">NutriPh Guide</h5>
                    </div>
                    <p class="text-muted small">A community-based monitoring and support system for San Antonio Central School.</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-success-light text-uppercase small fw-bold mb-3">Contact</h6>
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

        const staffPassword = document.getElementById('staffPassword');
        const staffPasswordConfirm = document.getElementById('staffPasswordConfirm');
        const staffStrengthBar = document.getElementById('staffStrengthBar');
        const staffStrengthText = document.getElementById('staffStrengthText');
        const staffMatchError = document.getElementById('staffMatchError');
        const staffMatchOk = document.getElementById('staffMatchOk');
        const createStaffSubmitBtn = document.getElementById('createStaffSubmitBtn');
        const createStaffForm = document.getElementById('createStaffForm');
        const newStaffRole = document.getElementById('newStaffRole');
        const newAdviserAssignment = document.getElementById('newAdviserAssignment');
        const newAdviserGrade = document.getElementById('newAdviserGrade');
        const newAdviserSections = document.getElementById('newAdviserSections');
        let staffPasswordValid = false;

        function updateAdviserFields() {
            const isAdviser = newStaffRole.value === 'Employee';
            newAdviserAssignment.classList.toggle('d-none', !isAdviser);
            newAdviserGrade.required = isAdviser;
            newAdviserSections.required = isAdviser;
        }
        newStaffRole.addEventListener('change', updateAdviserFields);
        updateAdviserFields();

        function updateCreateStaffButton() {
            const match = staffPassword.value === staffPasswordConfirm.value && staffPasswordConfirm.value.length > 0;
            const valid = staffPasswordValid && match;
            createStaffSubmitBtn.disabled = !valid;
        }

        function togglePasswordVisibility(button) {
            const targetId = button.dataset.togglePassword;
            const targetInput = document.getElementById(targetId);
            const icon = button.querySelector('i');

            if (!targetInput || !icon) return;

            const isHidden = targetInput.type === 'password';
            targetInput.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
        }

        document.querySelectorAll('[data-toggle-password]').forEach(button => {
            button.addEventListener('click', function() {
                togglePasswordVisibility(this);
            });
        });

        function checkStaffPasswordMatch() {
            const match = staffPassword.value === staffPasswordConfirm.value && staffPasswordConfirm.value.length > 0;
            staffMatchError.classList.toggle('d-none', match || staffPasswordConfirm.value.length === 0);
            staffMatchOk.classList.toggle('d-none', !match);
            updateCreateStaffButton();
        }

        staffPassword.addEventListener('input', function() {
            const v = staffPassword.value;
            const has6 = v.length >= 6;
            const hasUp = /[A-Z]/.test(v);
            const hasLo = /[a-z]/.test(v);
            const hasNum = /[0-9]/.test(v);
            let score = [has6, hasUp, hasLo, hasNum].filter(Boolean).length;
            if (v.length >= 10) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;

            const pct = Math.min(100, (score / 6) * 100);
            staffStrengthBar.style.width = pct + '%';

            if (score <= 2) {
                staffStrengthBar.style.background = '#e74c3c';
                staffStrengthText.textContent = 'Weak';
                staffStrengthText.style.color = '#e74c3c';
                staffPasswordValid = false;
            } else if (score <= 4) {
                staffStrengthBar.style.background = '#f39c12';
                staffStrengthText.textContent = 'Medium';
                staffStrengthText.style.color = '#f39c12';
                staffPasswordValid = has6 && hasUp && hasLo && hasNum;
            } else {
                staffStrengthBar.style.background = '#27ae60';
                staffStrengthText.textContent = 'Strong';
                staffStrengthText.style.color = '#27ae60';
                staffPasswordValid = has6 && hasUp && hasLo && hasNum;
            }

            if (!v) {
                staffStrengthBar.style.width = '0';
                staffStrengthText.textContent = '';
                staffPasswordValid = false;
            }

            checkStaffPasswordMatch();
        });

        staffPasswordConfirm.addEventListener('input', checkStaffPasswordMatch);

        createStaffForm.addEventListener('submit', function(e) {
            if (!staffPasswordValid || staffPassword.value !== staffPasswordConfirm.value) {
                e.preventDefault();
                checkStaffPasswordMatch();
                return;
            }

            createStaffSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating account...';
            createStaffSubmitBtn.disabled = true;
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
