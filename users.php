<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_roles(['super_admin', 'admin']);
require_once __DIR__ . '/config/database.php';

$pdo = database_connection();
$actorId = (int) $_SESSION['user_id'];
$actorRole = current_role();
$isSuperAdmin = $actorRole === 'super_admin';

$roleLabels = [
    'super_admin' => 'سوپر ادمین',
    'admin' => 'ادمین',
    'user' => 'کاربر',
];
$error = '';
$success = '';

if (isset($_SESSION['users_flash']) && is_array($_SESSION['users_flash'])) {
    $success = (string) ($_SESSION['users_flash']['success'] ?? '');
    $error = (string) ($_SESSION['users_flash']['error'] ?? '');
    unset($_SESSION['users_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirm'] ?? '');
            $requestedRole = (string) ($_POST['role'] ?? 'user');
            $allowedRoles = $isSuperAdmin ? ['super_admin', 'admin', 'user'] : ['admin', 'user'];

            if (!preg_match('/^[a-z0-9_.-]{3,80}$/', $username)) {
                throw new RuntimeException('نام کاربری باید ۳ تا ۸۰ حرف انگلیسی کوچک، عدد، نقطه، خط تیره یا زیرخط باشد.');
            }
            if (!in_array($requestedRole, $allowedRoles, true)) {
                throw new RuntimeException('شما اجازه ساخت این نوع حساب را ندارید.');
            }
            if (strlen($password) < 8 || strlen($password) > 4096) {
                throw new RuntimeException('رمز عبور باید حداقل ۸ کاراکتر داشته باشد.');
            }
            if (!hash_equals($password, $confirmation)) {
                throw new RuntimeException('رمز عبور و تکرار آن یکسان نیستند.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO users (username, password_hash, role, is_active) VALUES (:username, :password_hash, :role, 1)'
            );
            $statement->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $requestedRole,
            ]);
            $_SESSION['users_flash'] = ['success' => 'حساب کاربری با موفقیت ساخته شد.'];
        } elseif ($action === 'update') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $newUsername = strtolower(trim((string)($_POST['username'] ?? '')));
            $newRole = (string)($_POST['role'] ?? 'user');
            $newPassword = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['password_confirm'] ?? '');
            if (!$targetId) throw new RuntimeException('شناسه حساب معتبر نیست.');
            if (!preg_match('/^[a-z0-9_.-]{3,80}$/', $newUsername)) {
                throw new RuntimeException('نام کاربری باید ۳ تا ۸۰ حرف انگلیسی کوچک، عدد، نقطه، خط تیره یا زیرخط باشد.');
            }
            $targetStmt = $pdo->prepare('SELECT id, username, role, is_active FROM users WHERE id = :id LIMIT 1');
            $targetStmt->execute(['id' => $targetId]);
            $target = $targetStmt->fetch();
            if (!$target) throw new RuntimeException('حساب کاربری پیدا نشد.');
            if (!$isSuperAdmin && $target['role'] === 'super_admin') {
                throw new RuntimeException('فقط سوپر ادمین می‌تواند حساب سوپر ادمین را ویرایش کند.');
            }
            $allowedRoles = $isSuperAdmin ? ['super_admin', 'admin', 'user'] : ['admin', 'user'];
            if (!in_array($newRole, $allowedRoles, true)) {
                throw new RuntimeException('شما اجازه تعیین این نوع حساب را ندارید.');
            }
            if ($target['role'] === 'super_admin' && $newRole !== 'super_admin') {
                $activeSuperAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
                if ((int)$target['is_active'] === 1 && $activeSuperAdmins <= 1) {
                    throw new RuntimeException('نمی‌توان نقش آخرین سوپر ادمین فعال را تغییر داد.');
                }
            }
            if ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 4096)) {
                throw new RuntimeException('رمز عبور جدید باید حداقل ۸ کاراکتر داشته باشد.');
            }
            if ($newPassword !== '' && !hash_equals($newPassword, $confirmPassword)) {
                throw new RuntimeException('رمز عبور جدید و تکرار آن یکسان نیستند.');
            }
            $duplicateStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username AND id <> :id');
            $duplicateStmt->execute(['username' => $newUsername, 'id' => $targetId]);
            if ((int)$duplicateStmt->fetchColumn() > 0) {
                throw new RuntimeException('این نام کاربری قبلاً استفاده شده است.');
            }
            if ($newPassword !== '') {
                $updateStmt = $pdo->prepare('UPDATE users SET username = :username, role = :role, password_hash = :password_hash WHERE id = :id');
                $updateStmt->execute(['username' => $newUsername, 'role' => $newRole, 'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $targetId]);
            } else {
                $updateStmt = $pdo->prepare('UPDATE users SET username = :username, role = :role WHERE id = :id');
                $updateStmt->execute(['username' => $newUsername, 'role' => $newRole, 'id' => $targetId]);
            }
            if ($targetId === $actorId) {
                $_SESSION['username'] = $newUsername;
                if ($newRole !== $actorRole) {
                    $_SESSION['users_flash'] = ['success' => 'حساب شما به‌روزرسانی شد. برای اعمال نقش جدید، دوباره وارد شوید.'];
                } else {
                    $_SESSION['users_flash'] = ['success' => 'حساب کاربری با موفقیت به‌روزرسانی شد.'];
                }
            } else {
                $_SESSION['users_flash'] = ['success' => 'حساب کاربری با موفقیت به‌روزرسانی شد.'];
            }
        } elseif ($action === 'toggle') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$targetId || $targetId === $actorId) {
                throw new RuntimeException('نمی‌توانید حساب خودتان را از این بخش غیرفعال کنید.');
            }
            $statement = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $targetId]);
            $target = $statement->fetch();
            if (!$target) {
                throw new RuntimeException('حساب کاربری پیدا نشد.');
            }
            if (!$isSuperAdmin && $target['role'] === 'super_admin') {
                throw new RuntimeException('فقط سوپر ادمین می‌تواند این حساب را مدیریت کند.');
            }
            $newStatus = (int) $target['is_active'] === 1 ? 0 : 1;
            if ($newStatus === 0 && $target['role'] === 'super_admin') {
                $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
                if ($count <= 1) {
                    throw new RuntimeException('نمی‌توان آخرین سوپر ادمین فعال را غیرفعال کرد.');
                }
            }
            $statement = $pdo->prepare('UPDATE users SET is_active = :active WHERE id = :id');
            $statement->execute(['active' => $newStatus, 'id' => $targetId]);
            $_SESSION['users_flash'] = ['success' => $newStatus ? 'حساب فعال شد.' : 'حساب غیرفعال شد.'];
        } elseif ($action === 'delete') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$targetId || $targetId === $actorId) {
                throw new RuntimeException('نمی‌توانید حساب خودتان را حذف کنید.');
            }
            $statement = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $targetId]);
            $target = $statement->fetch();
            if (!$target) {
                throw new RuntimeException('حساب کاربری پیدا نشد.');
            }
            if (!$isSuperAdmin && $target['role'] === 'super_admin') {
                throw new RuntimeException('فقط سوپر ادمین می‌تواند این حساب را مدیریت کند.');
            }
            if ($target['role'] === 'super_admin') {
                $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
                if ($count <= 1) {
                    throw new RuntimeException('نمی‌توان آخرین سوپر ادمین را حذف کرد.');
                }
            }
            $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $targetId]);
            $_SESSION['users_flash'] = ['success' => 'حساب کاربری حذف شد.'];
        } else {
            throw new RuntimeException('درخواست نامعتبر است.');
        }
    } catch (RuntimeException $exception) {
        $_SESSION['users_flash'] = ['error' => $exception->getMessage()];
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            $_SESSION['users_flash'] = ['error' => 'این نام کاربری قبلاً استفاده شده است.'];
        } else {
            error_log('Student ID Generator user management error: ' . $exception->getMessage());
            $_SESSION['users_flash'] = ['error' => 'ذخیره تغییرات انجام نشد. تنظیمات پایگاه داده را بررسی کنید.'];
        }
    }

    header('Location: users.php');
    exit;
}

$editUser = null;
if (isset($_GET['edit'])) {
    $editUserId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editUserId) {
        $editStmt = $pdo->prepare('SELECT id, username, role, is_active FROM users WHERE id = :id LIMIT 1');
        $editStmt->execute(['id' => $editUserId]);
        $editUser = $editStmt->fetch() ?: null;
        if ($editUser && !$isSuperAdmin && $editUser['role'] === 'super_admin') {
            $editUser = null;
            $error = 'فقط سوپر ادمین می‌تواند حساب سوپر ادمین را ویرایش کند.';
        }
    }
}

$usersQuery = $pdo->query('SELECT id, username, role, is_active, created_at FROM users ORDER BY id ASC');
$allUsers = $usersQuery->fetchAll();

$username = (string) $_SESSION['username'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مدیریت کاربران | سامانه مدیریت دانش‌آموزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
<style>
/* School identity in the top of the sidebar */
.admin-sidebar .sidebar-brand{align-items:center;}
.admin-sidebar .sidebar-school-logo{width:48px;height:48px;min-width:48px;object-fit:contain;border-radius:10px;background:#fff;padding:4px;box-sizing:border-box;}
.admin-sidebar .sidebar-system-clock{display:flex;flex-direction:column;align-items:center;gap:4px;margin:12px 12px 18px;padding:10px 8px;border:1px solid rgba(255,255,255,.16);border-radius:10px;background:rgba(255,255,255,.07);text-align:center;direction:rtl;}
.admin-sidebar .sidebar-clock-date{font-size:12px;line-height:1.7;color:#dbeafe;}
.admin-sidebar .sidebar-clock-time{font-size:20px;font-weight:700;letter-spacing:1px;line-height:1.4;color:#fff;font-variant-numeric:tabular-nums;}

/* Polished user list: readable columns, calm row separation, and compact actions. */
.user-list-panel{margin-top:24px;padding:0;overflow:hidden}
.user-list-panel>h2{display:flex;align-items:center;gap:10px;margin:0;padding:22px 24px;border-bottom:1px solid var(--line);font-size:1.2rem}
.user-list-panel>h2::before{content:"";width:4px;height:25px;border-radius:4px;background:var(--blue)}
.user-table-wrap{width:100%;overflow-x:auto}
.user-table{width:100%;min-width:760px;border-collapse:separate;border-spacing:0;text-align:right;white-space:nowrap}
.user-table th,.user-table td{padding:15px 18px;border-bottom:1px solid #edf0f5;vertical-align:middle}
.user-table th{background:#f4f7fc;color:#53627a;font-size:.8rem;font-weight:800}
.user-table td{font-size:.86rem;color:#26354d}
.user-table tbody tr{transition:background .15s ease}
.user-table tbody tr:hover{background:#f8faff}
.user-table tbody tr:last-child td{border-bottom:0}
.user-table th:first-child,.user-table td:first-child{width:80px;color:#68758b;font-variant-numeric:tabular-nums}
.user-table td:nth-child(2){font-weight:800;color:#172b4d}
.user-table td:nth-child(3){font-weight:700}
.user-actions{display:flex;align-items:center;flex-wrap:wrap;gap:7px}
.user-actions form{margin:0}
.user-actions .button{min-height:34px;padding:6px 11px;border-radius:8px;font-size:.76rem;white-space:nowrap}
.user-actions .button-danger{border:1px solid #f3cccc}
.user-list-panel>.help-text{margin:0;padding:13px 24px;background:#fafbfe;border-top:1px solid var(--line);font-size:.78rem}
@media(max-width:760px){.user-list-panel>h2{padding:18px}.user-table th,.user-table td{padding:12px 14px}.user-list-panel>.help-text{padding:12px 18px}}

</style>
</head>
<body class="profile-page">
<div class="admin-layout">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="باز کردن منو"><span class="sidebar-toggle-icon" aria-hidden="true">☰</span><span class="sidebar-toggle-label">منو</span></button>
        <?php
$sidebarSchoolLogo = '';
try {
    require_once __DIR__ . '/config/database.php';
    $sidebarSettingsStmt = database_connection()->query('SELECT school_name, logo_path FROM school_configuration WHERE id = 1 LIMIT 1');
    $sidebarSchoolSettings = $sidebarSettingsStmt->fetch() ?: [];
    $sidebarSchoolLogo = (string) ($sidebarSchoolSettings['logo_path'] ?? '');
    $sidebarSchoolName = trim((string) ($sidebarSchoolSettings['school_name'] ?? ''));
} catch (Throwable $sidebarSettingsError) {
    $sidebarSchoolLogo = '';
    $sidebarSchoolName = '';
}
?>
<a class="sidebar-brand" href="dashboard.php">
    <?php if ($sidebarSchoolLogo !== ''): ?>
        <img class="sidebar-school-logo" src="<?= htmlspecialchars($sidebarSchoolLogo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="لوگوی مدرسه">
    <?php else: ?>
        <span class="sidebar-brand-mark" aria-hidden="true">م‌د</span>
    <?php endif; ?>
    <span><strong><?= htmlspecialchars($sidebarSchoolName !== '' ? $sidebarSchoolName : 'پنل مدیریت', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><small>سامانه مدیریت دانش‌آموزان</small></span>
</a>

<div class="sidebar-system-clock" aria-label="تاریخ و ساعت فعلی سیستم">
    <span class="sidebar-clock-date" data-sidebar-date></span>
    <span class="sidebar-clock-time" data-sidebar-time></span>
</div>
        <p class="sidebar-section-label">منوی اصلی</p>
        <nav class="sidebar-nav">
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'is-current' : '' ?>" href="dashboard.php"><span class="sidebar-icon" aria-hidden="true">⌂</span><span>داشبورد</span></a>

    <div class="sidebar-group">
        <button type="button" class="sidebar-group-title <?= basename($_SERVER['PHP_SELF']) === 'students.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">♙</span><span>مدیریت دانش‌آموزان</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'students.php' ? 'is-current' : '' ?>" href="students.php">فهرست و ثبت دانش‌آموزان</a>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">انتقال و ارتقای صنف <small>به‌زودی</small></span>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">تاریخچه تحصیلی <small>به‌زودی</small></span>
        </div>
    </div>

    <div class="sidebar-group">
        <button type="button" class="sidebar-group-title" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">♟</span><span>مدیریت آموزگاران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
        <div class="sidebar-submenu">
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">فهرست آموزگاران <small>به‌زودی</small></span>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">ثبت آموزگار <small>به‌زودی</small></span>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">تعیین صنف مسئول <small>به‌زودی</small></span>
        </div>
    </div>

    <div class="sidebar-group">
        <button type="button" class="sidebar-group-title <?= in_array(basename($_SERVER['PHP_SELF']), ['school.php', 'school_configuration.php'], true) ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">▦</span><span>تنظیمات مدرسه</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'school_configuration.php' ? 'is-current' : '' ?>" href="school_configuration.php">مشخصات مدرسه</a>
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'school.php' ? 'is-current' : '' ?>" href="school.php">سال تحصیلی</a>
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'classes.php' ? 'is-current' : '' ?>" href="classes.php">مدیریت صنف‌ها</a>
        </div>
    </div>

    <?php if (in_array(current_role(), ['super_admin', 'admin'], true)): ?>
    <div class="sidebar-group">
        <button type="button" class="sidebar-group-title <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">⚙</span><span>مدیریت کاربران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'is-current' : '' ?>" href="users.php">لیست کاربران</a>
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'add_user.php' ? 'is-current' : '' ?>" href="add_user.php">افزودن کاربر</a>
        </div>
    </div>
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'is-current' : '' ?>" href="#" aria-disabled="true" title="بخش گزارشات بعداً اضافه می‌شود"><span class="sidebar-icon" aria-hidden="true">▤</span><span>گزارشات</span></a>
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'database_updates.php' ? 'is-current' : '' ?>" href="database_updates.php"><span class="sidebar-icon" aria-hidden="true">↻</span><span>به‌روزرسانی پایگاه داده</span></a>
    <?php endif; ?>
</nav>
        <div class="sidebar-bottom">
            <span class="sidebar-user-avatar" aria-hidden="true"><?= escape(substr($username, 0, 1)) ?></span>
            <div class="sidebar-user-copy"><strong><?= escape($username) ?></strong><small><?= escape($roleLabels[$actorRole] ?? $actorRole) ?></small></div>
            <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><button class="sidebar-logout" type="submit" aria-label="خروج از حساب" title="خروج از حساب">↪</button></form>
        </div>
    </aside>

    <main class="admin-main">
        <header class="page-header dashboard-header">
            <div><p class="eyebrow">پنل مدیریت</p><h1>لیست کاربران</h1><p class="muted">تمام حساب‌های کاربری، ادمین‌ها و سوپر ادمین‌ها به ترتیب شناسه نمایش داده می‌شوند.</p></div>
            <div class="account-actions"><span class="signed-in-as">نقش شما: <strong><?= escape($roleLabels[$actorRole] ?? $actorRole) ?></strong></span><a class="button button-secondary" href="dashboard.php">بازگشت به پروفایل</a></div>
        </header>

        <?php if ($success !== ''): ?><div class="user-alert user-alert-success" role="status"><?= escape($success) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="user-alert user-alert-error" role="alert"><?= escape($error) ?></div><?php endif; ?>

        <?php if ($editUser): ?>
        <section class="panel user-create-panel" aria-labelledby="edit-user-title">
            <h2 id="edit-user-title">ویرایش حساب: <?= escape($editUser['username']) ?></h2>
            <p class="muted">نام کاربری و نوع حساب را تغییر دهید. برای حفظ رمز فعلی، فیلدهای رمز را خالی بگذارید.</p>
            <form method="post" action="users.php" class="user-create-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
                <label for="edit-username">نام کاربری</label>
                <input id="edit-username" name="username" required minlength="3" maxlength="80" pattern="[a-zA-Z0-9_.-]+" autocomplete="off" value="<?= escape($editUser['username']) ?>">
                <label for="edit-role">نوع حساب</label>
                <select id="edit-role" name="role" required>
                    <option value="user" <?= $editUser['role'] === 'user' ? 'selected' : '' ?>>کاربر</option>
                    <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>ادمین</option>
                    <?php if ($isSuperAdmin): ?><option value="super_admin" <?= $editUser['role'] === 'super_admin' ? 'selected' : '' ?>>سوپر ادمین</option><?php endif; ?>
                </select>
                <label for="edit-password">رمز عبور جدید (اختیاری، حداقل ۸ کاراکتر)</label>
                <input id="edit-password" name="password" type="password" minlength="8" maxlength="4096" autocomplete="new-password">
                <label for="edit-password-confirm">تکرار رمز عبور جدید</label>
                <input id="edit-password-confirm" name="password_confirm" type="password" minlength="8" maxlength="4096" autocomplete="new-password">
                <div class="form-actions"><button class="button button-primary" type="submit">ذخیره تغییرات</button> <a class="button button-secondary" href="users.php">انصراف</a></div>
            </form>
        </section>
        <?php endif; ?>

        <section class="panel user-list-panel" aria-labelledby="users-list-title">
            <h2 id="users-list-title">تمام کاربران (<?= count($allUsers) ?>)</h2>
            <div class="user-table-wrap">
                <table class="user-table">
                    <thead><tr><th>شناسه</th><th>نام کاربری</th><th>نوع حساب</th><th>وضعیت</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php if (!$allUsers): ?><tr><td colspan="6">هیچ حسابی ثبت نشده است.</td></tr><?php endif; ?>
                    <?php foreach ($allUsers as $listedUser): ?>
                        <tr>
                            <td><?= (int)$listedUser['id'] ?></td>
                            <td><?= escape($listedUser['username']) ?></td>
                            <td><?= escape($roleLabels[$listedUser['role']] ?? $listedUser['role']) ?></td>
                            <td><span class="account-state <?= (int)$listedUser['is_active'] === 1 ? 'state-active' : 'state-inactive' ?>"><?= (int)$listedUser['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></td>
                            <td><?= escape($listedUser['created_at'] ?? '—') ?></td>
                            <td><div class="user-actions">
                                <?php if ($isSuperAdmin || $listedUser['role'] !== 'super_admin'): ?>
                                <a class="button button-primary" href="users.php?edit=<?= (int)$listedUser['id'] ?>">ویرایش</a>
                                <?php endif; ?>
                                <?php if ((int)$listedUser['id'] !== $actorId): ?>
                                <form method="post" action="users.php" onsubmit="return confirm('آیا از تغییر وضعیت این حساب مطمئن هستید؟');">
                                    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$listedUser['id'] ?>">
                                    <button class="button button-secondary" type="submit"><?= (int)$listedUser['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button>
                                </form>
                                <form method="post" action="users.php" onsubmit="return confirm('این حساب برای همیشه حذف شود؟');">
                                    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$listedUser['id'] ?>">
                                    <button class="button button-danger" type="submit">حذف</button>
                                </form>
                                <?php else: ?><span>حساب فعلی</span><?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="help-text">فهرست بدون محدودیت تعداد، از شناسه کوچک به بزرگ مرتب شده است.</p>
        </section>

        <footer class="page-footer">سامانه مدیریت دانش‌آموزان · مدیریت کاربران</footer>
    </main>
</div>
<script>
(function () {
    const sidebar = document.getElementById('admin-sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    if (!sidebar || !toggle) return;
    toggle.addEventListener('click', function () {
        const isOpen = sidebar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'بستن منو' : 'باز کردن منو');
        toggle.querySelector('.sidebar-toggle-icon').textContent = isOpen ? '×' : '☰';
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            sidebar.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'باز کردن منو');
            toggle.querySelector('.sidebar-toggle-icon').textContent = '☰';
            toggle.focus();
        }
    });
}());
</script>
    <script src="assets/js/sidebar-clock.js" defer></script>
    <script src="assets/js/sidebar-submenus.js" defer></script>
</body>
</html>
