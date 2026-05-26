<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('POST');

$userId = require_auth();
$data = read_request_data();
$address = trim((string) ($data['address'] ?? ''));
$filter = $data['filter'] ?? [];

if ($address === '') {
    error_response('address is required.', 400);
}

if (!is_array($filter)) {
    error_response('filter must be an object.', 400);
}

$filterJson = json_encode($filter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$stmt = db()->prepare(
    'INSERT INTO search_history (user_id, address, filter_json, searched_at)
     VALUES (:user_id, :address, :filter_json, NOW())'
);
$stmt->execute([
    'user_id' => $userId,
    'address' => $address,
    'filter_json' => $filterJson,
]);

ok_response([
    'search_id' => (int) db()->lastInsertId(),
], 201);

