<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$username = (string) $_SESSION['username'];
$userId = (int) $_SESSION['user_id'];
$role = current_role();
$roleLabels = ['super_admin' => 'سوپر ادمین', 'admin' => 'ادمین', 'user' => 'کاربر'];
$canManageUsers = in_array($role, ['super_admin', 'admin'], true);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="داشبورد سامانه مدیریت دانش‌آموزان">
    <title>داشبورد | سامانه مدیریت دانش‌آموزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="profile-page">
    <div class="admin-layout">
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
            <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="باز کردن منو"><span class="sidebar-toggle-icon" aria-hidden="true">☰</span><span class="sidebar-toggle-label">منو</span></button>
            <a class="sidebar-brand" href="dashboard.php">
                <span class="sidebar-brand-mark" aria-hidden="true">م‌د</span>
                <span><strong>پنل مدیریت</strong><small>سامانه مدیریت دانش‌آموزان</small></span>
            </a>

            <p class="sidebar-section-label">منوی اصلی</p>
            <nav class="sidebar-nav">
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'is-current' : '' ?>" href="dashboard.php"><span class="sidebar-icon" aria-hidden="true">⌂</span><span>داشبورد</span></a>

    <div class="sidebar-group">
        <a class="sidebar-group-title <?= basename($_SERVER['PHP_SELF']) === 'students.php' ? 'is-current' : '' ?>" href="students.php"><span class="sidebar-icon" aria-hidden="true">♙</span><span>مدیریت دانش‌آموزان</span></a>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'students.php' ? 'is-current' : '' ?>" href="students.php">فهرست و ثبت دانش‌آموزان</a>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">انتقال و ارتقای صنف <small>به‌زودی</small></span>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">تاریخچه تحصیلی <small>به‌زودی</small></span>
        </div>
    </div>

    <div class="sidebar-group">
        <span class="sidebar-group-title"><span class="sidebar-icon" aria-hidden="true">♟</span><span>مدیریت آموزگاران</span></span>
        <div class="sidebar-submenu">
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">فهرست آموزگاران <small>به‌زودی</small></span>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">ثبت آموزگار <small>به‌زودی</small></span>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">تعیین صنف مسئول <small>به‌زودی</small></span>
        </div>
    </div>

    <div class="sidebar-group">
        <a class="sidebar-group-title <?= basename($_SERVER['PHP_SELF']) === 'school.php' ? 'is-current' : '' ?>" href="school.php"><span class="sidebar-icon" aria-hidden="true">▦</span><span>تنظیمات مدرسه</span></a>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'school.php' ? 'is-current' : '' ?>" href="school.php">سال تحصیلی</a>
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">صنف‌ها و بخش‌ها <small>به‌زودی</small></span>
        </div>
    </div>

    <?php if (in_array(current_role(), ['super_admin', 'admin'], true)): ?>
    <div class="sidebar-group">
        <a class="sidebar-group-title <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'is-current' : '' ?>" href="users.php"><span class="sidebar-icon" aria-hidden="true">⚙</span><span>مدیریت کاربران</span></a>
        <div class="sidebar-submenu">
            <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'is-current' : '' ?>" href="users.php">فهرست و افزودن کاربران</a>
        </div>
    </div>
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'database_updates.php' ? 'is-current' : '' ?>" href="database_updates.php"><span class="sidebar-icon" aria-hidden="true">↻</span><span>به‌روزرسانی پایگاه داده</span></a>
    <?php endif; ?>
</nav>

            <div class="sidebar-bottom">
                <span class="sidebar-user-avatar" aria-hidden="true"><?= escape(substr($username, 0, 1)) ?></span>
                <div class="sidebar-user-copy"><strong><?= escape($username) ?></strong><small><?= escape($roleLabels[$role] ?? $role) ?></small></div>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                    <button class="sidebar-logout" type="submit" aria-label="خروج از حساب" title="خروج از حساب">↪</button>
                </form>
            </div>
        </aside>

        <main class="admin-main">
            <header class="page-header dashboard-header">
                <div>
                    <p class="eyebrow">پنل مدیریت</p>
                    <h1>پروفایل مدیر</h1>
                    <p class="muted">از این بخش پرونده‌های دانش‌آموزان و حساب‌های سامانه را مدیریت کنید.</p>
                </div>
                <div class="account-actions">
                    <span class="signed-in-as">وارد شده‌اید با نام <strong><?= escape($username) ?></strong></span>
                    <form action="logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                        <button type="submit" class="button button-secondary">خروج از حساب</button>
                    </form>
                </div>
            </header>

            <section class="profile-card panel" aria-labelledby="profile-title">
                <div class="profile-avatar" aria-hidden="true"><?= escape(substr($username, 0, 1)) ?></div>
                <div class="profile-summary">
                    <p class="eyebrow">حساب مدیر</p>
                    <h2 id="profile-title"><?= escape($username) ?></h2>
                    <p class="muted"><?= escape($roleLabels[$role] ?? $role) ?></p>
                </div>
                <span class="profile-status">فعال</span>
            </section>

            <section class="profile-details panel" aria-labelledby="details-title">
                <h2 id="details-title">اطلاعات حساب</h2>
                <dl class="profile-info-list">
                    <div><dt>نام کاربری</dt><dd><?= escape($username) ?></dd></div>
                    <div><dt>شناسه حساب</dt><dd><?= escape((string) $userId) ?></dd></div>
                    <div><dt>نوع حساب</dt><dd><?= escape($roleLabels[$role] ?? $role) ?></dd></div>
                    <div><dt>وضعیت حساب</dt><dd>فعال</dd></div>
                </dl>
                <p class="help-text">در مرحله بعد می‌توانیم نام کامل، ایمیل، تصویر پروفایل و تغییر رمز عبور را اضافه کنیم.</p>
            </section>

            <section class="profile-next panel" aria-labelledby="next-title">
                <h2 id="next-title">بخش‌های بعدی</h2>
                <p class="muted">فعلاً روی پروفایل مدیر کار می‌کنیم. بخش‌های دیگر بعداً به پنل اضافه می‌شوند.</p>
                <div class="profile-feature-list">
                    <div><span aria-hidden="true">01</span><div><strong>مدیریت پروفایل</strong><p>نام، ایمیل و تصویر مدیر</p></div></div>
                    <div><span aria-hidden="true">02</span><div><strong>تغییر رمز عبور</strong><p>به‌روزرسانی امن رمز حساب</p></div></div>
                    <div><span aria-hidden="true">03</span><div><strong>مدیریت دانش‌آموزان</strong><p>ثبت، جست‌وجو و به‌روزرسانی پرونده‌ها</p></div></div>
                </div>
            </section>

            <footer class="page-footer">سامانه مدیریت دانش‌آموزان · پنل مدیریت</footer>
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
