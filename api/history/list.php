<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('GET');

$userId = require_auth();
$limit = (int) ($_GET['limit'] ?? 20);
$limit = max(1, min($limit, 100));

$pdo = db();

$searchStmt = $pdo->prepare(
    'SELECT search_id, address, filter_json, searched_at
       FROM search_history
      WHERE user_id = :user_id
      ORDER BY searched_at DESC
      LIMIT :limit'
);
$searchStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$searchStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$searchStmt->execute();

$wheelStmt = $pdo->prepare(
    'SELECT wh.wheel_id, wh.restaurant_id, r.name, wh.conditions_json, wh.spun_at
       FROM wheel_history wh
       JOIN restaurants r ON r.restaurant_id = wh.restaurant_id
      WHERE wh.user_id = :user_id
      ORDER BY wh.spun_at DESC
      LIMIT :limit'
);
$wheelStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$wheelStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$wheelStmt->execute();

ok_response([
    'search_history' => $searchStmt->fetchAll(),
    'wheel_history' => $wheelStmt->fetchAll(),
]);

