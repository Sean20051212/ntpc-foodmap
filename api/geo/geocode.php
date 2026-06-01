<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/geo.php';
require_once __DIR__ . '/../../lib/geocode.php';
require_once __DIR__ . '/../../lib/rate_limit.php';

requireMethod('POST');
rateLimitCheck('geocode');

$input = getInput();
$address = requireString($input, 'address', 255);
$coords = geocodeAddress($address);

if ($coords === null) {
    jsonErr('not_found', '找不到此地址或尚未設定 Geocoding key', 404);
}

$located = geoLocateCoordinates((float) $coords['lat'], (float) $coords['lng']);

jsonOk(array_merge([
    'lat' => (float) $coords['lat'],
    'lng' => (float) $coords['lng'],
], $located));
