<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('GET');

$userId = require_auth();

$stmt = db()->prepare('SELECT user_id, username, created_at FROM users WHERE user_id = :user_id LIMIT 1');
$stmt->execute(['user_id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    error_response('User not found', 404);
}

ok_response([
    'user' => [
        'user_id' => (int) $user['user_id'],
        'username' => (string) $user['username'],
        'created_at' => (string) $user['created_at'],
    ],
]);
