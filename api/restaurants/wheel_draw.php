<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('POST');
ensureSession();

$filters = restaurantParseFilters(getInput(), [
    'limit' => 200,
    'offset' => 0,
]);
$sessionKey = restaurantWheelSessionKey($filters);
$drawn = $_SESSION[$sessionKey] ?? [];
if (!is_array($drawn)) {
    $drawn = [];
}
$drawn = array_values(array_unique(array_map('intval', $drawn)));

$ids = restaurantFetchIds($filters);
$available = array_values(array_diff($ids, $drawn));

if (!$available) {
    jsonOk([
        'exhausted' => true,
        'restaurant' => null,
    ]);
}

$restaurantId = $available[random_int(0, count($available) - 1)];
$drawn[] = $restaurantId;
$_SESSION[$sessionKey] = array_values(array_unique($drawn));

jsonOk([
    'exhausted' => false,
    'restaurant' => restaurantFetchDetail($restaurantId),
]);
