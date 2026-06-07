# 前端實作計畫（給 Claude Design / 前端 agent）

> 動工前必讀：[design-audit.md](design-audit.md)（決策依據）
> 配對的後端規格：[backend-plan.md](backend-plan.md)。API 路徑 / 欄位 / 型別以**該檔為準**。
>
> 你的工作就是「**畫面 + 呼叫 API 渲染**」。所有運算、篩選、排序、判斷邏輯一律後端完成；你拿到的 JSON 直接 render。

---

## 0. 絕對禁止清單（最重要）

以下任何一項在 PR review 看到 = 直接打回重做：

| 禁止行為 | 為什麼 | 改用 |
|---|---|---|
| 寫死任何餐廳 / 區 / 分類 / 使用者資料（`const restaurants = [...]`） | mock data 會欺騙審查 | 全部走 API |
| 寫 Haversine 距離公式（含 `Math.sin / cos / sqrt` 與經緯度組合） | 後端已算好 | 用 API 回傳的 `distance_m` 欄位 |
| 在前端判斷「目前營業中」 | 後端比 NOW() 才準（時區、sentinel） | 用 API 回傳的 `is_open_now: bool` |
| 在前端 filter / sort / paginate 已拿到的資料 | 規模一大就壞、與後端結果不一致 | 條件變了重新呼叫 API（帶新 query） |
| 在前端寫推薦演算法 | 後端 `recommendations` 端點負責 | 直接 render |
| 在前端做輪盤抽選（`Math.random()` 選 id） | 抽過排除邏輯放後端 session | call `POST /api/restaurants/wheel_draw` |
| 在前端 parse 地址 / 用 regex 切「新北市XX區」做 fallback | 後端 `/api/geo/geocode` 統一處理 | call API |
| 自己算 rating 平均 / 評論總數 | DB 觸發器算好 | 用 `rating_avg` / `rating_count` |
| 在 `<script>` 內輸出 mock JSON 假裝是後端來的 | 一樣是寫死 | 全部走 fetch |
| 直接 access Google Maps API 來 geocode | key 會洩漏、後端要快取 | call 後端 `/api/geo/geocode` |
| 把 `password` 用任何方式顯示 / 留 placeholder | 一律 bcrypt 後端處理 | 個人頁顯示固定 `********` + 修改鈕 |

---

## 1. 允許做的事

**邊界很乾淨，只要不踩上面紅線就 OK。**

| 類別 | 範例 |
|---|---|
| Render API JSON | `card.innerHTML = data.restaurant_name` / `<RestaurantCard {...r} />` |
| 收 input → call API | 表單 submit、下拉變化、bar slider |
| 瀏覽器原生 | `setInterval(8000)` 跑馬燈、`setTimeout` debounce 300ms、`requestAnimationFrame` 動畫、CSS transition |
| Google Maps JS SDK | 用 key 載入後做 `new google.maps.Map()`、加 marker、pan/zoom、bbox 變化監聽 |
| LocalStorage | 搜尋歷史（最多 50 筆）、最近輪盤已抽過 id 備援（後端 session 為主）、最近 geocode 查詢快取（最多 50 筆）|
| 路由 | URL hash 或 location.pathname 切頁，跟 PHP page router 配合 |
| Loading / Error UI | API call 中顯示 spinner、失敗顯示 error 訊息 |

---

## 2. 技術選型

延續現有架構（[../PHP_VERSION_README.md](../PHP_VERSION_README.md) 已有）：
- PHP 頁面當 entry point（`pages/*.php`）
- React via Babel standalone（CDN）寫互動部分
- 純 CSS（`assets/css/`），不引入 Tailwind / Bootstrap
- Google Maps JS SDK，key 從 PHP 注入 `window.GOOGLE_MAPS_API_KEY`
- `fetch()` 呼叫 API，**所有 fetch 都帶 `credentials: 'same-origin'`** 才會帶 session cookie

**不引入新套件 / build tooling**（無 webpack / vite / npm install）。

---

## 3. 頁面清單與職責

### 3.1 `/pages/register.php`
- 表單：username（3-50）、password（≥8）
- submit → `POST /api/auth/register`
- 成功 → 自動 login → 跳首頁
- 失敗 `conflict` → 顯示「帳號已存在」

### 3.2 `/pages/login.php`
- 表單：username + password
- submit → `POST /api/auth/login`
- 成功跳首頁
- **不要再保留任何假登入邏輯**（目前 login.php 是 mock，必須砍掉重寫）

### 3.3 `/pages/index.php`（首頁）
- 開頁先 `GET /api/auth/me` 確認登入；未登入跳 `/pages/login.php`
- 上方：搜尋 bar（與其他頁共用 component）
- 中間：跑馬燈
  - `GET /api/restaurants/carousel?limit=10` 拿陣列
  - `setInterval(8000)` 換下一張
  - 點圖 → `/pages/detail.php?id=<restaurant_id>`
- 下方：三家推薦
  - `GET /api/restaurants/recommendations?limit=3`
  - 卡片：主圖、name、rating_avg + rating_count、description（前 60 字）
  - 點卡 → detail

### 3.4 `/pages/search.php`（搜尋結果 / 列表頁）
- 上方搜尋 bar
- 左邊側欄：篩選 UI
  - 多選區域下拉：先 `GET /api/dicts/districts`
  - 多選分類下拉：先 `GET /api/dicts/tags`
  - 距離 bar（不限/100/300/500/800/1000m）：值為 0 / 100 / 300 / 500 / 800 / 1000
  - 評分 bar（不限/1/2/3/4 星）：值為 0 / 1 / 2 / 3 / 4
  - **任何條件變動：debounce 300ms 後 `GET /api/restaurants/count`** 顯示「找到 X 家」
  - 按搜尋按鈕 / Enter → `GET /api/restaurants/list`
- 右邊：餐廳卡片列表 + 分頁
- 開頁時：
  - `POST /api/geo/locate` 帶瀏覽器 geolocation 結果
  - 若 `in_ntpc: false`：顯示提示 + `GET /api/restaurants/nearby_ntpc`
  - 若 `in_ntpc: true`：預設帶該區 + 鄰接區 zipcode call list
- 搜尋歷史寫 LocalStorage（key: `searchHistory`，陣列，FIFO 50 筆）

### 3.5 `/pages/map.php`（地圖頁）
- 全螢幕 Google Maps
- 開頁：`POST /api/geo/locate` 取得使用者位置 → map.setCenter
- 拿初始 markers：`GET /api/restaurants/list?bbox=...` 配 viewport
- 監聽 `idle` 事件（pan/zoom 停止後）：**不自動 reload**，顯示「重新搜尋此區域」按鈕；按下 → 用新 bbox 重 call list
- Marker：用 main_photo_url 當 icon 或標準 marker
- 點 marker → infoWindow：主圖、name、rating_avg、description 30 字、收藏鈕、詳情鈕
- 收藏鈕：`POST /api/favorites/toggle` → 成功後 marker 顯示愛心 icon
- 收藏永久顯示愛心：開頁時 `GET /api/favorites/list` 把所有收藏 id 記下，render marker 時對應 icon
- 輪盤按鈕（浮動）：
  - 開啟 modal
  - 進 modal 時 `GET /api/restaurants/wheel_pool?同前頁篩選` 顯示「N 家候選」
  - 點「開始抽」：`POST /api/restaurants/wheel_draw` 帶同篩選 → 拿到 1 家 detail → 動畫跑完顯示
  - 若 `exhausted: true`：顯示「全抽完」+ 重設按鈕 → `POST /api/restaurants/wheel_reset`
  - **不要在前端記抽過的 id**（後端 session 才是 source of truth）

### 3.6 `/pages/detail.php?id=`
- `GET /api/restaurants/detail?id=` 一次拿全部
- 上半部：6 圖大圖切換（左右箭頭 + 下方縮圖列）
  - 照片數動態（1~6 不等）
  - 只有一張：不顯示左右箭頭、不顯示縮圖列
- 中間：name、description、rating_avg + rating_count、收藏鈕、價格範圍、tags、phones
- 營業資訊：`opentime_regular` 渲染週一到週日表格
- 特殊營業資訊：`opentime_special` 字串清單
- 「目前營業中 / 已打烊」徽章：直接看 `is_open_now`（後端算好）
- 評論表單：
  - 一開始顯示一行「點此寫評論」
  - 點開：1~5 星選擇 + textarea（≤1000 字）
  - 已登入 + 已有評論：表單預填現有內容（`user_review` 欄位），按鈕變「更新評論」
  - 未登入：顯示「請先登入才能評論」+ 登入連結
  - submit → `POST /api/reviews/upsert`
- 評論列表：
  - `GET /api/reviews/by_restaurant?restaurant_id=&limit=20&offset=0`
  - 每則：username（可點，跳 `/pages/profile.php?id=<user_id>`）、`reviewer_total_reviews`、星數、內文、`created_at`

### 3.7 `/pages/profile.php?id=`
- `GET /api/users/profile?user_id=` 拿基本資訊
- `GET /api/reviews/by_user?user_id=` 拿留過的評論列表
- 區分「看自己」與「看別人」：
  - 看自己（`user_id` === currentUser.user_id）：
    - 顯示 username + 「修改密碼」按鈕（modal：舊密碼 + 新密碼 ×2，POST `/api/auth/change_password`）
    - 登出按鈕：`POST /api/auth/logout` → 跳 login
    - 密碼欄位顯示 `********`（純裝飾，**不可顯示真實密碼**）
  - 看別人：只顯示 username + 評論列表，無修改 / 登出按鈕
- 點評論 → `/pages/detail.php?id=<restaurant_id>#review-<user_id>`（用 anchor 跳）

### 3.8 `/pages/admin.php`
- 開頁先 `GET /api/auth/me` 確認 `is_admin=1`，否則跳首頁
- 分頁：餐廳管理 / 使用者管理
- **餐廳管理**：
  - 列表 = `GET /api/restaurants/list?limit=50&offset=...`
  - 「新增餐廳」按鈕：開表單（zipcode 用 `/api/dicts/districts` 做下拉、tags 用 `/api/dicts/tags` 做 multi-select）→ `POST /api/admin/restaurant/upsert`
  - 編輯：點列表 row → 載入該店 detail 預填表單
  - 刪除：`POST /api/admin/restaurant/delete`
  - 照片管理：列出該店所有 photos，可新增 url / 設 main / 刪
- **使用者管理**：
  - `GET /api/admin/users/list`
  - 列：user_id、username、is_admin、評論數、收藏數
  - `user_id === 1` 那列：promote / demote / delete 按鈕**全部 disable**（前端擋一層，後端會再擋一層）
  - 其他列：promote / demote / delete 三個按鈕
  - 操作前都 confirm dialog

### 3.9 通用 component：搜尋 bar（所有頁共用）
- 兩種模式：keyword / 地址
- keyword：debounce 300ms → 直接跳 `/pages/search.php?keyword=...`
- 地址：按搜尋鈕 → `POST /api/geo/geocode` → 拿到 lat/lng 後跳 `/pages/search.php?user_lat=...&user_lng=...`
- 自動補全：從 LocalStorage 搜尋歷史抓最近 5 筆顯示

### 3.10 通用 component：登入狀態 navbar
- 開頁 `GET /api/auth/me` 一次
- 已登入：顯示「Hi, {username}」 + 個人頁 link + 收藏 link + 登出
- 未登入：登入 / 註冊 link

---

## 4. LocalStorage schema

只存使用者本地偏好，**絕不存業務資料**：

| key | 內容 | 用途 |
|---|---|---|
| `searchHistory` | `[{type: 'keyword'\|'address', value: string, at: timestamp}]` | 搜尋 bar 自動補全 |
| `geocodeCache` | `{ [address]: { lat, lng, at: timestamp } }` | 重複地址查詢時跳過 API，TTL 24h |

**不要存**：餐廳資料、登入狀態（用 session cookie）、收藏 id（用 favorites API）、輪盤狀態（用 session）。

---

## 5. API 契約（精簡版，完整見 backend-plan.md §4）

| 端點 | 方法 | 權限 | 主要欄位 |
|---|---|---|---|
| `/api/auth/register` | POST | 公開 | username, password |
| `/api/auth/login` | POST | 公開 | username, password |
| `/api/auth/logout` | POST | 登入 | — |
| `/api/auth/me` | GET | 公開 | 回 `{user}` 或 `{user: null}` |
| `/api/auth/change_password` | POST | 登入 | old_password, new_password |
| `/api/restaurants/list` | GET | 公開 | district[], tag[], min_rating, max_distance_m, user_lat, user_lng, bbox, keyword, sort, limit, offset |
| `/api/restaurants/count` | GET | 公開 | 同 list 但只回 total |
| `/api/restaurants/detail` | GET | 公開 | id |
| `/api/restaurants/recommendations` | GET | 公開 | limit |
| `/api/restaurants/carousel` | GET | 公開 | limit |
| `/api/restaurants/nearby_ntpc` | GET | 公開 | lat, lng, limit |
| `/api/restaurants/wheel_pool` | GET | 公開 | 同 list 篩選 |
| `/api/restaurants/wheel_draw` | POST | 公開 | 同 list 篩選 + session 記抽過 |
| `/api/restaurants/wheel_reset` | POST | 公開 | 重設 session 抽過清單 |
| `/api/geo/locate` | POST | 公開 | lat, lng |
| `/api/geo/geocode` | POST | 公開（限流） | address |
| `/api/dicts/districts` | GET | 公開 | 29 區 + 鄰接 |
| `/api/dicts/tags` | GET | 公開 | 14 分類 |
| `/api/favorites/toggle` | POST | 登入 | restaurant_id |
| `/api/favorites/list` | GET | 登入 | — |
| `/api/reviews/upsert` | POST | 登入 | restaurant_id, rating, comment |
| `/api/reviews/delete` | DELETE | 登入 | restaurant_id |
| `/api/reviews/by_restaurant` | GET | 公開 | restaurant_id, limit, offset |
| `/api/reviews/by_user` | GET | 公開 | user_id, limit, offset |
| `/api/users/profile` | GET | 公開 | user_id |
| `/api/admin/restaurant/upsert` | POST | admin | 完整 restaurant 欄位 |
| `/api/admin/restaurant/delete` | POST | admin | restaurant_id |
| `/api/admin/photo/upsert` | POST | admin | photo_id?, restaurant_id, url, is_main, sort_order |
| `/api/admin/photo/delete` | POST | admin | photo_id |
| `/api/admin/users/list` | GET | admin | limit, offset, keyword |
| `/api/admin/users/promote` | POST | admin | user_id |
| `/api/admin/users/demote` | POST | admin | user_id（user_id=1 拒絕） |
| `/api/admin/users/delete` | POST | admin | user_id（user_id=1 拒絕） |

---

## 6. 共用 utility（前端可寫，但只能做 wrapper）

允許寫一支 `assets/js/api.js`，內容只能是：

```js
// 唯一允許的「邏輯」：fetch wrapper + 錯誤統一處理
async function api(method, path, body) {
  const opts = { method, credentials: 'same-origin', headers: {} };
  if (body) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(path, opts);
  const json = await res.json();
  if (!json.ok) throw new ApiError(json.error.code, json.error.message, res.status);
  return json.data;
}
class ApiError extends Error {
  constructor(code, msg, http) { super(msg); this.code = code; this.http = http; }
}
```

**不允許**在 `api.js` 內做：篩選、排序、合併、推薦、距離計算、任何 transform。

---

## 7. 驗收清單

每個頁面 PR 都要附這份檢查：

- [ ] 沒有任何 `const restaurants = [...]` 或類似的寫死資料
- [ ] 沒有 `Math.sin`、`Math.cos`（除了 CSS 動畫不算）
- [ ] 沒有 `Array.prototype.filter` 用來「篩餐廳」（純 UI filter 例如 modal 開關不算）
- [ ] 沒有 `Array.prototype.sort` 對餐廳清單排序
- [ ] 沒有 `Math.random` 抽餐廳
- [ ] 所有 `fetch` 都帶 `credentials: 'same-origin'`
- [ ] 所有 API 失敗都有對應 UI（不會白屏）
- [ ] 顯示「目前營業中」直接讀 `is_open_now`，沒重新算
- [ ] 顯示「距離 X 公尺」直接讀 `distance_m`，沒重新算
- [ ] 顯示「評分」直接讀 `rating_avg`，沒重新算
- [ ] 密碼欄位永遠是 `********` 顯示，不嘗試從 API 拿原文

---

## 8. 給設計師的提示

如果你是 Claude Design 在做視覺：
- 假資料只能在「視覺設計稿」階段用，**寫進 React/HTML 那一刻就要換成 API call**
- 一張卡片如果有 6 個欄位，那 6 個欄位的命名都要對齊 backend-plan.md §4 給的 JSON 範例
- 切版時把所有「即時更新」的地方標 `data-bind="rating_avg"` 之類的標記，方便我審查
