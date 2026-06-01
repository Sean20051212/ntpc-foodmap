<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$user = requireLogin();

$stmt = db()->prepare(
    'SELECT
        r.restaurant_id,
        r.restaurant_name,
        r.description,
        r.address,
        r.zipcode,
        r.latitude,
        r.longitude,
        r.rating_avg,
        r.rating_count,
        r.price_level,
        p.url AS main_photo_url,
        f.created_at AS favorited_at
     FROM favorites f
     JOIN restaurants r ON r.restaurant_id = f.restaurant_id
     LEFT JOIN restaurant_photos p ON p.restaurant_id = r.restaurant_id AND p.is_main = 1
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC'
);
$stmt->execute([(int) $user['user_id']]);

$restaurants = array_map(static function (array $row): array {
    $row['restaurant_id'] = (int) $row['restaurant_id'];
    $row['latitude'] = (float) $row['latitude'];
    $row['longitude'] = (float) $row['longitude'];
    $row['rating_avg'] = (float) $row['rating_avg'];
    $row['rating_count'] = (int) $row['rating_count'];
    $row['price_level'] = $row['price_level'] === null ? null : (int) $row['price_level'];
    $row['is_favorited'] = true;
    return $row;
}, $stmt->fetchAll());

jsonOk(['restaurants' => $restaurants]);

