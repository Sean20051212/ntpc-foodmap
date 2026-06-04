<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$filters = restaurantParseFilters(getInput());
$total = restaurantCount($filters);
$restaurants = restaurantFetchList($filters);

jsonOk([
    'total' => $total,
    'restaurants' => $restaurants,
]);
