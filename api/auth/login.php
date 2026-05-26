<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/session.php';

require_method('POST');

$data = read_request_data();
$username = trim((string) ($data['username'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($username === '' || $password === '') {
    error_response('Username and password are required.', 400);
}

$stmt = db()->prepare('SELECT user_id, username, password_hash FROM users WHERE username = :username LIMIT 1');
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    error_response('Invalid username or password.', 401);
}

login_user((int) $user['user_id'], (string) $user['username']);

ok_response([
    'user' => [
        'user_id' => (int) $user['user_id'],
        'username' => (string) $user['username'],
    ],
]);

