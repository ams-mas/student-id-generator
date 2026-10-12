<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to the Student ID Card Generator.">
    <title>Sign In | Student ID Card Generator</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-brand" aria-label="Student ID Card Generator">
            <div class="brand-mark" aria-hidden="true">SU</div>
            <p class="login-eyebrow">STUDENT SERVICES PORTAL</p>
            <h1>Student ID Card Generator</h1>
            <p class="login-intro">A simple workspace for preparing student identification cards.</p>
            <div class="login-feature-list">
                <p><span aria-hidden="true">✓</span> Create and preview student ID cards</p>
                <p><span aria-hidden="true">✓</span> Print cards when they are ready</p>
                <p><span aria-hidden="true">✓</span> Sign-in required to access the workspace</p>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-panel-heading">
                <p class="eyebrow">WELCOME BACK</p>
                <h2 id="login-title">Sign in to your account</h2>
                <p class="muted">Enter your account details to continue.</p>
            </div>

            <?php if (isset($_GET['invalid'])): ?>
                <div class="login-alert" role="alert">The username or password is incorrect.</div>
            <?php endif; ?>
            <?php if (isset($_GET['database'])): ?>
                <div class="login-alert" role="alert">The login service is unavailable. Start MySQL, import database/schema.sql, and create an account using the README instructions.</div>
            <?php endif; ?>

            <form action="login.php" method="post" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">

                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username"
                       maxlength="80" required autofocus>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password"
                       maxlength="4096" required>

                <button class="button button-primary login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>

            <p class="login-footnote">Authorized users only. Use a strong, unique password.</p>
        </section>
        <footer class="login-footer">Student ID Generator · Keep student information private</footer>
    </main>
</body>
</html>
