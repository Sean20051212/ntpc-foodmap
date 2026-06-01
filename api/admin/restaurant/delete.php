<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);

$stmt = db()->prepare('DELETE FROM restaurants WHERE restaurant_id = ?');
$stmt->execute([$restaurantId]);

if ($stmt->rowCount() === 0) {
    jsonErr('not_found', '找不到餐廳', 404);
}

jsonOk(['deleted' => true]);
