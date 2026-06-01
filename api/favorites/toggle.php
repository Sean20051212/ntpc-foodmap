<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('POST');

$user = requireLogin();
$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);

$exists = db()->prepare('SELECT 1 FROM restaurants WHERE restaurant_id = ?');
$exists->execute([$restaurantId]);
if (!$exists->fetchColumn()) {
    jsonErr('not_found', '找不到餐廳', 404);
}

$stmt = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND restaurant_id = ?');
$stmt->execute([(int) $user['user_id'], $restaurantId]);

if ($stmt->fetchColumn()) {
    $delete = db()->prepare('DELETE FROM favorites WHERE user_id = ? AND restaurant_id = ?');
    $delete->execute([(int) $user['user_id'], $restaurantId]);
    jsonOk(['is_favorited' => false]);
}

$insert = db()->prepare('INSERT INTO favorites (user_id, restaurant_id) VALUES (?, ?)');
$insert->execute([(int) $user['user_id'], $restaurantId]);

jsonOk(['is_favorited' => true]);

