<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);
$limit = requireLimit($input, 20, 100);
$offset = requireOffset($input);

$exists = db()->prepare('SELECT 1 FROM restaurants WHERE restaurant_id = ?');
$exists->execute([$restaurantId]);
if (!$exists->fetchColumn()) {
    jsonErr('not_found', '找不到餐廳', 404);
}

$count = db()->prepare('SELECT COUNT(*) FROM reviews WHERE restaurant_id = ?');
$count->execute([$restaurantId]);
$total = (int) $count->fetchColumn();

$stmt = db()->prepare(
    'SELECT
        rv.user_id,
        u.username,
        (SELECT COUNT(*) FROM reviews x WHERE x.user_id = rv.user_id) AS reviewer_total_reviews,
        rv.rating,
        rv.comment,
        rv.created_at,
        rv.updated_at
     FROM reviews rv
     JOIN users u ON u.user_id = rv.user_id
     WHERE rv.restaurant_id = :restaurant_id
     ORDER BY rv.updated_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':restaurant_id', $restaurantId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$reviews = array_map(static function (array $row): array {
    $row['user_id'] = (int) $row['user_id'];
    $row['reviewer_total_reviews'] = (int) $row['reviewer_total_reviews'];
    $row['rating'] = (int) $row['rating'];
    return $row;
}, $stmt->fetchAll());

jsonOk(['total' => $total, 'reviews' => $reviews]);
