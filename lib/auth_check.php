<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/session.php';

function current_user_id(): ?int
{
    start_app_session();
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_auth(): int
{
    $userId = current_user_id();
    if ($userId === null || $userId <= 0) {
        error_response('Authentication required', 401);
    }

    return $userId;
}

