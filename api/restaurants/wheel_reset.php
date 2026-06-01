<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/restaurants.php';

requireMethod('POST');
ensureSession();

$input = getInput();

if ($input) {
    $filters = restaurantParseFilters($input, [
        'limit' => 200,
        'offset' => 0,
    ]);
    unset($_SESSION[restaurantWheelSessionKey($filters)]);
} else {
    foreach (array_keys($_SESSION) as $key) {
        if (str_starts_with((string) $key, 'wheel_drawn_')) {
            unset($_SESSION[$key]);
        }
    }
}

jsonOk(['reset' => true]);
