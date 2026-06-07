# 新版網站設計與現有資料庫相容性審查 + 已確認決策

> 此檔是「後端計畫」與「前端計畫」共用的決策依據。動工時若兩份計畫有衝突，以此檔為準。
> 兩份計畫不重述本檔內容，需要時直接引用章節。

---

## ⚠️ 2026-06-02 待辦：Schema / 邏輯調整（推翻部分舊決策）

下列項目尚未實作，會直接影響本檔後續章節的判定。若有衝突以本區段為準。對應 README「待辦事項清單」DB-1 ~ DB-10、BE-2。

| # | 項目 | 影響到本檔章節 |
|---|---|---|
| DB-1 | `date` 欄位抽出為獨立資料表 | §7 共用資料字典 |
| DB-2 ✅ | **`opentime` 跨日判斷**（已完成 2026-06-06） — `lib/restaurants.php` `restaurantOpenNowSql()` 補上「昨天列、`start>end`、`CURTIME<=end`」第三分支，解決週一存 22:00-02:00、週二凌晨查詢誤判打烊的 bug | §3-E「是否營業中」 |
| DB-3 | `districts` 移除 `center_latitude/center_longitude`；改由後端比對 `address` 字串是否落在 `districts` 表中來判斷是否屬新北市 | §3-B「不在新北市的處理」、§6「是否在新北市」、§7 districts |
| DB-4 ✅ | **`restaurant_photos` 移除 `sort_order`**（2026-06-06 完成）| §7 photos |
| DB-5 ✅ | **拿掉 `main_marker` generated column，`is_main` 改 BOOLEAN + trigger 限制「每店至多一筆 true」**（2026-06-06 完成）| §3-D / §7 photos |
| DB-6 ✅ | **餐廳照片本機備援**（2026-06-06 完成）| §7 photos：`url` 為外部來源、`local_path` 為本機路徑；`scripts/sync_photos.mjs` cron-style 下載；前端用 `photoSrc(p) = p.local_path || p.url` |
| DB-7 ✅ | **tag 品質審查**（2026-06-07 完成）— 詳見 [docs/tag-audit.md](tag-audit.md)：3 筆誤分類已修，tag_id=2 改名「麵食」| seed 資料 |
| DB-8 ✅ | **ERD 正規化審查**（2026-06-07 完成）— 詳見 [docs/erd-normalization.md](erd-normalization.md)：3NF/BCNF/4NF/5NF 達成，反正規化處皆有理由 | 全檔 |
| DB-9 ✅ | **完整說明 `google_place_id` 用途**（2026-06-07 完成）| §7.1 |
| DB-10 ✅ | **`price_level` 抽出 `price_levels` 查找表**（2026-06-07 完成）— 新表四欄 `price_level_id / symbol / label_zh / label_en`；restaurants.price_level 由 CHECK 改 FK；新增 `/api/dicts/price_levels` | §7 |
| DB-11 ✅ | **opentime.day 抽出 `days_of_week` 查找表**（已完成 2026-06-06） — 教授質疑 `day` 應獨立成表以支援 i18n / 元資料擴充。新表三欄：`day_id`, `day_name_zh`, `day_name_en`；opentime.day 改 FK；新增 `/api/dicts/days` 端點；前端拿掉 `DAYS` 常數 | §3-E、§7、backend §4.4 |
| BE-2 | 「不在新北市」判定改為地址比對 `districts`（搭配 DB-3） | §3-B / §6 |

下列既存決策因上述變動而**作廢或需重新評估**：
- §3-B「不在新北市的處理」原本用「距離最近的 district 中心 > 15km」→ 改為地址比對。
- §5「不做的 schema 變動」中「不新增獨立 date / price_level 表」的隱含假設失效（這兩項現在要做）。

---

## 1. 設計初衷

使用者重新設計整個網站使用者旅程；本次目的是在動工之前，把每項功能對照現有 [../sql/database.sql](../sql/database.sql) 的 11 張表 + 3 個觸發器，找出衝突點與不可行之處，並把所有「需要使用者決定」的問題鎖死，避免後端 / 前端兩個 agent 各自做出不一致的選擇。

---

## 2. 設計需求摘要

- 強制註冊登入才能使用
- 上方永遠顯示搜尋 bar（地址 / keyword）
- 多選區域、距離 bar（不限/100/300/500/800/1000m）、多選分類、評分 bar（不限/1~4 星）
- 即時顯示符合條件餐廳數
- 搜尋紀錄存本地
- 首頁：圖片跑馬燈 + 三家推薦
- 地圖頁：輪盤浮動視窗（抽過不再抽到）+ 收藏永久顯示愛心 + 點 marker 顯示詳情卡 + 地圖移動後「重新搜尋」框內餐廳
- 詳情頁：6 張圖（大圖 + 縮圖切換）+ 完整資訊 + 評分 + 收藏 + 營業 / 特殊營業資訊 + 是否營業中 + 評論表單 + 評論列表 + 點評論者跳個人頁
- Admin 頁：CRUD 餐廳 + 刪使用者 + promote admin（原始 admin 不可動）
- 個人頁：名字 + 修改密碼 + 登出 + 個人所有評論

---

## 3. 逐項判定表

圖例：✅ schema 直接支援｜🟡 需要小修或邏輯補強｜❌ 需要新增 schema｜⚠️ 設計層問題

### A. 帳號 / 登入

| 設計 | 判定 | 說明 |
|---|---|---|
| 強制註冊登入才能用 | ✅ | `users(user_id, username, password_hash, is_admin)` 已有 |
| promote 別的帳號為 admin | ✅ | `UPDATE users SET is_admin=1` |
| 原始 admin 不可被改/刪 | 🟡 | 純 app 層 hardcode `user_id=1` 保護（決策） |
| 個人詳情頁顯示密碼 | ❌ 設計錯誤 | bcrypt 單向雜湊無法還原；改成「修改密碼」按鈕（決策） |

### B. 搜尋 / 篩選

| 設計 | 判定 | 說明 |
|---|---|---|
| 搜尋 bar（地址 / keyword） | ✅ | UI |
| 用使用者定位顯示「該區 + 鄰接區」 | ✅ | `districts.center_latitude/longitude` + `district_adjacency` |
| 不在新北市的處理 | 🟡 | 顯示「最近 N 家新北餐廳」（決策） |
| 多選區域 | ✅ | `WHERE r.zipcode IN (...)` |
| 距離 bar（100/300/500/800/1000m、不限） | ✅ | Haversine + `latitude/longitude` |
| 多選餐廳分類 | ✅ | `restaurant_tags_mapping`，14 個 tag 已 seed |
| 評分 bar（1~4 星、不限） | ✅ | `restaurants.rating_avg` 由觸發器維護 |
| 即時筆數 | 🟡 | `SELECT COUNT(*)`，前端 debounce 300ms |
| 搜尋紀錄存本地 | ✅ | LocalStorage，**不動 DB** |
| API rate limit | 🟡 | 登入用 user_id，未登入用 IP（決策）|

### C. 首頁

| 設計 | 判定 | 說明 |
|---|---|---|
| 跑馬燈（8 秒換） | ✅ | `restaurant_photos` 隨機選圖 |
| 三家推薦 | ✅ | `ORDER BY RAND() LIMIT 3` 或之後改成熱門度 |
| 點圖 / 卡進詳情頁 | ✅ | UI route |

### D. 地圖

| 設計 | 判定 | 說明 |
|---|---|---|
| 輪盤浮動視窗 | ✅ | 純前端動畫，後端負責抽 |
| 抽過不再抽到 | ✅ | 後端 session 記憶 |
| 收藏永久顯示愛心 | ✅ | `favorites(user_id, restaurant_id)` |
| 點 marker 顯示詳情卡 | 🟡 | 主圖 via `restaurant_photos.is_main=1` |
| 地圖移動後重新搜尋框內餐廳 | ✅ | `WHERE latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?`，需加 lat/lng 索引 |

### E. 詳情頁

| 設計 | 判定 | 說明 |
|---|---|---|
| 6 張圖 + 縮圖切換 | 🟡 | 有幾張顯示幾張（決策），不補 placeholder |
| 平均評分、收藏鈕 | ✅ | 觸發器自動算 |
| 營業 / 特殊營業資訊 | ✅ | `opentime(day, start_time, end_time, spec_rec)` |
| 是否營業中 | ✅ | 後端 SQL 子查詢比 `NOW()`，含跨午夜處理（今天 / 昨天列各一條件）；`day=0 AND spec_rec IS NOT NULL` 是 sentinel，須排除 |
| 評論表單 1~5 星 + 內文 | ✅ | `reviews`，`CHECK rating BETWEEN 1 AND 5` |
| 每人每店一則評論 | ✅ | PK 已強制；重複提交 = `ON DUPLICATE KEY UPDATE`（決策） |
| 顯示評論者帳號 / 評分 / 內文 | ✅ | JOIN users |
| 生涯總評論數 | ✅ | `SELECT COUNT(*) FROM reviews WHERE user_id=?` |
| 點評論者跳個人頁 | ✅ | UI route |
| **按讚 / 倒讚** | ❌ → 不做 | 決策：完全不做，schema 不動 |

### F. Admin 頁

| 設計 | 判定 | 說明 |
|---|---|---|
| 更改餐廳資訊（含 photos） | ✅ | UPDATE/INSERT/DELETE 各表 |
| 刪除使用者 | ✅ | FK CASCADE 連帶 reviews/favorites，觸發器自動重算評分 |
| 刪除餐廳 | ✅ | FK CASCADE |
| 新增餐廳 | 🟡 | zipcode 必須在 districts 內，表單做下拉 |
| promote / demote | ✅ | UPDATE is_admin |
| 原始 admin 保護 | 🟡 | app 層擋 `user_id=1`（決策） |

### G. 個人頁

| 設計 | 判定 | 說明 |
|---|---|---|
| 顯示名字 | ✅ | `users.username` |
| 修改密碼 | 🟡 | 替代「顯示密碼」設計（決策） |
| 登出 | ✅ | session destroy |
| 留過的評價 | ✅ | `SELECT FROM reviews WHERE user_id=?` |
| 點評價跳該餐廳該評價 | ✅ | UI anchor |

---

## 4. 已確認決策（7 項）

| 議題 | 決定 | 影響 |
|---|---|---|
| Admin 保護 | 純 app 層 hardcode `user_id=1` | 後端 demote/delete 端點要擋；前端對應按鈕 disable |
| 個人頁密碼 | 「修改密碼」按鈕 | 多一支 `/api/auth/change_password` 端點；前端表單收新舊密碼 |
| 評論按讚 / 倒讚 | 完全不做 | schema 不動，評論物件無 likes 欄位 |
| 不在新北市 | 顯示「最近 N 家新北餐廳」(N=20) | 多一支 `/api/restaurants/nearby_ntpc` 端點 |
| 重複評論 | `INSERT ... ON DUPLICATE KEY UPDATE` | `/api/reviews/upsert` 端點，前端不必先 SELECT |
| 照片不足 6 張 | 有幾張顯示幾張 | 前端 carousel 動態長度，後端正常回傳 |
| Rate limit key | 登入 = user_id，未登入 = IP | `lib/rate_limit.php` 對應實作 |

---

## 5. Schema 變動（後端 agent 開工要先做）

只有 2 項：

1. **新增 seed admin**：在 [sql/database.sql](../sql/database.sql) 結尾加：
   ```sql
   INSERT INTO users (user_id, username, password_hash, is_admin)
   VALUES (1, 'admin', '<PHP 端先用 password_hash() 算好>', 1);
   ```
   `user_id=1` 作為 app 層 hardcode 的保護對象。

2. **新增 lat/lng 索引**：
   ```sql
   ALTER TABLE restaurants ADD KEY idx_restaurants_latlng (latitude, longitude);
   ```
   地圖視口 bbox 查詢必用。

不做的 schema 變動（明確列出避免兩個 agent 自己加）：
- ❌ `is_super_admin` 欄位（走 app 層 hardcode）
- ❌ `review_votes` 表 / `likes_count` 欄（按讚不做）
- ❌ `search_history` 表（純前端 LocalStorage）
- ❌ `view_count` 欄（無此需求）
- ❌ `admin_logs` 表（暫不做，將來有需要再加）

---

## 6. 嚴格的前後端界線

**所有運算寫在後端。前端只負責顯示。**

| 運算 | 由誰做 |
|---|---|
| Haversine 距離 | 後端 |
| 排序、篩選、分頁 | 後端（SQL）|
| 評分平均 | DB 觸發器 |
| 是否在新北市 | 後端 `/api/geo/locate` |
| 是否營業中 | 後端 SQL 比 `NOW()` |
| 推薦演算法 | 後端 |
| 輪盤隨機抽 | 後端（session 記抽過的 ID） |
| 地址 → 經緯度 | 後端代打 Google + 快取 |
| 鄰接區查詢 | 後端 JOIN `district_adjacency` |
| 收藏狀態 | 後端 EXISTS 子查詢 |

前端可做：
- render JSON、收集 input、debounce、carousel timer、Google Maps marker 渲染
- LocalStorage 存使用者本地偏好（搜尋歷史、最近地址快取、輪盤已抽過 id）

---

## 7. 共用資料字典

兩個 agent 寫程式時用同一套欄位命名（與 schema 對齊）：

- 餐廳：`restaurant_id` (int), `restaurant_name` (string), `description`, `address`, `zipcode`, `latitude`, `longitude`, `price_level` (1-4 or null，FK → `price_levels.price_level_id`), `rating_avg` (float 0-5), `rating_count` (int), `google_place_id`（見下方 §7.1 說明）
- 區：`zipcode` (3 chars), `district_name`, `center_latitude`, `center_longitude`
- 分類：`tag_id` (int), `tag_name`
- 照片：`photo_id`, `restaurant_id`, `url`（外部來源）, `local_path`（本機路徑，可為 NULL）, `is_main` (0/1)
- 電話：`phone_id`, `restaurant_id`, `phone_number`
- 營業：`opentime_id`, `restaurant_id`, `day` (0=日 ~ 6=六，FK → `days_of_week.day_id`), `start_time`, `end_time`, `spec_rec`；UNIQUE `(restaurant_id, day, start_time, end_time, spec_rec)` 擋完全重複列；時段重疊由後端 `adminAssertHoursNoOverlap` 在寫入時檢查
- 星期查找表：`day_id` (0~6), `day_name_zh` (週日/週一/...), `day_name_en` (Sunday/Monday/...)
- 價位查找表：`price_level_id` (1~4), `symbol` ($/$$/$$$/$$$$), `label_zh` (便宜/平價/中價/高價), `label_en` (Cheap/Affordable/Mid-range/Expensive)
- 評論：`user_id`, `restaurant_id`, `rating` (1-5), `comment`, `created_at`, `updated_at`
- 收藏：`user_id`, `restaurant_id`, `created_at`
- 使用者：`user_id`, `username`, `password_hash`, `is_admin` (0/1), `created_at`, `updated_at`

**API 回傳一律使用上述欄位名稱**，前端 render 時直接對應。

### 7.1 `google_place_id` 用途與存在必要性

`restaurants.google_place_id VARCHAR(100) NULL UNIQUE` — Google Places 對每家店的全球唯一識別字串（例如 `ChIJN1t_tDeuEmsRUsoyG83frY4`），由 Places API 回傳。

**目前實際用途：**

1. **Google Maps 深層連結**（[js/ui.jsx:17](js/ui.jsx#L17) `googleMapsUrl()`）：詳情頁「在 Google Maps 開啟」按鈕用 `https://www.google.com/maps/place/?q=place_id:XXX` 直接跳到該店面，避免靠店名 / 地址搜尋造成的同名歧義（例：「麥當勞 板橋店」可能命中多筆）。沒 place_id 時 fallback 用店名 + 地址做關鍵字搜尋。
2. **資料補強腳本的斷點續跑** ([scripts/enrich_google.mjs:164](scripts/enrich_google.mjs#L164))：批次跑 Places API 補 `price_level` / 照片時，用 `WHERE google_place_id IS NULL` 跳過已處理的餐廳，避免重複呼叫 API（省 quota）。
3. **未來擴充的對應鍵**：若之後想接 Google Reviews / Photos API 取得更新資料，place_id 是 Google 那邊認的唯一鍵；只能透過它查，無法用店名 + 地址查。

**存在必要性分析：**

| 移除後會發生什麼 | 嚴重程度 |
|---|---|
| Google Maps 連結要 fallback 用名稱搜尋，可能跳錯店 | 🟡 中度（使用者體驗差，但不會壞掉）|
| enrich_google 每次跑都要重新搜尋全部 687 家，浪費 API 額度（Text Search 每千次約 $32 USD）| 🔴 高（成本問題）|
| 未來無法接 Places Details / Reviews / 新照片 API | 🟡 中（影響未來擴充性）|

**結論：必須保留。** 雖然 schema 上看起來只是一個外部 ID 不太「乾淨」，但這個欄位是和 Google 生態系唯一的可信對應鍵，移除會在「Google Maps 連結準確性」與「資料補強成本」兩方面付出代價。UNIQUE 限制確保每家餐廳對應到唯一一個 Google 地點，避免重複匯入。

---

## 8. 共用錯誤碼

| code | 意義 |
|---|---|
| `unauthenticated` | 未登入（401）|
| `forbidden` | 已登入但無權（403），含「動到 user_id=1」 |
| `not_found` | 資源不存在（404）|
| `invalid_input` | 參數錯誤（400），含 zipcode 不存在、rating 不在 1-5 |
| `rate_limited` | 超過 rate limit（429）|
| `conflict` | 唯一鍵衝突（409），如 username 重複 |
| `internal` | 後端錯誤（500）|

統一回傳格式：

```json
{ "ok": false, "error": { "code": "invalid_input", "message": "rating must be 1-5" } }
```

成功格式：
```json
{ "ok": true, "data": { ... } }
```
