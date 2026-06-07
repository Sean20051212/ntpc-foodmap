<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

// DB-3 後 districts 不再存中心座標。center_latitude / center_longitude 由該區餐廳座標 AVG 動態算出。
// 對 0 餐廳的區回傳 null，前端 focusDistrict 會判 Number.isFinite 跳過。
$districts = [];
$stmt = db()->query(
    'SELECT d.zipcode, d.district_name,
            AVG(r.latitude)  AS center_latitude,
            AVG(r.longitude) AS center_longitude
     FROM districts d
     LEFT JOIN restaurants r ON r.zipcode = d.zipcode
     GROUP BY d.zipcode, d.district_name
     ORDER BY d.zipcode ASC'
);

foreach ($stmt->fetchAll() as $row) {
    $districts[$row['zipcode']] = [
        'zipcode' => $row['zipcode'],
        'district_name' => $row['district_name'],
        'center_latitude' => $row['center_latitude'] === null ? null : (float) $row['center_latitude'],
        'center_longitude' => $row['center_longitude'] === null ? null : (float) $row['center_longitude'],
        'adjacent_zipcodes' => [],
    ];
}

$adjacency = db()->query(
    'SELECT zipcode_a, zipcode_b
     FROM district_adjacency
     ORDER BY zipcode_a ASC, zipcode_b ASC'
);

foreach ($adjacency->fetchAll() as $row) {
    if (isset($districts[$row['zipcode_a']])) {
        $districts[$row['zipcode_a']]['adjacent_zipcodes'][] = $row['zipcode_b'];
    }
    if (isset($districts[$row['zipcode_b']])) {
        $districts[$row['zipcode_b']]['adjacent_zipcodes'][] = $row['zipcode_a'];
    }
}

foreach ($districts as &$district) {
    sort($district['adjacent_zipcodes'], SORT_STRING);
}
unset($district);

jsonOk(['districts' => array_values($districts)]);
