<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/session.php';

require_method('POST');

$data = read_request_data();
$username = trim((string) ($data['username'] ?? ''));
$password = (string) ($data['password'] ?? '');

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    error_response('Username must be 3-50 characters and only contain letters, numbers, or underscore.', 400);
}

if (strlen($password) < 8 || strlen($password) > 72) {
    error_response('Password must be 8-72 characters.', 400);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = :username LIMIT 1');
$stmt->execute(['username' => $username]);
if ($stmt->fetch()) {
    error_response('Username already exists.', 409);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, created_at) VALUES (:username, :password_hash, NOW())'
);
$stmt->execute([
    'username' => $username,
    'password_hash' => $passwordHash,
]);

$userId = (int) $pdo->lastInsertId();
login_user($userId, $username);

ok_response([
    'user' => [
        'user_id' => $userId,
        'username' => $username,
    ],
], 201);

