<?php
/**
 * 首頁 (index.php)
 *
 * 使用 mock data 呈現餐廳列表、關鍵字搜尋、分類與距離篩選。
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$searchKeyword = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedCuisine = isset($_GET['cuisine']) ? trim((string) $_GET['cuisine']) : '';
$selectedDistance = isset($_GET['distance']) ? trim((string) $_GET['distance']) : '';

$defaultLat = 25.033964;
$defaultLng = 121.564468;

$cuisineOptions = [
    '' => '不限',
    '台式料理' => '台式料理',
    '日式料理' => '日式料理',
    '韓式料理' => '韓式料理',
    '義式料理' => '義式料理',
    '甜點' => '甜點',
    '飲料' => '飲料',
    '新北小吃' => '新北小吃',
    '新北料理' => '新北料理',
];

$distanceOptions = [
    '' => '不限',
    '500' => '500m',
    '1000' => '1km',
    '3000' => '3km',
    '5000' => '5km',
];

$mockRestaurants = [
    [
        'id' => 1,
        'name' => '暖心牛肉麵',
        'category' => '台式料理',
        'cuisine' => '台式料理',
        'rating' => 4.5,
        'latitude' => 25.0330,
        'longitude' => 121.5654,
    ],
    [
        'id' => 2,
        'name' => '巷口咖哩飯',
        'category' => '日式料理',
        'cuisine' => '日式料理',
        'rating' => 4.3,
        'latitude' => 25.0340,
        'longitude' => 121.5664,
    ],
    [
        'id' => 3,
        'name' => '海港小館',
        'category' => '新北料理',
        'cuisine' => '新北料理',
        'rating' => 4.6,
        'latitude' => 25.0320,
        'longitude' => 121.5644,
    ],
    [
        'id' => 4,
        'name' => '韓味烤肉',
        'category' => '韓式料理',
        'cuisine' => '韓式料理',
        'rating' => 4.2,
        'latitude' => 25.0350,
        'longitude' => 121.5674,
    ],
    [
        'id' => 5,
        'name' => '甜巷手作',
        'category' => '甜點',
        'cuisine' => '甜點',
        'rating' => 4.4,
        'latitude' => 25.0325,
        'longitude' => 121.5635,
    ],
    [
        'id' => 6,
        'name' => '板橋鹹酥雞',
        'category' => '新北小吃',
        'cuisine' => '新北小吃',
        'rating' => 4.1,
        'latitude' => 25.0368,
        'longitude' => 121.5623,
    ],
    [
        'id' => 7,
        'name' => '巷弄義麵坊',
        'category' => '義式料理',
        'cuisine' => '義式料理',
        'rating' => 4.0,
        'latitude' => 25.0290,
        'longitude' => 121.5700,
    ],
    [
        'id' => 8,
        'name' => '春光茶飲',
        'category' => '飲料',
        'cuisine' => '飲料',
        'rating' => 4.3,
        'latitude' => 25.0315,
        'longitude' => 121.5585,
    ],
];

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function distanceMeters($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

foreach ($mockRestaurants as &$restaurant) {
    $restaurant['distanceMeters'] = distanceMeters(
        $defaultLat,
        $defaultLng,
        $restaurant['latitude'],
        $restaurant['longitude']
    );
    $restaurant['lat'] = $restaurant['latitude'];
    $restaurant['lng'] = $restaurant['longitude'];
}
unset($restaurant);

$restaurants = array_values(array_filter($mockRestaurants, function ($restaurant) use ($searchKeyword, $selectedCuisine, $selectedDistance) {
    if ($searchKeyword !== '') {
        $keyword = mb_strtolower($searchKeyword, 'UTF-8');
        $name = mb_strtolower($restaurant['name'], 'UTF-8');
        $category = mb_strtolower($restaurant['category'], 'UTF-8');
        if (mb_strpos($name, $keyword, 0, 'UTF-8') === false
            && mb_strpos($category, $keyword, 0, 'UTF-8') === false) {
            return false;
        }
    }

    if ($selectedCuisine !== '' && $restaurant['category'] !== $selectedCuisine) {
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
        'success' => true,
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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新北美食地圖</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/home.css">
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
                                <span>★ <?php echo h(number_format((float) $restaurant['rating'], 1)); ?></span>
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
                    <div class="home-map-placeholder-text">Google Maps Loading</div>
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
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA-KRuntm9sB0oU30UkFkHnWiKQahpssNE"></script>
    <script src="../assets/js/map.js"></script>
    <script src="../assets/js/home-php.js"></script>
</body>
</html>
