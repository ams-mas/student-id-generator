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

$pdo = database_connection();

/*
 * Rate-limit by username+client IP and by client IP. REMOTE_ADDR is used
 * deliberately; do not trust forwarded headers unless a trusted proxy is configured.
 * A school LAN can share one IP, so the network-wide threshold is higher than
 * the per-username-and-IP threshold.
 */
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$pairKey = hash('sha256', "login-pair\0" . $username . "\0" . $clientIp);
$ipKey = hash('sha256', "login-ip\0" . $clientIp);

try {
    $limitStmt = $pdo->prepare(
        'SELECT bucket_key, failed_attempts, locked_until
         FROM login_rate_limits
         WHERE bucket_key IN (:pair_key, :ip_key)'
    );
    $limitStmt->execute(['pair_key' => $pairKey, 'ip_key' => $ipKey]);
    $limits = $limitStmt->fetchAll();
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    foreach ($limits as $limit) {
        if (!empty($limit['locked_until'])) {
            $lockedUntil = new DateTimeImmutable((string) $limit['locked_until'], new DateTimeZone('UTC'));
            if ($lockedUntil > $now) {
                header('Location: index.php?throttled=1');
                exit;
            }
        }
    }

    $statement = $pdo->prepare(
        'SELECT id, username, password_hash, role, is_active FROM users WHERE username = :username LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Student ID Generator login service error: ' . $exception->getMessage());
    header('Location: index.php?database=1');
    exit;
}

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    usleep(250000);
    try {
        $recordFailure = static function (PDO $pdo, string $key, int $threshold, int $lockMinutes): void {
            $statement = $pdo->prepare(
                'INSERT INTO login_rate_limits (bucket_key, failed_attempts, window_started_at, locked_until)
                 VALUES (:bucket_key, 1, UTC_TIMESTAMP(), NULL)
                 ON DUPLICATE KEY UPDATE
                    failed_attempts = IF(window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), 1, failed_attempts + 1),
                    locked_until = IF(
                        window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE),
                        NULL,
                        IF(failed_attempts + 1 >= :threshold, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . $lockMinutes . ' MINUTE), locked_until)
                    ),
                    window_started_at = IF(window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE), UTC_TIMESTAMP(), window_started_at)'
            );
            $statement->execute(['bucket_key' => $key, 'threshold' => $threshold]);
        };
        $recordFailure($pdo, $pairKey, 5, 15);
        $recordFailure($pdo, $ipKey, 40, 10);

        // Keep the table small without deleting active lockouts.
        $pdo->exec('DELETE FROM login_rate_limits WHERE updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) AND (locked_until IS NULL OR locked_until < UTC_TIMESTAMP())');
    } catch (PDOException $exception) {
        error_log('Student ID Generator login rate-limit update failed: ' . $exception->getMessage());
        header('Location: index.php?database=1');
        exit;
    }
    header('Location: index.php?invalid=1');
    exit;
}

if ((int) $user['is_active'] !== 1) {
    header('Location: index.php?inactive=1');
    exit;
}

// Clear this username+IP failure bucket after successful authentication.
// Keep the IP-wide bucket to prevent successful logins from resetting network limits.
try {
    $clearStmt = $pdo->prepare('DELETE FROM login_rate_limits WHERE bucket_key = :bucket_key');
    $clearStmt->execute(['bucket_key' => $pairKey]);
} catch (PDOException $exception) {
    error_log('Student ID Generator login rate-limit cleanup failed: ' . $exception->getMessage());
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['role'] = (string) $user['role'];
unset($_SESSION['csrf_token']);

header('Location: dashboard.php');
exit;
