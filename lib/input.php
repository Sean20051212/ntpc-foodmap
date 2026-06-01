<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

function getInput(): array
{
    $raw = file_get_contents('php://input');
    $json = [];

    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonErr('invalid_json', 'JSON 格式錯誤');
        }
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return array_merge($_REQUEST, $json);
}

function requireInt(array $input, string $key, ?int $min = null, ?int $max = null): int
{
    if (!isset($input[$key]) || filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
        jsonErr('invalid_input', "缺少或無效的 {$key}");
    }

    $value = (int) $input[$key];
    if ($min !== null && $value < $min) {
        jsonErr('invalid_input', "{$key} 不可小於 {$min}");
    }
    if ($max !== null && $value > $max) {
        jsonErr('invalid_input', "{$key} 不可大於 {$max}");
    }

    return $value;
}

function optionalInt(array $input, string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
{
    if (!isset($input[$key]) || $input[$key] === '') {
        return $default;
    }

    return requireInt($input, $key, $min, $max);
}

function requireString(array $input, string $key, int $maxLen = 255): string
{
    if (!isset($input[$key]) || !is_string($input[$key]) || trim($input[$key]) === '') {
        jsonErr('invalid_input', "缺少或無效的 {$key}");
    }

    $value = trim($input[$key]);
    if (mb_strlen($value, 'UTF-8') > $maxLen) {
        jsonErr('invalid_input', "{$key} 長度不可超過 {$maxLen}");
    }

    return $value;
}

function optionalString(array $input, string $key, int $maxLen = 255, string $default = ''): string
{
    if (!isset($input[$key]) || $input[$key] === null) {
        return $default;
    }
    if (!is_string($input[$key])) {
        jsonErr('invalid_input', "無效的 {$key}");
    }

    $value = trim($input[$key]);
    if (mb_strlen($value, 'UTF-8') > $maxLen) {
        jsonErr('invalid_input', "{$key} 長度不可超過 {$maxLen}");
    }

    return $value;
}

function requireLimit(array $input, int $default = 20, int $max = 100): int
{
    return optionalInt($input, 'limit', $default, 1, $max) ?? $default;
}

function requireOffset(array $input): int
{
    return optionalInt($input, 'offset', 0, 0, null) ?? 0;
}

