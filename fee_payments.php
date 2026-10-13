<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_roles(['super_admin','admin']);
require_once __DIR__ . '/config/database.php';
$pdo=database_connection();
$error=''; $success='';
function fee_pay_h(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_token_is_valid($_POST['csrf_token']??null)){http_response_code(400);exit('درخواست معتبر نیست. صفحه را تازه‌سازی کنید.');}
 try{
  if((string)($_POST['action']??'')!=='receive_payment') throw new RuntimeException('عملیات نامعتبر است.');
  $chargeId=filter_var($_POST['charge_id']??null,FILTER_VALIDATE_INT)?:0;
  $amount=filter_var(trim((string)($_POST['amount']??'')),FILTER_VALIDATE_FLOAT);
  $method=(string)($_POST['payment_method']??'cash');
  $notes=trim((string)($_POST['notes']??''));
  if($chargeId<1||$amount===false||!is_finite((float)$amount)||(float)$amount<=0) throw new RuntimeException('مبلغ پرداخت باید بیشتر از صفر باشد.');
  if(!in_array($method,['cash','bank','other'],true)) throw new RuntimeException('روش پرداخت معتبر نیست.');
  if(mb_strlen($notes)>500) throw new RuntimeException('یادداشت نباید بیشتر از ۵۰۰ نویسه باشد.');
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT c.id,c.amount_due,s.first_name,s.last_name,c.period_name FROM student_fee_charges c JOIN students s ON s.id=c.student_id WHERE c.id=? FOR UPDATE');
  $q->execute([$chargeId]); $charge=$q->fetch();
  if(!$charge) throw new RuntimeException('بدهی موردنظر پیدا نشد.');
  $paidQ=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM student_fee_payments WHERE charge_id=?');
  $paidQ->execute([$chargeId]); $paid=(float)$paidQ->fetchColumn();
  $remaining=max(0,(float)$charge['amount_due']-$paid);
  if((float)$amount>$remaining+0.0001) throw new RuntimeException('مبلغ پرداخت از باقی‌داری بیشتر است. باقی‌داری فعلی: '.number_format($remaining,2));
  $receipt='RC-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
  $insert=$pdo->prepare('INSERT INTO student_fee_payments (charge_id,amount,receipt_number,payment_method,notes) VALUES (?,?,?,?,?)');
  $insert->execute([$chargeId,$amount,$receipt,$method,$notes]);
  $pdo->commit();
  $success='پرداخت ثبت شد. شماره رسید: '.$receipt.' — '.$charge['first_name'].' '.$charge['last_name'].'، '.$charge['period_name'].'، مبلغ '.number_format((float)$amount,2);
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  if($e instanceof RuntimeException)$error=$e->getMessage();
  else{error_log('Student fee payment error: '.$e->getMessage());$error='پرداخت ذخیره نشد. بررسی کنید به‌روزرسانی فیس‌ها اجرا شده باشد.';}
 }
}
$year=$pdo->query('SELECT id,name FROM school_years WHERE is_active=1 AND is_closed=0 ORDER BY id DESC LIMIT 1')->fetch()?:null;
$charges=[];
if($year){
 try{
  $q=$pdo->prepare("SELECT c.id,c.period_name,c.amount_due,c.discount_amount,s.id AS student_id,s.student_number,s.first_name,s.last_name,s.father_name,s.class_name,COALESCE(SUM(p.amount),0) AS paid FROM student_fee_charges c JOIN students s ON s.id=c.student_id LEFT JOIN student_fee_payments p ON p.charge_id=c.id WHERE c.school_year_id=? GROUP BY c.id,c.period_name,c.amount_due,c.discount_amount,s.id,s.student_number,s.first_name,s.last_name,s.father_name,s.class_name ORDER BY s.last_name,s.first_name,c.id");
  $q->execute([(int)$year['id']]);$charges=$q->fetchAll();
 }catch(PDOException $e){$error=$error?:'ابتدا به‌روزرسانی فیس‌ها را از بخش به‌روزرسانی پایگاه داده اجرا کنید.';}
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>دریافت فیس شاگردان</title><link rel="stylesheet" href="assets/css/style.css"><style>
.pay-wrap{max-width:1200px;margin:24px auto;padding:0 18px}.pay-panel{background:#fff;border:1px solid #e1e7f0;border-radius:14px;padding:20px;margin:18px 0}.pay-alert{padding:12px 15px;border-radius:9px;margin:12px 0;background:#e8f7ef;color:#176644}.pay-error{background:#fff0f0;color:#a52c36}.pay-table-wrap{overflow-x:auto}.pay-table{width:100%;border-collapse:collapse}.pay-table th,.pay-table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap}.pay-table th{background:#f5f7fb}.pay-form{display:flex;align-items:center;gap:6px}.pay-form input,.pay-form select{min-height:36px;max-width:130px;border:1px solid #ccd5e2;border-radius:7px;padding:6px;font:inherit}.pay-status{font-weight:700}.pay-muted{color:#64748b}
</style></head><body class="profile-page"><div class="admin-layout"><?php require __DIR__.'/includes/sidebar.php';?><main class="admin-main"><?php require __DIR__.'/includes/topbar.php';?><div class="pay-wrap"><header class="page-header"><p class="eyebrow">امور مالی</p><h1>دریافت فیس شاگردان</h1><p class="pay-muted">برای هر دوره می‌توانید پرداخت کامل، قسمی یا پرداخت بعدی را ثبت کنید. هر پرداخت رسید جداگانه دارد.</p></header>
<?php if($success!==''):?><div class="pay-alert" role="status"><?=fee_pay_h($success)?></div><?php endif;?><?php if($error!==''):?><div class="pay-alert pay-error" role="alert"><?=fee_pay_h($error)?></div><?php endif;?>
<section class="pay-panel"><h2>بدهی‌های سال تعلیمی <?=fee_pay_h($year['name']??'')?></h2><?php if(!$year):?><p>ابتدا سال تعلیمی فعلی را فعال کنید.</p><?php elseif(!$charges):?><p>برای این سال هنوز فیس شاگردان ساخته نشده است. هنگام ثبت شاگرد، فیس‌های تعریف‌شده به پرونده او اضافه می‌شود.</p><?php else:?><div class="pay-table-wrap"><table class="pay-table"><thead><tr><th>شماره شاگرد</th><th>نام شاگرد</th><th>صنف</th><th>دوره</th><th>فیس پس از تخفیف</th><th>پرداخت‌شده</th><th>باقی‌داری</th><th>ثبت پرداخت</th></tr></thead><tbody><?php foreach($charges as $c):$remaining=max(0,(float)$c['amount_due']-(float)$c['paid']);?><tr><td><?=fee_pay_h($c['student_number'])?></td><td><?=fee_pay_h($c['first_name'].' '.$c['last_name'])?></td><td><?=fee_pay_h($c['class_name'])?></td><td><?=fee_pay_h($c['period_name'])?></td><td><?=number_format((float)$c['amount_due'],2)?></td><td><?=number_format((float)$c['paid'],2)?></td><td class="pay-status"><?=number_format($remaining,2)?></td><td><?php if($remaining>0):?><form class="pay-form" method="post"><input type="hidden" name="csrf_token" value="<?=fee_pay_h(csrf_token())?>"><input type="hidden" name="action" value="receive_payment"><input type="hidden" name="charge_id" value="<?=(int)$c['id']?>"><input name="amount" type="number" min="0.01" max="<?=number_format($remaining,2,'.','')?>" step="0.01" required placeholder="مبلغ"><select name="payment_method" aria-label="روش پرداخت"><option value="cash">نقدی</option><option value="bank">بانکی</option><option value="other">دیگر</option></select><button class="button button-primary" type="submit">ثبت</button></form><?php else:?><span>تکمیل‌شده</span><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></div></main></div><script src="assets/js/sidebar-clock.js" defer></script><script src="assets/js/sidebar-submenus.js" defer></script></body></html>