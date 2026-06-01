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

    $idRaw = getParam('restaurant_id');
    if ($idRaw === null || !ctype_digit($idRaw)) {
        respond(400, [ 'ok' => false, 'error' => 'restaurant_id is required and must be an integer' ]);
    }
    $id = (int) $idRaw;

    $pdo = connectDatabase();

    $sql = 'SELECT restaurant_id, name, description, tel, address, zipcode, opentime, longitude, latitude, changetime FROM restaurants WHERE restaurant_id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        respond(404, [ 'ok' => false, 'error' => 'restaurant not found' ]);
    }

    // load tags
    $tagSql = 'SELECT t.tag_id, t.tag_name FROM restaurant_tags_mapping m JOIN tags t ON m.tag_id = t.tag_id WHERE m.restaurant_id = :id';
    $tstmt = $pdo->prepare($tagSql);
    $tstmt->execute([':id' => $id]);
    $tags = $tstmt->fetchAll();

    $row['longitude'] = (float) $row['longitude'];
    $row['latitude'] = (float) $row['latitude'];
    $row['tags'] = $tags;

    respond(200, [ 'ok' => true, 'data' => $row ]);

} catch (Exception $e) {
    respond(500, [ 'ok' => false, 'error' => 'Internal Server Error' ]);
}
