<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('POST');

$userId = require_auth();
$data = read_request_data();
$reviewId = (int) ($data['review_id'] ?? 0);
$rating = (int) ($data['rating'] ?? 0);
$comment = trim((string) ($data['comment'] ?? ''));

if ($reviewId <= 0) {
    error_response('review_id is required.', 400);
}

if ($rating < 1 || $rating > 5) {
    error_response('rating must be between 1 and 5.', 400);
}

if (mb_strlen($comment) > 1000) {
    error_response('comment must be 1000 characters or fewer.', 400);
}

$stmt = db()->prepare(
    'UPDATE reviews
        SET rating = :rating, comment = :comment
      WHERE review_id = :review_id AND user_id = :user_id'
);
$stmt->execute([
    'rating' => $rating,
    'comment' => $comment === '' ? null : $comment,
    'review_id' => $reviewId,
    'user_id' => $userId,
]);

ok_response([
    'updated' => $stmt->rowCount() > 0,
    'review_id' => $reviewId,
]);

