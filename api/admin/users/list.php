<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('GET');
requireAdmin();

$input = getInput();
$limit = requireLimit($input, 50, 200);
$offset = requireOffset($input);
$keyword = optionalString($input, 'keyword', 50, '');
$like = '%' . $keyword . '%';

$where = $keyword === ''
    ? ''
    : ' WHERE u.username LIKE :like OR CAST(u.user_id AS CHAR) = :keyword';

$count = db()->prepare('SELECT COUNT(*) FROM users u' . $where);
if ($keyword !== '') {
    $count->bindValue(':like', $like);
    $count->bindValue(':keyword', $keyword);
}
$count->execute();
$total = (int) $count->fetchColumn();

$stmt = db()->prepare(
    'SELECT
        u.user_id,
        u.username,
        u.is_admin,
        u.created_at,
        (SELECT COUNT(*) FROM reviews rv WHERE rv.user_id = u.user_id) AS review_count,
        (SELECT COUNT(*) FROM favorites f WHERE f.user_id = u.user_id) AS favorite_count
     FROM users u'
     . $where .
    ' ORDER BY u.user_id ASC
      LIMIT :limit OFFSET :offset'
);
if ($keyword !== '') {
    $stmt->bindValue(':like', $like);
    $stmt->bindValue(':keyword', $keyword);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = array_map(static function (array $row): array {
    return [
        'user_id' => (int) $row['user_id'],
        'username' => $row['username'],
        'is_admin' => (int) $row['is_admin'],
        'review_count' => (int) $row['review_count'],
        'favorite_count' => (int) $row['favorite_count'],
        'created_at' => $row['created_at'],
    ];
}, $stmt->fetchAll());

jsonOk([
    'total' => $total,
    'users' => $users,
]);
