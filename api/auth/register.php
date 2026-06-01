<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('POST');
ensureSession();
rateLimitCheck('auth_register');

$input = getInput();
$username = requireString($input, 'username', 50);
$password = requireString($input, 'password', 100);

if (mb_strlen($username, 'UTF-8') < 3) {
    jsonErr('invalid_input', 'username 至少需要 3 個字');
}
if (mb_strlen($password, 'UTF-8') < 8) {
    jsonErr('invalid_input', 'password 至少需要 8 個字');
}

try {
    $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
        jsonErr('conflict', 'username 已被使用', 409);
    }
    throw $e;
}

$userId = (int) db()->lastInsertId();
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

jsonOk([
    'user' => [
        'user_id' => $userId,
        'username' => $username,
        'is_admin' => 0,
    ],
]);

