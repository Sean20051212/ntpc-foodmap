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
        this.userMarker = null;
        
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
                    this.userLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };
                    this.saveUserLocationToStorage();
                    console.log('已獲取用戶位置:', this.userLocation);
                    this.initMap();
                },
                (error) => {
                    console.warn('地理定位失敗:', error);
                    // 使用預設位置（新北市中心）
                    this.userLocation = {
                        latitude: 25.0330,
                        longitude: 121.5654,
                        accuracy: null
                    };
                    this.saveUserLocationToStorage();
                    this.initMap();
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            console.warn('瀏覽器不支援 Geolocation');
            // 使用預設位置
            this.userLocation = {
                latitude: 25.0330,
                longitude: 121.5654,
                accuracy: null
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
            lat: this.userLocation.latitude,
            lng: this.userLocation.longitude
        };
        
        this.map = new google.maps.Map(mapElement, {
            zoom: 14,
            center: center,
            mapTypeId: 'roadmap',
            styles: this.getMapStyles()
        });
        
        // 添加用戶位置 marker
        this.addUserMarker(center);
        
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
        const marker = new google.maps.Marker({
            position: { lat: restaurant.lat, lng: restaurant.lng },
            map: this.map,
            title: restaurant.name,
            icon: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png'
        });
        
        // 添加點擊事件
        marker.addListener('click', () => {
            this.onMarkerClick(marker, restaurant);
        });
        
        this.markers.push({
            marker: marker,
            restaurant: restaurant
        });
        
        return marker;
    }
    
    /**
     * Marker 點擊事件處理
     */
    onMarkerClick(marker, restaurant) {
        console.log('已點擊餐廳:', restaurant);
        // TODO: 顯示餐廳詳情 infoWindow
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
        this.clearRestaurantMarkers();
        restaurants.forEach((restaurant) => {
            this.addRestaurantMarker(restaurant);
        });
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
