<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function jsonOk($data = null): void
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonErr(string $code, string $message, int $http = 400): void
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function requireMethod(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        jsonErr('method_not_allowed', 'HTTP method 不允許', 405);
    }
}

function jsonUnexpectedError(Throwable $e): void
{
    $message = defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : '伺服器發生錯誤';
    jsonErr('server_error', $message, 500);
}

