<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function restaurantArrayInput(array $input, string $key): array
{
    if (!array_key_exists($key, $input)) {
        return [];
    }

    $value = $input[$key];
    if (is_array($value)) {
        return $value;
    }

    return array_filter(array_map('trim', explode(',', (string) $value)), static fn($v): bool => $v !== '');
}

function restaurantOptionalFloat(array $input, string $key, ?float $default = null): ?float
{
    if (!array_key_exists($key, $input) || $input[$key] === '') {
        return $default;
    }

    if (!is_numeric($input[$key])) {
        jsonErr('invalid_input', "{$key} must be numeric");
    }

    return (float) $input[$key];
}

function restaurantParseFilters(array $input, array $overrides = []): array
{
    $districts = [];
    foreach (restaurantArrayInput($input, 'district') as $zipcode) {
        $zipcode = trim((string) $zipcode);
        if (!preg_match('/^\d{3}$/', $zipcode)) {
            jsonErr('invalid_input', 'district must be a 3 digit zipcode');
        }
        $districts[] = $zipcode;
    }

    $tags = [];
    foreach (restaurantArrayInput($input, 'tag') as $tagId) {
        if (!is_numeric($tagId) || (int) $tagId < 1) {
            jsonErr('invalid_input', 'tag must be positive integer');
        }
        $tags[] = (int) $tagId;
    }

    $lat = restaurantOptionalFloat($input, 'user_lat');
    $lng = restaurantOptionalFloat($input, 'user_lng');
    if (($lat === null) !== ($lng === null)) {
        jsonErr('invalid_input', 'user_lat and user_lng must be provided together');
    }
    if ($lat !== null && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)) {
        jsonErr('invalid_input', 'invalid user location');
    }

    $bbox = null;
    $bboxRaw = optionalString($input, 'bbox', 100, '');
    if ($bboxRaw !== '') {
        $parts = array_map('trim', explode(',', $bboxRaw));
        if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
            jsonErr('invalid_input', 'bbox must be lat_sw,lng_sw,lat_ne,lng_ne');
        }
        $bbox = array_map('floatval', $parts);
    }

    $maxDistance = optionalInt($input, 'max_distance_m', null, 1, null);
    if ($maxDistance !== null && ($lat === null || $lng === null)) {
        jsonErr('invalid_input', 'max_distance_m requires user_lat and user_lng');
    }

    $sort = optionalString($input, 'sort', 30, 'rating_desc');
    if (!in_array($sort, ['rating_desc', 'distance_asc', 'name_asc'], true)) {
        jsonErr('invalid_input', 'invalid sort');
    }
    if ($sort === 'distance_asc' && ($lat === null || $lng === null)) {
        $sort = 'rating_desc';
    }

    $filters = [
        'districts' => array_values(array_unique($districts)),
        'tags' => array_values(array_unique($tags)),
        'min_rating' => restaurantOptionalFloat($input, 'min_rating'),
        'max_distance_m' => $maxDistance,
        'user_lat' => $lat,
        'user_lng' => $lng,
        'bbox' => $bbox,
        'keyword' => optionalString($input, 'keyword', 100, ''),
        'limit' => requireLimit($input, 50, 1000),
        'offset' => requireOffset($input),
        'sort' => $sort,
    ];

    return array_merge($filters, $overrides);
}

function restaurantAddParam(array &$params, string $name, $value, int $type = PDO::PARAM_STR): void
{
    $params[$name] = ['value' => $value, 'type' => $type];
}

function restaurantBindParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $name => $param) {
        $stmt->bindValue(':' . $name, $param['value'], $param['type']);
    }
}

function restaurantDistanceSql(string $lat1, string $lat2, string $lng1): string
{
    return "(6371000 * 2 * ASIN(SQRT(
        POW(SIN(RADIANS(:{$lat1} - r.latitude) / 2), 2) +
        COS(RADIANS(:{$lat2})) * COS(RADIANS(r.latitude)) *
        POW(SIN(RADIANS(:{$lng1} - r.longitude) / 2), 2)
    )))";
}

function restaurantOpenNowSql(): string
{
    return "EXISTS (
        SELECT 1
        FROM opentime ot
        WHERE ot.restaurant_id = r.restaurant_id
          AND (
              (ot.day = DAYOFWEEK(NOW()) - 1
                AND ot.start_time <= ot.end_time
                AND CURTIME() BETWEEN ot.start_time AND ot.end_time)
              OR
              (ot.day = DAYOFWEEK(NOW()) - 1
                AND ot.start_time > ot.end_time
                AND CURTIME() >= ot.start_time)
              OR
              (ot.day = MOD(DAYOFWEEK(NOW()) - 1 + 6, 7)
                AND ot.start_time > ot.end_time
                AND CURTIME() <= ot.end_time)
          )
    )";
}

function restaurantBuildWhere(array $filters, array &$params, string $prefix): string
{
    $where = [];

    if ($filters['districts']) {
        $holders = [];
        foreach ($filters['districts'] as $idx => $zipcode) {
            $name = "{$prefix}_district_{$idx}";
            restaurantAddParam($params, $name, $zipcode);
            $holders[] = ':' . $name;
        }
        $where[] = 'r.zipcode IN (' . implode(',', $holders) . ')';
    }

    if ($filters['tags']) {
        $holders = [];
        foreach ($filters['tags'] as $idx => $tagId) {
            $name = "{$prefix}_tag_{$idx}";
            restaurantAddParam($params, $name, $tagId, PDO::PARAM_INT);
            $holders[] = ':' . $name;
        }
        $where[] = 'EXISTS (
            SELECT 1
            FROM restaurant_tags_mapping rtm_filter
            WHERE rtm_filter.restaurant_id = r.restaurant_id
              AND rtm_filter.tag_id IN (' . implode(',', $holders) . ')
        )';
    }

    if ($filters['min_rating'] !== null) {
        restaurantAddParam($params, "{$prefix}_min_rating", $filters['min_rating']);
        $where[] = "r.rating_avg >= :{$prefix}_min_rating";
    }

    if ($filters['bbox'] !== null) {
        [$latSw, $lngSw, $latNe, $lngNe] = $filters['bbox'];
        restaurantAddParam($params, "{$prefix}_lat_min", min($latSw, $latNe));
        restaurantAddParam($params, "{$prefix}_lat_max", max($latSw, $latNe));
        restaurantAddParam($params, "{$prefix}_lng_min", min($lngSw, $lngNe));
        restaurantAddParam($params, "{$prefix}_lng_max", max($lngSw, $lngNe));
        $where[] = "r.latitude BETWEEN :{$prefix}_lat_min AND :{$prefix}_lat_max";
        $where[] = "r.longitude BETWEEN :{$prefix}_lng_min AND :{$prefix}_lng_max";
    }

    if ($filters['keyword'] !== '') {
        restaurantAddParam($params, "{$prefix}_keyword_name", '%' . $filters['keyword'] . '%');
        restaurantAddParam($params, "{$prefix}_keyword_description", '%' . $filters['keyword'] . '%');
        restaurantAddParam($params, "{$prefix}_keyword_address", '%' . $filters['keyword'] . '%');
        $where[] = "(
            r.restaurant_name LIKE :{$prefix}_keyword_name
            OR r.description LIKE :{$prefix}_keyword_description
            OR r.address LIKE :{$prefix}_keyword_address
        )";
    }

    if ($filters['max_distance_m'] !== null) {
        $distanceSql = restaurantDistanceSql("{$prefix}_dist_lat1", "{$prefix}_dist_lat2", "{$prefix}_dist_lng1");
        restaurantAddParam($params, "{$prefix}_dist_lat1", $filters['user_lat']);
        restaurantAddParam($params, "{$prefix}_dist_lat2", $filters['user_lat']);
        restaurantAddParam($params, "{$prefix}_dist_lng1", $filters['user_lng']);
        restaurantAddParam($params, "{$prefix}_max_distance", $filters['max_distance_m'], PDO::PARAM_INT);
        $where[] = "{$distanceSql} <= :{$prefix}_max_distance";
    }

    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function restaurantCurrentUserId(): ?int
{
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE[session_name()])) {
        return null;
    }

    $user = currentUser();
    return $user ? (int) $user['user_id'] : null;
}

function restaurantOrderBy(array $filters): string
{
    if ($filters['sort'] === 'name_asc') {
        return 'r.restaurant_name ASC, r.restaurant_id ASC';
    }

    if ($filters['sort'] === 'distance_asc' && $filters['user_lat'] !== null) {
        return 'distance_m ASC, r.rating_avg DESC, r.restaurant_id ASC';
    }

    return 'r.rating_avg DESC, r.rating_count DESC, r.restaurant_id ASC';
}

function restaurantNormalizeListRow(array $row): array
{
    return [
        'restaurant_id' => (int) $row['restaurant_id'],
        'restaurant_name' => $row['restaurant_name'],
        'description' => $row['description'],
        'address' => $row['address'],
        'zipcode' => $row['zipcode'],
        'district_name' => $row['district_name'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'rating_avg' => (float) $row['rating_avg'],
        'rating_count' => (int) $row['rating_count'],
        'price_level' => $row['price_level'] === null ? null : (int) $row['price_level'],
        'main_photo_url' => $row['main_photo_url'],
        'distance_m' => $row['distance_m'] === null ? null : (int) round((float) $row['distance_m']),
        'is_open_now' => (bool) $row['is_open_now'],
        'is_favorited' => (bool) $row['is_favorited'],
        'tags' => [],
    ];
}

function restaurantAttachTags(array &$restaurants): void
{
    if (!$restaurants) {
        return;
    }

    $ids = array_map(static fn(array $row): int => (int) $row['restaurant_id'], $restaurants);
    $holders = [];
    $params = [];
    foreach ($ids as $idx => $id) {
        $name = "id_{$idx}";
        restaurantAddParam($params, $name, $id, PDO::PARAM_INT);
        $holders[] = ':' . $name;
    }

    $stmt = db()->prepare(
        'SELECT rtm.restaurant_id, t.tag_id, t.tag_name
         FROM restaurant_tags_mapping rtm
         JOIN tags t ON t.tag_id = rtm.tag_id
         WHERE rtm.restaurant_id IN (' . implode(',', $holders) . ')
         ORDER BY t.tag_id ASC'
    );
    restaurantBindParams($stmt, $params);
    $stmt->execute();

    $tagsByRestaurant = [];
    foreach ($stmt->fetchAll() as $row) {
        $tagsByRestaurant[(int) $row['restaurant_id']][] = [
            'tag_id' => (int) $row['tag_id'],
            'tag_name' => $row['tag_name'],
        ];
    }

    foreach ($restaurants as &$restaurant) {
        $restaurant['tags'] = $tagsByRestaurant[(int) $restaurant['restaurant_id']] ?? [];
    }
}

function restaurantFetchList(array $filters): array
{
    $params = [];
    $where = restaurantBuildWhere($filters, $params, 'w');
    $userId = restaurantCurrentUserId();
    $distanceSql = 'NULL';

    if ($filters['user_lat'] !== null) {
        $distanceSql = restaurantDistanceSql('select_lat1', 'select_lat2', 'select_lng1');
        restaurantAddParam($params, 'select_lat1', $filters['user_lat']);
        restaurantAddParam($params, 'select_lat2', $filters['user_lat']);
        restaurantAddParam($params, 'select_lng1', $filters['user_lng']);
    }

    $favoriteJoin = '';
    $favoriteSelect = '0 AS is_favorited';
    if ($userId !== null) {
        $favoriteJoin = ' LEFT JOIN favorites f ON f.restaurant_id = r.restaurant_id AND f.user_id = :current_user_id';
        $favoriteSelect = 'CASE WHEN f.user_id IS NULL THEN 0 ELSE 1 END AS is_favorited';
        restaurantAddParam($params, 'current_user_id', $userId, PDO::PARAM_INT);
    }

    restaurantAddParam($params, 'limit', (int) $filters['limit'], PDO::PARAM_INT);
    restaurantAddParam($params, 'offset', (int) $filters['offset'], PDO::PARAM_INT);

    $sql = 'SELECT
            r.restaurant_id,
            r.restaurant_name,
            r.description,
            r.address,
            r.zipcode,
            d.district_name,
            r.latitude,
            r.longitude,
            r.rating_avg,
            r.rating_count,
            r.price_level,
            p.url AS main_photo_url,
            ' . $distanceSql . ' AS distance_m,
            CASE WHEN ' . restaurantOpenNowSql() . ' THEN 1 ELSE 0 END AS is_open_now,
            ' . $favoriteSelect . '
        FROM restaurants r
        LEFT JOIN districts d ON d.zipcode = r.zipcode
        LEFT JOIN restaurant_photos p ON p.restaurant_id = r.restaurant_id AND p.is_main = 1'
        . $favoriteJoin
        . $where
        . ' ORDER BY ' . restaurantOrderBy($filters)
        . ' LIMIT :limit OFFSET :offset';

    $stmt = db()->prepare($sql);
    restaurantBindParams($stmt, $params);
    $stmt->execute();

    $restaurants = array_map('restaurantNormalizeListRow', $stmt->fetchAll());
    restaurantAttachTags($restaurants);

    return $restaurants;
}

function restaurantCount(array $filters): int
{
    $params = [];
    $where = restaurantBuildWhere($filters, $params, 'c');
    $stmt = db()->prepare('SELECT COUNT(DISTINCT r.restaurant_id) FROM restaurants r' . $where);
    restaurantBindParams($stmt, $params);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function restaurantFetchIds(array $filters): array
{
    $params = [];
    $where = restaurantBuildWhere($filters, $params, 'ids');
    $stmt = db()->prepare('SELECT DISTINCT r.restaurant_id FROM restaurants r' . $where . ' ORDER BY r.restaurant_id ASC');
    restaurantBindParams($stmt, $params);
    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function restaurantFetchDetail(int $restaurantId): ?array
{
    $params = [];
    $userId = restaurantCurrentUserId();
    $favoriteJoin = '';
    $favoriteSelect = '0 AS is_favorited';

    if ($userId !== null) {
        $favoriteJoin = ' LEFT JOIN favorites f ON f.restaurant_id = r.restaurant_id AND f.user_id = :current_user_id';
        $favoriteSelect = 'CASE WHEN f.user_id IS NULL THEN 0 ELSE 1 END AS is_favorited';
        restaurantAddParam($params, 'current_user_id', $userId, PDO::PARAM_INT);
    }
    restaurantAddParam($params, 'restaurant_id', $restaurantId, PDO::PARAM_INT);

    $stmt = db()->prepare(
        'SELECT
            r.restaurant_id,
            r.restaurant_name,
            r.description,
            r.address,
            r.zipcode,
            d.district_name,
            r.latitude,
            r.longitude,
            r.rating_avg,
            r.rating_count,
            r.price_level,
            r.google_place_id,
            CASE WHEN ' . restaurantOpenNowSql() . ' THEN 1 ELSE 0 END AS is_open_now,
            ' . $favoriteSelect . '
         FROM restaurants r
         JOIN districts d ON d.zipcode = r.zipcode'
         . $favoriteJoin .
        ' WHERE r.restaurant_id = :restaurant_id'
    );
    restaurantBindParams($stmt, $params);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $restaurant = [
        'restaurant_id' => (int) $row['restaurant_id'],
        'restaurant_name' => $row['restaurant_name'],
        'description' => $row['description'],
        'address' => $row['address'],
        'zipcode' => $row['zipcode'],
        'district_name' => $row['district_name'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'rating_avg' => (float) $row['rating_avg'],
        'rating_count' => (int) $row['rating_count'],
        'price_level' => $row['price_level'] === null ? null : (int) $row['price_level'],
        'google_place_id' => $row['google_place_id'],
        'is_open_now' => (bool) $row['is_open_now'],
        'is_favorited' => (bool) $row['is_favorited'],
        'user_review' => null,
        'photos' => [],
        'phones' => [],
        'opentime_regular' => [],
        'opentime_special' => [],
        'tags' => [],
    ];

    $photos = db()->prepare(
        'SELECT photo_id, url, is_main, sort_order
         FROM restaurant_photos
         WHERE restaurant_id = ?
         ORDER BY is_main DESC, sort_order ASC, photo_id ASC'
    );
    $photos->execute([$restaurantId]);
    $restaurant['photos'] = array_map(static function (array $photo): array {
        return [
            'photo_id' => (int) $photo['photo_id'],
            'url' => $photo['url'],
            'is_main' => (int) $photo['is_main'],
            'sort_order' => (int) $photo['sort_order'],
        ];
    }, $photos->fetchAll());

    $phones = db()->prepare(
        'SELECT phone_number
         FROM restaurant_phones
         WHERE restaurant_id = ?
         ORDER BY phone_id ASC'
    );
    $phones->execute([$restaurantId]);
    $restaurant['phones'] = $phones->fetchAll(PDO::FETCH_COLUMN);

    $hours = db()->prepare(
        'SELECT ot.day, ot.start_time, ot.end_time, ot.spec_rec,
                d.day_name_zh, d.day_name_en
         FROM opentime ot
         LEFT JOIN days_of_week d ON d.day_id = ot.day
         WHERE ot.restaurant_id = ?
         ORDER BY ot.day ASC, ot.start_time ASC, ot.end_time ASC, ot.opentime_id ASC'
    );
    $hours->execute([$restaurantId]);
    $special = [];
    foreach ($hours->fetchAll() as $hour) {
        $restaurant['opentime_regular'][] = [
            'day' => (int) $hour['day'],
            'day_name_zh' => $hour['day_name_zh'],
            'day_name_en' => $hour['day_name_en'],
            'start_time' => $hour['start_time'],
            'end_time' => $hour['end_time'],
        ];
        if ($hour['spec_rec'] !== null && trim((string) $hour['spec_rec']) !== '') {
            $special[(string) $hour['spec_rec']] = true;
        }
    }
    $restaurant['opentime_special'] = array_keys($special);

    $tagWrapper = [[
        'restaurant_id' => $restaurantId,
        'tags' => [],
    ]];
    restaurantAttachTags($tagWrapper);
    $restaurant['tags'] = $tagWrapper[0]['tags'];

    if ($userId !== null) {
        $review = db()->prepare(
            'SELECT user_id, restaurant_id, rating, comment, created_at, updated_at
             FROM reviews
             WHERE user_id = ? AND restaurant_id = ?'
        );
        $review->execute([$userId, $restaurantId]);
        $reviewRow = $review->fetch();
        if ($reviewRow) {
            $reviewRow['user_id'] = (int) $reviewRow['user_id'];
            $reviewRow['restaurant_id'] = (int) $reviewRow['restaurant_id'];
            $reviewRow['rating'] = (int) $reviewRow['rating'];
            $restaurant['user_review'] = $reviewRow;
        }
    }

    return $restaurant;
}

function restaurantWheelSessionKey(array $filters): string
{
    $keyData = [
        'districts' => $filters['districts'],
        'tags' => $filters['tags'],
        'min_rating' => $filters['min_rating'],
        'max_distance_m' => $filters['max_distance_m'],
        'user_lat' => $filters['user_lat'],
        'user_lng' => $filters['user_lng'],
        'bbox' => $filters['bbox'],
        'keyword' => $filters['keyword'],
    ];

    return 'wheel_drawn_' . md5(json_encode($keyData));
}
