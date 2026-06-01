class HomeManager {
    constructor() {
        this.restaurants = [];
        this.filteredRestaurants = [];
        this.selectedRestaurantId = null;
        this.searchTimeout = null;
        this.total = 0;

        this.initElements();
        this.bindEvents();
        this.loadRestaurants();
    }

    initElements() {
        this.searchForm = document.getElementById('searchForm');
        this.searchInput = document.getElementById('searchInput');
        this.searchBtn = document.getElementById('searchBtn');
        this.distanceFilter = document.getElementById('distanceFilter');
        this.restaurantList = document.getElementById('restaurantList');
        this.resultsSummary = document.getElementById('resultsSummary');
    }

    bindEvents() {
        this.searchForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.handleSearch();
        });

        this.searchInput?.addEventListener('input', () => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => this.handleSearch(), 300);
        });

        this.distanceFilter?.addEventListener('change', () => this.handleSearch());

        this.restaurantList?.addEventListener('click', (event) => {
            const item = event.target.closest('.home-restaurant-item');
            if (!item) return;
            window.location.href = `./restaurant_detail.php?id=${Number(item.dataset.restaurantId)}`;
        });
    }

    handleSearch() {
        this.loadRestaurants(this.getFilterParams());
    }

    getFilterParams() {
        return {
            search: this.searchInput?.value.trim() || '',
            distance: this.distanceFilter?.value || '',
        };
    }

    loadRestaurants(filters = window.searchFilters || {}) {
        if (this.restaurantList) {
            this.restaurantList.innerHTML = '<div class="home-loading">正在從資料庫載入...</div>';
        }

        const params = new URLSearchParams();
        params.set('limit', '100');
        params.set('sort', 'rating_desc');

        if (filters.search) {
            params.set('keyword', filters.search);
        }

        const userLocation = this.getStoredUserLocation();
        if (filters.distance && userLocation) {
            params.set('user_lat', String(userLocation.lat));
            params.set('user_lng', String(userLocation.lng));
            params.set('max_distance_m', String(filters.distance));
            params.set('sort', 'distance_asc');
        }

        fetch(`../api/restaurants/list.php?${params.toString()}`)
            .then((response) => {
                if (!response.ok) throw new Error(`API error: ${response.status}`);
                return response.json();
            })
            .then((payload) => {
                if (!payload.ok) throw new Error(payload.error?.message || 'API response error');

                this.total = Number(payload.data?.total || 0);
                this.filteredRestaurants = (payload.data?.restaurants || []).map((row) => this.normalizeRestaurant(row));
                this.restaurants = [...this.filteredRestaurants];
                this.renderRestaurantList();
                this.updateMapMarkers();
                this.updateUrl(filters);
            })
            .catch((error) => {
                console.error('餐廳資料載入失敗:', error);
                if (this.restaurantList) {
                    this.restaurantList.innerHTML = '<div class="home-loading">餐廳資料載入失敗，請確認後端 API 與資料庫是否啟動。</div>';
                }
                this.updateResultsSummary(0, 0);
            });
    }

    normalizeRestaurant(row) {
        const firstTag = Array.isArray(row.tags) && row.tags.length > 0 ? row.tags[0].tag_name : '';
        return {
            id: Number(row.restaurant_id),
            name: row.restaurant_name || '',
            category: firstTag,
            cuisine: firstTag,
            rating: Number(row.rating_avg || 0),
            reviewCount: Number(row.rating_count || 0),
            latitude: Number(row.latitude),
            longitude: Number(row.longitude),
            lat: Number(row.latitude),
            lng: Number(row.longitude),
            address: row.address || '',
            distanceMeters: row.distance_m,
            mainPhotoUrl: row.main_photo_url || '',
            tags: row.tags || [],
        };
    }

    renderRestaurantList() {
        if (!this.restaurantList) return;

        this.restaurantList.innerHTML = '';
        this.updateResultsSummary(this.filteredRestaurants.length, this.total);

        if (this.filteredRestaurants.length === 0) {
            this.restaurantList.innerHTML = '<div class="home-loading">找不到符合條件的餐廳</div>';
            return;
        }

        this.filteredRestaurants.forEach((restaurant) => {
            this.restaurantList.appendChild(this.createRestaurantItem(restaurant));
        });
    }

    createRestaurantItem(restaurant) {
        const item = document.createElement('div');
        item.className = 'home-restaurant-item';
        item.dataset.restaurantId = restaurant.id;
        item.dataset.latitude = restaurant.latitude;
        item.dataset.longitude = restaurant.longitude;
        item.dataset.lat = restaurant.lat;
        item.dataset.lng = restaurant.lng;
        item.dataset.category = restaurant.category;
        item.dataset.cuisine = restaurant.cuisine;
        item.dataset.distance = restaurant.distanceMeters || '';

        const ratingDisplay = restaurant.reviewCount > 0
            ? `${restaurant.rating.toFixed(1)} (${restaurant.reviewCount})`
            : '尚無評分';
        const distanceDisplay = restaurant.distanceMeters === null || restaurant.distanceMeters === undefined
            ? ''
            : `${Number(restaurant.distanceMeters).toLocaleString()}m`;
        const categoryDisplay = restaurant.category || '未分類';

        item.innerHTML = `
            <div class="home-restaurant-name">${this.escapeHtml(restaurant.name)}</div>
            <div class="home-restaurant-cuisine">${this.escapeHtml(categoryDisplay)} · ${this.escapeHtml(restaurant.address)}</div>
            <div class="home-restaurant-meta">
                <span>${this.escapeHtml(ratingDisplay)}</span>
                <span>${this.escapeHtml(distanceDisplay)}</span>
            </div>
        `;

        return item;
    }

    updateMapMarkers() {
        if (typeof mapManager === 'undefined') return;
        mapManager.addMultipleMarkers(this.filteredRestaurants);
    }

    updateResultsSummary(shown, total) {
        if (!this.resultsSummary) return;
        this.resultsSummary.textContent = `顯示 ${shown} 筆，符合條件共 ${total} 筆`;
    }

    updateUrl(filters) {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.distance) params.set('distance', filters.distance);

        const query = params.toString();
        window.history.replaceState(null, '', query ? `index.php?${query}` : 'index.php');
    }

    getStoredUserLocation() {
        try {
            const raw = sessionStorage.getItem('userLocation');
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            return Number.isFinite(parsed.lat) && Number.isFinite(parsed.lng) ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.homeManager = new HomeManager();
});
