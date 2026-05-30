# pages/index.php 說明

> 此頁是首頁，目前仍使用 **寫死的 8 筆 mock data**，尚未串接 `sql/seed.sql` 匯入的 693 筆真實餐廳。

## 目前實作

### 渲染
- 伺服器端用 PHP 篩選 `$mockRestaurants` 陣列（`search` / `cuisine` / `distance`）後輸出列表
- 同檔提供 AJAX 模式：`?ajax=1` 會回傳 JSON 而非 HTML
- `window.restaurantsData` 與 `window.searchFilters` 由 PHP 注入給前端 JS

### 搜尋參數

| 參數 | 用途 |
|---|---|
| `search` | 關鍵字（比對 name / category，用 `mb_strpos` 支援中文） |
| `cuisine` | 分類（固定 8 個選項，與 schema 的 14 個分類**不一致**，待改） |
| `distance` | 距離上限（單位公尺，跟固定中心點 25.033964, 121.564468 算 Haversine） |
| `ajax` | 設 1 時回傳 JSON |

### 相依檔
- `assets/css/styles.css`、`assets/css/home.css`
- `assets/js/map.js`、`assets/js/home-php.js`
- `https://maps.googleapis.com/maps/api/js?key=YOUR_FRONTEND_KEY_HERE` ← **placeholder，必須改成從 `config.php` 讀**

## 待辦（讓這頁變成真實版）

1. **接 DB**：開頭 `require __DIR__ . '/../config.php';` 與 `require __DIR__ . '/../lib/db.php';`，把 `$mockRestaurants` 換成 `SELECT * FROM restaurants JOIN ...`
2. **分類選單對齊 schema**：目前 8 個寫死選項要換成 `SELECT id, name FROM tags`（14 個）
3. **Google Maps key**：把 `YOUR_FRONTEND_KEY_HERE` 改成 `<?php echo h(GOOGLE_MAPS_KEY_FRONTEND); ?>`
4. **距離中心點**：目前固定板橋附近，應改為瀏覽器 geolocation 或讓使用者選區（用 `districts` 的中心經緯度）

## AJAX 回傳格式

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "...", "category": "...", "rating": 4.5, "lat": 25.0, "lng": 121.5, "distanceMeters": 320 }
  ],
  "count": 1,
  "filters": { "search": "...", "cuisine": "...", "distance": "..." }
}
```

## 故障排除

- **中文搜尋無結果**：確認 PHP 啟用 `mbstring` 擴展，且檔案編碼 UTF-8
- **地圖標記不顯示**：先檢查瀏覽器 console 是否因 `YOUR_FRONTEND_KEY_HERE` 報 InvalidKey
- **改了 PHP 沒效果**：若使用 XAMPP，記得專案是 Copy-Item 到 `htdocs/`，要重新複製 + Ctrl+F5
