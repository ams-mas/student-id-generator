<?php
declare(strict_types=1);

$topbarUsername = (string) ($_SESSION['username'] ?? '');
$topbarRole = current_role();
$topbarRoleLabels = ['super_admin' => 'سوپر ادمین', 'admin' => 'ادمین', 'user' => 'کاربر'];
$topbarEsc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<header class="admin-topbar">
    <div class="admin-topbar-title">
        <span class="admin-topbar-caption">پنل مدیریت مدرسه</span>
        <h1>سامانه مدیریت دانش‌آموزان</h1>
    </div>
    <div class="admin-topbar-account">
        <span class="admin-topbar-avatar" aria-hidden="true"><?= $topbarEsc(function_exists('mb_substr') ? mb_substr($topbarUsername, 0, 1) : substr($topbarUsername, 0, 1)) ?></span>
        <span class="admin-topbar-user"><strong><?= $topbarEsc($topbarUsername) ?></strong><small><?= $topbarEsc($topbarRoleLabels[$topbarRole] ?? $topbarRole) ?></small></span>
    </div>
</header>
