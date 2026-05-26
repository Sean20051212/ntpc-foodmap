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
- 回傳：
	- 成功：`{ "ok": true, "data": { "center": { "lat": number, "lng": number }, "radius": number, "count": number, "restaurants": [...] } }`
	- 餐廳項目欄位：`restaurant_id`、`name`（詳細內容請用 `detail.php` 取得）
	- 錯誤：`{ "ok": false, "error": "訊息" }`

### GET /api/restaurants/filter.php
- 用途：依據區域（`zipcode`）、距離（以 `lat`/`lng` 為中心計算）與 `tag` 篩選餐廳
- 參數：
	- `lat`（必填）: 中心緯度
	- `lng`（必填）: 中心經度
	- `radius`（公尺，選填，預設 1000）
	- `zipcode`（選填）: 可逗號分隔多個郵遞區號，例如 `231,220`
	- `tag_id`（選填）: 可逗號分隔多個 tag id，例如 `1,2`
- 回傳：
	- 成功：`{ "ok": true, "data": { "center": { "lat": number, "lng": number }, "radius": number, "count": number, "restaurants": [...] } }`
	- 餐廳項目欄位：`restaurant_id`、`name`（詳細內容請使用 `detail.php` 取得）
	- 錯誤：`{ "ok": false, "error": "訊息" }`

### GET /api/restaurants/detail.php
- 用途：取得單一餐廳詳細資料（供前端在點選餐廳時呼叫）
- 參數：
		- `restaurant_id`（必填）：餐廳主鍵（整數）
- 回傳：
		- 成功：
			```json
			{
				"ok": true,
				"data": {
					"restaurant_id": number,
					"name": string,
					"description": string,
					"tel": string,
					"address": string,
					"zipcode": string,
					"opentime": string,
					"longitude": number,
					"latitude": number,
					"changetime": string,
					"tags": [ { "tag_id": number, "tag_name": string }, ... ]
				}
			}
			```
		- 錯誤：`{ "ok": false, "error": "訊息" }`

### GET /api/restaurants/wheel_pool.php
（待補）

### GET /api/dicts/classes.php
（待補）

### GET /api/dicts/districts.php
（待補）

## 使用者類 API（負責人：C）

### POST /api/auth/register.php
（待補）

### POST /api/auth/login.php
（待補）

（其他 API 規格待補）

## Google Maps 代理（負責人：D）

### POST /api/maps/geocode.php
（待補）

### POST /api/maps/directions.php
（待補）
