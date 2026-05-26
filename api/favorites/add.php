<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('POST');

$userId = require_auth();
$data = read_request_data();
$restaurantId = (int) ($data['restaurant_id'] ?? 0);

if ($restaurantId <= 0) {
    error_response('restaurant_id is required.', 400);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT restaurant_id FROM restaurants WHERE restaurant_id = :restaurant_id LIMIT 1');
$stmt->execute(['restaurant_id' => $restaurantId]);
if (!$stmt->fetch()) {
    error_response('Restaurant not found.', 404);
}

$stmt = $pdo->prepare(
    'SELECT favorite_id FROM favorites WHERE user_id = :user_id AND restaurant_id = :restaurant_id LIMIT 1'
);
$stmt->execute([
    'user_id' => $userId,
    'restaurant_id' => $restaurantId,
]);
$existing = $stmt->fetch();

if ($existing) {
    ok_response([
        'favorite_id' => (int) $existing['favorite_id'],
        'restaurant_id' => $restaurantId,
        'already_exists' => true,
    ]);
}

$stmt = $pdo->prepare(
    'INSERT INTO favorites (user_id, restaurant_id, created_at) VALUES (:user_id, :restaurant_id, NOW())'
);
$stmt->execute([
    'user_id' => $userId,
    'restaurant_id' => $restaurantId,
]);

ok_response([
    'favorite_id' => (int) $pdo->lastInsertId(),
    'restaurant_id' => $restaurantId,
], 201);

