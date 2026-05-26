<?php
/**
 * 餐廳詳細頁 (restaurant_detail.php)
 *
 * 目前使用 mock data 呈現餐廳資訊與評論，未串接後端 API。
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$restaurantId = isset($_GET['id']) ? (int) $_GET['id'] : 1;

$mockRestaurantsDetail = [
    1 => [
        'id' => 1,
        'name' => '暖心牛肉麵',
        'category' => '台式料理',
        'rating' => 4.5,
        'reviewCount' => 186,
        'latitude' => 25.0330,
        'longitude' => 121.5654,
        'address' => '新北市板橋區文化路一段 9 號',
        'phone' => '(02) 8101-8656',
        'hours' => '11:00 - 21:00',
        'description' => '以清燉湯頭與手工麵條聞名，午餐時段人潮較多。店內座位簡單明亮，適合想快速吃一碗熱湯麵的上班族與附近居民。',
        'image' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=1200&q=80',
    ],
    2 => [
        'id' => 2,
        'name' => '巷口咖哩飯',
        'category' => '日式料理',
        'rating' => 4.3,
        'reviewCount' => 142,
        'latitude' => 25.0340,
        'longitude' => 121.5664,
        'address' => '新北市板橋區民生路二段 213 號',
        'phone' => '(02) 2771-9912',
        'hours' => '11:00 - 22:00',
        'description' => '濃郁咖哩醬搭配酥脆炸豬排是招牌組合，也提供蔬食咖哩與加飯選項。用餐尖峰建議先訂位。',
        'image' => 'https://images.unsplash.com/photo-1604909052743-94e838986d24?auto=format&fit=crop&w=1200&q=80',
    ],
    3 => [
        'id' => 3,
        'name' => '海港小館',
        'category' => '海鮮料理',
        'rating' => 4.6,
        'reviewCount' => 218,
        'latitude' => 25.0320,
        'longitude' => 121.5644,
        'address' => '新北市板橋區文化路二段 100 號',
        'phone' => '(02) 8789-1234',
        'hours' => '11:30 - 22:30',
        'description' => '主打每日鮮魚、熱炒與多人合菜，份量充足。適合家庭聚餐，也有兩人份套餐可選。',
        'image' => 'https://images.unsplash.com/photo-1559847844-5315695dadae?auto=format&fit=crop&w=1200&q=80',
    ],
    4 => [
        'id' => 4,
        'name' => '綠野早午餐',
        'category' => '早午餐',
        'rating' => 4.2,
        'reviewCount' => 165,
        'latitude' => 25.0350,
        'longitude' => 121.5674,
        'address' => '新北市板橋區中山路一段 101 號',
        'phone' => '(02) 2776-5566',
        'hours' => '08:00 - 16:00',
        'description' => '提供手作三明治、沙拉碗與咖啡。空間明亮，假日常有候位，平日上午最舒適。',
        'image' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=1200&q=80',
    ],
    5 => [
        'id' => 5,
        'name' => '甜巷手作',
        'category' => '甜點飲品',
        'rating' => 4.4,
        'reviewCount' => 134,
        'latitude' => 25.0325,
        'longitude' => 121.5635,
        'address' => '新北市板橋區莒光路 120 號',
        'phone' => '(02) 2357-8899',
        'hours' => '12:00 - 22:00',
        'description' => '每日限量塔類甜點與季節水果茶是人氣品項。店內座位不多，適合下午短暫放鬆。',
        'image' => 'https://images.unsplash.com/photo-1551024506-0bccd828d307?auto=format&fit=crop&w=1200&q=80',
    ],
];

$mockReviews = [
    [
        'id' => 1,
        'username' => '陳小安',
        'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=an',
        'rating' => 5,
        'comment' => '湯頭很清爽，牛肉軟嫩不乾柴。晚餐時間人比較多，但翻桌速度還算快。',
        'date' => '2026-05-20',
    ],
    [
        'id' => 2,
        'username' => '林品妤',
        'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=pin',
        'rating' => 4,
        'comment' => '服務親切，餐點穩定。座位間距稍微近一點，聊天音量會互相影響。',
        'date' => '2026-05-18',
    ],
    [
        'id' => 3,
        'username' => '王阿哲',
        'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=zhe',
        'rating' => 5,
        'comment' => '用料新鮮，價格合理。推薦第一次來的人點招牌套餐，份量剛好。',
        'date' => '2026-05-16',
    ],
    [
        'id' => 4,
        'username' => 'Mina',
        'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=mina',
        'rating' => 4,
        'comment' => '環境乾淨，出餐速度普通。整體是會想再回訪的店。',
        'date' => '2026-05-15',
    ],
    [
        'id' => 5,
        'username' => '周建宏',
        'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=hong',
        'rating' => 5,
        'comment' => '店員會主動介紹餐點，對選擇困難的人很友善。Google 導航位置也準。',
        'date' => '2026-05-14',
    ],
    [
        'id' => 6,
        'username' => 'Ivy',
        'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=ivy',
        'rating' => 4,
        'comment' => '口味偏清淡，可以另外加辣。附近停車比較不方便，建議搭捷運或騎車。',
        'date' => '2026-05-13',
    ],
];

$restaurant = $mockRestaurantsDetail[$restaurantId] ?? $mockRestaurantsDetail[1];
$categoryColor = in_array($restaurant['category'], ['台式料理', '早午餐', '甜點飲品'], true) ? 'green' : '';
$mapsNavUrl = sprintf(
    'https://www.google.com/maps/dir/?api=1&destination=%s,%s',
    $restaurant['latitude'],
    $restaurant['longitude']
);

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function renderStars($rating)
{
    $rating = max(0, min(5, (int) $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($restaurant['name']); ?> | 新北美食地圖</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/detail.css">
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="index.php" class="nav-logo" aria-label="回到新北美食地圖首頁">
                <div class="nav-logo-mark">食</div>
                <span>新北美食地圖</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">首頁</a>
                <a href="wheel.php" class="nav-link">轉盤</a>
                <a href="favorites.php" class="nav-link">我的收藏</a>
                <a href="history.php" class="nav-link">瀏覽紀錄</a>
            </div>
            <div class="nav-auth">
                <a href="login.php" class="nav-auth-link">登入</a>
                <div class="nav-auth-divider"></div>
                <a href="register.php" class="nav-auth-primary">註冊</a>
            </div>
        </div>
    </nav>

    <main class="detail-container">
        <div class="detail-header">
            <a href="index.php" class="detail-back-btn" aria-label="返回餐廳列表">← 返回列表</a>
        </div>

        <div class="detail-content">
            <section class="detail-main" aria-label="餐廳資訊">
                <div class="detail-image-wrapper">
                    <img
                        src="<?php echo h($restaurant['image']); ?>"
                        alt="<?php echo h($restaurant['name']); ?>"
                        class="detail-image"
                    >
                </div>

                <div class="detail-info">
                    <div class="detail-header-info">
                        <div>
                            <h1 class="detail-name"><?php echo h($restaurant['name']); ?></h1>
                            <p class="detail-category <?php echo $categoryColor ? 'category-green' : ''; ?>">
                                <?php echo h($restaurant['category']); ?>
                            </p>
                        </div>
                        <button class="detail-favorite-btn" type="button" aria-label="加入收藏" title="加入收藏">♡</button>
                    </div>

                    <div class="detail-rating">
                        <div class="detail-rating-main">
                            <span class="detail-rating-score">★ <?php echo h(number_format($restaurant['rating'], 1)); ?></span>
                            <span class="detail-rating-count">(<?php echo h($restaurant['reviewCount']); ?> 則評論)</span>
                        </div>
                    </div>

                    <div class="detail-meta">
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">地址</span>
                            <span class="detail-meta-value"><?php echo h($restaurant['address']); ?></span>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">電話</span>
                            <a href="tel:<?php echo h($restaurant['phone']); ?>" class="detail-meta-value detail-link">
                                <?php echo h($restaurant['phone']); ?>
                            </a>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">營業時間</span>
                            <span class="detail-meta-value"><?php echo h($restaurant['hours']); ?></span>
                        </div>
                    </div>

                    <div class="detail-description">
                        <h2 class="detail-section-title">餐廳介紹</h2>
                        <p class="detail-description-text"><?php echo h($restaurant['description']); ?></p>
                    </div>

                    <div class="detail-actions">
                        <a
                            href="<?php echo h($mapsNavUrl); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-primary btn-lg btn-block"
                            aria-label="使用 Google Maps 導航至<?php echo h($restaurant['name']); ?>"
                        >
                            開啟 Google Maps 導航
                        </a>
                    </div>
                </div>
            </section>

            <aside class="detail-sidebar" aria-label="餐廳評論">
                <div class="detail-reviews-header">
                    <div>
                        <h2 class="detail-reviews-title">評論</h2>
                        <p class="detail-reviews-subtitle">來自近期訪客的用餐心得</p>
                    </div>
                    <span class="detail-reviews-count"><?php echo count($mockReviews); ?> 則</span>
                </div>

                <div class="detail-reviews-container">
                    <?php foreach ($mockReviews as $review): ?>
                        <article class="detail-review-card">
                            <div class="detail-review-header">
                                <div class="detail-review-user">
                                    <img
                                        src="<?php echo h($review['avatar']); ?>"
                                        alt="<?php echo h($review['username']); ?> 的頭像"
                                        class="detail-review-avatar"
                                    >
                                    <div class="detail-review-user-info">
                                        <h3 class="detail-review-username"><?php echo h($review['username']); ?></h3>
                                        <time class="detail-review-date" datetime="<?php echo h($review['date']); ?>">
                                            <?php echo h($review['date']); ?>
                                        </time>
                                    </div>
                                </div>
                                <div class="detail-review-rating" aria-label="<?php echo h($review['rating']); ?> 顆星">
                                    <?php echo h(renderStars($review['rating'])); ?>
                                </div>
                            </div>
                            <p class="detail-review-text"><?php echo h($review['comment']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </main>

    <script>
        window.restaurantData = <?php echo json_encode([
            'id' => $restaurant['id'],
            'name' => $restaurant['name'],
            'latitude' => $restaurant['latitude'],
            'longitude' => $restaurant['longitude'],
            'category' => $restaurant['category'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="../assets/js/detail.js"></script>
</body>
</html>
