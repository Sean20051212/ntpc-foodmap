<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('GET');

$input = getInput();
$filters = restaurantParseFilters($input, [
    'limit' => 200,
    'offset' => 0,
]);

$count = (int)($input['count'] ?? 8);
if ($count < 1) $count = 1;
if ($count > 32) $count = 32;

$exclude = [];
$excludeRaw = $input['exclude'] ?? '';
if (is_string($excludeRaw) && $excludeRaw !== '') {
    foreach (explode(',', $excludeRaw) as $v) {
        $n = (int)trim($v);
        if ($n > 0) $exclude[$n] = true;
    }
}

$ids = restaurantFetchIds($filters);
$availableIds = array_values(array_filter($ids, fn($id) => !isset($exclude[(int)$id])));

$list = restaurantFetchList($filters);
$candidates = [];
foreach ($list as $r) {
    if (isset($exclude[(int)$r['restaurant_id']])) continue;
    $candidates[] = $r;
    if (count($candidates) >= $count) break;
}

jsonOk([
    'restaurant_ids' => $availableIds,
    'candidates' => $candidates,
]);
