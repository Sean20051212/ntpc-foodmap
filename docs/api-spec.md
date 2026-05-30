# API 規格文件

> 由 B、C 共同維護。所有 API 改動必須先更新此文件。
>
> **狀態（2026-05）**：`api/` 目錄下所有 PHP 端點都還沒寫，目前只有空資料夾。下表為規劃中的 P0 端點清單。

## 共用規範

- **回傳格式**：`{ "ok": true/false, "data": {...}, "error": "訊息" }`
- **錯誤碼**：200 / 400 / 401 / 403 / 404 / 429 / 500
- **編碼**：UTF-8
- **需要登入的 API**：第一行 `require __DIR__ . '/../../lib/auth.php';`（lib 尚未實作）
- **DB 連線**：透過 `lib/db.php` 提供的 PDO singleton（尚未實作）
- **設定來源**：所有端點都要先 `require __DIR__ . '/../../config.php';`（已存在範本，本機需自填）

## P0 規劃端點（8 支）

| 路徑 | 方法 | 用途 | 負責人 | 狀態 |
|---|---|---|---|---|
| `/api/auth/register.php` | POST | 註冊 | C | ❌ |
| `/api/auth/login.php` | POST | 登入（建立 session） | C | ❌ |
| `/api/auth/logout.php` | POST | 登出 | C | ❌ |
| `/api/auth/me.php` | GET | 取得當前登入使用者 | C | ❌ |
| `/api/restaurants/list.php` | GET | 列表 + 篩選（區、分類、關鍵字、評分、距離） | B | ❌ |
| `/api/restaurants/detail.php` | GET | 單筆詳情（含電話、照片、營業時間、分類） | B | ❌ |
| `/api/dicts/districts.php` | GET | 29 區字典（含中心經緯度） | B | ❌ |
| `/api/dicts/tags.php` | GET | 14 個分類字典 | B | ❌ |

## P1 規劃端點

### 餐廳類（B）
- `GET /api/restaurants/nearby.php` — 依經緯度 + 半徑找附近（用 Haversine 或 ST_Distance_Sphere）
- `GET /api/restaurants/wheel_pool.php` — 輪盤候選池（依篩選條件回傳 id 陣列）

### 使用者類（C）
- `POST /api/favorites/add.php` / `POST /api/favorites/remove.php` / `GET /api/favorites/list.php`
- `POST /api/reviews/create.php` / `GET /api/reviews/list.php`（依 restaurant_id）
- `GET /api/history/list.php`（造訪/搜尋紀錄，schema 未含此表，需另設計）

### Google Maps 代理（D）
- `POST /api/maps/geocode.php` — 後端代打 Geocoding API（用 `GOOGLE_MAPS_KEY_BACKEND`，鎖 IP）
- `POST /api/maps/directions.php` — 後端代打 Directions API

> 前端的 Maps JavaScript 仍直接用 `GOOGLE_MAPS_KEY_FRONTEND`（鎖 referrer）載入，不走後端代理。

## 資料模型參考

詳見 `sql/schema.sql`。重點：
- `restaurants.rating_avg` / `rating_count` 由觸發器從 `reviews` 即時重算，**API 不可直接寫入**
- `restaurant_photos.is_main` 透過 generated column + UNIQUE 保證每店至多一張主圖
- `district_adjacency` 有 `CHECK (zipcode_a < zipcode_b)`，插入時要先排序
- `opentime` 一店多筆，每筆代表（day, start_time, end_time）；不規則營業以 `spec_rec` 文字描述、day=0
