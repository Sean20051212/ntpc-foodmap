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

$stmt = db()->prepare(
    'DELETE FROM favorites WHERE user_id = :user_id AND restaurant_id = :restaurant_id'
);
$stmt->execute([
    'user_id' => $userId,
    'restaurant_id' => $restaurantId,
]);

ok_response([
    'removed' => $stmt->rowCount() > 0,
    'restaurant_id' => $restaurantId,
]);

