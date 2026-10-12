<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid form token. Return to the sign-in page and try again.');
}

$username = strtolower(trim((string) ($_POST['username'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
if ($username === '' || strlen($username) > 80 || $password === '' || strlen($password) > 4096) {
    header('Location: index.php?invalid=1');
    exit;
}

try {
    $statement = database_connection()->prepare(
        'SELECT id, username, password_hash, role, is_active FROM users WHERE username = :username LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Student ID Generator login database error: ' . $exception->getMessage());
    header('Location: index.php?database=1');
    exit;
}

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    usleep(250000);
    header('Location: index.php?invalid=1');
    exit;
}
if ((int) $user['is_active'] !== 1) {
    header('Location: index.php?inactive=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['role'] = (string) $user['role'];
unset($_SESSION['csrf_token']);

header('Location: dashboard.php');
exit;
