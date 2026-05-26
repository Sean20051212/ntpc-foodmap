<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$searchKeyword = trim((string) ($_GET['search'] ?? ''));
$selectedCuisine = trim((string) ($_GET['cuisine'] ?? ''));
$selectedDistance = trim((string) ($_GET['distance'] ?? ''));

$defaultLat = 25.033964;
$defaultLng = 121.564468;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function contains_keyword(string $value, string $keyword): bool
{
    if ($keyword === '') {
        return true;
    }

    if (function_exists('mb_stripos')) {
        return mb_stripos($value, $keyword, 0, 'UTF-8') !== false;
    }

    return stripos($value, $keyword) !== false;
}

$pdo = db();

$stmt = $pdo->query(
    'SELECT r.restaurant_id AS id, r.name, r.description, r.tel, r.address, r.opentime,
            r.latitude, r.longitude, d.district_name,
            COALESCE(GROUP_CONCAT(DISTINCT t.tag_name ORDER BY t.tag_name SEPARATOR "、"), "餐廳") AS category,
            COALESCE(AVG(rv.rating), 0) AS rating,
            COUNT(rv.review_id) AS review_count
       FROM restaurants r
       LEFT JOIN districts d ON d.zipcode = r.zipcode
       LEFT JOIN restaurant_tags_mapping rtm ON rtm.restaurant_id = r.restaurant_id
       LEFT JOIN tags t ON t.tag_id = rtm.tag_id
       LEFT JOIN reviews rv ON rv.restaurant_id = r.restaurant_id
      GROUP BY r.restaurant_id, r.name, r.description, r.tel, r.address, r.opentime,
               r.latitude, r.longitude, d.district_name
      ORDER BY r.restaurant_id'
);

$allRestaurants = array_map(static function (array $restaurant) use ($defaultLat, $defaultLng): array {
    $lat = (float) $restaurant['latitude'];
    $lng = (float) $restaurant['longitude'];
    $category = (string) ($restaurant['category'] ?: '餐廳');

    return [
        'id' => (int) $restaurant['id'],
        'name' => (string) $restaurant['name'],
        'description' => (string) ($restaurant['description'] ?? ''),
        'category' => $category,
        'cuisine' => $category,
        'district' => (string) ($restaurant['district_name'] ?? ''),
        'rating' => round((float) $restaurant['rating'], 1),
        'reviewCount' => (int) $restaurant['review_count'],
        'latitude' => $lat,
        'longitude' => $lng,
        'lat' => $lat,
        'lng' => $lng,
        'address' => (string) $restaurant['address'],
        'hours' => (string) ($restaurant['opentime'] ?? ''),
        'tel' => (string) ($restaurant['tel'] ?? ''),
        'distanceMeters' => distanceMeters($defaultLat, $defaultLng, $lat, $lng),
    ];
}, $stmt->fetchAll());

$cuisineValues = [];
foreach ($allRestaurants as $restaurant) {
    foreach (explode('、', $restaurant['category']) as $category) {
        $category = trim($category);
        if ($category !== '') {
            $cuisineValues[$category] = $category;
        }
    }
}
ksort($cuisineValues);

$cuisineOptions = ['' => '不限'] + $cuisineValues;
$distanceOptions = [
    '' => '不限',
    '500' => '500m',
    '1000' => '1km',
    '3000' => '3km',
    '5000' => '5km',
];

$restaurants = array_values(array_filter($allRestaurants, static function (array $restaurant) use ($searchKeyword, $selectedCuisine, $selectedDistance): bool {
    if ($searchKeyword !== '') {
        $haystack = implode(' ', [
            $restaurant['name'],
            $restaurant['category'],
            $restaurant['district'],
            $restaurant['address'],
        ]);

        if (!contains_keyword($haystack, $searchKeyword)) {
            return false;
        }
    }

    if ($selectedCuisine !== '' && !contains_keyword($restaurant['category'], $selectedCuisine)) {
        return false;
    }

    if ($selectedDistance !== '' && $restaurant['distanceMeters'] > (int) $selectedDistance) {
        return false;
    }

    return true;
}));

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'data' => $restaurants,
        'count' => count($restaurants),
        'filters' => [
            'search' => $searchKeyword,
            'cuisine' => $selectedCuisine,
            'distance' => $selectedDistance,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$mapsKey = defined('GOOGLE_MAPS_KEY_FRONTEND') ? (string) GOOGLE_MAPS_KEY_FRONTEND : '';
$hasMapsKey = $mapsKey !== '' && $mapsKey !== 'YOUR_FRONTEND_KEY_HERE';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新北美食地圖</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css?v=2">
    <link rel="stylesheet" href="../assets/css/home.css?v=2">
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="index.php" class="nav-logo" aria-label="回到新北美食地圖首頁">
                <div class="nav-logo-mark">食</div>
                <span>新北食指南</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link active">首頁</a>
                <a href="wheel.php" class="nav-link">輪盤</a>
                <a href="favorites.php" class="nav-link">我的收藏</a>
                <a href="history.php" class="nav-link">我的歷史</a>
            </div>
            <div class="nav-auth">
                <a href="login.php" class="nav-auth-link">登入</a>
                <div class="nav-auth-divider"></div>
                <a href="register.php" class="nav-auth-primary">註冊</a>
            </div>
        </div>
    </nav>

    <div class="home-container">
        <aside class="home-sidebar">
            <div class="home-search-wrapper">
                <form method="GET" action="index.php" id="searchForm" class="home-search-form">
                    <div class="home-keyword-row">
                        <input
                            type="text"
                            name="search"
                            id="searchInput"
                            class="home-search-bar"
                            placeholder="搜尋餐廳名稱、菜系..."
                            value="<?php echo h($searchKeyword); ?>"
                            aria-label="搜尋餐廳"
                        >
                        <button type="submit" id="searchBtn" class="home-search-btn" aria-label="搜尋餐廳">
                            搜尋
                        </button>
                    </div>

                    <div class="home-filter-grid" aria-label="搜尋篩選">
                        <label class="home-filter-field" for="cuisineFilter">
                            <span class="home-filter-label">餐點類型</span>
                            <select name="cuisine" id="cuisineFilter" class="home-filter-select">
                                <?php foreach ($cuisineOptions as $value => $label): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo $selectedCuisine === $value ? 'selected' : ''; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="home-filter-field" for="distanceFilter">
                            <span class="home-filter-label">距離範圍</span>
                            <select name="distance" id="distanceFilter" class="home-filter-select">
                                <?php foreach ($distanceOptions as $value => $label): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo $selectedDistance === $value ? 'selected' : ''; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </form>
            </div>

            <div class="home-results-summary" id="resultsSummary">
                找到 <?php echo count($restaurants); ?> 間餐廳
            </div>

            <div class="home-restaurant-list" id="restaurantList">
                <?php if (empty($restaurants)): ?>
                    <div class="home-loading">沒有找到相符的餐廳</div>
                <?php else: ?>
                    <?php foreach ($restaurants as $restaurant): ?>
                        <div
                            class="home-restaurant-item"
                            data-restaurant-id="<?php echo h($restaurant['id']); ?>"
                            data-latitude="<?php echo h($restaurant['latitude']); ?>"
                            data-longitude="<?php echo h($restaurant['longitude']); ?>"
                            data-lat="<?php echo h($restaurant['lat']); ?>"
                            data-lng="<?php echo h($restaurant['lng']); ?>"
                            data-category="<?php echo h($restaurant['category']); ?>"
                            data-cuisine="<?php echo h($restaurant['cuisine']); ?>"
                            data-distance="<?php echo h($restaurant['distanceMeters']); ?>"
                        >
                            <div class="home-restaurant-name"><?php echo h($restaurant['name']); ?></div>
                            <div class="home-restaurant-cuisine"><?php echo h($restaurant['category']); ?></div>
                            <div class="home-restaurant-meta">
                                <span><?php echo $restaurant['rating'] > 0 ? '★ ' . h(number_format((float) $restaurant['rating'], 1)) : '暫無評分'; ?></span>
                                <span><?php echo h($restaurant['distanceMeters']); ?>m</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <main class="home-map-wrapper">
            <div id="map" class="home-map">
                <div class="home-map-placeholder">
                    <div class="home-map-placeholder-text">
                        <?php echo $hasMapsKey ? 'Google Maps Loading' : '尚未設定 Google Maps API Key'; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.restaurantsData = <?php echo json_encode($restaurants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.searchFilters = <?php echo json_encode([
            'search' => $searchKeyword,
            'cuisine' => $selectedCuisine,
            'distance' => $selectedDistance,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <?php if ($hasMapsKey): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo h($mapsKey); ?>"></script>
    <script src="../assets/js/map.js?v=2"></script>
    <?php endif; ?>
    <script src="../assets/js/api-client.js"></script>
    <script src="../assets/js/home-php.js?v=2"></script>
</body>
</html>
