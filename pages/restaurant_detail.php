<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/restaurants.php';

$restaurantId = isset($_GET['id']) ? max(1, (int) $_GET['id']) : 1;
$restaurant = restaurantFetchDetail($restaurantId);

if (!$restaurant) {
    http_response_code(404);
    echo '找不到餐廳';
    exit;
}

$currentUser = currentUser();
$userReview = $restaurant['user_review'] ?? null;

$reviewsStmt = db()->prepare(
    'SELECT rv.user_id, u.username, rv.rating, rv.comment, rv.created_at
     FROM reviews rv
     JOIN users u ON u.user_id = rv.user_id
     WHERE rv.restaurant_id = ?
     ORDER BY rv.updated_at DESC
     LIMIT 20'
);
$reviewsStmt->execute([$restaurantId]);
$reviews = $reviewsStmt->fetchAll();

$mainPhoto = $restaurant['photos'][0]['url'] ?? 'https://newtaipei.travel/content/images/shops/14159/480x360_default.jpg';
$primaryTag = $restaurant['tags'][0]['tag_name'] ?? '未分類';
$phone = $restaurant['phones'][0] ?? '';
$regularHours = array_slice($restaurant['opentime_regular'], 0, 3);
$mapsNavUrl = sprintf(
    'https://www.google.com/maps/dir/?api=1&destination=%s,%s',
    $restaurant['latitude'],
    $restaurant['longitude']
);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function renderStars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function formatHours(array $hours): string
{
    if (!$hours) {
        return '尚無營業時間資料';
    }

    $dayNames = ['日', '一', '二', '三', '四', '五', '六'];
    $parts = [];
    foreach ($hours as $hour) {
        $day = $dayNames[(int) $hour['day']] ?? (string) $hour['day'];
        $parts[] = sprintf('週%s %s-%s', $day, substr($hour['start_time'], 0, 5), substr($hour['end_time'], 0, 5));
    }

    return implode('、', $parts);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($restaurant['restaurant_name']); ?> | 新北美食地圖</title>
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

    <main class="detail-container">
        <div class="detail-header">
            <a href="index.php" class="detail-back-btn" aria-label="返回餐廳列表">← 返回列表</a>
        </div>

        <div class="detail-content">
            <section class="detail-main" aria-label="餐廳資料">
                <div class="detail-image-wrapper">
                    <img src="<?php echo h($mainPhoto); ?>" alt="<?php echo h($restaurant['restaurant_name']); ?>" class="detail-image">
                </div>

                <div class="detail-info">
                    <div class="detail-header-info">
                        <div>
                            <h1 class="detail-name"><?php echo h($restaurant['restaurant_name']); ?></h1>
                            <p class="detail-category"><?php echo h($primaryTag); ?></p>
                        </div>
                        <button
                            class="detail-favorite-btn<?php echo $restaurant['is_favorited'] ? ' is-favorited' : ''; ?>"
                            type="button"
                            aria-label="加入收藏"
                            title="加入收藏"
                        >♡</button>
                    </div>

                    <div class="detail-rating">
                        <div class="detail-rating-main">
                            <span class="detail-rating-score"><?php echo h(number_format((float) $restaurant['rating_avg'], 1)); ?></span>
                            <span class="detail-rating-count">(<?php echo h($restaurant['rating_count']); ?> 則評論)</span>
                        </div>
                    </div>

                    <div class="detail-meta">
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">行政區</span>
                            <span class="detail-meta-value"><?php echo h($restaurant['district_name']); ?> <?php echo h($restaurant['zipcode']); ?></span>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">地址</span>
                            <span class="detail-meta-value"><?php echo h($restaurant['address']); ?></span>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">電話</span>
                            <?php if ($phone !== ''): ?>
                                <a href="tel:<?php echo h($phone); ?>" class="detail-meta-value detail-link"><?php echo h($phone); ?></a>
                            <?php else: ?>
                                <span class="detail-meta-value">尚無電話資料</span>
                            <?php endif; ?>
                        </div>
                        <div class="detail-meta-item">
                            <span class="detail-meta-label">營業時間</span>
                            <span class="detail-meta-value"><?php echo h(formatHours($regularHours)); ?></span>
                        </div>
                    </div>

                    <div class="detail-description">
                        <h2 class="detail-section-title">餐廳介紹</h2>
                        <p class="detail-description-text"><?php echo h($restaurant['description'] ?: '尚無介紹'); ?></p>
                    </div>

                    <?php if (!empty($restaurant['tags'])): ?>
                        <div class="detail-description">
                            <h2 class="detail-section-title">分類標籤</h2>
                            <p class="detail-description-text">
                                <?php echo h(implode('、', array_column($restaurant['tags'], 'tag_name'))); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="detail-description">
                        <h2 class="detail-section-title">撰寫評論</h2>
                        <?php if ($currentUser): ?>
                            <form class="detail-review-form" id="reviewForm">
                                <label class="field" for="reviewRating">
                                    <span class="label">評分</span>
                                    <select class="input" id="reviewRating" name="rating" required>
                                        <?php for ($score = 5; $score >= 1; $score--): ?>
                                            <option value="<?php echo $score; ?>" <?php echo (int) ($userReview['rating'] ?? 5) === $score ? 'selected' : ''; ?>>
                                                <?php echo $score; ?> 星
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label class="field" for="reviewComment">
                                    <span class="label">評論內容</span>
                                    <textarea
                                        class="input detail-review-input"
                                        id="reviewComment"
                                        name="comment"
                                        maxlength="1000"
                                        rows="4"
                                        placeholder="分享你的用餐心得"
                                    ><?php echo h($userReview['comment'] ?? ''); ?></textarea>
                                </label>
                                <button type="submit" class="btn btn-secondary btn-block">
                                    <?php echo $userReview ? '更新評論' : '送出評論'; ?>
                                </button>
                                <p class="detail-review-message" id="reviewMessage" aria-live="polite"></p>
                            </form>
                        <?php else: ?>
                            <p class="detail-description-text">
                                <a class="link-primary" href="login.php">登入後</a> 可以收藏餐廳並撰寫評論。
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="detail-actions">
                        <a href="<?php echo h($mapsNavUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg btn-block">
                            用 Google Maps 導航
                        </a>
                    </div>
                </div>
            </section>

            <aside class="detail-sidebar" aria-label="餐廳評論">
                <div class="detail-reviews-header">
                    <div>
                        <h2 class="detail-reviews-title">評論</h2>
                        <p class="detail-reviews-subtitle">來自資料庫的使用者評論</p>
                    </div>
                    <span class="detail-reviews-count"><?php echo count($reviews); ?> 則</span>
                </div>

                <div class="detail-reviews-container">
                    <?php if (!$reviews): ?>
                        <article class="detail-review-card">
                            <p class="detail-review-text">目前還沒有評論。</p>
                        </article>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <article class="detail-review-card">
                                <div class="detail-review-header">
                                    <div class="detail-review-user">
                                        <img
                                            src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo h($review['user_id']); ?>"
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
                                    <div class="detail-review-rating" aria-label="<?php echo h($review['rating']); ?> 星">
                                        <?php echo h(renderStars((int) $review['rating'])); ?>
                                    </div>
                                </div>
                                <p class="detail-review-text"><?php echo h($review['comment'] ?: ''); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>

    <script>
        window.restaurantData = <?php echo json_encode([
            'id' => $restaurant['restaurant_id'],
            'name' => $restaurant['restaurant_name'],
            'is_favorited' => $restaurant['is_favorited'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="../assets/js/auth-nav.js?v=1"></script>
    <script src="../assets/js/detail.js?v=5"></script>
</body>
</html>
