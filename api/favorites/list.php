<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('GET');

$userId = require_auth();

$stmt = db()->prepare(
    'SELECT f.favorite_id, f.restaurant_id, f.created_at,
            r.name, r.address, r.tel, r.opentime, r.latitude, r.longitude,
            COALESCE(GROUP_CONCAT(t.tag_name ORDER BY t.tag_name SEPARATOR "、"), "餐廳") AS category
       FROM favorites f
       JOIN restaurants r ON r.restaurant_id = f.restaurant_id
       LEFT JOIN restaurant_tags_mapping rtm ON rtm.restaurant_id = r.restaurant_id
       LEFT JOIN tags t ON t.tag_id = rtm.tag_id
      WHERE f.user_id = :user_id
      GROUP BY f.favorite_id, f.restaurant_id, f.created_at,
               r.name, r.address, r.tel, r.opentime, r.latitude, r.longitude
      ORDER BY f.created_at DESC'
);
$stmt->execute(['user_id' => $userId]);

ok_response([
    'favorites' => $stmt->fetchAll(),
]);
