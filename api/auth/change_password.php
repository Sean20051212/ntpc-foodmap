<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('POST');
rateLimitCheck('change_password');

$user = requireLogin();
$input = getInput();
$oldPassword = requireString($input, 'old_password', 100);
$newPassword = requireString($input, 'new_password', 100);

if (mb_strlen($newPassword, 'UTF-8') < 8) {
    jsonErr('invalid_input', 'new_password 至少需要 8 個字');
}

$stmt = db()->prepare('SELECT password_hash FROM users WHERE user_id = ?');
$stmt->execute([(int) $user['user_id']]);
$row = $stmt->fetch();

if (!$row || !password_verify($oldPassword, $row['password_hash'])) {
    jsonErr('forbidden', '舊密碼錯誤', 403);
}

$update = db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
$update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['user_id']]);

jsonOk(null);

