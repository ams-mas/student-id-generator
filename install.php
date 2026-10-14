<?php
declare(strict_types=1);

/*
 * First-run installer for Student ID Generator.
 * Open /install.php on a fresh local installation, create the database and
 * first super administrator, then remove this file or leave the DB lock in place.
 */
session_start();

function install_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_db_settings(): array
{
    return [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'student_id_generator',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') === false ? '' : (string) getenv('DB_PASS'),
    ];
}

function install_pdo(array $settings, bool $selectDatabase = true): PDO
{
    $dsn = 'mysql:host=' . $settings['host'] . ';charset=utf8mb4';
    if ($selectDatabase) {
        $dsn .= ';dbname=' . $settings['name'];
    }
    return new PDO($dsn, $settings['user'], $settings['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** Run ordinary SQL files containing statements separated by semicolons on their own lines. */
function install_run_sql(PDO $pdo, string $sql): void
{
    // Remove full-line SQL comments. The project migrations do not use stored procedures.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statementSql) {
        $statementSql = trim($statementSql);
        if ($statementSql === '') {
            continue;
        }
        // The installer has already selected the target database.
        if (preg_match('/^USE\s+[`\w]+\s*$/i', $statementSql)) {
            continue;
        }
        // schema.sql may include CREATE DATABASE; database creation is handled safely above.
        if (preg_match('/^CREATE\s+DATABASE\b/i', $statementSql)) {
            continue;
        }
        $statement = $pdo->query($statementSql);
        if ($statement instanceof PDOStatement) {
            if ($statement->columnCount() > 0) {
                $statement->fetchAll(PDO::FETCH_ASSOC);
            }
            $statement->closeCursor();
        }
    }
}

function install_migrations(PDO $pdo): array
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration_name VARCHAR(255) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_schema_migration_name (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $applied = $pdo->query('SELECT migration_name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $directories = [__DIR__ . '/database/migrations', __DIR__ . '/sql_updates'];
    $files = [];
    foreach ($directories as $directory) {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
            $files[$file] = true;
        }
    }
    $paths = array_keys($files);
    usort($paths, static fn(string $a, string $b): int => strcmp(basename($a), basename($b)));

    $completed = [];
    foreach ($paths as $path) {
        $name = basename($path);
        if (in_array($name, $applied, true)) {
            continue;
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Could not read SQL migration: ' . $name);
        }
        install_run_sql($pdo, $sql);
        $track = $pdo->prepare('INSERT INTO schema_migrations (migration_name) VALUES (:name)');
        $track->execute(['name' => $name]);
        $completed[] = $name;
    }
    return $completed;
}

$settings = install_db_settings();
$message = '';
$messageType = 'error';
$completedMigrations = [];
$installed = false;
$alreadyInstalled = false;
$databaseExists = false;

try {
    $server = install_pdo($settings, false);
    $dbName = str_replace('`', '``', $settings['name']);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = install_pdo($settings, true);
    $databaseExists = true;

    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    if ($tables) {
        $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if ($columns) {
            $superAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
            $alreadyInstalled = $superAdmins > 0;
        }
    }
} catch (Throwable $exception) {
    $message = 'اتصال به MySQL برقرار نشد. مطمئن شوید MySQL فعال است و تنظیمات DB_HOST، DB_NAME، DB_USER و DB_PASS درست هستند. جزئیات: ' . $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !isset($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $token)) {
        http_response_code(400);
        $message = 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($fullName === '' || mb_strlen($fullName) > 160) {
            $message = 'نام کامل مدیر را وارد کنید.';
        } elseif (!preg_match('/^[a-z0-9_.-]{3,80}$/', $username)) {
            $message = 'نام کاربری باید ۳ تا ۸۰ حرف و شامل حروف انگلیسی کوچک، عدد، نقطه، خط تیره یا زیرخط باشد.';
        } elseif (strlen($password) < 12) {
            $message = 'رمز عبور باید حداقل ۱۲ کاراکتر باشد.';
        } elseif (!hash_equals($password, $confirmation)) {
            $message = 'رمز عبور و تأیید آن یکسان نیستند.';
        } else {
            try {
                // Create/upgrade the database without deleting existing records.
                $server = install_pdo($settings, false);
                $dbName = str_replace('`', '``', $settings['name']);
                $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = install_pdo($settings, true);

                $schemaPath = __DIR__ . '/database/schema.sql';
                $schema = file_get_contents($schemaPath);
                if ($schema === false) {
                    throw new RuntimeException('database/schema.sql is missing or unreadable.');
                }
                install_run_sql($pdo, $schema);
                $completedMigrations = install_migrations($pdo);

                // Never silently take over an existing installation or change an existing account.
                $hasSuperAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1")->fetchColumn();
                if ($hasSuperAdmin > 0) {
                    $alreadyInstalled = true;
                    throw new RuntimeException('An active super administrator already exists. Installer stopped without changing accounts.');
                }

                $checkUser = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
                $checkUser->execute(['username' => $username]);
                if ($checkUser->fetch()) {
                    throw new RuntimeException('این نام کاربری قبلاً استفاده شده است. نام دیگری انتخاب کنید.');
                }

                $createUser = $pdo->prepare(
                    'INSERT INTO users (username, full_name, password_hash, role, is_active)
                     VALUES (:username, :full_name, :password_hash, :role, 1)'
                );
                $createUser->execute([
                    'username' => $username,
                    'full_name' => $fullName,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => 'super_admin',
                ]);

                $installed = true;
                $messageType = 'success';
                $message = 'نصب موفق شد. جدول‌ها و به‌روزرسانی‌های پایگاه داده اجرا شدند و حساب مدیر کل ساخته شد.';
                unset($_SESSION['install_csrf']);
            } catch (Throwable $exception) {
                error_log('Student ID Generator installer error: ' . $exception->getMessage());
                if (!$alreadyInstalled) {
                    $message = 'نصب کامل نشد: ' . $exception->getMessage() . ' — خطای دقیق در گزارش PHP/Apache نیز ثبت شده است.';
                } else {
                    $message = 'این سیستم قبلاً مدیر کل فعال دارد. برای امنیت، ساخت مدیر کل جدید متوقف شد.';
                }
            }
        }
    }
}

if (!isset($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>راه‌اندازی اولیه سیستم مدیریت مکتب</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body{background:#f3f6fb;margin:0;color:#172033;font-family:inherit}
        .setup-shell{max-width:760px;margin:44px auto;padding:0 18px}
        .setup-card{background:#fff;border:1px solid #e4e9f2;border-radius:18px;padding:28px;box-shadow:0 12px 36px rgba(15,23,42,.07);margin-bottom:18px}
        .setup-card h1{margin-top:0;font-size:1.6rem}
        .setup-subtitle{color:#64748b;line-height:1.9}
        .setup-field{display:grid;gap:7px;margin:16px 0}
        .setup-field label{font-weight:600}
        .setup-field input{box-sizing:border-box;width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}
        .setup-field input:focus{outline:2px solid #93c5fd;border-color:#2563eb}
        .setup-button{border:0;border-radius:10px;background:#1d4ed8;color:#fff;padding:12px 18px;font:inherit;font-weight:700;cursor:pointer}
        .setup-alert{padding:14px 16px;border-radius:10px;margin:16px 0;line-height:1.8;overflow-wrap:anywhere}
        .setup-alert.success{background:#dcfce7;color:#166534}
        .setup-alert.error{background:#fee2e2;color:#991b1b}
        .setup-alert.warning{background:#fff7ed;color:#9a3412}
        .setup-note{font-size:.92rem;color:#64748b;line-height:1.9}
        .setup-ltr{direction:ltr;text-align:left}
    </style>
</head>
<body>
<main class="setup-shell">
    <section class="setup-card">
        <p class="eyebrow">راه‌اندازی سیستم</p>
        <h1>راه‌اندازی اولیه سیستم مدیریت مکتب</h1>
        <p class="setup-subtitle">این صفحه پایگاه داده را ایجاد می‌کند، ساختار اصلی و فایل‌های SQL به‌روزرسانی را اجرا می‌کند و حساب نخست «مدیر کل» را می‌سازد. اطلاعات موجود حذف نمی‌شوند.</p>
        <?php if ($alreadyInstalled): ?>
            <div class="setup-alert warning">این سیستم از قبل یک مدیر کل فعال دارد. برای جلوگیری از سوءاستفاده، نصب مجدد از این صفحه غیرفعال است.</div>
            <p><a href="index.php">رفتن به صفحه ورود</a></p>
        <?php elseif ($message !== ''): ?>
            <div class="setup-alert <?= install_escape($messageType) ?>" role="status"><?= install_escape($message) ?></div>
        <?php endif; ?>

        <?php if ($installed): ?>
            <p><strong>اکنون می‌توانید وارد سیستم شوید.</strong></p>
            <p><a class="setup-button" href="index.php">رفتن به صفحه ورود</a></p>
            <p class="setup-note">برای امنیت بیشتر، پس از تأیید ورود موفق، فایل install.php را از سرور حذف کنید.</p>
        <?php elseif (!$alreadyInstalled): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= install_escape($_SESSION['install_csrf']) ?>">
                <div class="setup-field">
                    <label for="full_name">نام کامل مدیر کل</label>
                    <input id="full_name" name="full_name" maxlength="160" required value="<?= install_escape((string) ($_POST['full_name'] ?? '')) ?>">
                </div>
                <div class="setup-field">
                    <label for="username">نام کاربری (انگلیسی)</label>
                    <input class="setup-ltr" id="username" name="username" minlength="3" maxlength="80" pattern="[a-zA-Z0-9_.-]+" autocomplete="username" required value="<?= install_escape((string) ($_POST['username'] ?? '')) ?>">
                </div>
                <div class="setup-field">
                    <label for="password">رمز عبور مدیر کل (حداقل ۱۲ کاراکتر)</label>
                    <input class="setup-ltr" type="password" id="password" name="password" minlength="12" maxlength="4096" autocomplete="new-password" required>
                </div>
                <div class="setup-field">
                    <label for="password_confirmation">تکرار رمز عبور</label>
                    <input class="setup-ltr" type="password" id="password_confirmation" name="password_confirmation" minlength="12" maxlength="4096" autocomplete="new-password" required>
                </div>
                <button class="setup-button" type="submit">ایجاد پایگاه داده و مدیر کل</button>
            </form>
            <p class="setup-note">تنظیمات پیش‌فرض برای نصب محلی XAMPP هستند: پایگاه داده student_id_generator، کاربر MySQL برابر root و رمز خالی. در صورت نیاز، متغیرهای محیطی DB_HOST، DB_NAME، DB_USER و DB_PASS را تنظیم کنید. این ابزار را روی سرور عمومی باز نگذارید.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
