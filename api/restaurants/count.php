<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$filters = restaurantParseFilters(getInput(), [
    'limit' => 1,
    'offset' => 0,
]);

jsonOk(['total' => restaurantCount($filters)]);
