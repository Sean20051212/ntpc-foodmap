<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$input = getInput();
$limit = requireLimit($input, 3, 20);
$filters = restaurantParseFilters($input, [
    'limit' => $limit,
    'offset' => 0,
    'sort' => 'rating_desc',
]);

jsonOk(['restaurants' => restaurantFetchList($filters)]);
