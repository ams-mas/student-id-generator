<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$username = (string) $_SESSION['username'];
$userId = (int) $_SESSION['user_id'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="پروفایل مدیر سامانه ساخت کارت شناسایی">
    <title>پروفایل مدیر | سامانه ساخت کارت شناسایی</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="profile-page">
    <div class="admin-layout">
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
            <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="باز کردن منو"><span class="sidebar-toggle-icon" aria-hidden="true">☰</span><span class="sidebar-toggle-label">منو</span></button>
            <a class="sidebar-brand" href="dashboard.php">
                <span class="sidebar-brand-mark" aria-hidden="true">ID</span>
                <span><strong>پنل مدیریت</strong><small>سامانه کارت شناسایی</small></span>
            </a>

            <p class="sidebar-section-label">منوی اصلی</p>
            <nav class="sidebar-nav">
                <a class="sidebar-link is-current" href="dashboard.php" aria-current="page">
                    <span class="sidebar-icon" aria-hidden="true">⌂</span><span>پروفایل مدیر</span>
                </a>
                <div class="sidebar-group">
                    <div class="sidebar-group-title"><span class="sidebar-icon" aria-hidden="true">♙</span><span>مدیریت کاربران</span></div>
                    <div class="sidebar-submenu">
                        <button type="button" class="sidebar-link sidebar-link-disabled" disabled><span>افزودن کاربر</span><small>بعداً</small></button>
                        <button type="button" class="sidebar-link sidebar-link-disabled" disabled><span>حذف کاربر</span><small>بعداً</small></button>
                        <button type="button" class="sidebar-link sidebar-link-disabled" disabled><span>غیرفعال‌کردن کاربر</span><small>بعداً</small></button>
                    </div>
                </div>
            </nav>

            <div class="sidebar-bottom">
                <span class="sidebar-user-avatar" aria-hidden="true"><?= escape(substr($username, 0, 1)) ?></span>
                <div class="sidebar-user-copy"><strong><?= escape($username) ?></strong><small>حساب واردشده</small></div>
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
                    <p class="muted">به پنل مدیریت سامانه ساخت کارت شناسایی خوش آمدید.</p>
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
                    <p class="eyebrow">حساب کاربری</p>
                    <h2 id="profile-title"><?= escape($username) ?></h2>
                    <p class="muted">مدیر سامانه</p>
                </div>
                <span class="profile-status">فعال</span>
            </section>

            <section class="profile-details panel" aria-labelledby="details-title">
                <h2 id="details-title">اطلاعات حساب</h2>
                <dl class="profile-info-list">
                    <div><dt>نام کاربری</dt><dd><?= escape($username) ?></dd></div>
                    <div><dt>شناسه حساب</dt><dd><?= escape((string) $userId) ?></dd></div>
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
                    <div><span aria-hidden="true">03</span><div><strong>مدیریت کارت‌ها</strong><p>این بخش را بعداً می‌سازیم</p></div></div>
                </div>
            </section>

            <footer class="page-footer">سامانه ساخت کارت شناسایی · پنل مدیریت</footer>
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
