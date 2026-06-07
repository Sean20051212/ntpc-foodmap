<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/geocode.php';

function geoDistanceSql(string $latParam1, string $latParam2, string $lngParam, string $latColumn, string $lngColumn): string
{
    return "(6371000 * 2 * ASIN(SQRT(
        POW(SIN(RADIANS(:{$latParam1} - {$latColumn}) / 2), 2) +
        COS(RADIANS(:{$latParam2})) * COS(RADIANS({$latColumn})) *
        POW(SIN(RADIANS(:{$lngParam} - {$lngColumn}) / 2), 2)
    )))";
}

function geoAdjacentDistricts(string $zipcode): array
{
    $stmt = db()->prepare(
        'SELECT d.zipcode, d.district_name, d.center_latitude, d.center_longitude
         FROM district_adjacency da
         JOIN districts d
           ON d.zipcode = CASE
                WHEN da.zipcode_a = :zipcode_a THEN da.zipcode_b
                ELSE da.zipcode_a
              END
         WHERE da.zipcode_a = :zipcode_b OR da.zipcode_b = :zipcode_c
         ORDER BY d.zipcode ASC'
    );
    $stmt->execute([
        'zipcode_a' => $zipcode,
        'zipcode_b' => $zipcode,
        'zipcode_c' => $zipcode,
    ]);

    return array_map(static function (array $row): array {
        return [
            'zipcode' => $row['zipcode'],
            'district_name' => $row['district_name'],
            'center_latitude' => (float) $row['center_latitude'],
            'center_longitude' => (float) $row['center_longitude'],
        ];
    }, $stmt->fetchAll());
}

function geoDistrictPayload(array $district): array
{
    return [
        'zipcode' => $district['zipcode'],
        'district_name' => $district['district_name'],
        'center_latitude' => (float) $district['center_latitude'],
        'center_longitude' => (float) $district['center_longitude'],
    ];
}

function geoLocateAddress(string $address): array
{
    $address = trim($address);
    if ($address === '') {
        return [
            'in_ntpc' => false,
            'district' => null,
            'adjacent' => [],
            'source' => 'address',
        ];
    }

    $stmt = db()->query(
        'SELECT zipcode, district_name, center_latitude, center_longitude
         FROM districts
         ORDER BY CHAR_LENGTH(district_name) DESC, zipcode ASC'
    );

    foreach ($stmt->fetchAll() as $district) {
        if (mb_strpos($address, (string) $district['district_name'], 0, 'UTF-8') !== false) {
            return [
                'in_ntpc' => true,
                'district' => geoDistrictPayload($district),
                'adjacent' => geoAdjacentDistricts($district['zipcode']),
                'source' => 'address',
            ];
        }
    }

    return [
        'in_ntpc' => false,
        'district' => null,
        'adjacent' => [],
        'source' => 'address',
    ];
}

function geoLocateCoordinatesByNearestCenterLegacy(float $lat, float $lng): array
{
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonErr('invalid_input', 'lat/lng 超出有效範圍');
    }

    $distanceSql = geoDistanceSql('lat1', 'lat2', 'lng', 'center_latitude', 'center_longitude');
    $stmt = db()->prepare(
        "SELECT zipcode, district_name, center_latitude, center_longitude, {$distanceSql} AS distance_m
         FROM districts
         ORDER BY distance_m ASC
         LIMIT 1"
    );
    $stmt->execute([
        'lat1' => $lat,
        'lat2' => $lat,
        'lng' => $lng,
    ]);
    $district = $stmt->fetch();

    if (!$district) {
        return [
            'in_ntpc' => false,
            'district' => null,
            'adjacent' => [],
            'source' => 'coordinates',
        ];
    }

    return [
        'in_ntpc' => true,
        'district' => geoDistrictPayload($district),
        'adjacent' => geoAdjacentDistricts($district['zipcode']),
        'source' => 'coordinates',
    ];
}

function geoLocateCoordinates(float $lat, float $lng, string $address = ''): array
{
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonErr('invalid_input', 'invalid lat/lng');
    }

    $address = trim($address);
    if ($address === '') {
        $reverse = geocodeCoordinates($lat, $lng);
        $address = trim((string) ($reverse['formatted_address'] ?? ''));
    }

    if ($address !== '') {
        $located = geoLocateAddress($address);
        $located['source'] = 'coordinates_address';
        $located['formatted_address'] = $address;
        return $located;
    }

    return [
        'in_ntpc' => null,
        'district' => null,
        'adjacent' => [],
        'source' => 'coordinates',
    ];
}

function geoNearestDistrictCluster(float $lat, float $lng): array
{
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonErr('invalid_input', 'invalid lat/lng');
    }

    $distanceSql = geoDistanceSql('lat1', 'lat2', 'lng', 'center_latitude', 'center_longitude');
    $stmt = db()->prepare(
        "SELECT zipcode, district_name, center_latitude, center_longitude, {$distanceSql} AS distance_m
         FROM districts
         ORDER BY distance_m ASC
         LIMIT 1"
    );
    $stmt->execute([
        'lat1' => $lat,
        'lat2' => $lat,
        'lng' => $lng,
    ]);

    $district = $stmt->fetch();
    if (!$district) {
        return [];
    }

    return array_values(array_unique(array_merge(
        [$district['zipcode']],
        array_map(static fn(array $adjacent): string => (string) $adjacent['zipcode'], geoAdjacentDistricts($district['zipcode']))
    )));
}
