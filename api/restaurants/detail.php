<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$input = getInput();
$restaurantId = requireInt($input, 'id', 1);
$restaurant = restaurantFetchDetail($restaurantId);

if (!$restaurant) {
    jsonErr('not_found', 'restaurant not found', 404);
}

jsonOk(['restaurant' => $restaurant]);
