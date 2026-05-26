<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('POST');

$userId = require_auth();
$data = read_request_data();
$reviewId = (int) ($data['review_id'] ?? 0);

if ($reviewId <= 0) {
    error_response('review_id is required.', 400);
}

$stmt = db()->prepare('DELETE FROM reviews WHERE review_id = :review_id AND user_id = :user_id');
$stmt->execute([
    'review_id' => $reviewId,
    'user_id' => $userId,
]);

ok_response([
    'removed' => $stmt->rowCount() > 0,
    'review_id' => $reviewId,
]);

