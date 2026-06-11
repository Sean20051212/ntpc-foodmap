<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$restaurantId = optionalInt($input, 'restaurant_id', null, 1);
$restaurant = adminRestaurantPayload($input);
$phones = adminRestaurantPhones($input);
$tags = adminRestaurantTags($input);
$hours = adminRestaurantHours($input);

if ($restaurantId !== null) {
    adminEnsureRestaurantExists($restaurantId);
}

$pdo = db();
$pdo->beginTransaction();

try {
    if ($restaurantId === null) {
        $dup = $pdo->prepare('
            SELECT restaurant_id FROM restaurants
            WHERE restaurant_name = ?
              AND (6371000 * 2 * ASIN(SQRT(
                    POW(SIN(RADIANS(? - latitude) / 2), 2) +
                    COS(RADIANS(?)) * COS(RADIANS(latitude)) *
                    POW(SIN(RADIANS(? - longitude) / 2), 2)
                  ))) < 50
        ');
        $dup->execute([
            $restaurant['restaurant_name'],
            $restaurant['latitude'], $restaurant['latitude'],
            $restaurant['longitude'],
        ]);
        if ($dup->fetchColumn()) {
            jsonErr('duplicate', '50 公尺內已有同名餐廳，請確認是否重複新增');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO restaurants
                (restaurant_name, description, address, zipcode, latitude, longitude, price_level, google_place_id)
             VALUES
                (:restaurant_name, :description, :address, :zipcode, :latitude, :longitude, :price_level, :google_place_id)'
        );
        $stmt->execute($restaurant);
        $restaurantId = (int) $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare(
            'UPDATE restaurants
             SET restaurant_name = :restaurant_name,
                 description = :description,
                 address = :address,
                 zipcode = :zipcode,
                 latitude = :latitude,
                 longitude = :longitude,
                 price_level = :price_level,
                 google_place_id = :google_place_id
             WHERE restaurant_id = :restaurant_id'
        );
        $stmt->execute(array_merge($restaurant, ['restaurant_id' => $restaurantId]));
    }

    adminReplaceRestaurantChildren($restaurantId, $phones, $tags, $hours);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonOk(['restaurant' => restaurantFetchDetail($restaurantId)]);
