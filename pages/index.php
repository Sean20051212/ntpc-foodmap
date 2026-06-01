<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

$currentUser = currentUser();
$searchKeyword = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$selectedDistance = isset($_GET['distance']) ? trim((string) $_GET['distance']) : '';

$distanceOptions = [
    '' => '不限',
    '500' => '500m',
    '1000' => '1km',
    '3000' => '3km',
    '5000' => '5km',
];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
                <span>新北美食地圖</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link active">首頁</a>
                <a href="wheel.php" class="nav-link">輪盤</a>
                <a href="favorites.php" class="nav-link">我的收藏</a>
                <a href="history.php" class="nav-link">瀏覽紀錄</a>
            </div>
            <div class="nav-auth">
                <?php if ($currentUser): ?>
                    <a href="profile.php" class="nav-auth-link"><?php echo h($currentUser['username']); ?></a>
                    <div class="nav-auth-divider"></div>
                    <button type="button" class="nav-auth-primary" data-logout>登出</button>
                <?php else: ?>
                    <a href="login.php" class="nav-auth-link">登入</a>
                    <div class="nav-auth-divider"></div>
                    <a href="register.php" class="nav-auth-primary">註冊</a>
                <?php endif; ?>
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
                            placeholder="搜尋餐廳、地址或介紹..."
                            value="<?php echo h($searchKeyword); ?>"
                            aria-label="搜尋餐廳"
                        >
                        <button type="submit" id="searchBtn" class="home-search-btn" aria-label="搜尋餐廳">
                            搜尋
                        </button>
                    </div>

                    <div class="home-filter-grid" aria-label="篩選條件">
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

            <div class="home-results-summary" id="resultsSummary">正在載入真實餐廳資料...</div>
            <div class="home-restaurant-list" id="restaurantList">
                <div class="home-loading">正在從資料庫載入...</div>
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
        window.restaurantsData = [];
        window.searchFilters = <?php echo json_encode([
            'search' => $searchKeyword,
            'distance' => $selectedDistance,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_FRONTEND_KEY_HERE"></script>
    <script src="../assets/js/auth-nav.js?v=1"></script>
    <script src="../assets/js/map.js?v=4"></script>
    <script src="../assets/js/home-php.js?v=4"></script>
</body>
</html>
