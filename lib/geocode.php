<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function geocodeAddress(string $address): ?array
{
    if (trim($address) === '' || GOOGLE_MAPS_KEY_BACKEND === '' || GOOGLE_MAPS_KEY_BACKEND === 'YOUR_BACKEND_KEY_HERE') {
        return null;
    }

    $cacheDir = sys_get_temp_dir() . '/ntpc_foodmap_geocode';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0700, true);
    }

    $cacheFile = $cacheDir . '/' . md5($address) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        return is_array($cached) ? $cached : null;
    }

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address' => $address,
        'region' => 'tw',
        'language' => 'zh-TW',
        'key' => GOOGLE_MAPS_KEY_BACKEND,
    ]);

    $response = @file_get_contents($url);
    if ($response === false) {
        return null;
    }

    $json = json_decode($response, true);
    if (($json['status'] ?? '') !== 'OK' || empty($json['results'][0]['geometry']['location'])) {
        return null;
    }

    $loc = $json['results'][0]['geometry']['location'];
    $result = ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng']];
    file_put_contents($cacheFile, json_encode($result), LOCK_EX);

    return $result;
}

