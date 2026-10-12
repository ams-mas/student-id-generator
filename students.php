<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';

$pdo = database_connection();
$role = current_role();
$canEdit = in_array($role, ['super_admin', 'admin'], true);
$roleLabels = ['super_admin' => 'سوپر ادمین', 'admin' => 'ادمین', 'user' => 'کاربر'];
$error = '';
$success = '';
$editing = null;

if (isset($_SESSION['students_flash']) && is_array($_SESSION['students_flash'])) {
    $success = (string) ($_SESSION['students_flash']['success'] ?? '');
    $error = (string) ($_SESSION['students_flash']['error'] ?? '');
    unset($_SESSION['students_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Refresh the page and try again.');
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if (!$canEdit) {
            throw new RuntimeException('شما اجازه تغییر پرونده‌های دانش‌آموزان را ندارید.');
        }
        if ($action === 'save') {
            $id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $number = trim((string)($_POST['student_number'] ?? ''));
            $first = trim((string)($_POST['first_name'] ?? ''));
            $last = trim((string)($_POST['last_name'] ?? ''));
            $gender = (string)($_POST['gender'] ?? 'unspecified');
            $dob = trim((string)($_POST['date_of_birth'] ?? ''));
            $class = trim((string)($_POST['class_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $guardian = trim((string)($_POST['guardian_name'] ?? ''));
            $guardianPhone = trim((string)($_POST['guardian_phone'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9._\/-]{1,40}$/', $number)) {
                throw new RuntimeException('شماره دانش‌آموزی الزامی است و فقط می‌تواند شامل حروف انگلیسی، عدد و . _ / - باشد.');
            }
            if ($first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($last) > 100) {
                throw new RuntimeException('نام و نام خانوادگی الزامی است و نباید بیشتر از ۱۰۰ نویسه باشد.');
            }
            if (!in_array($gender, ['female','male','unspecified'], true)) {
                throw new RuntimeException('جنسیت انتخاب‌شده معتبر نیست.');
            }
            if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                throw new RuntimeException('تاریخ تولد معتبر نیست.');
            }
            $params = ['number'=>$number,'first'=>$first,'last'=>$last,'gender'=>$gender,'dob'=>$dob !== '' ? $dob : null,'class'=>$class,'phone'=>$phone,'guardian'=>$guardian,'guardian_phone'=>$guardianPhone,'address'=>$address];
            if ($id) {
                $params['id'] = $id;
                $stmt = $pdo->prepare('UPDATE students SET student_number=:number, first_name=:first, last_name=:last, gender=:gender, date_of_birth=:dob, class_name=:class, phone=:phone, guardian_name=:guardian, guardian_phone=:guardian_phone, address=:address WHERE id=:id');
                $stmt->execute($params);
                $_SESSION['students_flash'] = ['success' => 'پرونده دانش‌آموز به‌روزرسانی شد.'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO students (student_number, first_name, last_name, gender, date_of_birth, class_name, phone, guardian_name, guardian_phone, address) VALUES (:number,:first,:last,:gender,:dob,:class,:phone,:guardian,:guardian_phone,:address)');
                $stmt->execute($params);
                $_SESSION['students_flash'] = ['success' => 'دانش‌آموز با موفقیت ثبت شد.'];
            }
            header('Location: students.php');
            exit;
        }
        if ($action === 'toggle') {
            $id = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('شناسه پرونده معتبر نیست.');
            $stmt = $pdo->prepare('UPDATE students SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=:id');
            $stmt->execute(['id'=>$id]);
            $_SESSION['students_flash'] = ['success' => 'وضعیت پرونده تغییر کرد.'];
            header('Location: students.php');
            exit;
        }
        throw new RuntimeException('درخواست نامعتبر است.');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = 'این شماره دانش‌آموزی قبلاً ثبت شده است.';
        } else {
            error_log('Student management error: '.$e->getMessage());
            $error = 'ذخیره اطلاعات انجام نشد. وجود جدول دانش‌آموزان در پایگاه داده را بررسی کنید.';
        }
    }
}

if ($canEdit && isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM students WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$editId]);
        $editing = $stmt->fetch() ?: null;
    }
}
$search = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT * FROM students';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE student_number LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3 OR class_name LIKE :q4 OR guardian_name LIKE :q5';
    $term = '%'.$search.'%';
    $params = ['q1'=>$term, 'q2'=>$term, 'q3'=>$term, 'q4'=>$term, 'q5'=>$term];
}
$sql .= ' ORDER BY is_active DESC, last_name ASC, first_name ASC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
$total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$active = (int)$pdo->query('SELECT COUNT(*) FROM students WHERE is_active=1')->fetchColumn();
function student_escape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$genderLabels = ['female'=>'دختر','male'=>'پسر','unspecified'=>'ثبت نشده'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>مدیریت دانش‌آموزان | سامانه مدیریت دانش‌آموزان</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
.students-grid{display:grid;grid-template-columns:minmax(280px,.75fr) minmax(0,1.5fr);gap:20px;align-items:start}
.students-form label{display:block;margin:12px 0 6px;font-weight:700;font-size:14px}
.students-form input,.students-form select,.students-form textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #d5deeb;border-radius:10px;background:#fff;color:var(--ink);font:inherit}
.students-form textarea{min-height:78px;resize:vertical}
.students-form .form-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.students-form .button{font-size:14px}
.students-table-wrap{overflow-x:auto;width:100%}
.students-table{width:100%;border-collapse:collapse;white-space:nowrap;text-align:right}
.students-table th,.students-table td{padding:11px 9px;border-bottom:1px solid var(--line);font-size:13px;vertical-align:middle}
.students-table th{background:#f5f7fb;color:#56637a}
.students-table .student-inactive{opacity:.62}
.students-flash{margin-bottom:16px;padding:12px 15px;border-radius:10px;background:#e8f7ef;color:#176644}
.students-error{background:#fff0f0;color:#a52c36}
.student-actions{display:flex;gap:6px;align-items:center}
.student-actions form{margin:0}
.student-actions .button{padding:7px 9px;min-height:34px;font-size:12px}
.students-search{display:flex;gap:8px;align-items:center;margin:12px 0 18px}
.students-search input{max-width:360px}
.students-metrics{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.students-metric{padding:10px 14px;border:1px solid var(--line);border-radius:12px;background:#fff}
.students-metric strong{display:block;font-size:20px}
@media(max-width:1000px){.students-grid{grid-template-columns:1fr}}
@media(max-width:600px){.students-search{align-items:stretch;flex-direction:column}.students-search input{max-width:none}.student-actions{flex-wrap:wrap}}
</style>
</head>
<body class="profile-page">
<div class="admin-layout">
<aside class="admin-sidebar" id="admin-sidebar" aria-label="منوی اصلی">
<button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="false" aria-label="باز کردن منو"><span class="sidebar-toggle-icon" aria-hidden="true">☰</span><span class="sidebar-toggle-label">منو</span></button>
<a class="sidebar-brand" href="dashboard.php"><span class="sidebar-brand-mark" aria-hidden="true">م‌د</span><span><strong>پنل مدیریت</strong><small>سامانه مدیریت دانش‌آموزان</small></span></a>
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
<div class="sidebar-bottom"><span class="sidebar-user-avatar" aria-hidden="true"><?= student_escape(substr((string)$_SESSION['username'],0,1)) ?></span><div class="sidebar-user-copy"><strong><?= student_escape($_SESSION['username']) ?></strong><small><?= student_escape($roleLabels[$role] ?? $role) ?></small></div><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= student_escape(csrf_token()) ?>"><button class="sidebar-logout" type="submit" aria-label="خروج از حساب" title="خروج از حساب">↪</button></form></div>
</aside>
<main class="admin-main">
<header class="page-header dashboard-header"><div><p class="eyebrow">پرونده‌ها</p><h1>مدیریت دانش‌آموزان</h1><p class="muted">ثبت، جست‌وجو و به‌روزرسانی پرونده‌های دانش‌آموزان.</p></div><div class="account-actions"><span class="signed-in-as">نقش شما: <strong><?= student_escape($roleLabels[$role] ?? $role) ?></strong></span></div></header>
<?php if ($success !== ''): ?><div class="students-flash" role="status"><?= student_escape($success) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="students-flash students-error" role="alert"><?= student_escape($error) ?></div><?php endif; ?>
<div class="students-metrics"><div class="students-metric"><span>همه دانش‌آموزان</span><strong><?= $total ?></strong></div><div class="students-metric"><span>پرونده‌های فعال</span><strong><?= $active ?></strong></div></div>
<div class="students-grid">
<?php if ($canEdit): ?>
<section class="panel"><h2><?= $editing ? 'ویرایش پرونده' : 'ثبت دانش‌آموز جدید' ?></h2>
<form class="students-form" method="post" action="students.php">
<input type="hidden" name="csrf_token" value="<?= student_escape(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="student_id" value="<?= student_escape($editing['id'] ?? '') ?>">
<label for="student_number">شماره دانش‌آموزی *</label><input id="student_number" name="student_number" required maxlength="40" value="<?= student_escape($editing['student_number'] ?? '') ?>" placeholder="مثلاً ST-1001">
<label for="first_name">نام *</label><input id="first_name" name="first_name" required maxlength="100" value="<?= student_escape($editing['first_name'] ?? '') ?>">
<label for="last_name">نام خانوادگی *</label><input id="last_name" name="last_name" required maxlength="100" value="<?= student_escape($editing['last_name'] ?? '') ?>">
<label for="gender">جنسیت</label><select id="gender" name="gender"><option value="unspecified">ثبت نشده</option><option value="female" <?= (($editing['gender'] ?? '')==='female')?'selected':'' ?>>دختر</option><option value="male" <?= (($editing['gender'] ?? '')==='male')?'selected':'' ?>>پسر</option></select>
<label for="date_of_birth">تاریخ تولد (هجری شمسی)</label><input id="date_of_birth" name="date_of_birth" type="date" data-persian-date value="<?= student_escape($editing['date_of_birth'] ?? '') ?>">
<label for="class_name">صنف / کلاس</label><input id="class_name" name="class_name" maxlength="100" value="<?= student_escape($editing['class_name'] ?? '') ?>">
<label for="phone">شماره تماس دانش‌آموز</label><input id="phone" name="phone" maxlength="40" value="<?= student_escape($editing['phone'] ?? '') ?>">
<label for="guardian_name">نام والد / سرپرست</label><input id="guardian_name" name="guardian_name" maxlength="200" value="<?= student_escape($editing['guardian_name'] ?? '') ?>">
<label for="guardian_phone">شماره تماس سرپرست</label><input id="guardian_phone" name="guardian_phone" maxlength="40" value="<?= student_escape($editing['guardian_phone'] ?? '') ?>">
<label for="address">نشانی</label><textarea id="address" name="address" maxlength="500"><?= student_escape($editing['address'] ?? '') ?></textarea>
<div class="form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'ذخیره تغییرات' : 'ثبت دانش‌آموز' ?></button><?php if ($editing): ?><a class="button button-secondary" href="students.php">لغو ویرایش</a><?php endif; ?></div>
</form></section>
<?php endif; ?>
<section class="panel"><h2>فهرست دانش‌آموزان</h2>
<form class="students-search" method="get" action="students.php"><input type="search" name="q" value="<?= student_escape($search) ?>" placeholder="جست‌وجو با شماره، نام، صنف یا سرپرست"><button class="button button-primary" type="submit">جست‌وجو</button><?php if ($search !== ''): ?><a class="button button-secondary" href="students.php">پاک کردن</a><?php endif; ?></form>
<div class="students-table-wrap"><table class="students-table"><thead><tr><th>شماره</th><th>نام کامل</th><th>صنف / کلاس</th><th>سرپرست</th><th>وضعیت</th><?php if ($canEdit): ?><th>عملیات</th><?php endif; ?></tr></thead><tbody>
<?php if (!$students): ?><tr><td colspan="<?= $canEdit ? 6 : 5 ?>">هنوز دانش‌آموزی ثبت نشده است.</td></tr><?php endif; ?>
<?php foreach ($students as $student): ?><tr class="<?= (int)$student['is_active']===1 ? '' : 'student-inactive' ?>"><td><?= student_escape($student['student_number']) ?></td><td><?= student_escape($student['first_name'].' '.$student['last_name']) ?></td><td><?= student_escape($student['class_name'] ?: '—') ?></td><td><?= student_escape($student['guardian_name'] ?: '—') ?></td><td><?= (int)$student['is_active']===1 ? 'فعال' : 'غیرفعال' ?></td><?php if ($canEdit): ?><td><div class="student-actions"><a class="button button-secondary" href="students.php?edit=<?= (int)$student['id'] ?>">ویرایش</a><form method="post" action="students.php"><input type="hidden" name="csrf_token" value="<?= student_escape(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>"><button class="button button-secondary" type="submit"><?= (int)$student['is_active']===1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form></div></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div>
<p class="help-text">حداکثر ۵۰۰ پرونده در این فهرست نمایش داده می‌شود. از جست‌وجو برای پیدا کردن رکوردها استفاده کنید.</p>
</section></div>
</main></div>
<script>
(function(){const sidebar=document.getElementById('admin-sidebar');const toggle=document.getElementById('sidebar-toggle');if(!sidebar||!toggle)return;toggle.addEventListener('click',function(){const open=sidebar.classList.toggle('is-open');toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'بستن منو':'باز کردن منو');const icon=toggle.querySelector('.sidebar-toggle-icon');if(icon)icon.textContent=open?'×':'☰';});document.addEventListener('keydown',function(event){if(event.key==='Escape'){sidebar.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','باز کردن منو');const icon=toggle.querySelector('.sidebar-toggle-icon');if(icon)icon.textContent='☰';}});})();
</script>
<link rel="stylesheet" href="assets/css/persian-calendar.css">
<script src="assets/js/persian-calendar.js"></script>
</body></html>
