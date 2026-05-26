<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('POST');

$userId = require_auth();
$data = read_request_data();
$restaurantId = (int) ($data['restaurant_id'] ?? 0);
$conditions = $data['conditions'] ?? [];

if ($restaurantId <= 0) {
    error_response('restaurant_id is required.', 400);
}

if (!is_array($conditions)) {
    error_response('conditions must be an object.', 400);
}

$conditionsJson = json_encode($conditions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$pdo = db();

$stmt = $pdo->prepare('SELECT restaurant_id FROM restaurants WHERE restaurant_id = :restaurant_id LIMIT 1');
$stmt->execute(['restaurant_id' => $restaurantId]);
if (!$stmt->fetch()) {
    error_response('Restaurant not found.', 404);
}

$stmt = $pdo->prepare(
    'INSERT INTO wheel_history (user_id, restaurant_id, conditions_json, spun_at)
     VALUES (:user_id, :restaurant_id, :conditions_json, NOW())'
);
$stmt->execute([
    'user_id' => $userId,
    'restaurant_id' => $restaurantId,
    'conditions_json' => $conditionsJson,
]);

ok_response([
    'wheel_id' => (int) $pdo->lastInsertId(),
    'restaurant_id' => $restaurantId,
], 201);

