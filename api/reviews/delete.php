<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('DELETE');
rateLimitCheck('reviews_write');

$user = requireLogin();
$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);

$stmt = db()->prepare('DELETE FROM reviews WHERE user_id = ? AND restaurant_id = ?');
$stmt->execute([(int) $user['user_id'], $restaurantId]);

jsonOk(['deleted' => $stmt->rowCount() > 0]);

