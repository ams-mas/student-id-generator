<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_roles(['super_admin', 'admin']);
require_once __DIR__ . '/config/database.php';

$pdo = database_connection();
$error = '';
$success = '';
function school_config_h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$defaults = [
    'school_name' => 'نام مدرسه',
    'school_address' => '',
    'school_email' => '',
    'school_phone_1' => '',
    'school_phone_2' => '',
    'logo_path' => null,
];
$stmt = $pdo->query('SELECT * FROM school_configuration WHERE id = 1 LIMIT 1');
$settings = $stmt->fetch() ?: $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('درخواست معتبر نیست. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.');
    }
    try {
        $name = trim((string)($_POST['school_name'] ?? ''));
        $address = trim((string)($_POST['school_address'] ?? ''));
        $email = trim((string)($_POST['school_email'] ?? ''));
        $phone1 = trim((string)($_POST['school_phone_1'] ?? ''));
        $phone2 = trim((string)($_POST['school_phone_2'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) {
            throw new RuntimeException('نام مدرسه الزامی است و نباید بیشتر از ۱۸۰ نویسه باشد.');
        }
        if (mb_strlen($address) > 500 || mb_strlen($phone1) > 50 || mb_strlen($phone2) > 50) {
            throw new RuntimeException('آدرس یا شماره تلفن بیش از حد طولانی است.');
        }
        if ($email !== '' && (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('آدرس ایمیل معتبر نیست.');
        }

        $oldLogo = (string)($settings['logo_path'] ?? '');
        $logoPath = $oldLogo !== '' ? $oldLogo : null;
        $upload = $_FILES['school_logo'] ?? null;
        if (is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)$upload['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('بارگذاری لوگو انجام نشد. دوباره تلاش کنید.');
            }
            if ((int)$upload['size'] > 3 * 1024 * 1024) {
                throw new RuntimeException('حجم لوگو نباید بیشتر از ۳ مگابایت باشد.');
            }
            $tmp = (string)($upload['tmp_name'] ?? '');
            if (!is_uploaded_file($tmp)) {
                throw new RuntimeException('فایل بارگذاری‌شده معتبر نیست.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp);
            $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
            if (!isset($extensions[$mime])) {
                throw new RuntimeException('فرمت لوگو باید JPG، PNG یا WEBP باشد.');
            }
            $uploadDir = __DIR__ . '/uploads/school-logo';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('پوشه ذخیره لوگو ساخته نشد. دسترسی پوشه uploads را بررسی کنید.');
            }
            $fileName = 'school-logo-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($tmp, $uploadDir . '/' . $fileName)) {
                throw new RuntimeException('ذخیره لوگو انجام نشد.');
            }
            $logoPath = 'uploads/school-logo/' . $fileName;
        }

        $save = $pdo->prepare('INSERT INTO school_configuration (id, school_name, school_address, school_email, school_phone_1, school_phone_2, logo_path) VALUES (1, :name, :address, :email, :phone1, :phone2, :logo) ON DUPLICATE KEY UPDATE school_name=VALUES(school_name), school_address=VALUES(school_address), school_email=VALUES(school_email), school_phone_1=VALUES(school_phone_1), school_phone_2=VALUES(school_phone_2), logo_path=VALUES(logo_path)');
        $save->execute([
            'name'=>$name, 'address'=>$address !== '' ? $address : null,
            'email'=>$email !== '' ? $email : null, 'phone1'=>$phone1 !== '' ? $phone1 : null,
            'phone2'=>$phone2 !== '' ? $phone2 : null, 'logo'=>$logoPath,
        ]);
        if ($oldLogo !== '' && $logoPath !== $oldLogo && str_starts_with($oldLogo, 'uploads/school-logo/')) {
            $oldPath = __DIR__ . '/' . $oldLogo;
            if (is_file($oldPath)) @unlink($oldPath);
        }
        $success = 'مشخصات مدرسه با موفقیت ذخیره شد.';
        $settings = $pdo->query('SELECT * FROM school_configuration WHERE id = 1 LIMIT 1')->fetch() ?: $defaults;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('School configuration save failed: ' . $e->getMessage());
        $error = 'ذخیره تنظیمات انجام نشد. اجرای به‌روزرسانی پایگاه داده را بررسی کنید.';
    }
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>مشخصات مدرسه | سامانه مدیریت دانش‌آموزان</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
/* School identity in the top of the sidebar */
.admin-sidebar .sidebar-brand{align-items:center;}
.admin-sidebar .sidebar-school-logo{width:48px;height:48px;min-width:48px;object-fit:contain;border-radius:10px;background:#fff;padding:4px;box-sizing:border-box;}
.admin-sidebar .sidebar-system-clock{display:flex;flex-direction:column;align-items:center;gap:4px;margin:12px 12px 18px;padding:10px 8px;border:1px solid rgba(255,255,255,.16);border-radius:10px;background:rgba(255,255,255,.07);text-align:center;direction:rtl;}
.admin-sidebar .sidebar-clock-date{font-size:12px;line-height:1.7;color:#dbeafe;}
.admin-sidebar .sidebar-clock-time{font-size:20px;font-weight:700;letter-spacing:1px;line-height:1.4;color:#fff;font-variant-numeric:tabular-nums;}
</style>
<style>
.school-config-wrap{max-width:1000px;margin:28px auto;padding:0 18px}
.school-config-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin:18px 0;box-shadow:0 6px 24px rgba(15,23,42,.04)}
.school-config-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.school-config-field{min-width:0}
.school-config-field label{display:block;margin-bottom:7px;font-weight:700;font-size:14px}
.school-config-field input,.school-config-field textarea{box-sizing:border-box;width:100%;min-height:44px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;color:#1e293b;background:#fff}
.school-config-field textarea{min-height:100px;resize:vertical}
.school-config-wide{grid-column:1/-1}
.school-config-logo{display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.school-config-logo img{width:132px;height:132px;object-fit:contain;border:1px dashed #cbd5e1;border-radius:12px;padding:8px;background:#f8fafc}
.school-config-help{display:block;color:#64748b;font-size:12px;margin-top:6px;line-height:1.7}
.school-config-alert{padding:13px 16px;border-radius:10px;margin:14px 0}
.school-config-success{background:#dcfce7;color:#166534}
.school-config-error{background:#fee2e2;color:#991b1b}
.school-config-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
@media(max-width:650px){.school-config-grid{grid-template-columns:1fr}.school-config-wide{grid-column:auto}.school-config-panel{padding:17px}}
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
<a class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'is-current' : '' ?>" href="dashboard.php"><span class="sidebar-icon" aria-hidden="true">⌂</span><span>داشبورد</span></a>
<div class="sidebar-group">
<button type="button" class="sidebar-group-title <?= $currentPage === 'students.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">♙</span><span>مدیریت دانش‌آموزان</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
<div class="sidebar-submenu"><a class="sidebar-link <?= $currentPage === 'students.php' ? 'is-current' : '' ?>" href="students.php">فهرست و ثبت دانش‌آموزان</a><span class="sidebar-link sidebar-link-disabled" aria-disabled="true">انتقال و ارتقای صنف <small>به‌زودی</small></span><span class="sidebar-link sidebar-link-disabled" aria-disabled="true">تاریخچه تحصیلی <small>به‌زودی</small></span></div>
</div>
<div class="sidebar-group">
<button type="button" class="sidebar-group-title" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">♟</span><span>مدیریت آموزگاران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
<div class="sidebar-submenu"><span class="sidebar-link sidebar-link-disabled" aria-disabled="true">فهرست آموزگاران <small>به‌زودی</small></span><span class="sidebar-link sidebar-link-disabled" aria-disabled="true">ثبت آموزگار <small>به‌زودی</small></span><span class="sidebar-link sidebar-link-disabled" aria-disabled="true">تعیین صنف مسئول <small>به‌زودی</small></span></div>
</div>
<div class="sidebar-group">
<button type="button" class="sidebar-group-title <?= in_array($currentPage, ['school.php','school_configuration.php'], true) ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">▦</span><span>تنظیمات مدرسه</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
<div class="sidebar-submenu"><a class="sidebar-link <?= $currentPage === 'school_configuration.php' ? 'is-current' : '' ?>" href="school_configuration.php">مشخصات مدرسه</a><a class="sidebar-link <?= $currentPage === 'school.php' ? 'is-current' : '' ?>" href="school.php">سال تحصیلی</a><a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'classes.php' ? 'is-current' : '' ?>" href="classes.php">مدیریت صنف‌ها</a></div>
</div>
<?php if (in_array(current_role(), ['super_admin','admin'], true)): ?>
<div class="sidebar-group">
<button type="button" class="sidebar-group-title <?= $currentPage === 'users.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">⚙</span><span>مدیریت کاربران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
<div class="sidebar-submenu">
            <a class="sidebar-link <?= $currentPage === 'users.php' ? 'is-current' : '' ?>" href="users.php">لیست کاربران</a>
            <a class="sidebar-link <?= $currentPage === 'add_user.php' ? 'is-current' : '' ?>" href="add_user.php">افزودن کاربر</a>
        </div>
</div>
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'is-current' : '' ?>" href="#" aria-disabled="true" title="بخش گزارشات بعداً اضافه می‌شود"><span class="sidebar-icon" aria-hidden="true">▤</span><span>گزارشات</span></a>
<a class="sidebar-link <?= $currentPage === 'database_updates.php' ? 'is-current' : '' ?>" href="database_updates.php"><span class="sidebar-icon" aria-hidden="true">↻</span><span>به‌روزرسانی پایگاه داده</span></a>
<?php endif; ?>
</nav>
<div class="sidebar-bottom"><span class="sidebar-user-avatar" aria-hidden="true"><?= school_config_h(substr((string)($_SESSION['username'] ?? ''),0,1)) ?></span><div class="sidebar-user-copy"><strong><?= school_config_h((string)($_SESSION['username'] ?? '')) ?></strong><small><?= school_config_h(current_role() === 'super_admin' ? 'سوپر ادمین' : 'ادمین') ?></small></div><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= school_config_h(csrf_token()) ?>"><button class="sidebar-logout" type="submit" aria-label="خروج از حساب" title="خروج از حساب">↪</button></form></div>
</aside>
<main class="admin-main">
<div class="school-config-wrap">
<a href="dashboard.php" class="button button-secondary">بازگشت به داشبورد</a>
<section class="school-config-panel">
<p class="eyebrow">تنظیمات مدرسه</p><h1>مشخصات مدرسه</h1>
<p>نام، لوگو و اطلاعات تماس مدرسه را ثبت کنید. هر نصب جداگانه از سامانه، مشخصات مدرسه خودش را نگه می‌دارد.</p>
<?php if ($success !== ''): ?><div class="school-config-alert school-config-success" role="status"><?= school_config_h($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="school-config-alert school-config-error" role="alert"><?= school_config_h($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= school_config_h(csrf_token()) ?>">
<div class="school-config-grid">
<div class="school-config-field school-config-wide"><label for="school_name">نام مدرسه *</label><input id="school_name" name="school_name" maxlength="180" required value="<?= school_config_h($settings['school_name'] ?? '') ?>" placeholder="مثلاً لیسه معرفت"></div>
<div class="school-config-field school-config-wide"><label for="school_address">آدرس مدرسه</label><textarea id="school_address" name="school_address" maxlength="500" placeholder="ولایت، شهر، ناحیه و آدرس دقیق"><?= school_config_h($settings['school_address'] ?? '') ?></textarea></div>
<div class="school-config-field"><label for="school_email">ایمیل مدرسه</label><input type="email" id="school_email" name="school_email" maxlength="190" value="<?= school_config_h($settings['school_email'] ?? '') ?>" placeholder="info@example.com"></div>
<div class="school-config-field"><label for="school_phone_1">شماره تلفن اول</label><input type="tel" id="school_phone_1" name="school_phone_1" maxlength="50" value="<?= school_config_h($settings['school_phone_1'] ?? '') ?>" placeholder="+93 ..."></div>
<div class="school-config-field"><label for="school_phone_2">شماره تلفن دوم (اختیاری)</label><input type="tel" id="school_phone_2" name="school_phone_2" maxlength="50" value="<?= school_config_h($settings['school_phone_2'] ?? '') ?>" placeholder="+93 ..."></div>
<div class="school-config-field school-config-wide"><label for="school_logo">لوگوی مدرسه</label><div class="school-config-logo">
<?php if (!empty($settings['logo_path'])): ?><img src="<?= school_config_h($settings['logo_path']) ?>" alt="لوگوی فعلی مدرسه"><?php else: ?><img src="assets/images/school-logo-placeholder.svg" alt="جایگاه لوگوی مدرسه"><?php endif; ?>
<div><input type="file" id="school_logo" name="school_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="school-config-help">فرمت‌های مجاز: JPG، PNG و WEBP. حداکثر حجم: ۳ مگابایت. برای نگه‌داشتن لوگوی فعلی، فایل جدید انتخاب نکنید.</small></div>
</div></div>
</div>
<div class="school-config-actions"><button class="button button-primary" type="submit">ذخیره مشخصات مدرسه</button><a href="school.php" class="button button-secondary">تنظیم سال تحصیلی</a></div>
</form>
</section>
</div>
</main>
</div>
<script src="assets/js/sidebar-clock.js" defer></script>
    <script src="assets/js/sidebar-submenus.js" defer></script>
</body>
</html>
