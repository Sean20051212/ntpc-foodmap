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

// 反向 geocode：lat/lng → formatted_address（中文）
// 用於 BE-2 「依地址比對 districts」判斷使用者是否在新北市
function reverseGeocode(float $lat, float $lng): ?string
{
    if (GOOGLE_MAPS_KEY_BACKEND === '' || GOOGLE_MAPS_KEY_BACKEND === 'YOUR_BACKEND_KEY_HERE') {
        return null;
    }

    $cacheDir = sys_get_temp_dir() . '/ntpc_foodmap_reverse_geocode';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0700, true);
    }

    // 4 位小數 (~11m 精度) 已足夠決定行政區，提高 cache 命中率
    $key = round($lat, 4) . ',' . round($lng, 4);
    $cacheFile = $cacheDir . '/' . md5($key) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        return is_array($cached) ? ($cached['address'] ?? null) : null;
    }

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'latlng' => $lat . ',' . $lng,
        'language' => 'zh-TW',
        'key' => GOOGLE_MAPS_KEY_BACKEND,
    ]);

    $response = @file_get_contents($url);
    if ($response === false) {
        return null;
    }

    $json = json_decode($response, true);
    if (($json['status'] ?? '') !== 'OK' || empty($json['results'][0]['formatted_address'])) {
        return null;
    }

    $address = (string) $json['results'][0]['formatted_address'];
    file_put_contents($cacheFile, json_encode(['address' => $address]), LOCK_EX);

    return $address;
}

