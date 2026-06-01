# 後端實作計畫（給後端 agent）

> 動工前必讀：[design-audit.md](design-audit.md)（決策依據，本檔不重述）
> 與本檔配對的前端規格：[frontend-plan.md](frontend-plan.md)。兩邊 API 契約必須一致，欄位名 / 型別 / 端點路徑以**本檔**為準。
>
> 環境：PHP 8.x + MariaDB 10.4 + XAMPP。**禁止引入 Composer 套件或框架**，純 PHP 標準函式 + PDO + session。

---

## 0. 開工順序

1. 改 `sql/database.sql`（§1）
2. 寫 `config.php` 與 `lib/`（§2、§3）
3. 寫 `api/` 每支端點（§4）
4. 用 curl 驗收（§5）

---

## 1. SQL 前置變動

在 [../sql/database.sql](../sql/database.sql) **檔尾**追加：

```sql
-- ① 建立 lat/lng 索引（地圖視口查詢用）
ALTER TABLE `restaurants`
  ADD KEY `idx_restaurants_latlng` (`latitude`, `longitude`);

-- ② Seed 預設 admin（user_id=1 為「不可被改/刪」的 super admin）
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `is_admin`)
VALUES (1, 'admin', '__REPLACE_WITH_BCRYPT_HASH__', 1);
```

`__REPLACE_WITH_BCRYPT_HASH__` 由你執行 `php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"` 算出後貼回。**不可在 git 留明文密碼**；commit message 提醒使用者首次登入後立刻在前端改密碼。

---

## 2. `config.php` 補完

[../config.php.example](../config.php.example) 已有常數，但缺少：(a) 從 `.env` 讀環境變數的 helper、(b) 啟動 session、(c) 設定錯誤模式。

寫到 `config.php`（**不是 .example**，因為要包含真實 key）：

```php
<?php
// 從 .env 讀環境變數（如果存在），優先序：getenv > $_ENV > $_SERVER > .env 檔
function projectEnv($key, $default = null) {
    static $env = null;
    foreach ([getenv($key), $_ENV[$key] ?? false, $_SERVER[$key] ?? false] as $v) {
        if ($v !== false && $v !== null) return $v;
    }
    if ($env === null) {
        $env = is_file(__DIR__ . '/.env') ? parse_ini_file(__DIR__ . '/.env') : [];
    }
    return $env[$key] ?? $default;
}

define('DB_HOST',     projectEnv('DB_HOST', 'localhost'));
define('DB_NAME',     projectEnv('DB_NAME', 'ntpc_foodmap'));
define('DB_USER',     projectEnv('DB_USER', 'root'));
define('DB_PASS',     projectEnv('DB_PASS', ''));
define('DB_CHARSET',  projectEnv('DB_CHARSET', 'utf8mb4'));

define('GOOGLE_MAPS_KEY_BACKEND',  projectEnv('GOOGLE_MAPS_KEY_BACKEND', ''));
define('GOOGLE_MAPS_KEY_FRONTEND', projectEnv('GOOGLE_MAPS_KEY_FRONTEND', ''));

define('SESSION_LIFETIME',      (int) projectEnv('SESSION_LIFETIME', 86400));
define('RATE_LIMIT_PER_MINUTE', (int) projectEnv('RATE_LIMIT_PER_MINUTE', 10));
define('RATE_LIMIT_PER_DAY',    (int) projectEnv('RATE_LIMIT_PER_DAY', 50));
define('DEBUG_MODE',            projectEnv('DEBUG_MODE', 'false') === 'true');

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'httponly' => true, 'samesite' => 'Lax']);
```

---

## 3. `lib/` 目錄共用模組（6 支）

每支都以 `require_once __DIR__ . '/../config.php';` 開頭。

### 3.1 `lib/db.php`

```php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
```

### 3.2 `lib/response.php`

```php
function jsonOk($data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function jsonErr(string $code, string $message, int $http = 400): void {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    exit;
}
```

錯誤碼一覽見 [design-audit.md §8](design-audit.md#8-共用錯誤碼)。

### 3.3 `lib/auth.php`

```php
function currentUser(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT user_id, username, is_admin FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
function requireLogin(): array {
    $u = currentUser();
    if (!$u) jsonErr('unauthenticated', '請先登入', 401);
    return $u;
}
function requireAdmin(): array {
    $u = requireLogin();
    if (!$u['is_admin']) jsonErr('forbidden', '需要管理員權限', 403);
    return $u;
}
function isSuperAdmin(int $userId): bool { return $userId === 1; }
```

### 3.4 `lib/input.php`

```php
function getInput(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return $_REQUEST;
    $j = json_decode($raw, true);
    return is_array($j) ? array_merge($_REQUEST, $j) : $_REQUEST;
}
function requireInt($input, string $key, ?int $min = null, ?int $max = null): int {
    if (!isset($input[$key]) || !is_numeric($input[$key])) jsonErr('invalid_input', "缺少 $key");
    $v = (int) $input[$key];
    if ($min !== null && $v < $min) jsonErr('invalid_input', "$key 過小");
    if ($max !== null && $v > $max) jsonErr('invalid_input', "$key 過大");
    return $v;
}
function requireString($input, string $key, int $maxLen = 255): string {
    if (!isset($input[$key]) || !is_string($input[$key]) || trim($input[$key]) === '') {
        jsonErr('invalid_input', "缺少 $key");
    }
    $v = trim($input[$key]);
    if (mb_strlen($v) > $maxLen) jsonErr('invalid_input', "$key 過長");
    return $v;
}
function optionalIntArray($input, string $key): array {
    if (!isset($input[$key])) return [];
    $arr = is_array($input[$key]) ? $input[$key] : explode(',', (string)$input[$key]);
    return array_values(array_filter(array_map('intval', $arr), fn($x) => $x > 0));
}
```

### 3.5 `lib/rate_limit.php`

用檔案系統當 storage（APCu 不一定裝），key 為登入者 user_id 或 IP：

```php
function rateLimitCheck(string $bucket): void {
    $key = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'] ?? 'anon';
    $dir = sys_get_temp_dir() . '/ntpc_foodmap_rl';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $now = time();
    foreach ([['min', 60, RATE_LIMIT_PER_MINUTE], ['day', 86400, RATE_LIMIT_PER_DAY]] as [$tag, $window, $limit]) {
        $file = "$dir/{$bucket}_{$tag}_" . md5($key);
        $hits = is_file($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
        $hits = array_values(array_filter($hits, fn($t) => $t > $now - $window));
        if (count($hits) >= $limit) jsonErr('rate_limited', "超過 $tag 限制", 429);
        $hits[] = $now;
        file_put_contents($file, json_encode($hits), LOCK_EX);
    }
}
```

呼叫範例：`rateLimitCheck('geocode');` 在 `/api/geo/geocode.php` 開頭。

### 3.6 `lib/geocode.php`

後端代打 Google Geocoding + 24 小時快取（用檔案系統，key = md5(address)）：

```php
function geocodeAddress(string $address): ?array {
    $cacheDir = sys_get_temp_dir() . '/ntpc_foodmap_geocode';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0700, true);
    $cacheFile = "$cacheDir/" . md5($address);
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?'
         . http_build_query(['address' => $address, 'region' => 'tw', 'language' => 'zh-TW', 'key' => GOOGLE_MAPS_KEY_BACKEND]);
    $resp = @file_get_contents($url);
    if ($resp === false) return null;
    $json = json_decode($resp, true);
    if (($json['status'] ?? '') !== 'OK' || empty($json['results'])) return null;
    $loc = $json['results'][0]['geometry']['location'];
    $result = ['lat' => $loc['lat'], 'lng' => $loc['lng']];
    file_put_contents($cacheFile, json_encode($result));
    return $result;
}
```

---

## 4. API 端點實作（共 27 支）

每支檔案的開頭骨架：

```php
<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/input.php';
session_start();
// ... 業務邏輯 ...
```

下方列每支端點的：路徑、方法、權限、輸入、輸出、SQL / 業務邏輯。

---

### 4.1 Auth 區（5 支）

#### POST `/api/auth/register`
- 權限：公開
- 輸入：`username` (3-50 字), `password` (8-100 字)
- 輸出：`{ ok, data: { user: { user_id, username, is_admin: 0 } } }`
- 邏輯：
  ```php
  $u = requireString($i, 'username', 50);
  $p = requireString($i, 'password', 100);
  if (mb_strlen($u) < 3) jsonErr('invalid_input', 'username 過短');
  if (mb_strlen($p) < 8) jsonErr('invalid_input', 'password 過短');
  try {
    $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
  } catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) jsonErr('conflict', '帳號已存在', 409);
    throw $e;
  }
  $id = (int) db()->lastInsertId();
  $_SESSION['user_id'] = $id;
  jsonOk(['user' => ['user_id' => $id, 'username' => $u, 'is_admin' => 0]]);
  ```

#### POST `/api/auth/login`
- 公開；輸入 `username`, `password`；輸出 `{ user }`
- 失敗回 `invalid_input` `帳號或密碼錯誤`（不洩漏哪邊錯）；成功 `$_SESSION['user_id'] = ...`

#### POST `/api/auth/logout`
- 需登入；`session_destroy()`；回 `{ ok: true, data: null }`

#### GET `/api/auth/me`
- 公開；回 `{ user }` 或 `{ user: null }`（**不用 401，方便前端判斷登入狀態**）

#### POST `/api/auth/change_password`
- 需登入；輸入 `old_password`, `new_password`
- `password_verify($old, $row['password_hash'])` 失敗 → `forbidden`
- 成功 UPDATE

---

### 4.2 Restaurants 區（8 支）

#### GET `/api/restaurants/list`
- 公開
- 輸入（皆可選）：
  - `district[]` int[] zipcode 陣列
  - `tag[]` int[]
  - `min_rating` float (0-5)
  - `max_distance_m` int（需配 `user_lat`+`user_lng`）
  - `user_lat`, `user_lng` float
  - `bbox` `"lat_sw,lng_sw,lat_ne,lng_ne"`
  - `keyword` string（比對 name / description / address）
  - `limit` 預設 50，上限 200
  - `offset` 預設 0
  - `sort` `rating_desc` / `distance_asc` / `name_asc`（預設 `rating_desc`）
- 輸出：
  ```json
  {
    "ok": true,
    "data": {
      "total": 123,
      "restaurants": [{
        "restaurant_id": 1,
        "restaurant_name": "蘇義興餐廳",
        "description": "...",
        "address": "...",
        "zipcode": "220",
        "latitude": 25.012,
        "longitude": 121.46,
        "rating_avg": 4.2,
        "rating_count": 15,
        "price_level": 2,
        "main_photo_url": "https://...",
        "distance_m": 320,
        "is_open_now": true,
        "is_favorited": false,
        "tags": [{"tag_id": 1, "tag_name": "小吃／熱炒"}]
      }]
    }
  }
  ```
- 邏輯：
  - 動態組 WHERE：district / tag / rating / bbox / keyword
  - 距離過濾用 SQL Haversine `6371000 * 2 * ASIN(SQRT(POW(SIN(RADIANS(? - latitude)/2),2) + COS(RADIANS(?)) * COS(RADIANS(latitude)) * POW(SIN(RADIANS(? - longitude)/2),2)))` AS `distance_m`
  - `is_open_now`：LEFT JOIN opentime WHERE day=WEEKDAY(NOW()) AND NOW() BETWEEN start_time AND end_time AND spec_rec IS NULL，存在即 1
  - `is_favorited`：若登入 LEFT JOIN favorites
  - `main_photo_url`：LEFT JOIN restaurant_photos ON is_main=1
  - `tags`：另一次查詢 by restaurant_id IN (...) 後在 PHP 端 group

#### GET `/api/restaurants/count`
- 同 list 的篩選參數，但只回 `{ total }`，用於前端即時筆數

#### GET `/api/restaurants/detail?id=`
- 公開；回完整資料
  ```json
  {
    "ok": true,
    "data": {
      "restaurant": {
        "restaurant_id": 1, "restaurant_name": "...", "description": "...",
        "address": "...", "zipcode": "220", "district_name": "板橋區",
        "latitude": 25.012, "longitude": 121.46,
        "rating_avg": 4.2, "rating_count": 15, "price_level": 2,
        "google_place_id": "ChIJ...",
        "is_open_now": true, "is_favorited": false, "user_review": null,
        "photos": [{"photo_id": 1, "url": "...", "is_main": 1, "sort_order": 0}],
        "phones": ["02-1234-5678"],
        "opentime_regular": [{"day": 1, "start_time": "11:00:00", "end_time": "21:00:00"}],
        "opentime_special": ["週五公休", "農曆春節休"],
        "tags": [{"tag_id": 1, "tag_name": "小吃／熱炒"}]
      }
    }
  }
  ```
- `user_review`：登入時若該使用者對此店有評論，附上自己的那則
- `opentime_special`：篩 `day=0 AND spec_rec IS NOT NULL` 的 `spec_rec` 字串清單

#### GET `/api/restaurants/recommendations?limit=3`
- 公開；`ORDER BY rating_avg DESC, rating_count DESC LIMIT ?`（簡單版；之後可改）
- 回同 list 格式

#### GET `/api/restaurants/carousel?limit=10`
- 公開；隨機抽 N 張 is_main=1 的照片
  ```sql
  SELECT p.url, r.restaurant_id, r.restaurant_name
  FROM restaurant_photos p JOIN restaurants r USING(restaurant_id)
  WHERE p.is_main = 1
  ORDER BY RAND() LIMIT ?;
  ```

#### GET `/api/restaurants/nearby_ntpc?lat=&lng=&limit=20`
- 公開；用 Haversine 排序，無 district / tag 篩選
- 回同 list 格式

#### GET `/api/restaurants/wheel_pool`
- 公開；同 list 的篩選參數，但只回 `{ restaurant_ids: [int] }`，給前端先顯示候選數

#### POST `/api/restaurants/wheel_draw`
- 公開；輸入：同 list 篩選參數
- 邏輯：
  ```php
  $sessionKey = 'wheel_drawn_' . md5(json_encode($filters));
  $drawn = $_SESSION[$sessionKey] ?? [];
  // 查符合篩選且 restaurant_id NOT IN ($drawn) 的 id 清單
  // 隨機抽一筆，加進 $_SESSION[$sessionKey]，回傳 detail
  ```
- 若候選空，回 `{ exhausted: true, restaurant: null }`，前端顯示「都抽完了」並提供重設按鈕
- 重設輪盤：另一支 `POST /api/restaurants/wheel_reset` 清掉 session（也納入此區）

---

### 4.3 Geo 區（2 支）

#### POST `/api/geo/locate`
- 公開；輸入：`lat`, `lng`
- 邏輯：
  - 找出 districts 中離 (lat,lng) 最近的中心點（用 SQL 算 Haversine）
  - 若距離 > 15km，回 `in_ntpc: false`
  - 否則 `in_ntpc: true`，加查 district_adjacency 取得鄰接區
- 輸出：
  ```json
  {
    "ok": true,
    "data": {
      "in_ntpc": true,
      "district": {"zipcode": "220", "district_name": "板橋區", "center_latitude": 25.012, "center_longitude": 121.46},
      "adjacent": [{"zipcode": "234", "district_name": "永和區"}, ...]
    }
  }
  ```

#### POST `/api/geo/geocode`
- 公開；輸入：`address`
- **必 rateLimitCheck('geocode')**
- 呼叫 `geocodeAddress()`；失敗回 `not_found`
- 成功後再呼叫 `locate` 邏輯，回 `{ lat, lng, in_ntpc, district, adjacent }`

---

### 4.4 Dicts 區（2 支）

#### GET `/api/dicts/districts`
- 公開；回 29 個區 + 各區鄰接清單
  ```json
  { "ok": true, "data": { "districts": [
    {"zipcode": "220", "district_name": "板橋區", "center_latitude": 25.012, "center_longitude": 121.46,
     "adjacent_zipcodes": ["234", "235", ...]}
  ]}}
  ```
- 邏輯：先撈所有 districts，再撈 district_adjacency 兩欄 SELECT，在 PHP 端 group by zipcode（注意 `a < b` 約束，雙向都要 push）

#### GET `/api/dicts/tags`
- 公開；回 14 個 tag

---

### 4.5 Favorites 區（2 支）

#### POST `/api/favorites/toggle`
- 需登入；輸入 `restaurant_id`
- 邏輯：先 SELECT，存在就 DELETE，不存在就 INSERT
- 回 `{ is_favorited: bool }`

#### GET `/api/favorites/list`
- 需登入；回 `{ restaurants: [...] }` 用 list 格式

---

### 4.6 Reviews 區（4 支）

#### POST `/api/reviews/upsert`
- 需登入；輸入 `restaurant_id`, `rating` (1-5), `comment` (0-1000 字)
- 邏輯：
  ```sql
  INSERT INTO reviews (user_id, restaurant_id, rating, comment)
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment);
  ```
- 觸發器自動重算 `rating_avg`
- 回 `{ review: { user_id, restaurant_id, rating, comment, created_at, updated_at } }`

#### DELETE `/api/reviews/delete`
- 需登入；輸入 `restaurant_id`；只能刪自己的
- `DELETE FROM reviews WHERE user_id=? AND restaurant_id=?`

#### GET `/api/reviews/by_restaurant?restaurant_id=&limit=20&offset=0`
- 公開
- JOIN users 取 username，子查詢取 `(SELECT COUNT(*) FROM reviews WHERE user_id=u.user_id)` AS `reviewer_total_reviews`
- 輸出：
  ```json
  { "ok": true, "data": { "total": 5, "reviews": [
    {"user_id": 2, "username": "alice", "reviewer_total_reviews": 7,
     "rating": 5, "comment": "...", "created_at": "..."}
  ]}}
  ```

#### GET `/api/reviews/by_user?user_id=&limit=20&offset=0`
- 公開；JOIN restaurants 取餐廳名與主圖
  ```json
  { "ok": true, "data": { "total": 3, "reviews": [
    {"restaurant_id": 1, "restaurant_name": "...", "main_photo_url": "...",
     "rating": 5, "comment": "...", "created_at": "..."}
  ]}}
  ```

---

### 4.7 User 區（1 支）

#### GET `/api/users/profile?user_id=`
- 公開；回 `{ user: { user_id, username, is_admin, review_count, created_at } }`
- 不含 `password_hash`、不含 reviews（用 `/api/reviews/by_user` 另抓）

---

### 4.8 Admin 區（8 支）

所有端點皆 `requireAdmin()`，凡涉及 user_id 操作前先 `if (isSuperAdmin($targetUserId)) jsonErr('forbidden', ...)`。

#### POST `/api/admin/restaurant/upsert`
- 輸入：`restaurant_id` 可選（無則 INSERT，有則 UPDATE）+ 所有 restaurant 欄位 + `phones: []`, `tags: [tag_id]`, `opentime: []`
- 用 transaction：upsert 主表 → DELETE + INSERT phones / tags / opentime（最簡單，避免複雜 diff）

#### POST `/api/admin/restaurant/delete`
- 輸入：`restaurant_id`；DELETE，FK CASCADE 自動清

#### POST `/api/admin/photo/upsert`
- 輸入：`photo_id` 可選、`restaurant_id`、`url`、`is_main`、`sort_order`
- 注意：`is_main=1` 時若已存在另一筆 main，要先把舊的設 0（觸發 UNIQUE）

#### POST `/api/admin/photo/delete`
- 輸入：`photo_id`

#### GET `/api/admin/users/list?limit=&offset=&keyword=`
- 列使用者 + 評論數 + 收藏數（子查詢）

#### POST `/api/admin/users/promote`
- 輸入：`user_id`；`UPDATE users SET is_admin = 1`

#### POST `/api/admin/users/demote`
- 輸入：`user_id`；**先擋 super admin**；`UPDATE users SET is_admin = 0`

#### POST `/api/admin/users/delete`
- 輸入：`user_id`；**先擋 super admin**；DELETE，FK CASCADE 連帶 reviews / favorites，觸發器自動重算 rating

---

## 5. 驗收清單（curl 範例）

每支端點都要能用以下 curl 跑通（在 XAMPP 本機）：

```bash
# 註冊
curl -X POST http://localhost/ntpc-foodmap/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"username":"test1","password":"test1234"}'

# 登入（帶 cookie jar）
curl -c cookies.txt -X POST http://localhost/ntpc-foodmap/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"test1","password":"test1234"}'

# 列表（帶 cookie 才會有 is_favorited）
curl -b cookies.txt "http://localhost/ntpc-foodmap/api/restaurants/list.php?district[]=220&min_rating=4&limit=5"

# 加收藏
curl -b cookies.txt -X POST http://localhost/ntpc-foodmap/api/favorites/toggle.php \
  -H "Content-Type: application/json" \
  -d '{"restaurant_id":1}'

# 寫評論
curl -b cookies.txt -X POST http://localhost/ntpc-foodmap/api/reviews/upsert.php \
  -H "Content-Type: application/json" \
  -d '{"restaurant_id":1,"rating":5,"comment":"很讚"}'
```

驗收標準：
- 回傳 JSON 100% 符合 §4 的範例 schema
- 錯誤情境（無權、缺參數、超限、衝突）都用 `{ ok: false, error: { code, message } }` 對應的 HTTP code 回
- 全部端點都不洩漏 `password_hash`

---

## 6. 注意事項彙整

1. 所有 SQL 用 prepared statement，零字串拼接
2. 觸發器自動維護 `rating_avg` / `rating_count`，**API 永遠不直接 SET 這兩欄**
3. `opentime` sentinel rows (`day=0 AND spec_rec IS NOT NULL`) 是「無法解析的特殊營業時間」，「是否營業中」判斷必須排除
4. 評論 PK 是 `(user_id, restaurant_id)`，第二次寫一律 ON DUPLICATE KEY UPDATE
5. `restaurant_photos.is_main` 受 generated column + UNIQUE 約束，同店至多一張 main
6. `district_adjacency` 有 `CHECK (zipcode_a < zipcode_b)`，INSERT 前先排序
7. session 用 `httponly` + `samesite=Lax`
8. CORS 不開放（前後端同源）
9. 所有 admin 操作前都跑 super admin 擋牆
10. rate limit 桶 (`bucket`) 命名：`geocode` / `auth_register` / `reviews_write` ── 自己歸類
