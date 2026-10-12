<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_roles(['super_admin', 'admin']);
require_once __DIR__ . '/config/database.php';
$pdo = database_connection();
$error = '';
$success = '';
$gradeNames = [1=>'اول',2=>'دوم',3=>'سوم',4=>'چهارم',5=>'پنجم',6=>'ششم',7=>'هفتم',8=>'هشتم',9=>'نهم',10=>'دهم',11=>'یازدهم',12=>'دوازدهم'];
$letters = ['الف','ب','ج','د','هـ','و','ز','ح','ط','ی','ک','ل','م','ن','س','ع','ف','ص','ق','ر','ش','ت','ث','خ','ذ','ض','ظ','غ'];
function classes_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
try {
 if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) { http_response_code(400); exit('درخواست معتبر نیست. صفحه را تازه‌سازی کنید.'); }
  $year=$pdo->query('SELECT id,is_closed FROM school_years WHERE is_active=1 LIMIT 1')->fetch();
  if (!$year) throw new RuntimeException('ابتدا از بخش سال تحصیلی، یک سال تحصیلی را فعال کنید.');
  if ((int)$year['is_closed']===1) throw new RuntimeException('سال تحصیلی فعلی بسته است؛ یک سال باز را فعال کنید.');
  $action=(string)($_POST['action']??'');
  if ($action==='add') {
   $grade=filter_var($_POST['grade']??null,FILTER_VALIDATE_INT);
   $letter=trim((string)($_POST['section_name']??''));
   if (!$grade || !isset($gradeNames[$grade]) || !in_array($letter,$letters,true)) throw new RuntimeException('پایه یا حرف صنف معتبر نیست.');
   $q=$pdo->prepare('INSERT INTO class_sections (school_year_id,grade,section_name,is_active) VALUES (?,?,?,1)');
   $q->execute([(int)$year['id'],$grade,$letter]); $success='صنف «'.$gradeNames[$grade].' '.$letter.'» ثبت شد.';
  } elseif ($action==='toggle') {
   $id=filter_var($_POST['section_id']??null,FILTER_VALIDATE_INT);
   $active=(int)($_POST['is_active']??0)===1?1:0;
   $q=$pdo->prepare('UPDATE class_sections SET is_active=? WHERE id=? AND school_year_id=?');
   $q->execute([$active,$id,(int)$year['id']]);
   if (!$q->rowCount()) throw new RuntimeException('صنف پیدا نشد یا وضعیت آن تغییر نکرد.');
   $success=$active?'صنف فعال شد.':'صنف غیرفعال شد؛ سوابق آن حفظ شده است.';
  } else throw new RuntimeException('عملیات معتبر نیست.');
 }
} catch (PDOException $e) {
 $error=$e->getCode()==='23000'?'این صنف قبلاً در سال تحصیلی فعلی ثبت شده است.':'عملیات انجام نشد. به‌روزرسانی پایگاه داده را بررسی کنید.';
 error_log('Class management: '.$e->getMessage());
} catch (Throwable $e) { $error=$e->getMessage(); }
$year=$pdo->query('SELECT id,name,is_closed FROM school_years WHERE is_active=1 LIMIT 1')->fetch() ?: null;
$sections=[];
if ($year) {
 $q=$pdo->prepare('SELECT cs.id,cs.grade,cs.section_name,cs.is_active,cs.teacher_id,t.full_name AS teacher_name,(SELECT COUNT(*) FROM student_enrollments se WHERE se.class_section_id=cs.id AND se.is_current=1) AS student_count FROM class_sections cs LEFT JOIN teachers t ON t.id=cs.teacher_id WHERE cs.school_year_id=? ORDER BY cs.grade,cs.section_name');
 $q->execute([(int)$year['id']]); $sections=$q->fetchAll();
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>مدیریت صنف‌ها</title><link rel="stylesheet" href="assets/css/style.css"><style>
/* School identity in the top of the sidebar */
.admin-sidebar .sidebar-brand{align-items:center;}
.admin-sidebar .sidebar-school-logo{width:48px;height:48px;min-width:48px;object-fit:contain;border-radius:10px;background:#fff;padding:4px;box-sizing:border-box;}
.admin-sidebar .sidebar-system-clock{display:flex;flex-direction:column;align-items:center;gap:4px;margin:12px 12px 18px;padding:10px 8px;border:1px solid rgba(255,255,255,.16);border-radius:10px;background:rgba(255,255,255,.07);text-align:center;direction:rtl;}
.admin-sidebar .sidebar-clock-date{font-size:12px;line-height:1.7;color:#dbeafe;}
.admin-sidebar .sidebar-clock-time{font-size:20px;font-weight:700;letter-spacing:1px;line-height:1.4;color:#fff;font-variant-numeric:tabular-nums;}
</style>
<style>
.class-grid{display:grid;grid-template-columns:minmax(250px,.75fr) minmax(320px,1.25fr);gap:20px;align-items:start}.class-form{display:grid;gap:14px}.class-form label{display:grid;gap:7px;font-weight:700}.class-form select{width:100%;padding:12px;border:1px solid var(--line);border-radius:9px;background:white;font:inherit}.class-table-wrap{overflow-x:auto}.class-table{width:100%;border-collapse:collapse}.class-table th,.class-table td{padding:11px;text-align:right;border-bottom:1px solid var(--line);white-space:nowrap}.class-table th{background:#e8eef8}.class-label{font-weight:800;color:#173579}.notice{padding:12px 14px;border-radius:9px;margin-bottom:16px}.notice.ok{background:#dcfce7;color:#166534}.notice.bad{background:#fee2e2;color:#991b1b}.abjad-help{font-size:.9rem;color:var(--muted);line-height:1.9}@media(max-width:850px){.class-grid{grid-template-columns:1fr}}
</style></head><body class="profile-page"><div class="admin-layout"><?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-main"><header class="page-header"><p class="eyebrow">تنظیمات مدرسه</p><h1>مدیریت صنف‌ها</h1><p class="muted">صنف‌هایی مانند اول الف، اول ب، اول ج و دوم الف را برای سال تحصیلی فعلی بسازید.</p></header>
<?php if ($success!==''): ?><div class="notice ok"><?= classes_h($success) ?></div><?php endif; ?><?php if ($error!==''): ?><div class="notice bad"><?= classes_h($error) ?></div><?php endif; ?>
<?php if (!$year): ?><section class="panel"><h2>سال تحصیلی فعال نیست</h2><p>ابتدا یک سال تحصیلی در تنظیمات سال تحصیلی ایجاد و فعال کنید.</p><a class="button button-primary" href="school.php">رفتن به سال تحصیلی</a></section><?php else: ?>
<section class="panel" style="margin-bottom:18px"><strong>سال تحصیلی فعلی:</strong> <?= classes_h($year['name']) ?></section>
<div class="class-grid"><section class="panel"><h2>افزودن صنف</h2><form class="class-form" method="post"><input type="hidden" name="csrf_token" value="<?= classes_h(csrf_token()) ?>"><input type="hidden" name="action" value="add"><label>پایه<select name="grade" required><?php foreach($gradeNames as $n=>$label): ?><option value="<?= $n ?>"><?= classes_h($label) ?> (<?= $n ?>)</option><?php endforeach; ?></select></label><label>حرف صنف<select name="section_name" required><?php foreach($letters as $letter): ?><option value="<?= classes_h($letter) ?>"><?= classes_h($letter) ?></option><?php endforeach; ?></select></label><p class="abjad-help">حروف به ترتیب ابجد: الف، ب، ج، د، هـ، و، ز، ح، ط، ی، ک، ل، م، ن، س، ع، ف، ص، ق، ر، ش، ت، ث، خ، ذ، ض، ظ، غ</p><button class="button button-primary" type="submit" <?= (int)$year['is_closed']===1?'disabled':'' ?>>ثبت صنف</button></form></section>
<section class="panel"><h2>صنف‌های سال جاری</h2><?php if (!$sections): ?><p class="muted">هنوز صنفی برای این سال ثبت نشده است.</p><?php else: ?><div class="class-table-wrap"><table class="class-table"><thead><tr><th>نام صنف</th><th>آموزگار مسئول</th><th>شاگردان ثبت‌شده</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach($sections as $s): ?><tr><td class="class-label"><?= classes_h(($gradeNames[(int)$s['grade']]??$s['grade']).' '.$s['section_name']) ?></td><td><?= classes_h($s['teacher_name']?:'تعیین نشده') ?></td><td><?= (int)$s['student_count'] ?></td><td><?= (int)$s['is_active']===1?'فعال':'غیرفعال' ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= classes_h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="section_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="is_active" value="<?= (int)$s['is_active']===1?0:1 ?>"><button class="button button-secondary" type="submit"><?= (int)$s['is_active']===1?'غیرفعال‌سازی':'فعال‌سازی' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div><?php endif; ?>
<footer class="page-footer">سامانه مدیریت دانش‌آموزان · مدیریت صنف‌ها</footer></main></div><script src="assets/js/sidebar-clock.js" defer></script><script src="assets/js/sidebar-submenus.js" defer></script></body></html>