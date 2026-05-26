<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$restaurantId = (int) ($_GET['id'] ?? 0);
if ($restaurantId <= 0) {
    http_response_code(404);
    exit('Restaurant not found.');
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function renderStars($rating): string
{
    $rating = max(0, min(5, (int) round((float) $rating)));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

$pdo = db();

$stmt = $pdo->prepare(
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
      WHERE r.restaurant_id = :restaurant_id
      GROUP BY r.restaurant_id, r.name, r.description, r.tel, r.address, r.opentime,
               r.latitude, r.longitude, d.district_name
      LIMIT 1'
);
$stmt->execute(['restaurant_id' => $restaurantId]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    http_response_code(404);
    exit('Restaurant not found.');
}

$reviewStmt = $pdo->prepare(
    'SELECT rv.review_id, rv.rating, rv.comment, rv.created_at, u.username
       FROM reviews rv
       JOIN users u ON u.user_id = rv.user_id
      WHERE rv.restaurant_id = :restaurant_id
      ORDER BY rv.created_at DESC'
);
$reviewStmt->execute(['restaurant_id' => $restaurantId]);
$reviews = $reviewStmt->fetchAll();

$fallbackImages = [
    'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=1200&q=80',
    'https://images.unsplash.com/photo-1604909052743-94e838986d24?auto=format&fit=crop&w=1200&q=80',
    'https://images.unsplash.com/photo-1559847844-5315695dadae?auto=format&fit=crop&w=1200&q=80',
    'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=1200&q=80',
];
$image = $fallbackImages[((int) $restaurant['id'] - 1) % count($fallbackImages)];
$categoryColor = preg_match('/咖啡|甜點|早午餐/u', (string) $restaurant['category']) ? 'green' : '';
$mapsNavUrl = sprintf(
    'https://www.google.com/maps/dir/?api=1&destination=%s,%s',
    $restaurant['latitude'],
    $restaurant['longitude']
);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($restaurant['name']); ?> | 新北美食地圖</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css?v=2">
    <link rel="stylesheet" href="../assets/css/detail.css?v=2">
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
                        src="<?php echo h($image); ?>"
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
                            <span class="detail-rating-score">
                                <?php echo (float) $restaurant['rating'] > 0 ? '★ ' . h(number_format((float) $restaurant['rating'], 1)) : '暫無評分'; ?>
                            </span>
                            <span class="detail-rating-count">(<?php echo h($restaurant['review_count']); ?> 則評論)</span>
                        </div>
                    </div>

                    <div class="detail-meta">
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">地址</span>
                            <span class="detail-meta-value"><?php echo h($restaurant['address']); ?></span>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">電話</span>
                            <?php if (!empty($restaurant['tel'])): ?>
                                <a href="tel:<?php echo h($restaurant['tel']); ?>" class="detail-meta-value detail-link">
                                    <?php echo h($restaurant['tel']); ?>
                                </a>
                            <?php else: ?>
                                <span class="detail-meta-value">尚無電話</span>
                            <?php endif; ?>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">營業時間</span>
                            <span class="detail-meta-value"><?php echo h($restaurant['opentime'] ?: '尚無營業時間'); ?></span>
                        </div>
                    </div>

                    <div class="detail-description">
                        <h2 class="detail-section-title">餐廳介紹</h2>
                        <p class="detail-description-text"><?php echo h($restaurant['description'] ?: '目前尚無餐廳介紹。'); ?></p>
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
                    <span class="detail-reviews-count"><?php echo count($reviews); ?> 則</span>
                </div>

                <div class="detail-reviews-container">
                    <?php if (empty($reviews)): ?>
                        <article class="detail-review-card">
                            <p class="detail-review-text">目前尚無評論。</p>
                        </article>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <article class="detail-review-card">
                                <div class="detail-review-header">
                                    <div class="detail-review-user">
                                        <img
                                            src="https://api.dicebear.com/7.x/initials/svg?seed=<?php echo rawurlencode((string) $review['username']); ?>"
                                            alt="<?php echo h($review['username']); ?> 的頭像"
                                            class="detail-review-avatar"
                                        >
                                        <div class="detail-review-user-info">
                                            <h3 class="detail-review-username"><?php echo h($review['username']); ?></h3>
                                            <time class="detail-review-date" datetime="<?php echo h($review['created_at']); ?>">
                                                <?php echo h(substr((string) $review['created_at'], 0, 10)); ?>
                                            </time>
                                        </div>
                                    </div>
                                    <div class="detail-review-rating" aria-label="<?php echo h($review['rating']); ?> 顆星">
                                        <?php echo h(renderStars($review['rating'])); ?>
                                    </div>
                                </div>
                                <p class="detail-review-text"><?php echo h($review['comment'] ?: '未留下文字評論。'); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <script>
        window.restaurantData = <?php echo json_encode([
            'id' => (int) $restaurant['id'],
            'name' => (string) $restaurant['name'],
            'latitude' => (float) $restaurant['latitude'],
            'longitude' => (float) $restaurant['longitude'],
            'category' => (string) $restaurant['category'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="../assets/js/api-client.js"></script>
    <script src="../assets/js/detail.js?v=2"></script>
</body>
</html>
