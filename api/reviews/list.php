<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('GET');

$restaurantId = (int) ($_GET['restaurant_id'] ?? 0);

if ($restaurantId > 0) {
    $stmt = db()->prepare(
        'SELECT rv.review_id, rv.user_id, u.username, rv.restaurant_id,
                rv.rating, rv.comment, rv.created_at
           FROM reviews rv
           JOIN users u ON u.user_id = rv.user_id
          WHERE rv.restaurant_id = :restaurant_id
          ORDER BY rv.created_at DESC'
    );
    $stmt->execute(['restaurant_id' => $restaurantId]);
} else {
    $userId = require_auth();
    $stmt = db()->prepare(
        'SELECT rv.review_id, rv.restaurant_id, r.name, rv.rating,
                rv.comment, rv.created_at
           FROM reviews rv
           JOIN restaurants r ON r.restaurant_id = rv.restaurant_id
          WHERE rv.user_id = :user_id
          ORDER BY rv.created_at DESC'
    );
    $stmt->execute(['user_id' => $userId]);
}

ok_response([
    'reviews' => $stmt->fetchAll(),
]);

