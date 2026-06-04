<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$input = getInput();
$userId = optionalInt($input, 'user_id', null, 1);

if ($userId === null) {
    $current = currentUser();
    if (!$current) {
        jsonErr('invalid_input', '缺少 user_id');
    }
    $userId = (int) $current['user_id'];
}

$stmt = db()->prepare(
    'SELECT
        u.user_id,
        u.username,
        u.is_admin,
        u.created_at,
        COUNT(rv.restaurant_id) AS review_count
     FROM users u
     LEFT JOIN reviews rv ON rv.user_id = u.user_id
     WHERE u.user_id = ?
     GROUP BY u.user_id, u.username, u.is_admin, u.created_at'
);
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    jsonErr('not_found', '找不到使用者', 404);
}

jsonOk([
    'user' => [
        'user_id' => (int) $row['user_id'],
        'username' => $row['username'],
        'is_admin' => (int) $row['is_admin'],
        'review_count' => (int) $row['review_count'],
        'created_at' => $row['created_at'],
    ],
]);

