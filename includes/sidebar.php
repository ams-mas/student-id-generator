<?php
declare(strict_types=1);

$sidebarUsername = (string) ($_SESSION['username'] ?? '');
$sidebarRole = current_role();
$sidebarRoleLabels = ['super_admin' => 'سوپر ادمین', 'admin' => 'ادمین', 'user' => 'کاربر'];
$sidebarCurrentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$sidebarSchoolLogo = '';
$sidebarSchoolName = '';
try {
    require_once __DIR__ . '/../config/database.php';
    $sidebarSettingsStmt = database_connection()->query('SELECT school_name, logo_path FROM school_configuration WHERE id = 1 LIMIT 1');
    $sidebarSchoolSettings = $sidebarSettingsStmt->fetch() ?: [];
    $sidebarSchoolLogo = (string) ($sidebarSchoolSettings['logo_path'] ?? '');
    $sidebarSchoolName = trim((string) ($sidebarSchoolSettings['school_name'] ?? ''));
} catch (Throwable $sidebarSettingsError) {
    $sidebarSchoolLogo = '';
    $sidebarSchoolName = '';
}
$sidebarEsc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
    <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="باز کردن منو"><span class="sidebar-toggle-icon" aria-hidden="true">☰</span><span class="sidebar-toggle-label">منو</span></button>
    <a class="sidebar-brand" href="dashboard.php">
        <?php if ($sidebarSchoolLogo !== ''): ?>
            <img class="sidebar-school-logo" src="<?= $sidebarEsc($sidebarSchoolLogo) ?>" alt="لوگوی مدرسه">
        <?php else: ?>
            <span class="sidebar-brand-mark" aria-hidden="true">م‌د</span>
        <?php endif; ?>
        <span><strong><?= $sidebarEsc($sidebarSchoolName !== '' ? $sidebarSchoolName : 'پنل مدیریت') ?></strong><small>سامانه مدیریت دانش‌آموزان</small></span>
    </a>
    <div class="sidebar-system-clock" aria-label="تاریخ و ساعت فعلی سیستم"><span class="sidebar-clock-date" data-sidebar-date></span><span class="sidebar-clock-time" data-sidebar-time></span></div>
    <p class="sidebar-section-label">منوی اصلی</p>
    <nav class="sidebar-nav">
        <a class="sidebar-link <?= $sidebarCurrentPage === 'dashboard.php' ? 'is-current' : '' ?>" href="dashboard.php"><span class="sidebar-icon" aria-hidden="true">⌂</span><span>داشبورد</span></a>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title <?= in_array($sidebarCurrentPage, ['students.php', 'generate.php'], true) ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">♙</span><span>مدیریت دانش‌آموزان</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
            <div class="sidebar-submenu">
                <a class="sidebar-link" href="students.php?new=1">۱. افزودن دانش‌آموز جدید</a>
                <a class="sidebar-link" href="students.php">۲. مشاهده همه دانش‌آموزان</a>
                <a class="sidebar-link" href="students.php">۳. ویرایش اطلاعات دانش‌آموز</a>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۴. پروفایل دانش‌آموز <small>به‌زودی</small></span>
                <a class="sidebar-link" href="students.php">۵. جست‌وجوی دانش‌آموزان</a>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۶. ارتقای دانش‌آموزان به صنف بالاتر <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۷. انتقال دانش‌آموزان <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۸. مدیریت ثبت‌نام دانش‌آموزان <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۹. مدیریت حاضری دانش‌آموزان <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۰. مدیریت اسناد دانش‌آموزان <small>به‌زودی</small></span>
                <a class="sidebar-link <?= $sidebarCurrentPage === 'generate.php' ? 'is-current' : '' ?>" href="generate.php">۱۱. کارت هویت دانش‌آموز</a>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۲. سوابق تحصیلی دانش‌آموز <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۳. مدیریت اولیا و سرپرستان <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۴. تعلیق یا انصراف دانش‌آموز <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۵. فارغ‌التحصیلان <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۶. واردکردن دانش‌آموزان از Excel/CSV <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۷. صادرکردن پرونده‌های دانش‌آموزان <small>به‌زودی</small></span>
                <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">۱۸. دانش‌آموزان حذف‌شده / بایگانی‌شده <small>به‌زودی</small></span>
            </div>
        </div>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title <?= $sidebarCurrentPage === 'teachers.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">♟</span><span>مدیریت آموزگاران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
            <div class="sidebar-submenu">
                <a class="sidebar-link <?= $sidebarCurrentPage === 'teachers.php' && (!isset($_GET['mode']) || $_GET['mode'] === 'add') ? 'is-current' : '' ?>" href="teachers.php?mode=add">ثبت آموزگار</a>
                <a class="sidebar-link <?= $sidebarCurrentPage === 'teachers.php' && ($_GET['mode'] ?? '') === 'edit' ? 'is-current' : '' ?>" href="teachers.php?mode=edit">ویرایش آموزگار</a>
                <a class="sidebar-link <?= $sidebarCurrentPage === 'teachers.php' && ($_GET['mode'] ?? '') === 'delete' ? 'is-current' : '' ?>" href="teachers.php?mode=delete">حذف آموزگار</a>
            </div>
        </div>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title <?= $sidebarCurrentPage === 'classes.php' ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">▦</span><span>مدیریت صنف‌ها</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
            <div class="sidebar-submenu">
                <a class="sidebar-link <?= $sidebarCurrentPage === 'classes.php' && (!isset($_GET['mode']) || $_GET['mode'] === 'add') ? 'is-current' : '' ?>" href="classes.php?mode=add">اضافه کردن صنف</a>
                <a class="sidebar-link <?= $sidebarCurrentPage === 'classes.php' && ($_GET['mode'] ?? '') === 'edit' ? 'is-current' : '' ?>" href="classes.php?mode=edit">ویرایش صنف</a>
                <a class="sidebar-link <?= $sidebarCurrentPage === 'classes.php' && ($_GET['mode'] ?? '') === 'delete' ? 'is-current' : '' ?>" href="classes.php?mode=delete">حذف صنف</a>
            </div>
        </div>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title <?= in_array($sidebarCurrentPage, ['school.php', 'school_configuration.php'], true) ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">🏫</span><span>تنظیمات مدرسه</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
            <div class="sidebar-submenu"><a class="sidebar-link <?= $sidebarCurrentPage === 'school_configuration.php' ? 'is-current' : '' ?>" href="school_configuration.php">مشخصات مدرسه</a><a class="sidebar-link <?= $sidebarCurrentPage === 'school.php' ? 'is-current' : '' ?>" href="school.php">سال تحصیلی</a></div>
        </div>
        <?php if (in_array($sidebarRole, ['super_admin', 'admin'], true)): ?>
        <div class="sidebar-group">
            <button type="button" class="sidebar-group-title <?= in_array($sidebarCurrentPage, ['users.php', 'add_user.php'], true) ? 'is-current' : '' ?>" data-submenu-toggle aria-expanded="false"><span class="sidebar-icon" aria-hidden="true">⚙</span><span>مدیریت کاربران</span><span class="submenu-chevron" aria-hidden="true">⌄</span></button>
            <div class="sidebar-submenu"><a class="sidebar-link <?= $sidebarCurrentPage === 'users.php' ? 'is-current' : '' ?>" href="users.php">لیست کاربران</a><a class="sidebar-link <?= $sidebarCurrentPage === 'add_user.php' ? 'is-current' : '' ?>" href="add_user.php">افزودن کاربر</a></div>
        </div>
        <a class="sidebar-link" href="#" aria-disabled="true" title="بخش گزارشات بعداً اضافه می‌شود"><span class="sidebar-icon" aria-hidden="true">▤</span><span>گزارشات</span></a>
        <a class="sidebar-link" href="#" aria-disabled="true" title="بخش مالی بعداً اضافه می‌شود"><span class="sidebar-icon" aria-hidden="true">◈</span><span>بخش مالی</span></a>
        <a class="sidebar-link <?= $sidebarCurrentPage === 'database_updates.php' ? 'is-current' : '' ?>" href="database_updates.php"><span class="sidebar-icon" aria-hidden="true">↻</span><span>به‌روزرسانی پایگاه داده</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <span class="sidebar-user-avatar" aria-hidden="true"><?= $sidebarEsc(mb_substr($sidebarUsername, 0, 1)) ?></span>
        <div class="sidebar-user-copy"><strong><?= $sidebarEsc($sidebarUsername) ?></strong><small><?= $sidebarEsc($sidebarRoleLabels[$sidebarRole] ?? $sidebarRole) ?></small></div>
        <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= $sidebarEsc(csrf_token()) ?>"><button class="sidebar-logout" type="submit" aria-label="خروج از حساب" title="خروج از حساب">↪</button></form>
    </div>
</aside>
<script>
(function () {
    var sidebar = document.getElementById('admin-sidebar');
    var toggle = document.getElementById('sidebar-toggle');
    if (!sidebar || !toggle || toggle.dataset.bound === '1') return;
    toggle.dataset.bound = '1';
    toggle.addEventListener('click', function () {
        var isOpen = sidebar.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'بستن منو' : 'باز کردن منو');
        var icon = toggle.querySelector('.sidebar-toggle-icon');
        if (icon) icon.textContent = isOpen ? '×' : '☰';
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            sidebar.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'باز کردن منو');
            var icon = toggle.querySelector('.sidebar-toggle-icon');
            if (icon) icon.textContent = '☰';
            toggle.focus();
        }
    });
}());
</script>
