<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$userId = requireInt($input, 'user_id', 1);

if (isSuperAdmin($userId)) {
    jsonErr('forbidden', '不可刪除 super admin', 403);
}

adminEnsureUserExists($userId);

$pdo = db();
$pdo->beginTransaction();

try {
    $reviewedRestaurants = $pdo->prepare('SELECT DISTINCT restaurant_id FROM reviews WHERE user_id = ?');
    $reviewedRestaurants->execute([$userId]);
    $restaurantIds = array_map('intval', $reviewedRestaurants->fetchAll(PDO::FETCH_COLUMN));

    $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM reviews WHERE user_id = ?')->execute([$userId]);

    if ($restaurantIds) {
        $placeholders = implode(',', array_fill(0, count($restaurantIds), '?'));
        $recalculate = $pdo->prepare(
            "UPDATE restaurants r
             SET rating_count = (SELECT COUNT(*) FROM reviews rv WHERE rv.restaurant_id = r.restaurant_id),
                 rating_avg = COALESCE((SELECT AVG(rv.rating) FROM reviews rv WHERE rv.restaurant_id = r.restaurant_id), 0)
             WHERE r.restaurant_id IN ({$placeholders})"
        );
        $recalculate->execute($restaurantIds);
    }

    $delete = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
    $delete->execute([$userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonOk(['deleted' => true]);
