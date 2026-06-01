<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$user = requireLogin();

$openNow = restaurantOpenNowSql();
$stmt = db()->prepare(
    "SELECT
        r.restaurant_id,
        r.restaurant_name,
        r.description,
        r.address,
        r.zipcode,
        d.district_name,
        r.latitude,
        r.longitude,
        r.rating_avg,
        r.rating_count,
        r.price_level,
        p.url AS main_photo_url,
        CASE WHEN $openNow THEN 1 ELSE 0 END AS is_open_now,
        f.created_at AS favorited_at
     FROM favorites f
     JOIN restaurants r ON r.restaurant_id = f.restaurant_id
     LEFT JOIN districts d ON d.zipcode = r.zipcode
     LEFT JOIN restaurant_photos p ON p.restaurant_id = r.restaurant_id AND p.is_main = 1
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
$stmt->execute([(int) $user['user_id']]);
$rows = $stmt->fetchAll();

// 撈所有相關餐廳的 tags 一次，再以 restaurant_id 分組
$tagsByRestaurant = [];
if ($rows) {
    $ids = array_map(static function ($r) { return (int) $r['restaurant_id']; }, $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tagStmt = db()->prepare(
        "SELECT m.restaurant_id, t.tag_id, t.tag_name
         FROM restaurant_tags_mapping m
         JOIN tags t ON t.tag_id = m.tag_id
         WHERE m.restaurant_id IN ($placeholders)"
    );
    $tagStmt->execute($ids);
    foreach ($tagStmt->fetchAll() as $t) {
        $rid = (int) $t['restaurant_id'];
        $tagsByRestaurant[$rid][] = ['tag_id' => (int) $t['tag_id'], 'tag_name' => $t['tag_name']];
    }
}

$restaurants = array_map(static function (array $row) use ($tagsByRestaurant): array {
    $row['restaurant_id'] = (int) $row['restaurant_id'];
    $row['latitude'] = (float) $row['latitude'];
    $row['longitude'] = (float) $row['longitude'];
    $row['rating_avg'] = (float) $row['rating_avg'];
    $row['rating_count'] = (int) $row['rating_count'];
    $row['price_level'] = $row['price_level'] === null ? null : (int) $row['price_level'];
    $row['is_open_now'] = (bool) $row['is_open_now'];
    $row['is_favorited'] = true;
    $row['tags'] = $tagsByRestaurant[$row['restaurant_id']] ?? [];
    return $row;
}, $rows);

jsonOk(['restaurants' => $restaurants]);

