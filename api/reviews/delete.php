<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('DELETE');
rateLimitCheck('reviews_write');

$user = requireLogin();
$input = getInput();
$restaurantId = requireInt($input, 'restaurant_id', 1);

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('DELETE FROM reviews WHERE user_id = ? AND restaurant_id = ?');
    $stmt->execute([(int) $user['user_id'], $restaurantId]);
    $deleted = $stmt->rowCount() > 0;

    if ($deleted) {
        $recalculate = $pdo->prepare(
            'UPDATE restaurants
             SET rating_count = (SELECT COUNT(*) FROM reviews WHERE restaurant_id = ?),
                 rating_avg = COALESCE((SELECT AVG(rating) FROM reviews WHERE restaurant_id = ?), 0)
             WHERE restaurant_id = ?'
        );
        $recalculate->execute([$restaurantId, $restaurantId, $restaurantId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonOk(['deleted' => $deleted]);
