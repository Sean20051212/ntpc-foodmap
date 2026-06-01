<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond($statusCode, $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function connectDatabase()
{
    $configPath = __DIR__ . '/../../config.php';

    if (!file_exists($configPath)) {
        throw new RuntimeException('找不到 config.php');
    }

    require_once $configPath;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    return new PDO($dsn, DB_USER, DB_PASS, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
}

function getParam($key)
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : null;
}

try {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(405, [ 'ok' => false, 'error' => 'Method Not Allowed' ]);
    }

    // center required to compute distance-based filtering
    $latRaw = getParam('lat');
    $lngRaw = getParam('lng');
    if ($latRaw === null || $lngRaw === null || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
        respond(400, [ 'ok' => false, 'error' => 'lat and lng are required and must be numeric' ]);
    }
    $lat = (float) $latRaw;
    $lng = (float) $lngRaw;

    $radius = getParam('radius');
    $radius = ($radius !== null && is_numeric($radius)) ? (float) $radius : 1000.0;

    // optional comma-separated zipcodes
    $zipRaw = getParam('zipcode');
    $zipcodes = null;
    if ($zipRaw !== null && $zipRaw !== '') {
        $zipcodes = array_values(array_filter(array_map('trim', explode(',', $zipRaw))));
    }

    // optional comma-separated tag ids
    $tagRaw = getParam('tag_id');
    $tagIds = null;
    if ($tagRaw !== null && $tagRaw !== '') {
        $parts = array_map('trim', explode(',', $tagRaw));
        $tagIds = array();
        foreach ($parts as $p) {
            if ($p !== '' && is_numeric($p)) $tagIds[] = (int) $p;
        }
        if (count($tagIds) === 0) $tagIds = null;
    }

    $pdo = connectDatabase();

    // Build dynamic WHERE and parameters
    $where = [];
    $params = [];

    if ($zipcodes !== null) {
        $placeholders = [];
        foreach ($zipcodes as $i => $z) {
            $ph = ':z' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $z;
        }
        $where[] = 'r.zipcode IN (' . implode(',', $placeholders) . ')';
    }

    $joinTag = '';
    if ($tagIds !== null) {
        $joinTag = ' JOIN restaurant_tags_mapping m ON r.restaurant_id = m.restaurant_id ';
        $placeholders = [];
        foreach ($tagIds as $i => $t) {
            $ph = ':t' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $t;
        }
        $where[] = 'm.tag_id IN (' . implode(',', $placeholders) . ')';
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = <<<SQL
SELECT
    r.restaurant_id,
    r.name,
    r.description,
    r.tel,
    r.address,
    r.zipcode,
    r.opentime,
    r.longitude,
    r.latitude,
    r.changetime,
    (
        6371000 * 2 * ASIN(
            SQRT(
                POWER(SIN(RADIANS(r.latitude - :center_lat) / 2), 2)
                + COS(RADIANS(:center_lat_cos)) * COS(RADIANS(r.latitude))
                * POWER(SIN(RADIANS(r.longitude - :center_lng) / 2), 2)
            )
        )
    ) AS distance_meters
FROM restaurants r
{JOIN_TAG}
{WHERE_SQL}
GROUP BY r.restaurant_id
HAVING distance_meters <= :radius
ORDER BY distance_meters ASC, r.restaurant_id ASC
SQL;

    $sql = str_replace('{JOIN_TAG}', $joinTag, $sql);
    $sql = str_replace('{WHERE_SQL}', $whereSql, $sql);

    // base params
    $params[':center_lat'] = $lat;
    $params[':center_lat_cos'] = $lat;
    $params[':center_lng'] = $lng;
    $params[':radius'] = $radius;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $restaurants = $stmt->fetchAll();

    $short = array_map(function ($r) {
        return [
            'restaurant_id' => $r['restaurant_id'],
            'name' => $r['name'],
        ];
    }, $restaurants);

    respond(200, [
        'ok' => true,
        'data' => [
            'center' => ['lat' => $lat, 'lng' => $lng],
            'radius' => (int) round($radius),
            'count' => count($short),
            'restaurants' => $short,
        ],
    ]);

} catch (Exception $e) {
    respond(500, [ 'ok' => false, 'error' => 'Internal Server Error' ]);
}
