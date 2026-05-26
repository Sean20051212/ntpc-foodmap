<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新北美食地圖</title>
    
    <!-- 載入全域 CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    
    <!-- 載入首頁 CSS -->
    <link rel="stylesheet" href="../assets/css/home.css">
</head>
<body>
    <div class="home-container">
        <!-- Sidebar - 餐廳列表 -->
        <aside class="home-sidebar">
            <!-- 搜尋欄 -->
            <div class="home-search-wrapper">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="home-search-bar" 
                    placeholder="搜尋餐廳名稱、菜系..."
                    aria-label="搜尋餐廳"
                >
                <button id="searchBtn" class="home-search-btn" aria-label="搜尋">
                    🔍
                </button>
            </div>
            
            <!-- 餐廳列表容器 -->
            <div class="home-restaurant-list" id="restaurantList">
                <!-- 餐廳項目將由 home.js 動態插入 -->
                <div class="home-loading">載入中...</div>
            </div>
        </aside>
        
        <!-- Google Maps 容器 -->
        <main class="home-map-wrapper">
            <div id="map" class="home-map"></div>
        </main>
    </div>

    <!-- 載入 Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY" async defer></script>
    
    <!-- 載入首頁相關 JS -->
    <script src="../assets/js/map.js"></script>
    <script src="../assets/js/home.js"></script>
</body>
</html>
