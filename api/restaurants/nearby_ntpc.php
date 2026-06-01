<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$input = getInput();
$lat = restaurantOptionalFloat($input, 'lat');
$lng = restaurantOptionalFloat($input, 'lng');
if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    jsonErr('invalid_input', 'valid lat and lng are required');
}

$limit = requireLimit($input, 20, 100);
$filters = restaurantParseFilters([
    'user_lat' => (string) $lat,
    'user_lng' => (string) $lng,
    'limit' => (string) $limit,
    'offset' => '0',
    'sort' => 'distance_asc',
], [
    'districts' => [],
    'tags' => [],
    'keyword' => '',
    'bbox' => null,
    'min_rating' => null,
    'max_distance_m' => null,
]);

jsonOk(['restaurants' => restaurantFetchList($filters)]);
