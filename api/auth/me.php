<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/auth_check.php';

require_method('GET');

$userId = require_auth();

ok_response([
    'user' => [
        'user_id' => $userId,
        'username' => $_SESSION['username'] ?? null,
    ],
]);

