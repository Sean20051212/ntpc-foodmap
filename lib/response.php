<?php
declare(strict_types=1);

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok_response(array $data = [], int $statusCode = 200): void
{
    json_response([
        'ok' => true,
        'data' => $data,
    ], $statusCode);
}

function error_response(string $message, int $statusCode = 400, array $extra = []): void
{
    json_response(array_merge([
        'ok' => false,
        'error' => $message,
    ], $extra), $statusCode);
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        error_response('Method not allowed', 405);
    }
}

function read_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        if ($rawBody === false || trim($rawBody) === '') {
            return [];
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            error_response('Invalid JSON body', 400);
        }

        return $data;
    }

    return $_POST;
}

