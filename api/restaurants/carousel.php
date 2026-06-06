<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$input = getInput();
$limit = requireLimit($input, 10, 50);

$stmt = db()->prepare(
    'SELECT p.url, p.local_path, r.restaurant_id, r.restaurant_name
     FROM restaurant_photos p
     JOIN restaurants r ON r.restaurant_id = p.restaurant_id
     WHERE p.is_main = 1
     ORDER BY RAND()
     LIMIT :limit'
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$photos = array_map(static function (array $row): array {
    return [
        'url' => $row['url'],
        'local_path' => $row['local_path'],
        'restaurant_id' => (int) $row['restaurant_id'],
        'restaurant_name' => $row['restaurant_name'],
    ];
}, $stmt->fetchAll());

jsonOk(['photos' => $photos]);
