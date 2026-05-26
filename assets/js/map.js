/**
 * 地圖管理模組 (map.js)
 * 
 * 功能：
 * - Google Maps 初始化
 * - 地理定位
 * - Marker 管理
 * - sessionStorage userLocation 管理
 */

class MapManager {
    constructor() {
        this.map = null;
        this.userLocation = null;
        this.markers = [];
        this.pendingRestaurants = [];
        this.userMarker = null;
        
        // 預設位置：台北101 (25.033964, 121.564468)
        this.DEFAULT_LAT = 25.033964;
        this.DEFAULT_LNG = 121.564468;
        this.DEFAULT_ZOOM = 15;
        
        // 初始化
        this.init();
    }
    
    /**
     * 初始化地圖
     */
    init() {
        // 檢查 userLocation 是否已存在 sessionStorage
        this.loadUserLocationFromStorage();
        
        // 如果沒有 userLocation，進行地理定位
        if (!this.userLocation) {
            this.getUserLocation();
        } else {
            this.initMap();
        }
    }
    
    /**
     * 從 sessionStorage 載入 userLocation
     * 格式：{lat, lng}
     */
    loadUserLocationFromStorage() {
        const stored = sessionStorage.getItem('userLocation');
        if (stored) {
            try {
                this.userLocation = JSON.parse(stored);
                console.log('已從 sessionStorage 載入位置:', this.userLocation);
            } catch (e) {
                console.error('sessionStorage userLocation 解析失敗:', e);
            }
        }
    }
    
    /**
     * 保存 userLocation 到 sessionStorage
     * 格式：{lat, lng}
     */
    saveUserLocationToStorage() {
        if (this.userLocation) {
            sessionStorage.setItem('userLocation', JSON.stringify(this.userLocation));
        }
    }
    
    /**
     * 獲取用戶地理位置
     */
    getUserLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    // 用戶允許定位
                    this.userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    this.saveUserLocationToStorage();
                    console.log('已獲取用戶位置:', this.userLocation);
                    this.initMap();
                },
                (error) => {
                    // 地理定位失敗，使用預設位置（台北101）
                    console.warn('地理定位失敗:', error.message);
                    this.userLocation = {
                        lat: this.DEFAULT_LAT,
                        lng: this.DEFAULT_LNG
                    };
                    this.saveUserLocationToStorage();
                    console.log('使用預設位置（台北101）:', this.userLocation);
                    this.initMap();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            // 瀏覽器不支援 Geolocation
            console.warn('瀏覽器不支援 Geolocation，使用預設位置');
            this.userLocation = {
                lat: this.DEFAULT_LAT,
                lng: this.DEFAULT_LNG
            };
            this.saveUserLocationToStorage();
            this.initMap();
        }
    }
    
    /**
     * 初始化 Google Maps
     */
    initMap() {
        const mapElement = document.getElementById('map');
        if (!mapElement) {
            console.error('找不到 #map 元素');
            return;
        }
        
        const center = {
            lat: this.userLocation.lat,
            lng: this.userLocation.lng
        };
        
        this.map = new google.maps.Map(mapElement, {
            zoom: this.DEFAULT_ZOOM,
            center: center,
            mapTypeId: 'roadmap',
            streetViewControl: false,
            mapTypeControl: false,
            fullscreenControl: false,
            styles: this.getMapStyles()
        });
        
        // 添加用戶位置 marker
        this.addUserMarker(center);
        this.flushPendingRestaurants();
        
        console.log('Google Maps 已初始化');
    }
    
    /**
     * 添加用戶位置 marker
     */
    addUserMarker(position) {
        this.userMarker = new google.maps.Marker({
            position: position,
            map: this.map,
            title: '您的位置',
            icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
            zIndex: 1000
        });
    }
    
    /**
     * 添加餐廳 marker
     * @param {Object} restaurant - 餐廳資訊 { id, name, lat, lng, cuisine, rating }
     */
    addRestaurantMarker(restaurant) {
        const lat = Number(restaurant.lat ?? restaurant.latitude);
        const lng = Number(restaurant.lng ?? restaurant.longitude);

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }

        const marker = new google.maps.Marker({
            position: { lat, lng },
            map: this.map,
            title: restaurant.name,
            label: {
                text: restaurant.name,
                color: '#2D2D2D',
                fontSize: '12px',
                fontWeight: '600'
            },
            icon: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'
        });
        
        // 添加點擊事件
        marker.addListener('click', () => {
            this.onMarkerClick(marker, restaurant);
        });
        
        this.markers.push({
            marker: marker,
            restaurant: {
                ...restaurant,
                lat,
                lng
            }
        });
        
        return marker;
    }
    
    /**
     * Marker 點擊事件處理
     */
    onMarkerClick(marker, restaurant) {
        console.log('已點擊餐廳:', restaurant);
    }
    
    /**
     * 清除所有餐廳 marker
     */
    clearRestaurantMarkers() {
        this.markers.forEach(({ marker }) => {
            marker.setMap(null);
        });
        this.markers = [];
    }
    
    /**
     * 添加多個餐廳 marker
     * @param {Array} restaurants - 餐廳陣列
     */
    addMultipleMarkers(restaurants) {
        if (!this.map) {
            this.pendingRestaurants = Array.isArray(restaurants) ? restaurants : [];
            return;
        }

        this.clearRestaurantMarkers();
        restaurants.forEach((restaurant) => {
            this.addRestaurantMarker(restaurant);
        });
    }

    flushPendingRestaurants() {
        if (this.pendingRestaurants.length === 0) return;

        const restaurants = [...this.pendingRestaurants];
        this.pendingRestaurants = [];
        this.addMultipleMarkers(restaurants);
    }
    
    /**
     * 獲取地圖樣式
     */
    getMapStyles() {
        return [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ];
    }
    
    /**
     * 設置地圖中心
     */
    setCenter(lat, lng) {
        if (this.map) {
            this.map.setCenter({ lat, lng });
        }
    }
    
    /**
     * 設置地圖縮放等級
     */
    setZoom(zoom) {
        if (this.map) {
            this.map.setZoom(zoom);
        }
    }
}

/**
 * 全域地圖管理器實例
 * 在 DOM 載入完成後初始化
 */
let mapManager;

document.addEventListener('DOMContentLoaded', () => {
    mapManager = new MapManager();
});
