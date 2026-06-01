<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);
$userId = requireInt($input, 'user_id', 1);

adminEnsureRestaurantExists($restaurantId);
adminEnsureUserExists($userId);

$pdo = db();
$pdo->beginTransaction();

try {
    $delete = $pdo->prepare('DELETE FROM reviews WHERE restaurant_id = ? AND user_id = ?');
    $delete->execute([$restaurantId, $userId]);

    if ($delete->rowCount() === 0) {
        $pdo->rollBack();
        jsonErr('not_found', '找不到這筆評論', 404);
    }

    $recalculate = $pdo->prepare(
        'UPDATE restaurants
         SET rating_count = (SELECT COUNT(*) FROM reviews WHERE restaurant_id = ?),
             rating_avg = COALESCE((SELECT AVG(rating) FROM reviews WHERE restaurant_id = ?), 0)
         WHERE restaurant_id = ?'
    );
    $recalculate->execute([$restaurantId, $restaurantId, $restaurantId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonOk(['deleted' => true]);
