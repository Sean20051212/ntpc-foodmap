# pages/index.php 過渡狀態說明

> ⚠️ 此檔記錄的是 **過渡期** 的 `pages/index.php`。最終實作以 [docs/frontend-plan.md](docs/frontend-plan.md) §3.3 為準，**此頁將被重寫**。

## 目前實作狀態

`pages/index.php` 仍是 **寫死的 8 筆 mock data**，使用 PHP 在伺服器端做篩選並輸出 HTML，同時提供 `?ajax=1` 模式回 JSON。

這份實作違反 [docs/frontend-plan.md §0 嚴禁清單](docs/frontend-plan.md) 的多項規則：
- 寫死餐廳資料
- 伺服器端 PHP 直接算 Haversine 距離
- 沒有 API 層，前後端混在同一支 PHP 檔
- 自訂的 8 個分類選項與 schema 的 14 個 tag 不一致

## 為何留著

純粹是「等後端 API 寫好之前的占位畫面」，方便組員看到 UI 長相。一旦 [docs/backend-plan.md §4.2](docs/backend-plan.md) 的 restaurants 端點實作完成，這個檔案會被全面改寫成符合 frontend-plan §3.3 的版本：
- 開頁 `GET /api/auth/me` 確認登入
- 跑馬燈 `GET /api/restaurants/carousel`
- 推薦 `GET /api/restaurants/recommendations`
- 所有渲染只 render API 回傳的 JSON

## 過渡期可用的 URL

```
http://localhost/ntpc-foodmap/pages/index.php
http://localhost/ntpc-foodmap/pages/index.php?search=日本料理
http://localhost/ntpc-foodmap/pages/index.php?search=台菜&ajax=1
```

## 已知問題（重寫時會一起修掉）

- 分類選單寫死 8 個，與 schema 14 tag 不一致
- 距離中心點固定在板橋附近，未用瀏覽器 geolocation 或使用者選區
- Google Maps key 在 HTML 內為 `YOUR_FRONTEND_KEY_HERE` placeholder
- 中文搜尋大小寫 / 全形空白未處理
- 假登入 `pages/login.php`：任何非「wrong」的密碼都會通過

這些不要在這個 mock 版本修補，**等後端 API 出來後直接照 frontend-plan 重寫**比較划算。

## 故障排除（過渡期用）

- **中文搜尋無結果**：確認 PHP 啟用 `mbstring` 擴展，且檔案編碼 UTF-8
- **地圖標記不顯示**：placeholder key 必定報 InvalidKey，這個版本本來就不會顯示地圖
- **改了 PHP 沒效果**：XAMPP 部署是 Copy-Item 不是 symlink，要重新複製 + Ctrl+F5
