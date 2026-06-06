<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$photoId = optionalInt($input, 'photo_id', null, 1);
$restaurantId = requireInt($input, 'restaurant_id', 1);
$url = requireString($input, 'url', 500);
$isMain = adminBool($input, 'is_main');

adminEnsureRestaurantExists($restaurantId);

if ($photoId !== null) {
    $exists = db()->prepare('SELECT 1 FROM restaurant_photos WHERE photo_id = ?');
    $exists->execute([$photoId]);
    if (!$exists->fetchColumn()) {
        jsonErr('not_found', '找不到照片', 404);
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    if ($isMain) {
        $params = [$restaurantId];
        $exclude = '';
        if ($photoId !== null) {
            $exclude = ' AND photo_id <> ?';
            $params[] = $photoId;
        }
        $clear = $pdo->prepare("UPDATE restaurant_photos SET is_main = 0 WHERE restaurant_id = ?{$exclude}");
        $clear->execute($params);
    }

    if ($photoId === null) {
        $stmt = $pdo->prepare(
            'INSERT INTO restaurant_photos (restaurant_id, url, is_main)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$restaurantId, $url, $isMain ? 1 : 0]);
        $photoId = (int) $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare(
            'UPDATE restaurant_photos
             SET restaurant_id = ?, url = ?, is_main = ?
             WHERE photo_id = ?'
        );
        $stmt->execute([$restaurantId, $url, $isMain ? 1 : 0, $photoId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$photo = db()->prepare(
    'SELECT photo_id, restaurant_id, url, is_main
     FROM restaurant_photos
     WHERE photo_id = ?'
);
$photo->execute([$photoId]);
$row = $photo->fetch();

jsonOk([
    'photo' => [
        'photo_id' => (int) $row['photo_id'],
        'restaurant_id' => (int) $row['restaurant_id'],
        'url' => $row['url'],
        'is_main' => (int) $row['is_main'],
    ],
]);
