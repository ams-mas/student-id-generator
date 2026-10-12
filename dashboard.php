<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$student = [
    'name' => 'Alex Morgan',
    'student_id' => 'STU-2026-001',
    'school' => 'Example University',
    'department' => 'Computer Science',
    'valid_until' => '2027-06-30',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student ID Card Generator</title>
<link rel="stylesheet" href="assets/css/style.css"><script src="assets/js/app.js" defer></script>
</head>
<body><main class="app-shell">
<header class="page-header dashboard-header">
    <div>
        <p class="eyebrow">STUDENT SERVICES</p>
        <h1>Student ID Card Generator</h1>
        <p class="muted">Enter details and preview a sample student ID card.</p>
    </div>
    <div class="account-actions">
        <span class="signed-in-as">Signed in as <strong><?= escape((string) $_SESSION['username']) ?></strong></span>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
            <button type="submit" class="button button-secondary">Sign out</button>
        </form>
    </div>
</header>
<div class="workspace">
<form class="panel form-panel" id="student-form">
<h2>Student details</h2><p class="muted">This starter version previews details in your browser. It does not save records.</p>
<label for="student-name">Full name</label><input id="student-name" name="name" type="text" maxlength="100" value="<?= escape($student['name']) ?>" required>
<label for="student-number">Student ID</label><input id="student-number" name="student_id" type="text" maxlength="40" value="<?= escape($student['student_id']) ?>" required>
<label for="school-name">School or university</label><input id="school-name" name="school" type="text" maxlength="120" value="<?= escape($student['school']) ?>" required>
<label for="department">Department</label><input id="department" name="department" type="text" maxlength="100" value="<?= escape($student['department']) ?>">
<label for="valid-until">Valid until</label><input id="valid-until" name="valid_until" type="date" value="<?= escape($student['valid_until']) ?>">
<label for="photo">Student photo</label><input id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp"><p class="help-text">Photo preview stays in this browser; no file is uploaded.</p>
<div class="button-row"><button type="button" class="button button-secondary" id="reset-button">Reset sample</button><button type="button" class="button button-primary" id="print-button">Print card</button></div>
</form>
<section class="panel preview-panel" aria-labelledby="preview-heading">
<div class="preview-heading"><div><h2 id="preview-heading">Live preview</h2><p class="muted">Sample card — not an official ID.</p></div><span class="status-pill">PREVIEW</span></div>
<article class="id-card" id="id-card" aria-label="Student ID card preview">
<div class="card-top"><div class="school-mark" aria-hidden="true">SU</div><div class="school-copy"><p class="card-label">STUDENT IDENTIFICATION</p><h3 id="card-school"><?= escape($student['school']) ?></h3></div></div>
<div class="card-main"><div class="photo-frame"><img id="card-photo" src="assets/images/default-avatar.svg" alt="Student photo preview"></div><div class="student-copy">
<p class="card-label">STUDENT NAME</p><h2 id="card-name"><?= escape($student['name']) ?></h2><p class="card-label">STUDENT ID</p><p class="student-number" id="card-student-id"><?= escape($student['student_id']) ?></p><p class="card-label">DEPARTMENT</p><p id="card-department"><?= escape($student['department']) ?></p>
</div></div>
<div class="card-bottom"><span>VALID UNTIL <strong id="card-valid-until">JUN 30, 2027</strong></span><span class="sample-watermark">SAMPLE</span></div>
</article><p class="help-text">Use fictional information while developing. Real student records require secure storage and access controls.</p>
</section></div><footer class="page-footer">Student ID Generator · Starter project</footer>
</main></body></html>
