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
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">صنف‌ها و بخش‌ها <small>به‌زودی</small></span>
        </div>
    </div>

    <?php if (in_array(current_role(), ['super_admin', 'admin'], true)): ?>
    <div class="sidebar-group">
        <button type="button" class="sidebar-group-title <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">⚙</span><span>مدیریت کاربران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'is-current' : '' ?>" href="users.php">فهرست و افزودن کاربران</a>
        </div>
    </div>
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
            <div><p class="eyebrow">پنل مدیریت</p><h1>مدیریت کاربران</h1><p class="muted">ساخت حساب و تعیین سطح دسترسی کاربران سامانه.</p></div>
            <div class="account-actions"><span class="signed-in-as">نقش شما: <strong><?= escape($roleLabels[$actorRole] ?? $actorRole) ?></strong></span><a class="button button-secondary" href="dashboard.php">بازگشت به پروفایل</a></div>
        </header>

        <?php if ($success !== ''): ?><div class="user-alert user-alert-success" role="status"><?= escape($success) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="user-alert user-alert-error" role="alert"><?= escape($error) ?></div><?php endif; ?>

        <section class="panel user-create-panel" aria-labelledby="create-user-title">
            <h2 id="create-user-title">افزودن حساب جدید</h2>
            <p class="muted">نام کاربری و رمز عبور را تعیین کنید و نوع حساب را انتخاب کنید.</p>
            <form method="post" action="users.php" class="user-create-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <label for="new-username">نام کاربری</label>
                <input id="new-username" name="username" required minlength="3" maxlength="80" pattern="[a-zA-Z0-9_.-]+" autocomplete="off" placeholder="مثال: school.user">
                <label for="new-role">نوع حساب</label>
                <select id="new-role" name="role" required>
                    <option value="user">کاربر</option>
                    <option value="admin">ادمین</option>
                    <?php if ($isSuperAdmin): ?><option value="super_admin">سوپر ادمین</option><?php endif; ?>
                </select>
                <p class="help-text"><?= $isSuperAdmin ? 'شما می‌توانید هر سه نوع حساب را ایجاد کنید.' : 'شما می‌توانید کاربر یا ادمین ایجاد کنید؛ ساخت سوپر ادمین فقط برای سوپر ادمین مجاز است.' ?></p>
                <label for="new-password">رمز عبور (حداقل ۸ کاراکتر)</label>
                <input id="new-password" name="password" type="password" required minlength="8" maxlength="4096" autocomplete="new-password">
                <label for="new-password-confirm">تکرار رمز عبور</label>
                <input id="new-password-confirm" name="password_confirm" type="password" required minlength="8" maxlength="4096" autocomplete="new-password">
                <button class="button button-primary" type="submit">ساخت حساب</button>
            </form>
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
