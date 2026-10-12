<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$username = (string) $_SESSION['username'];
$userId = (int) $_SESSION['user_id'];
$role = current_role();
$roleLabels = ['super_admin' => 'سوپر ادمین', 'admin' => 'ادمین', 'user' => 'کاربر'];
$canManageUsers = in_array($role, ['super_admin', 'admin'], true);

require_once __DIR__ . '/config/database.php';
$pdo = database_connection();
$dashboardStats = ['students' => 0, 'teachers' => 0, 'classes' => 0, 'users' => 0, 'male' => 0, 'female' => 0];
$recentStudents = [];
$classSummary = [];
$activeYearName = 'سال تحصیلی تعیین نشده';
try {
    $dashboardStats['students'] = (int) $pdo->query('SELECT COUNT(*) FROM students WHERE is_active = 1')->fetchColumn();
    $dashboardStats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    $dashboardStats['male'] = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1 AND gender = 'male'")->fetchColumn();
    $dashboardStats['female'] = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1 AND gender = 'female'")->fetchColumn();
    $recentStudents = $pdo->query('SELECT student_number, first_name, last_name, class_name, gender, created_at FROM students ORDER BY id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
    try {
        $year = $pdo->query('SELECT id, name FROM school_years WHERE is_active = 1 AND is_closed = 0 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($year) {
            $activeYearName = (string) $year['name'];
            $classStmt = $pdo->prepare('SELECT COUNT(*) FROM class_sections WHERE school_year_id = :year_id AND is_active = 1');
            $classStmt->execute(['year_id' => (int) $year['id']]);
            $dashboardStats['classes'] = (int) $classStmt->fetchColumn();
            $classStmt = $pdo->prepare('SELECT CONCAT(grade, " ", section_name) AS class_label, COUNT(se.id) AS student_count FROM class_sections cs LEFT JOIN student_enrollments se ON se.class_section_id = cs.id AND se.is_current = 1 AND se.status = "enrolled" WHERE cs.school_year_id = :year_id AND cs.is_active = 1 GROUP BY cs.id, cs.grade, cs.section_name ORDER BY cs.grade, cs.section_name LIMIT 6');
            $classStmt->execute(['year_id' => (int) $year['id']]);
            $classSummary = $classStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $yearError) {
        $dashboardStats['classes'] = (int) $pdo->query('SELECT COUNT(*) FROM class_sections WHERE is_active = 1')->fetchColumn();
    }
    try {
        $dashboardStats['teachers'] = (int) $pdo->query('SELECT COUNT(*) FROM teachers WHERE is_active = 1')->fetchColumn();
    } catch (Throwable $teacherError) {
        $dashboardStats['teachers'] = 0;
    }
} catch (Throwable $dashboardError) {
    error_log('Dashboard statistics could not be loaded: ' . $dashboardError->getMessage());
}
$genderTotal = $dashboardStats['male'] + $dashboardStats['female'];
$malePercent = $genderTotal > 0 ? (int) round($dashboardStats['male'] * 100 / $genderTotal) : 0;
$femalePercent = $genderTotal > 0 ? 100 - $malePercent : 0;
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="داشبورد سامانه مدیریت دانش‌آموزان">
    <title>داشبورد | سامانه مدیریت دانش‌آموزان</title>
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
    <a class="sidebar-link" href="#" aria-disabled="true" title="بخش مالی بعداً اضافه می‌شود"><span class="sidebar-icon" aria-hidden="true">◈</span><span>بخش مالی</span></a>
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

        <main class="admin-main dashboard-main">
            <header class="dash-heading">
                <div></div>
                <div class="dash-year"><span>سال تحصیلی فعلی</span><strong><?= htmlspecialchars($activeYearName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            </header>
            <section class="dash-stat-grid" aria-label="آمار کلی">
                <article class="dash-stat dash-blue"><span class="dash-stat-icon">♙</span><div><span>دانش‌آموزان فعال</span><strong><?= number_format($dashboardStats['students']) ?></strong><small>مجموع پرونده‌های فعال</small></div></article>
                <article class="dash-stat dash-green"><span class="dash-stat-icon">♟</span><div><span>آموزگاران فعال</span><strong><?= number_format($dashboardStats['teachers']) ?></strong><small>ثبت‌شده در سامانه</small></div></article>
                <article class="dash-stat dash-violet"><span class="dash-stat-icon">▦</span><div><span>صنف‌های فعال</span><strong><?= number_format($dashboardStats['classes']) ?></strong><small>در سال تحصیلی فعلی</small></div></article>
                <article class="dash-stat dash-orange"><span class="dash-stat-icon">⚙</span><div><span>حساب‌های فعال</span><strong><?= number_format($dashboardStats['users']) ?></strong><small>کاربران سامانه</small></div></article>
            </section>
            <section class="dash-middle-grid">
                <article class="dash-panel dash-gender-panel"><div class="dash-panel-title"><h2>ترکیب جنسیتی دانش‌آموزان</h2><span>فعال</span></div>
                    <div class="dash-gender-content"><div class="dash-donut" style="--male-share:<?= $malePercent ?>%"><div><strong><?= number_format($genderTotal) ?></strong><small>دانش‌آموز</small></div></div>
                        <div class="dash-legend"><p><i class="dash-dot dash-dot-blue"></i><span>پسر</span><strong><?= number_format($dashboardStats['male']) ?></strong><small><?= $malePercent ?>٪</small></p><p><i class="dash-dot dash-dot-green"></i><span>دختر</span><strong><?= number_format($dashboardStats['female']) ?></strong><small><?= $femalePercent ?>٪</small></p></div></div>
                </article>
                <article class="dash-panel"><div class="dash-panel-title"><h2>تعداد دانش‌آموزان هر صنف</h2><span>تا ۶ صنف</span></div>
                    <?php if ($classSummary): ?><div class="dash-bars"><?php $maxClassCount = max(1, ...array_map(static fn(array $item): int => (int) $item['student_count'], $classSummary)); foreach ($classSummary as $item): ?><div class="dash-bar-row"><span><?= htmlspecialchars((string) $item['class_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><div class="dash-bar-track"><i style="width:<?= (int) round((int) $item['student_count'] * 100 / $maxClassCount) ?>%"></i></div><strong><?= number_format((int) $item['student_count']) ?></strong></div><?php endforeach; ?></div><?php else: ?><div class="dash-empty-note">پس از ایجاد صنف و ثبت‌نام دانش‌آموزان، آمار این بخش نمایش داده می‌شود.</div><?php endif; ?>
                </article>
            </section>
            <section class="dash-panel dash-recent-panel"><div class="dash-panel-title"><h2>آخرین دانش‌آموزان ثبت‌شده</h2><a href="students.php">مشاهده فهرست <span aria-hidden="true">←</span></a></div>
                <?php if ($recentStudents): ?><div class="dash-table-wrap"><table class="dash-table"><thead><tr><th>شماره مسلسل</th><th>نام دانش‌آموز</th><th>صنف</th><th>جنسیت</th><th>تاریخ ثبت</th></tr></thead><tbody><?php foreach ($recentStudents as $student): ?><tr><td dir="ltr"><?= htmlspecialchars((string) $student['student_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= htmlspecialchars(trim((string) $student['first_name'] . ' ' . (string) $student['last_name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($student['class_name'] ?: 'تعیین نشده'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= $student['gender'] === 'male' ? 'پسر' : ($student['gender'] === 'female' ? 'دختر' : 'تعیین نشده') ?></td><td><?= !empty($student['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime((string) $student['created_at'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '—' ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="dash-empty-note">هنوز دانش‌آموزی ثبت نشده است. پس از ثبت اولین دانش‌آموز، فهرست او در این بخش ظاهر می‌شود.</div><?php endif; ?>
            </section>
            <footer class="dash-footer">سامانه مدیریت دانش‌آموزان</footer>
        </main>
        <style>
        .dashboard-main{min-width:0;padding:28px clamp(16px,2.5vw,34px);background:#f3f6fb;color:#1c2940;direction:rtl}
        .dash-heading{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:22px}.dash-heading h1{font-size:clamp(1.7rem,3vw,2.35rem);margin:4px 0}.dash-eyebrow{margin:0;color:#2d59b4;font-size:.8rem;font-weight:800}.dash-muted{margin:0;color:#748096;font-size:.9rem}.dash-year{padding:12px 16px;border:1px solid #e0e7f1;border-radius:12px;background:#fff;display:flex;flex-direction:column;gap:5px;min-width:160px}.dash-year span{font-size:.75rem;color:#78859a}.dash-year strong{font-size:.92rem}
        .dash-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.dash-stat{display:flex;align-items:center;gap:13px;min-width:0;padding:18px 15px;border-radius:14px;color:#fff;box-shadow:0 8px 22px #1e293b12}.dash-stat>div{display:flex;flex-direction:column;min-width:0;gap:4px}.dash-stat span:not(.dash-stat-icon){font-size:.82rem}.dash-stat strong{font-size:clamp(1.45rem,2.4vw,2rem);line-height:1.2}.dash-stat small{font-size:.7rem;opacity:.86}.dash-stat-icon{display:grid;place-items:center;flex:0 0 43px;width:43px;height:43px;border-radius:12px;background:#ffffff25;font-size:1.55rem}.dash-blue{background:linear-gradient(135deg,#2463c9,#1686df)}.dash-green{background:linear-gradient(135deg,#13866c,#27b58c)}.dash-violet{background:linear-gradient(135deg,#5355ad,#7779d7)}.dash-orange{background:linear-gradient(135deg,#e28b2c,#f5ad48)}
        .dash-middle-grid{display:grid;grid-template-columns:minmax(260px,.85fr) minmax(0,1.5fr);gap:16px;margin-bottom:18px}.dash-panel{min-width:0;padding:20px;border:1px solid #e5eaf2;border-radius:14px;background:#fff;box-shadow:0 5px 18px #17233b08}.dash-panel-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:17px}.dash-panel-title h2{margin:0;font-size:1rem}.dash-panel-title>span{color:#8994a7;font-size:.75rem}.dash-panel-title a{color:#2459b7;text-decoration:none;font-size:.82rem;font-weight:700}.dash-gender-content{display:flex;align-items:center;justify-content:space-around;gap:18px;min-height:190px}.dash-donut{width:145px;height:145px;flex:0 0 145px;display:grid;place-items:center;border-radius:50%;background:conic-gradient(#2876d2 0 var(--male-share),#22a784 var(--male-share) 100%);position:relative}.dash-donut:before{content:"";position:absolute;inset:19px;border-radius:50%;background:white}.dash-donut>div{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px}.dash-donut strong{font-size:1.25rem}.dash-donut small{color:#8490a4;font-size:.72rem}.dash-legend{width:100%;max-width:170px}.dash-legend p{display:grid;grid-template-columns:10px 1fr auto;align-items:center;gap:7px;margin:14px 0;font-size:.82rem}.dash-legend p small{grid-column:2 / 4;color:#7c879a}.dash-legend p strong{font-variant-numeric:tabular-nums}.dash-dot{width:9px;height:9px;border-radius:50%}.dash-dot-blue{background:#2876d2}.dash-dot-green{background:#22a784}
        .dash-bars{display:grid;gap:18px;padding:4px 0}.dash-bar-row{display:grid;grid-template-columns:75px minmax(40px,1fr) 35px;align-items:center;gap:10px;font-size:.8rem}.dash-bar-track{height:12px;border-radius:99px;background:#edf1f7;overflow:hidden}.dash-bar-track i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#2385d8,#2e62c2)}.dash-bar-row strong{text-align:left;font-variant-numeric:tabular-nums}.dash-empty-note{padding:25px 12px;text-align:center;color:#7a879a;line-height:1.9;font-size:.9rem;background:#f8fafc;border-radius:10px}
        .dash-recent-panel{margin-bottom:12px}.dash-table-wrap{overflow-x:auto}.dash-table{width:100%;border-collapse:collapse;white-space:nowrap;font-size:.82rem}.dash-table th{padding:12px 11px;background:#1d365e;color:#fff;text-align:right;font-weight:700}.dash-table th:first-child{border-radius:0 8px 0 0}.dash-table th:last-child{border-radius:8px 0 0 0}.dash-table td{padding:12px 11px;border-bottom:1px solid #edf0f5;color:#47546a}.dash-table tbody tr:nth-child(even){background:#f8fafd}.dash-table tbody tr:hover{background:#eef5ff}.dash-footer{text-align:center;color:#8a95a7;font-size:.75rem;padding:10px}
        @media(max-width:1100px){.dash-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dash-middle-grid{grid-template-columns:1fr}}@media(max-width:620px){.dashboard-main{padding:18px 12px}.dash-heading{align-items:flex-start;flex-direction:column}.dash-year{width:100%}.dash-stat-grid{gap:9px}.dash-stat{padding:13px 10px;gap:9px}.dash-stat-icon{width:35px;height:35px;flex-basis:35px;font-size:1.2rem}.dash-stat small{display:none}.dash-gender-content{flex-wrap:wrap}.dash-panel{padding:15px}.dash-bar-row{grid-template-columns:60px minmax(35px,1fr) 25px;gap:7px}}
        </style>
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
