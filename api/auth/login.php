<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('POST');
ensureSession();
rateLimitCheck('auth_login');

$input = getInput();
$username = requireString($input, 'username', 50);
$password = requireString($input, 'password', 100);

$stmt = db()->prepare(
    'SELECT user_id, username, password_hash, is_admin, created_at
     FROM users
     WHERE username = ?'
);
$stmt->execute([$username]);
$row = $stmt->fetch();

if (!$row || !password_verify($password, $row['password_hash'])) {
    jsonErr('invalid_credentials', '帳號或密碼錯誤', 401);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $row['user_id'];

jsonOk(['user' => publicUser($row)]);

