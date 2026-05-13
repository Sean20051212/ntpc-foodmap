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
（待補）

### POST /api/auth/login.php
（待補）

（其他 API 規格待補）

## Google Maps 代理（負責人：D）

### POST /api/maps/geocode.php
（待補）

### POST /api/maps/directions.php
（待補）
