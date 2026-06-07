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
        'SELECT d.zipcode, d.district_name
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
        ];
    }, $stmt->fetchAll());
}

// BE-2：用地址字串比對 districts.district_name 判斷使用者是否在新北市
// 取代舊版「距離區中心 > 15km 視為不在新北」的距離判斷
function geoLocateCoordinates(float $lat, float $lng): array
{
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonErr('invalid_input', 'lat/lng 超出有效範圍');
    }

    $address = reverseGeocode($lat, $lng);
    $notInNtpc = [
        'in_ntpc' => false,
        'district' => null,
        'adjacent' => [],
    ];

    if ($address === null) {
        return $notInNtpc;
    }

    // 撈所有新北區名，比對 address 中是否含某一區名
    $stmt = db()->query('SELECT zipcode, district_name FROM districts ORDER BY zipcode ASC');
    $matched = null;
    foreach ($stmt->fetchAll() as $district) {
        if (str_contains($address, (string) $district['district_name'])) {
            $matched = $district;
            break;
        }
    }

    if ($matched === null) {
        return $notInNtpc;
    }

    return [
        'in_ntpc' => true,
        'district' => [
            'zipcode' => $matched['zipcode'],
            'district_name' => $matched['district_name'],
        ],
        'adjacent' => geoAdjacentDistricts($matched['zipcode']),
    ];
}
