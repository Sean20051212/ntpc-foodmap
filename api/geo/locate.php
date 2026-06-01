<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/geo.php';

requireMethod('POST');

$input = getInput();
$lat = isset($input['lat']) && is_numeric($input['lat']) ? (float) $input['lat'] : null;
$lng = isset($input['lng']) && is_numeric($input['lng']) ? (float) $input['lng'] : null;

if ($lat === null || $lng === null) {
    jsonErr('invalid_input', '缺少或無效的 lat/lng');
}

jsonOk(geoLocateCoordinates($lat, $lng));
