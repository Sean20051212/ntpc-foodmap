<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$filters = restaurantParseFilters(getInput(), [
    'limit' => 200,
    'offset' => 0,
]);

jsonOk(['restaurant_ids' => restaurantFetchIds($filters)]);
