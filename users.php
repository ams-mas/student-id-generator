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

$statement = $pdo->query('SELECT id, username, role, is_active, created_at FROM users ORDER BY id DESC');
$users = $statement->fetchAll();
$username = (string) $_SESSION['username'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مدیریت کاربران | سامانه کارت شناسایی</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="profile-page">
<div class="admin-layout">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
        <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="باز کردن منو"><span class="sidebar-toggle-icon" aria-hidden="true">☰</span><span class="sidebar-toggle-label">منو</span></button>
        <a class="sidebar-brand" href="dashboard.php"><span class="sidebar-brand-mark" aria-hidden="true">ID</span><span><strong>پنل مدیریت</strong><small>سامانه کارت شناسایی</small></span></a>
        <p class="sidebar-section-label">منوی اصلی</p>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="dashboard.php"><span class="sidebar-icon" aria-hidden="true">⌂</span><span>پروفایل مدیر</span></a>
            <div class="sidebar-group">
                <div class="sidebar-group-title"><span class="sidebar-icon" aria-hidden="true">♙</span><span>مدیریت کاربران</span></div>
                <div class="sidebar-submenu">
                    <a class="sidebar-link is-current" href="users.php" aria-current="page"><span>افزودن و مدیریت کاربران</span></a>
                </div>
            </div>
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

        <section class="panel user-list-panel" aria-labelledby="user-list-title">
            <h2 id="user-list-title">حساب‌های ثبت‌شده <span class="user-count"><?= count($users) ?></span></h2>
            <div class="user-table-wrap">
                <table class="user-table">
                    <thead><tr><th>شناسه</th><th>نام کاربری</th><th>نوع حساب</th><th>وضعیت</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $account): ?>
                        <?php
                        $targetId = (int) $account['id'];
                        $targetRole = (string) $account['role'];
                        $canManage = $targetId !== $actorId && ($isSuperAdmin || $targetRole !== 'super_admin');
                        ?>
                        <tr>
                            <td><?= escape((string) $targetId) ?></td>
                            <td><?= escape((string) $account['username']) ?><?= $targetId === $actorId ? ' <span class="you-label">(شما)</span>' : '' ?></td>
                            <td><?= escape($roleLabels[$targetRole] ?? $targetRole) ?></td>
                            <td><span class="account-state <?= (int) $account['is_active'] === 1 ? 'state-active' : 'state-inactive' ?>"><?= (int) $account['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span></td>
                            <td><?= escape((string) $account['created_at']) ?></td>
                            <td>
                                <?php if ($canManage): ?>
                                    <div class="user-actions">
                                        <form method="post" action="users.php">
                                            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= $targetId ?>">
                                            <button class="button button-secondary button-small" type="submit"><?= (int) $account['is_active'] === 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button>
                                        </form>
                                        <form method="post" action="users.php" onsubmit="return confirm('آیا از حذف دائمی این حساب مطمئن هستید؟');">
                                            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $targetId ?>">
                                            <button class="button button-danger button-small" type="submit">حذف</button>
                                        </form>
                                    </div>
                                <?php else: ?><span class="muted">—</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?><tr><td colspan="6">هنوز حسابی ساخته نشده است.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="help-text">برای امنیت، نمی‌توانید حساب خودتان را حذف یا غیرفعال کنید و آخرین سوپر ادمین نیز قابل حذف یا غیرفعال‌کردن نیست.</p>
        </section>
        <footer class="page-footer">سامانه ساخت کارت شناسایی · مدیریت کاربران</footer>
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
</body>
</html>
