<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_roles(['super_admin', 'admin']);
require_once __DIR__ . '/config/database.php';

$pdo = database_connection();
$migrationDirectories = [__DIR__ . '/sql_updates', __DIR__ . '/database/migrations'];
$message = '';
$messageType = 'info';
$results = [];

function update_page_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration_name VARCHAR(255) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_schema_migration_name (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $appliedNames = $pdo->query('SELECT migration_name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $exception) {
    error_log('Database updater could not initialize migration tracking: ' . $exception->getMessage());
    http_response_code(500);
    exit('ساخت جدول پیگیری به‌روزرسانی‌ها انجام نشد. دسترسی پایگاه داده را بررسی کنید.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('درخواست معتبر نیست. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.');
    }

    $requestedMigration = (string) ($_POST['migration'] ?? '');
    $availableFiles = [];
    foreach ($migrationDirectories as $directory) {
        $availableFiles = array_merge($availableFiles, glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: []);
    }
    $selectedPath = null;
    foreach ($availableFiles as $filePath) {
        if (hash_equals(basename($filePath), $requestedMigration)) {
            $selectedPath = $filePath;
            break;
        }
    }

    if ($selectedPath === null) {
        $message = 'فایل به‌روزرسانی پیدا نشد.';
        $messageType = 'error';
    } elseif (in_array(basename($selectedPath), $appliedNames, true)) {
        $message = 'این فایل قبلاً اجرا شده است.';
        $messageType = 'info';
    } else {
        $sql = file_get_contents($selectedPath);
        if ($sql === false) {
            $message = 'خواندن فایل SQL انجام نشد.';
            $messageType = 'error';
        } else {
            // Supports ordinary SQL statements separated by semicolons, not stored procedures or triggers.
            $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
            try {
                foreach ($statements as $statementSql) {
                    $statementSql = trim($statementSql);
                    if ($statementSql === '') {
                        continue;
                    }
                    // The configured PDO connection already selects the database.
                    if (preg_match('/^USE\s+[`\w]+\s*$/i', $statementSql)) {
                        continue;
                    }
                    // Use query() and fully consume any result set (for example SELECT 1
                    // in idempotent migrations) before executing the next statement.
                    $statement = $pdo->query($statementSql);
                    if ($statement instanceof PDOStatement) {
                        if ($statement->columnCount() > 0) {
                            $statement->fetchAll(PDO::FETCH_ASSOC);
                        }
                        $statement->closeCursor();
                    }
                }
                $track = $pdo->prepare('INSERT INTO schema_migrations (migration_name) VALUES (:name)');
                $track->execute(['name' => basename($selectedPath)]);
                $appliedNames[] = basename($selectedPath);
                $message = 'به‌روزرسانی با موفقیت اجرا شد: ' . basename($selectedPath);
                $messageType = 'success';
            } catch (Throwable $exception) {
                error_log('Database migration failed (' . basename($selectedPath) . '): ' . $exception->getMessage());
                $message = 'اجرای به‌روزرسانی متوقف شد: ' . $exception->getMessage()
                    . ' — ممکن است بعضی جدول‌ها پیش از خطا ساخته شده باشند. خطا را بررسی کنید.';
                $messageType = 'error';
            }
        }
    }
}

$availableFiles = [];
foreach ($migrationDirectories as $directory) {
    $availableFiles = array_merge($availableFiles, glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: []);
}
usort($availableFiles, static fn(string $a, string $b): int => strcmp(basename($a), basename($b)));
foreach ($availableFiles as $filePath) {
    $name = basename($filePath);
    $results[] = ['name' => $name, 'applied' => in_array($name, $appliedNames, true)];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>به‌روزرسانی پایگاه داده</title>
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
        .migration-wrap{max-width:1000px;margin:32px auto;padding:0 18px}
        .migration-panel{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;margin:18px 0;box-shadow:0 6px 24px rgba(15,23,42,.05)}
        .migration-list{display:grid;gap:12px}
        .migration-row{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
        .migration-name{direction:ltr;text-align:left;overflow-wrap:anywhere;font-family:monospace}
        .migration-status{font-size:.9rem;color:#475569}
        .migration-alert{padding:14px 16px;border-radius:10px;margin:16px 0;overflow-wrap:anywhere}
        .migration-alert.success{background:#dcfce7;color:#166534}
        .migration-alert.error{background:#fee2e2;color:#991b1b}
        .migration-alert.info{background:#e0f2fe;color:#075985}
        .migration-warning{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:14px;border-radius:10px;line-height:1.9}
        .migration-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .migration-actions form{margin:0}
        .migration-back{display:inline-block;margin-bottom:12px}
    </style>
</head>
<body class="profile-page">
<div class="admin-layout">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
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
            <span class="sidebar-link sidebar-link-disabled" aria-disabled="true">صنف‌ها و بخش‌ها <small>به‌زودی</small></span>
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
    <a class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'database_updates.php' ? 'is-current' : '' ?>" href="database_updates.php"><span class="sidebar-icon" aria-hidden="true">↻</span><span>به‌روزرسانی پایگاه داده</span></a>
    <?php endif; ?>
</nav>
        <div class="sidebar-bottom">
            <span class="sidebar-user-avatar" aria-hidden="true"><?= update_page_escape(substr((string) ($_SESSION['username'] ?? ''), 0, 1)) ?></span>
            <div class="sidebar-user-copy"><strong><?= update_page_escape((string) ($_SESSION['username'] ?? '')) ?></strong><small><?= update_page_escape(current_role() === 'super_admin' ? 'سوپر ادمین' : 'ادمین') ?></small></div>
            <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= update_page_escape(csrf_token()) ?>"><button class="sidebar-logout" type="submit" aria-label="خروج از حساب" title="خروج از حساب">↪</button></form>
        </div>
    </aside>
    <main class="admin-main">
      <div class="migration-wrap">
    <a class="migration-back" href="dashboard.php">بازگشت به داشبورد</a>
    <section class="migration-panel">
        <p class="eyebrow">مدیریت سیستم</p>
        <h1>به‌روزرسانی پایگاه داده</h1>
        <p><a href="download_database_backup.php" class="button button-primary">دانلود پشتیبان کامل پایگاه داده (SQL)</a></p>
        <p>هر تغییر جدید در جدول‌ها باید به‌صورت یک فایل SQL جدید در پوشه sql_updates/ اضافه شود. فایل‌های قدیمی database/migrations/ نیز همچنان نمایش داده می‌شوند. این صفحه فایل‌های اجرا‌نشده را نمایش می‌دهد.</p>
        <div class="migration-warning">پیش از اجرای هر تغییر، از پایگاه داده نسخه پشتیبان بگیرید. فایل‌های SQL می‌توانند جدول یا ستون بسازند، تغییر دهند یا حذف کنند. دستورهایی مانند DROP TABLE و DROP COLUMN داده‌ها را برای همیشه حذف می‌کنند. اگر خطا رخ دهد، ممکن است بخشی از تغییرات اعمال شده باشد.</div>
        <?php if ($message !== ''): ?>
            <div class="migration-alert <?= update_page_escape($messageType) ?>" role="status"><?= update_page_escape($message) ?></div>
        <?php endif; ?>
    </section>
    <section class="migration-panel">
        <h2>فایل‌های SQL</h2>
        <?php if ($results === []): ?>
            <p>هیچ فایل SQL در پوشه مهاجرت‌ها پیدا نشد.</p>
        <?php else: ?>
            <div class="migration-list">
                <?php foreach ($results as $item): ?>
                    <div class="migration-row">
                        <div>
                            <div class="migration-name"><?= update_page_escape($item['name']) ?></div>
                            <div class="migration-status"><?= $item['applied'] ? 'قبلاً اجرا شده' : 'در انتظار اجرا' ?></div>
                        </div>
                        <div class="migration-actions">
                            <?php if ($item['applied']): ?>
                                <span class="button button-secondary" aria-disabled="true">اجرا شده</span>
                            <?php else: ?>
                                <form method="post" onsubmit="return confirm('پیش از اجرا از پایگاه داده نسخه پشتیبان گرفته‌اید؟ آیا این فایل اجرا شود؟');">
                                    <input type="hidden" name="csrf_token" value="<?= update_page_escape(csrf_token()) ?>">
                                    <input type="hidden" name="migration" value="<?= update_page_escape($item['name']) ?>">
                                    <button type="submit" class="button button-primary">اجرای به‌روزرسانی</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <p class="migration-status">دسترسی این صفحه فقط برای سوپر ادمین و ادمین فعال است.</p>
</div>
    </main>
</div>
    <script src="assets/js/sidebar-clock.js" defer></script>
    <script src="assets/js/sidebar-submenus.js" defer></script>
</body>
</html>
