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
    <main class="app-shell profile-shell">
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
            <div class="profile-avatar" aria-hidden="true"><?= escape(mb_substr($username, 0, 1, 'UTF-8')) ?></div>
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
</body>
</html>
