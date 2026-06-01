<?php
declare(strict_types=1);

require_once __DIR__ . '/restaurants.php';

function adminOptionalNullableString(array $input, string $key, int $maxLen): ?string
{
    if (!array_key_exists($key, $input) || $input[$key] === null) {
        return null;
    }
    if (!is_string($input[$key])) {
        jsonErr('invalid_input', "無效的 {$key}");
    }

    $value = trim($input[$key]);
    if ($value === '') {
        return null;
    }
    if (mb_strlen($value, 'UTF-8') > $maxLen) {
        jsonErr('invalid_input', "{$key} 長度不可超過 {$maxLen}");
    }

    return $value;
}

function adminRequireFloat(array $input, string $key, float $min, float $max): float
{
    if (!isset($input[$key]) || !is_numeric($input[$key])) {
        jsonErr('invalid_input', "缺少或無效的 {$key}");
    }

    $value = (float) $input[$key];
    if ($value < $min || $value > $max) {
        jsonErr('invalid_input', "{$key} 超出有效範圍");
    }

    return $value;
}

function adminBool(array $input, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $input)) {
        return $default;
    }

    $value = $input[$key];
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value === 1;
    }
    if (is_string($value)) {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    return $default;
}

function adminEnsureRestaurantExists(int $restaurantId): void
{
    $stmt = db()->prepare('SELECT 1 FROM restaurants WHERE restaurant_id = ?');
    $stmt->execute([$restaurantId]);
    if (!$stmt->fetchColumn()) {
        jsonErr('not_found', '找不到餐廳', 404);
    }
}

function adminEnsureUserExists(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT user_id, username, is_admin, created_at
         FROM users
         WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonErr('not_found', '找不到使用者', 404);
    }

    return publicUser($user);
}

function adminNormalizeTime($value, string $key): string
{
    if (!is_string($value) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
        jsonErr('invalid_input', "無效的 {$key}");
    }

    return strlen($value) === 5 ? $value . ':00' : $value;
}

function adminInputArray(array $input, string $key): array
{
    if (!array_key_exists($key, $input) || $input[$key] === null) {
        return [];
    }
    if (!is_array($input[$key])) {
        jsonErr('invalid_input', "{$key} 必須是陣列");
    }

    return $input[$key];
}

function adminRestaurantPayload(array $input): array
{
    $zipcode = requireString($input, 'zipcode', 3);
    if (!preg_match('/^\d{3}$/', $zipcode)) {
        jsonErr('invalid_input', 'zipcode 必須是 3 碼');
    }

    $district = db()->prepare('SELECT 1 FROM districts WHERE zipcode = ?');
    $district->execute([$zipcode]);
    if (!$district->fetchColumn()) {
        jsonErr('invalid_input', 'zipcode 不存在');
    }

    return [
        'restaurant_name' => requireString($input, 'restaurant_name', 255),
        'description' => adminOptionalNullableString($input, 'description', 5000),
        'address' => requireString($input, 'address', 255),
        'zipcode' => $zipcode,
        'latitude' => adminRequireFloat($input, 'latitude', -90, 90),
        'longitude' => adminRequireFloat($input, 'longitude', -180, 180),
        'price_level' => optionalInt($input, 'price_level', null, 1, 4),
        'google_place_id' => adminOptionalNullableString($input, 'google_place_id', 100),
    ];
}

function adminRestaurantPhones(array $input): array
{
    $phones = [];
    foreach (adminInputArray($input, 'phones') as $phone) {
        if (!is_string($phone)) {
            jsonErr('invalid_input', 'phones 必須是字串陣列');
        }
        $phone = trim($phone);
        if ($phone === '') {
            continue;
        }
        if (mb_strlen($phone, 'UTF-8') > 50) {
            jsonErr('invalid_input', 'phone 長度不可超過 50');
        }
        $phones[] = $phone;
    }

    return array_values(array_unique($phones));
}

function adminRestaurantTags(array $input): array
{
    $tags = [];
    foreach (adminInputArray($input, 'tags') as $tagId) {
        if (!is_numeric($tagId) || (int) $tagId < 1) {
            jsonErr('invalid_input', 'tags 必須是正整數陣列');
        }
        $tags[] = (int) $tagId;
    }

    $tags = array_values(array_unique($tags));
    if (!$tags) {
        return [];
    }

    $holders = implode(',', array_fill(0, count($tags), '?'));
    $stmt = db()->prepare("SELECT tag_id FROM tags WHERE tag_id IN ({$holders})");
    $stmt->execute($tags);
    $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (count($existing) !== count($tags)) {
        jsonErr('invalid_input', '包含不存在的 tag_id');
    }

    return $tags;
}

function adminRestaurantHours(array $input): array
{
    $hours = [];
    foreach (adminInputArray($input, 'opentime') as $idx => $hour) {
        if (!is_array($hour)) {
            jsonErr('invalid_input', "opentime[{$idx}] 必須是物件");
        }

        $hours[] = [
            'day' => requireInt($hour, 'day', 0, 6),
            'start_time' => adminNormalizeTime($hour['start_time'] ?? null, 'start_time'),
            'end_time' => adminNormalizeTime($hour['end_time'] ?? null, 'end_time'),
            'spec_rec' => adminOptionalNullableString($hour, 'spec_rec', 255),
        ];
    }

    return $hours;
}

function adminReplaceRestaurantChildren(int $restaurantId, array $phones, array $tags, array $hours): void
{
    db()->prepare('DELETE FROM restaurant_phones WHERE restaurant_id = ?')->execute([$restaurantId]);
    $phoneInsert = db()->prepare('INSERT INTO restaurant_phones (restaurant_id, phone_number) VALUES (?, ?)');
    foreach ($phones as $phone) {
        $phoneInsert->execute([$restaurantId, $phone]);
    }

    db()->prepare('DELETE FROM restaurant_tags_mapping WHERE restaurant_id = ?')->execute([$restaurantId]);
    $tagInsert = db()->prepare('INSERT INTO restaurant_tags_mapping (restaurant_id, tag_id) VALUES (?, ?)');
    foreach ($tags as $tagId) {
        $tagInsert->execute([$restaurantId, $tagId]);
    }

    db()->prepare('DELETE FROM opentime WHERE restaurant_id = ?')->execute([$restaurantId]);
    $hourInsert = db()->prepare(
        'INSERT INTO opentime (restaurant_id, day, start_time, end_time, spec_rec)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($hours as $hour) {
        $hourInsert->execute([
            $restaurantId,
            $hour['day'],
            $hour['start_time'],
            $hour['end_time'],
            $hour['spec_rec'],
        ]);
    }
}
