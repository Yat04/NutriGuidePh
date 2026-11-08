<?php
// Shared navbar - set $activePage before including
$activePage = $activePage ?? '';
$navFirstName = htmlspecialchars($_SESSION['firstName'] ?? '');
$navRole = htmlspecialchars(isAdviser() ? 'Adviser' : ($_SESSION['role'] ?? ''));
$navCsrfToken = generateCsrf();
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid px-3 px-lg-5">
        <a class="navbar-brand d-flex align-items-center gap-2" href="../index.php">
            <img src="../images/logo.png" alt="Logo" height="40"><span class="fw-bold brand-text">NutriPh Guide</span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto gap-1 align-items-center">
                <li class="nav-item"><a class="nav-link <?= $activePage==='dashboard'?'active':'' ?>" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $activePage==='records'?'active':'' ?>" href="records.php">Records</a></li>
                <?php if (isAdmin()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($activePage,['feeding','meal_plan'])?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown">Feeding</a>
                    <ul class="dropdown-menu border-0 shadow-lg rounded-3">
                        <li><a class="dropdown-item small" href="feeding_program.php"><i class="fa-solid fa-utensils me-2 text-success"></i>Feeding Program</a></li>
                        <li><a class="dropdown-item small" href="meal_planning.php"><i class="fa-solid fa-calendar-week me-2 text-primary"></i>Meal Planning</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (isStaff()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($activePage,['reports','import'])?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown">Tools</a>
                    <ul class="dropdown-menu border-0 shadow-lg rounded-3">
                        <li><a class="dropdown-item small" href="reports.php"><i class="fa-solid fa-print me-2 text-success"></i>Print Reports</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a class="dropdown-item small" href="deped_reports.php"><i class="fa-solid fa-file-lines me-2 text-info"></i>DepEd Reports (SBFP/OPT)</a></li>
                        <li><a class="dropdown-item small" href="bulk_import.php"><i class="fa-solid fa-file-import me-2 text-primary"></i>Bulk Import</a></li>
                        <?php endif; ?>
                        <?php if (isSuperAdmin()): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small" href="backup.php"><i class="fa-solid fa-database me-2 text-warning"></i>Download Backup</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (isSuperAdmin()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($activePage,['users','audit'])?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown">Admin</a>
                    <ul class="dropdown-menu border-0 shadow-lg rounded-3">
                        <li><a class="dropdown-item small" href="user_management.php"><i class="fa-solid fa-users-gear me-2 text-success"></i>Users</a></li>
                        <li><a class="dropdown-item small" href="audit_log.php"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Audit Log</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1 dm-toggle" href="#" onclick="toggleDarkMode(event)" title="Toggle Dark Mode">
                        <i class="fa-solid fa-moon dm-icon" data-extra=""></i>
                        <span class="small fw-semibold dm-label">Dark Mode</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-success-light fw-semibold" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-circle-user me-1"></i><?= $navFirstName ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3" style="min-width:200px;">
                        <li class="px-3 py-2"><div class="small fw-bold"><?= $navFirstName ?></div><div class="text-muted" style="font-size:0.75rem;"><?= $navRole ?></div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small" href="#" id="openProfileBtn"><i class="fa-solid fa-user-pen me-2 text-success"></i>My Profile</a></li>
                        <li><a class="dropdown-item small" href="#" id="openPasswordBtn"><i class="fa-solid fa-key me-2 text-warning"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-end text-bg-dark" id="mobileSidebar">
    <div class="offcanvas-header"><h5 class="offcanvas-title brand-text">NutriPh Guide</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body">
        <ul class="navbar-nav gap-2">
            <li class="nav-item"><a class="nav-link text-white" href="dashboard.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="records.php"><i class="fa-solid fa-folder-open me-2"></i>Records</a></li>
            <?php if (isAdmin()): ?>
            <li class="nav-item"><a class="nav-link text-white" href="feeding_program.php"><i class="fa-solid fa-utensils me-2"></i>Feeding Program</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="meal_planning.php"><i class="fa-solid fa-calendar-week me-2"></i>Meal Planning</a></li>
            <?php endif; ?>
            <?php if (isStaff()): ?>
            <li class="nav-item"><a class="nav-link text-white" href="reports.php"><i class="fa-solid fa-print me-2"></i>Print Reports</a></li>
            <?php if (isAdmin()): ?>
            <li class="nav-item"><a class="nav-link text-white" href="deped_reports.php"><i class="fa-solid fa-file-lines me-2"></i>DepEd Reports</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="bulk_import.php"><i class="fa-solid fa-file-import me-2"></i>Bulk Import</a></li>
            <?php endif; ?>
            <?php if (isSuperAdmin()): ?>
            <li class="nav-item"><a class="nav-link text-white" href="user_management.php"><i class="fa-solid fa-users-gear me-2"></i>Users</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="audit_log.php"><i class="fa-solid fa-clipboard-list me-2"></i>Audit Log</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="backup.php"><i class="fa-solid fa-database me-2"></i>Backup</a></li>
            <?php endif; ?>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link text-white" href="#" id="openProfileBtnMobile"><i class="fa-solid fa-user-pen me-2"></i>My Profile</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="#" id="openPasswordBtnMobile"><i class="fa-solid fa-key me-2"></i>Change Password</a></li>
            <li class="nav-item"><a class="nav-link text-white" href="#" onclick="toggleDarkMode(event)"><i class="fa-solid fa-moon me-2 dm-icon" data-extra="me-2"></i><span class="dm-label">Dark Mode</span></a></li>
            <li><hr class="border-secondary my-2"></li>
            <li class="nav-item"><a class="nav-link text-white" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
        </ul>
    </div>
</div>

<script>
function toggleDarkMode(e) {
    if (e) e.preventDefault();
    try {
        var html = document.documentElement;
        var isDark = html.getAttribute('data-theme') === 'dark';
        if (isDark) {
            html.removeAttribute('data-theme');
            localStorage.setItem('nutriph_dark', '0');
        } else {
            html.setAttribute('data-theme', 'dark');
            localStorage.setItem('nutriph_dark', '1');
        }
        _updateDarkIcons();
    } catch(err) { console.error('Dark mode toggle error:', err); }
}
function _updateDarkIcons() {
    try {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var icon = isDark ? 'fa-sun' : 'fa-moon';
        var text = isDark ? 'Light Mode' : 'Dark Mode';
        document.querySelectorAll('.dm-icon').forEach(function(el) {
            var extra = el.getAttribute('data-extra') || '';
            el.className = 'fa-solid ' + icon + (extra ? ' ' + extra : '') + ' dm-icon';
        });
        document.querySelectorAll('.dm-label').forEach(function(el) { el.textContent = text; });
    } catch(err) { console.error('Dark mode icon update error:', err); }
}
_updateDarkIcons();
if('serviceWorker' in navigator){navigator.serviceWorker.register('../sw.js').catch(function(){});}

window.__nutriphCsrfToken = <?= json_encode($navCsrfToken) ?>;
if (!document.querySelector('meta[name="csrf-token"]')) {
    const meta = document.createElement('meta');
    meta.name = 'csrf-token';
    meta.content = window.__nutriphCsrfToken;
    document.head.appendChild(meta);
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || window.__nutriphCsrfToken || '';
}

function showToast(message, type = 'success') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    const id = 'toast_' + Date.now();
    const icon = type === 'success' ? 'circle-check' : (type === 'error' ? 'circle-exclamation' : 'triangle-exclamation');
    container.insertAdjacentHTML('beforeend', '<div id="'+id+'" class="toast-custom toast-'+type+' mb-2"><div class="toast-body d-flex align-items-center gap-2"><i class="fa-solid fa-'+icon+'"></i> '+message+'</div></div>');
    setTimeout(() => { const el = document.getElementById(id); if (el) el.remove(); }, 4000);
}
window.showToast = showToast;

(function() {
    if (window.__nutriphProfileHandlersBound) return;
    window.__nutriphProfileHandlersBound = true;

    function makeModalHtml(title, iconClass) {
        const modalTitle = title === 'profileModal' ? 'My Profile' : 'Change Password';
        const modalBodyContent = title === 'profileModal' 
            ? '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>' 
            : '<div class="alert alert-danger small py-2 d-none" id="changePasswordError"></div>' +
              '<form id="changePasswordForm" action="update_profile.php" method="POST">' +
              '<input type="hidden" name="action" value="change_password">' +
              '<input type="hidden" name="csrf_token" value="' + getCsrfToken() + '">' +
              '<div class="mb-3">' +
              '<label class="form-label small fw-semibold text-muted">Current Password</label>' +
              '<input type="password" class="form-control form-control-sm" name="current_password" required autocomplete="current-password">' +
              '</div>' +
              '<div class="mb-3">' +
              '<label class="form-label small fw-semibold text-muted">New Password</label>' +
              '<input type="password" class="form-control form-control-sm" name="new_password" required minlength="6" autocomplete="new-password" placeholder="Min 6 characters">' +
              '</div>' +
              '<div class="mb-3">' +
              '<label class="form-label small fw-semibold text-muted">Confirm New Password</label>' +
              '<input type="password" class="form-control form-control-sm" name="confirm_password" required minlength="6" autocomplete="new-password" placeholder="Repeat new password">' +
              '</div>' +
              '<button type="submit" class="btn btn-green w-100 py-2">' +
              '<i class="fa-solid fa-key me-2"></i>Update Password' +
              '</button>' +
              '</form>';

        return '<div class="modal fade" id="'+title+'" tabindex="-1" aria-hidden="true">'+
            '<div class="modal-dialog modal-dialog-centered">'+
            '<div class="modal-content border-0 rounded-4 overflow-hidden">'+
            '<div class="modal-header border-0 py-3 px-4" style="background-color:#3d6b0f;">'+
            '<h6 class="modal-title text-white fw-bold"><i class="fa-solid '+iconClass+' me-2"></i>'+modalTitle+'</h6>'+
            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>'+
            '</div>'+
            '<div class="modal-body p-4" id="'+(title === 'profileModal' ? 'profileModalBody' : '')+'">'+
            modalBodyContent +
            '</div></div></div></div>';
    }

    function ensureProfileModals() {
        if (!document.getElementById('profileModal')) {
            const wrap = document.createElement('div');
            wrap.innerHTML = makeModalHtml('profileModal', 'fa-user-pen');
            document.body.appendChild(wrap.firstElementChild);
        }
        if (!document.getElementById('passwordModal')) {
            const wrap = document.createElement('div');
            wrap.innerHTML = makeModalHtml('passwordModal', 'fa-key');
            document.body.appendChild(wrap.firstElementChild);
        }
        if (!document.getElementById('toastContainer')) {
            const tc = document.createElement('div');
            tc.className = 'toast-container';
            tc.id = 'toastContainer';
            document.body.appendChild(tc);
        }
        return {
            profileModal: document.getElementById('profileModal'),
            passwordModal: document.getElementById('passwordModal'),
            profileBody: document.getElementById('profileModalBody')
        };
    }

    function esc(s) { if (!s) return ''; const el = document.createElement('span'); el.textContent = s; return el.innerHTML; }

    function openProfile() {
        const { profileModal, profileBody } = ensureProfileModals();
        if (!profileModal || !profileBody) return;
        profileBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>';
        const modal = bootstrap.Modal.getOrCreateInstance(profileModal);
        modal.show();

        fetch('get_profile.php', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    profileBody.innerHTML = '<p class="text-danger text-center">Failed to load profile.</p>';
                    return;
                }
                const u = data.user;
                const joined = u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'}) : '—';
                const csrfToken = getCsrfToken();

                let h = '<div class="text-center mb-4"><div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:72px;height:72px;background:#f4ffe8;color:#78bc27;font-size:1.8rem;font-weight:800;">'+esc((u.firstName || '').charAt(0))+esc((u.lastName || '').charAt(0))+'</div><h6 class="fw-bold mb-0">'+esc(u.firstName)+' '+esc(u.lastName)+'</h6><span class="badge bg-success-subtle text-success rounded-pill mt-1" style="font-size:0.75rem;">'+esc(u.role)+'</span></div>';
                h += '<form id="profileEditForm"><input type="hidden" name="action" value="update_info"><input type="hidden" name="csrf_token" value="'+csrfToken+'"><div class="row g-3 mb-3"><div class="col-6"><label class="form-label small fw-semibold text-muted">First Name</label><input type="text" class="form-control form-control-sm" name="firstName" value="'+esc(u.firstName)+'" required></div><div class="col-6"><label class="form-label small fw-semibold text-muted">Last Name</label><input type="text" class="form-control form-control-sm" name="lastName" value="'+esc(u.lastName)+'" required></div></div>';
                h += '<div class="mb-3"><label class="form-label small fw-semibold text-muted">Email</label><input type="email" class="form-control form-control-sm" name="email" value="'+esc(u.email)+'" required></div>';
                h += '<div class="mb-3"><label class="form-label small fw-semibold text-muted">Role</label><input type="text" class="form-control form-control-sm" value="'+esc(u.role)+'" disabled></div>';
                h += '<div class="d-flex justify-content-between align-items-center small text-muted mb-3"><span><i class="fa-solid fa-calendar me-1"></i>Joined: '+joined+'</span>';
                if (u.consent_agreed == 1) h += '<span class="text-success"><i class="fa-solid fa-shield-check me-1"></i>Consent</span>';
                h += '</div><button type="submit" class="btn btn-green w-100 py-2"><i class="fa-solid fa-floppy-disk me-2"></i>Save</button></form>';
                profileBody.innerHTML = h;
            })
            .catch(() => {
                profileBody.innerHTML = '<p class="text-danger text-center">Failed to load profile.</p>';
            });
    }

    function openPassword() {
        const { passwordModal } = ensureProfileModals();
        if (passwordModal) {
            const errEl = passwordModal.querySelector('#changePasswordError');
            if (errEl) { errEl.textContent = ''; errEl.classList.add('d-none'); }
            const form = passwordModal.querySelector('#changePasswordForm');
            if (form) {
                form.reset();
                const csrfInput = form.querySelector('input[name="csrf_token"]');
                if (csrfInput) csrfInput.value = getCsrfToken();
            }
            const modal = bootstrap.Modal.getOrCreateInstance(passwordModal);
            modal.show();
        }
    }

    document.addEventListener('click', function (event) {
        const profileLink = event.target.closest('#openProfileBtn, #openProfileBtnMobile');
        if (profileLink) {
            event.preventDefault();
            const sidebar = document.getElementById('mobileSidebar');
            if (sidebar) { bootstrap.Offcanvas.getInstance(sidebar)?.hide(); }
            openProfile();
            return;
        }

        const passwordLink = event.target.closest('#openPasswordBtn, #openPasswordBtnMobile');
        if (passwordLink) {
            event.preventDefault();
            const sidebar = document.getElementById('mobileSidebar');
            if (sidebar) { bootstrap.Offcanvas.getInstance(sidebar)?.hide(); }
            openPassword();
            return;
        }
    });

    // Event delegation for Profile Edit Form submission
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'profileEditForm') {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const origHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
            }

            const csrfInput = form.querySelector('input[name="csrf_token"]');
            if (csrfInput && !csrfInput.value) {
                csrfInput.value = getCsrfToken();
            }

            fetch('update_profile.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form)
            })
            .then(r => r.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                }
                if (res.success) {
                    const modalEl = document.getElementById('profileModal');
                    if (modalEl) {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }
                    showToast('Profile updated!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(res.error || 'Failed to update profile.', 'error');
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                }
                showToast('Network error.', 'error');
            });
        }
    });

    // Event delegation for Change Password Form submission
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'changePasswordForm') {
            e.preventDefault();
            const form = e.target;
            const modalEl = document.getElementById('passwordModal');
            const errEl = modalEl ? modalEl.querySelector('#changePasswordError') : null;
            if (errEl) { errEl.textContent = ''; errEl.classList.add('d-none'); }

            const newPass = form.querySelector('input[name="new_password"]')?.value || '';
            const confirmPass = form.querySelector('input[name="confirm_password"]')?.value || '';

            if (newPass.length < 6) {
                const msg = 'Password must be at least 6 characters.';
                if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
                showToast(msg, 'error');
                return;
            }

            if (newPass !== confirmPass) {
                const msg = 'New passwords do not match.';
                if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
                showToast(msg, 'error');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const origHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating...';
            }

            const csrfInput = form.querySelector('input[name="csrf_token"]');
            if (csrfInput && !csrfInput.value) {
                csrfInput.value = getCsrfToken();
            }

            fetch('update_profile.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form)
            })
            .then(r => r.json())
            .then(res => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                }
                if (res.success) {
                    if (modalEl) {
                        bootstrap.Modal.getInstance(modalEl)?.hide();
                    }
                    form.reset();
                    showToast('Password changed successfully!');
                } else {
                    const msg = res.error || 'Failed to update password.';
                    if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
                    showToast(msg, 'error');
                }
            })
            .catch(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                }
                const msg = 'Network error. Please try again.';
                if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
                showToast(msg, 'error');
            });
        }
    });
})();
</script>
