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

function parseFloatParam($key, $required)
{
	if (!isset($_GET[$key])) {
		if ($required) {
			respond(400, [
				'ok' => false,
				'error' => sprintf('%s is required and must be numeric', $key),
			]);
		}

		return null;
	}

	$value = trim((string) $_GET[$key]);

	if ($value === '' || !is_numeric($value)) {
		respond(400, [
			'ok' => false,
			'error' => sprintf('%s is required and must be numeric', $key),
		]);
	}

	return (float) $value;
}

try {
	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
		respond(405, [
			'ok' => false,
			'error' => 'Method Not Allowed',
		]);
	}

	$lat = parseFloatParam('lat', true);
	$lng = parseFloatParam('lng', true);
	$radius = parseFloatParam('radius', false);
	if ($radius === null) {
		$radius = 1000.0;
	}

	if ($lat < -90 || $lat > 90) {
		respond(400, [
			'ok' => false,
			'error' => 'lat must be between -90 and 90',
		]);
	}

	if ($lng < -180 || $lng > 180) {
		respond(400, [
			'ok' => false,
			'error' => 'lng must be between -180 and 180',
		]);
	}

	if ($radius <= 0) {
		respond(400, [
			'ok' => false,
			'error' => 'radius must be greater than 0',
		]);
	}

	$pdo = connectDatabase();

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
				POWER(SIN(RADIANS(r.latitude - :center_lat_delta) / 2), 2)
				+ COS(RADIANS(:center_lat_cos)) * COS(RADIANS(r.latitude))
				* POWER(SIN(RADIANS(r.longitude - :center_lng_delta) / 2), 2)
			)
		)
	) AS distance_meters
FROM restaurants r
HAVING distance_meters <= :radius
ORDER BY distance_meters ASC, r.restaurant_id ASC
SQL;

	$stmt = $pdo->prepare($sql);
	$stmt->execute([
		':center_lat_delta' => $lat,
		':center_lat_cos' => $lat,
		':center_lng_delta' => $lng,
		':radius' => $radius,
	]);

	$restaurants = $stmt->fetchAll();

	// Only return id and name here; details belong to detail.php
	$short = array_map(function ($r) {
		return [
			'restaurant_id' => $r['restaurant_id'],
			'name' => $r['name'],
		];
	}, $restaurants);

	respond(200, [
		'ok' => true,
		'data' => [
			'center' => [ 'lat' => $lat, 'lng' => $lng ],
			'radius' => (int) round($radius),
			'count' => count($short),
			'restaurants' => $short,
		],
	]);
} catch (Exception $e) {
	respond(500, [
		'ok' => false,
		'error' => 'Internal Server Error',
	]);
}
