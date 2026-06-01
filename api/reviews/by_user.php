<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$input = getInput();
$userId = requireInt($input, 'user_id', 1);
$limit = requireLimit($input, 20, 100);
$offset = requireOffset($input);

$exists = db()->prepare('SELECT 1 FROM users WHERE user_id = ?');
$exists->execute([$userId]);
if (!$exists->fetchColumn()) {
    jsonErr('not_found', '找不到使用者', 404);
}

$count = db()->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');
$count->execute([$userId]);
$total = (int) $count->fetchColumn();

$stmt = db()->prepare(
    'SELECT
        r.restaurant_id,
        r.restaurant_name,
        p.url AS main_photo_url,
        rv.rating,
        rv.comment,
        rv.created_at,
        rv.updated_at
     FROM reviews rv
     JOIN restaurants r ON r.restaurant_id = rv.restaurant_id
     LEFT JOIN restaurant_photos p ON p.restaurant_id = r.restaurant_id AND p.is_main = 1
     WHERE rv.user_id = :user_id
     ORDER BY rv.updated_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$reviews = array_map(static function (array $row): array {
    $row['restaurant_id'] = (int) $row['restaurant_id'];
    $row['rating'] = (int) $row['rating'];
    return $row;
}, $stmt->fetchAll());

jsonOk(['total' => $total, 'reviews' => $reviews]);

