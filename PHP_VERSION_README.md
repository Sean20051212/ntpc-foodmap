# PHP 版本使用指南

## 概述
已經將 `index.html` 的功能改寫成 PHP 版本的 `index.php`，實現了服務端渲染和搜尋功能。

## 文件說明

### 1. **index.php** (新版首頁 - 推薦使用)
- **位置**: `pages/index.php`
- **功能**:
  - 伺服器端搜尋和過濾餐廳
  - 服務端渲染初始餐廳列表
  - 支援 AJAX 搜尋 (GET 參數: `?search=關鍵字&ajax=1`)
  - 返回 JSON 格式搜尋結果

**主要特性**:
```php
- 搜尋參數: $_GET['search']
- AJAX 模式: 檢測 $_GET['ajax'] 返回 JSON
- 中文搜尋支援: 使用 mb_strpos() 處理多位元字符
- HTML 轉義: 防止 XSS 攻擊
```

### 2. **index.html** (原始版本 - 已保留)
- **位置**: `pages/index.html`
- 保留供參考或備用

### 3. **home-php.js** (新版 JavaScript)
- **位置**: `assets/js/home-php.js`
- **功能**:
  - 從 DOM 提取初始餐廳資料
  - AJAX 即時搜尋
  - 列表項目點擊事件處理
  - 地圖標記更新

## 使用方法

### 基本訪問
```
http://localhost/ntpc-foodmap/pages/index.php
```

### 帶搜尋參數訪問
```
http://localhost/ntpc-foodmap/pages/index.php?search=日本料理
http://localhost/ntpc-foodmap/pages/index.php?search=台菜
```

### AJAX 搜尋 (JavaScript)
```javascript
// 自動搜尋示例
fetch('./index.php?search=日本料理&ajax=1')
    .then(res => res.json())
    .then(data => {
        console.log(data.data);  // 搜尋結果
        console.log(data.count); // 結果數量
    });
```

## 搜尋響應格式 (AJAX)

**成功响应**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "餐廳名稱",
            "cuisine": "菜系",
            "rating": 4.5,
            "latitude": 25.0330,
            "longitude": 121.5654
        }
    ],
    "count": 1
}
```

## 整合建議

### 1. 連接到數據庫
在 `index.php` 中取消註釋並實現：
```php
require_once '../config.php';
// 替換 $mockRestaurants 為從數據庫查詢
$restaurants = $db->query("SELECT * FROM restaurants WHERE name LIKE ? OR cuisine LIKE ?");
```

### 2. 提供完整的餐廳座標
當前 JavaScript 會自動尋找 DOM 中的座標資訊。建議在服務端直接渲染：
```php
<div class="home-restaurant-item" 
     data-restaurant-id="<?php echo $restaurant['id']; ?>"
     data-latitude="<?php echo $restaurant['latitude']; ?>"
     data-longitude="<?php echo $restaurant['longitude']; ?>">
    ...
</div>
```

並在 `home-php.js` 中修改提取座標：
```javascript
const latitude = parseFloat(item.dataset.latitude) || 25.0330;
const longitude = parseFloat(item.dataset.longitude) || 121.5654;
```

### 3. 使用新的 JavaScript 文件
在 `index.php` 中替換 JavaScript 引入：
```html
<!-- 舊版本 -->
<!-- <script src="../assets/js/home.js"></script> -->

<!-- 新版本 -->
<script src="../assets/js/home-php.js"></script>
```

### 4. 部署到生產環境
- 確認 PHP 版本 >= 7.0 (支援 ?? 運算符)
- 啟用 multibyte 字符串擴展 (`mbstring`)
- 配置正確的字符集編碼 (UTF-8)

## 功能對比

| 功能 | 原始版本 (index.html) | PHP 版本 (index.php) |
|------|-----------------|-----------------|
| 搜尋 | 客戶端 (JavaScript) | 伺服器端 + AJAX |
| 初始加載 | 動態加載 | 伺服器端渲染 |
| SEO | 不友善 | 友善 |
| 性能 | 依賴 JavaScript | 更快初始加載 |
| 離線支援 | 有 (若快取資料) | 否 |

## 性能優化建議

1. **快取查詢結果**
   ```php
   $cacheKey = 'restaurants_search_' . md5($searchKeyword);
   $cached = apcu_fetch($cacheKey);
   ```

2. **限制搜尋結果**
   ```php
   $restaurants = array_slice($restaurants, 0, 50);
   ```

3. **添加資料庫索引**
   ```sql
   CREATE INDEX idx_restaurant_name ON restaurants(name);
   CREATE INDEX idx_restaurant_cuisine ON restaurants(cuisine);
   ```

4. **預加載常用搜尋**
   - 在初頁面中嵌入熱門搜尋結果

## 故障排除

**問題**: 搜尋不返回結果
- 檢查 PHP 的 `mbstring` 擴展是否啟用
- 確認數據庫中的字符集編碼

**問題**: 地圖標記不顯示
- 確認座標資料正確傳遞
- 檢查 `home-php.js` 中的 `extractRestaurantsFromDOM()` 方法

**問題**: 中文搜尋無法正常工作
- 確認 PHP 文件編碼為 UTF-8
- 檢查數據庫連接編碼設定
