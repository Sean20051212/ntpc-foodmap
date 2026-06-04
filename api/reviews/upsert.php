<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('POST');
rateLimitCheck('reviews_write');

$user = requireLogin();
$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);
$rating = requireInt($input, 'rating', 1, 5);
$comment = optionalString($input, 'comment', 1000, '');

$exists = db()->prepare('SELECT 1 FROM restaurants WHERE restaurant_id = ?');
$exists->execute([$restaurantId]);
if (!$exists->fetchColumn()) {
    jsonErr('not_found', '找不到餐廳', 404);
}

$stmt = db()->prepare(
    'INSERT INTO reviews (user_id, restaurant_id, rating, comment)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        rating = VALUES(rating),
        comment = VALUES(comment)'
);
$stmt->execute([(int) $user['user_id'], $restaurantId, $rating, $comment]);

$review = db()->prepare(
    'SELECT user_id, restaurant_id, rating, comment, created_at, updated_at
     FROM reviews
     WHERE user_id = ? AND restaurant_id = ?'
);
$review->execute([(int) $user['user_id'], $restaurantId]);
$row = $review->fetch();
$row['user_id'] = (int) $row['user_id'];
$row['restaurant_id'] = (int) $row['restaurant_id'];
$row['rating'] = (int) $row['rating'];

jsonOk(['review' => $row]);

