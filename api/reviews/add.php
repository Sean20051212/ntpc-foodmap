<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('POST');

$userId = require_auth();
$data = read_request_data();
$restaurantId = (int) ($data['restaurant_id'] ?? 0);
$rating = (int) ($data['rating'] ?? 0);
$comment = trim((string) ($data['comment'] ?? ''));

if ($restaurantId <= 0) {
    error_response('restaurant_id is required.', 400);
}

if ($rating < 1 || $rating > 5) {
    error_response('rating must be between 1 and 5.', 400);
}

if (mb_strlen($comment) > 1000) {
    error_response('comment must be 1000 characters or fewer.', 400);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT restaurant_id FROM restaurants WHERE restaurant_id = :restaurant_id LIMIT 1');
$stmt->execute(['restaurant_id' => $restaurantId]);
if (!$stmt->fetch()) {
    error_response('Restaurant not found.', 404);
}

$stmt = $pdo->prepare(
    'INSERT INTO reviews (user_id, restaurant_id, rating, comment, created_at)
     VALUES (:user_id, :restaurant_id, :rating, :comment, NOW())'
);
$stmt->execute([
    'user_id' => $userId,
    'restaurant_id' => $restaurantId,
    'rating' => $rating,
    'comment' => $comment === '' ? null : $comment,
]);

ok_response([
    'review_id' => (int) $pdo->lastInsertId(),
    'restaurant_id' => $restaurantId,
    'rating' => $rating,
], 201);

