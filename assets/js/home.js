/**
 * 首頁管理模組 (home.js)
 * 
 * 功能：
 * - 管理搜尋事件
 * - 管理 sidebar rendering
 * - API fetch 預留
 */

class HomeManager {
    constructor() {
        this.restaurants = [];
        this.filteredRestaurants = [];
        this.selectedRestaurantId = null;
        
        this.initElements();
        this.bindEvents();
        this.loadRestaurants();
    }
    
    /**
     * 初始化 DOM 元素引用
     */
    initElements() {
        this.searchInput = document.getElementById('searchInput');
        this.searchBtn = document.getElementById('searchBtn');
        this.restaurantList = document.getElementById('restaurantList');
    }
    
    /**
     * 綁定事件監聽
     */
    bindEvents() {
        // 搜尋按鈕點擊
        this.searchBtn?.addEventListener('click', () => {
            this.handleSearch(true);
        });
        
        // 搜尋框 Enter 鍵
        this.searchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.handleSearch(true);
            }
        });
        
        // 實時搜尋（可選）
        this.searchInput?.addEventListener('input', () => {
            this.handleSearch(false);
        });
    }
    
    /**
     * 處理搜尋事件
     */
    handleSearch(shouldRecord = false) {
        const keyword = this.searchInput?.value.trim() || '';
        console.log('搜尋關鍵字:', keyword);
        if (shouldRecord) this.recordSearch(keyword);
        
        // 過濾餐廳
        this.filteredRestaurants = this.restaurants.filter((restaurant) => {
            const matchName = restaurant.name.toLowerCase().includes(keyword.toLowerCase());
            const matchCuisine = restaurant.cuisine.toLowerCase().includes(keyword.toLowerCase());
            return matchName || matchCuisine;
        });
        
        console.log('搜尋結果數量:', this.filteredRestaurants.length);
        
        // 重新渲染列表
        this.renderRestaurantList();
        
        // 更新地圖上的 marker
        if (mapManager) {
            mapManager.addMultipleMarkers(
                this.filteredRestaurants.map(r => ({
                    ...r,
                    lat: parseFloat(r.latitude),
                    lng: parseFloat(r.longitude)
                }))
            );
        }
    }

    async recordSearch(keyword) {
        if (!keyword || !window.apiRequest) return;
        try {
            await window.apiRequest('history/record_search.php', {
                method: 'POST',
                body: {
                    address: keyword,
                    filter: { keyword }
                }
            });
        } catch (error) {
            if (error.status !== 401) {
                console.warn('搜尋紀錄儲存失敗:', error);
            }
        }
    }
    
    /**
     * 渲染餐廳列表
     */
    renderRestaurantList() {
        if (!this.restaurantList) return;
        
        // 清空列表
        this.restaurantList.innerHTML = '';
        
        // 如果沒有搜尋結果
        if (this.filteredRestaurants.length === 0) {
            this.restaurantList.innerHTML = '<div class="home-loading">沒有找到相符的餐廳</div>';
            return;
        }
        
        // 遍歷餐廳資料，建立列表項目
        this.filteredRestaurants.forEach((restaurant) => {
            const item = this.createRestaurantItem(restaurant);
            this.restaurantList.appendChild(item);
        });
    }
    
    /**
     * 建立餐廳列表項目
     * @param {Object} restaurant - 餐廳資訊
     */
    createRestaurantItem(restaurant) {
        const item = document.createElement('div');
        item.className = 'home-restaurant-item';
        item.dataset.restaurantId = restaurant.id;
        
        // 檢查是否為選中狀態
        if (restaurant.id === this.selectedRestaurantId) {
            item.classList.add('active');
        }
        
        // 建立內容
        item.innerHTML = `
            <div class="home-restaurant-name">${this.escapeHtml(restaurant.name)}</div>
            <div class="home-restaurant-cuisine">${this.escapeHtml(restaurant.cuisine)}</div>
            <div class="home-restaurant-rating">⭐ ${restaurant.rating || '暫無評分'}</div>
        `;
        
        // 綁定點擊事件
        item.addEventListener('click', () => {
            this.selectRestaurant(restaurant);
        });
        
        return item;
    }
    
    /**
     * 選擇餐廳
     */
    selectRestaurant(restaurant) {
        this.selectedRestaurantId = restaurant.id;
        console.log('已選擇餐廳:', restaurant);
        
        // 更新 active class
        this.restaurantList?.querySelectorAll('.home-restaurant-item').forEach((item) => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-restaurant-id="${restaurant.id}"]`)?.classList.add('active');
        
        // 移動地圖中心到該餐廳
        if (mapManager) {
            mapManager.setCenter(parseFloat(restaurant.latitude), parseFloat(restaurant.longitude));
        }
        
        // TODO: 顯示餐廳詳情（評論、聯絡方式等）
    }
    
    /**
     * 載入餐廳資料
     * 從 API 或本地獲取（預留接口）
     */
    loadRestaurants() {
        console.log('開始載入餐廳資料...');
        
        // 模擬載入狀態
        if (this.restaurantList) {
            this.restaurantList.innerHTML = '<div class="home-loading">載入中...</div>';
        }
        
        // TODO: 實現 API 調用
        // this.fetchRestaurantsFromAPI()
        //     .then(data => {
        //         this.restaurants = data;
        //         this.filteredRestaurants = [...this.restaurants];
        //         this.renderRestaurantList();
        //         
        //         // 初始化地圖 marker
        //         if (mapManager) {
        //             mapManager.addMultipleMarkers(
        //                 this.restaurants.map(r => ({
        //                     ...r,
        //                     lat: parseFloat(r.latitude),
        //                     lng: parseFloat(r.longitude)
        //                 }))
        //             );
        //         }
        //     })
        //     .catch(error => {
        //         console.error('載入餐廳資料失敗:', error);
        //         if (this.restaurantList) {
        //             this.restaurantList.innerHTML = '<div class="home-loading">載入失敗，請稍後重試</div>';
        //         }
        //     });
        
        // 暫時使用模擬資料
        this.loadMockRestaurants();
    }
    
    /**
     * 載入模擬餐廳資料（開發用）
     */
    loadMockRestaurants() {
        const mockData = [
            {
                id: 1,
                name: '鼎泰豐',
                cuisine: '台菜',
                rating: 4.5,
                latitude: 25.0330,
                longitude: 121.5654
            },
            {
                id: 2,
                name: '牛肉麵大王',
                cuisine: '台菜',
                rating: 4.3,
                latitude: 25.0340,
                longitude: 121.5664
            },
            {
                id: 3,
                name: '東京食堂',
                cuisine: '日本料理',
                rating: 4.6,
                latitude: 25.0320,
                longitude: 121.5644
            },
            {
                id: 4,
                name: '韓式烤肉',
                cuisine: '韓國料理',
                rating: 4.2,
                latitude: 25.0350,
                longitude: 121.5674
            },
            {
                id: 5,
                name: '披薩樂園',
                cuisine: '義大利料理',
                rating: 4.4,
                latitude: 25.0325,
                longitude: 121.5635
            }
        ];
        
        this.restaurants = mockData;
        this.filteredRestaurants = [...this.restaurants];
        this.renderRestaurantList();
        
        // 初始化地圖 marker
        if (mapManager) {
            mapManager.addMultipleMarkers(
                this.restaurants.map(r => ({
                    ...r,
                    lat: parseFloat(r.latitude),
                    lng: parseFloat(r.longitude)
                }))
            );
        }
    }
    
    /**
     * 預留 API fetch 函數
     * @param {string} endpoint - API 端點
     * @param {Object} options - fetch 選項
     */
    async fetchFromAPI(endpoint, options = {}) {
        try {
            const response = await fetch(`../api/${endpoint}`, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });
            
            if (!response.ok) {
                throw new Error(`API 錯誤: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API 調用失敗:', error);
            throw error;
        }
    }
    
    /**
     * 預留：從 API 獲取餐廳資料
     */
    async fetchRestaurantsFromAPI() {
        return this.fetchFromAPI('restaurants/list', {
            method: 'GET'
        });
    }
    
    /**
     * 防止 XSS：轉義 HTML 字符
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

/**
 * 全域首頁管理器實例
 * 在 DOM 載入完成後初始化
 */
let homeManager;

document.addEventListener('DOMContentLoaded', () => {
    homeManager = new HomeManager();
});
