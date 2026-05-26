# API 規格文件

> 由 B、C 共同維護。所有 API 改動必須先更新此文件。

## 共用規範

- **回傳格式**：`{ "ok": true/false, "data": {...}, "error": "訊息" }`
- **錯誤碼**：200 / 400 / 401 / 403 / 404 / 429 / 500
- **編碼**：UTF-8
- **需要登入的 API**：第一行 `require 'lib/auth_check.php'`

## 餐廳類 API（負責人：B）

### GET /api/restaurants/nearby.php
- 用途：依地址 + 半徑找附近餐廳
- 參數：`lat`（必填）、`lng`（必填）、`radius`（公尺，預設 1000）
- 回傳：（待補）

### GET /api/restaurants/filter.php
（待補）

### GET /api/restaurants/detail.php
（待補）

### GET /api/restaurants/wheel_pool.php
（待補）

### GET /api/dicts/classes.php
（待補）

### GET /api/dicts/districts.php
（待補）

## 使用者類 API（負責人：C）

### POST /api/auth/register.php
說明：註冊新使用者，成功後直接建立 session。

Body:
```json
{ "username": "sean_01", "password": "password123" }
```

Response:
```json
{
  "ok": true,
  "data": {
    "user": { "user_id": 1, "username": "sean_01" }
  }
}
```

### POST /api/auth/login.php
說明：登入，成功後寫入 `$_SESSION['user_id']`。

Body:
```json
{ "username": "sean_01", "password": "password123" }
```

### POST /api/auth/logout.php
說明：登出並清除 session。

### GET /api/auth/me.php
說明：取得目前登入使用者，未登入回 `401`。

Response:
```json
{
  "ok": true,
  "data": {
    "user": {
      "user_id": 1,
      "username": "sean_01",
      "created_at": "2026-05-26 12:00:00"
    }
  }
}
```

### GET /api/favorites/list.php
說明：取得我的收藏，需要登入。

### POST /api/favorites/add.php
說明：新增收藏，需要登入。

Body:
```json
{ "restaurant_id": 1 }
```

### POST /api/favorites/remove.php
說明：移除收藏，需要登入。

Body:
```json
{ "restaurant_id": 1 }
```

### GET /api/reviews/list.php
說明：查詢評論。帶 `restaurant_id` 時查餐廳評論；不帶時查我的評論，需要登入。

Query:
```text
restaurant_id=1
```

### POST /api/reviews/add.php
說明：新增評論，需要登入。

Body:
```json
{ "restaurant_id": 1, "rating": 5, "comment": "好吃" }
```

### POST /api/reviews/update.php
說明：修改自己的評論，需要登入。

Body:
```json
{ "review_id": 1, "rating": 4, "comment": "更新評論" }
```

### POST /api/reviews/delete.php
說明：刪除自己的評論，需要登入。

Body:
```json
{ "review_id": 1 }
```

### GET /api/history/list.php
說明：取得我的搜尋紀錄與輪盤紀錄，需要登入。

Query:
```text
limit=20
```

### POST /api/history/record_search.php
說明：寫入搜尋紀錄，需要登入。給搜尋 API 或前端呼叫。

Body:
```json
{ "address": "新北市板橋區", "filter": { "radius": 1000 } }
```

### POST /api/history/record_wheel.php
說明：寫入輪盤抽選紀錄，需要登入。給輪盤功能呼叫。

Body:
```json
{ "restaurant_id": 1, "conditions": { "district": "板橋區" } }
```

所有需要登入的 API 都會透過 `lib/auth_check.php` 檢查 session，未登入統一回 `401`。

## Google Maps 代理（負責人：D）

### POST /api/maps/geocode.php
（待補）

### POST /api/maps/directions.php
（待補）
