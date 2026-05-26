class HomeManager {
    constructor() {
        this.restaurants = Array.isArray(window.restaurantsData) ? window.restaurantsData : [];
        this.filteredRestaurants = [...this.restaurants];
        this.selectedRestaurantId = null;
        this.searchTimeout = null;

        this.initElements();
        this.bindEvents();
        this.renderRestaurantList();
        this.updateMapMarkers();
    }

    initElements() {
        this.searchForm = document.getElementById('searchForm');
        this.searchInput = document.getElementById('searchInput');
        this.searchBtn = document.getElementById('searchBtn');
        this.cuisineFilter = document.getElementById('cuisineFilter');
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
            this.searchTimeout = setTimeout(() => {
                this.handleSearch();
            }, 300);
        });

        this.cuisineFilter?.addEventListener('change', () => {
            this.handleSearch();
        });

        this.distanceFilter?.addEventListener('change', () => {
            this.handleSearch();
        });

        this.restaurantList?.addEventListener('click', (event) => {
            const item = event.target.closest('.home-restaurant-item');
            if (!item) return;

            const restaurantId = Number(item.dataset.restaurantId);
            const restaurant = this.filteredRestaurants.find((entry) => Number(entry.id) === restaurantId)
                || this.restaurants.find((entry) => Number(entry.id) === restaurantId);

            if (restaurant) {
                window.location.href = `./restaurant_detail.php?id=${restaurant.id}`;
            }
        });
    }

    handleSearch() {
        const params = this.getFilterParams();
        this.fetchSearchResults(params);
    }

    getFilterParams() {
        return {
            search: this.searchInput?.value.trim() || '',
            cuisine: this.cuisineFilter?.value || '',
            distance: this.distanceFilter?.value || '',
        };
    }

    fetchSearchResults(filters) {
        if (this.restaurantList) {
            this.restaurantList.innerHTML = '<div class="home-loading">搜尋中...</div>';
        }

        const params = new URLSearchParams({
            ...filters,
            ajax: '1',
        });

        fetch(`./index.php?${params.toString()}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`API 錯誤: ${response.status}`);
                }
                return response.json();
            })
            .then((payload) => {
                if (!payload.ok) {
                    throw new Error('API 返回失敗');
                }

                this.filteredRestaurants = Array.isArray(payload.data) ? payload.data : [];
                this.renderRestaurantList();
                this.updateMapMarkers();
                this.updateUrl(filters);
                this.recordSearch(filters);
            })
            .catch((error) => {
                console.error('搜尋失敗:', error);
                if (this.restaurantList) {
                    this.restaurantList.innerHTML = '<div class="home-loading">搜尋失敗，請稍後重試</div>';
                }
                this.updateResultsSummary(0);
            });
    }

    renderRestaurantList() {
        if (!this.restaurantList) return;

        this.restaurantList.innerHTML = '';
        this.updateResultsSummary(this.filteredRestaurants.length);

        if (this.filteredRestaurants.length === 0) {
            this.restaurantList.innerHTML = '<div class="home-loading">沒有找到相符的餐廳</div>';
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
        item.dataset.latitude = restaurant.latitude || restaurant.lat || 25.0330;
        item.dataset.longitude = restaurant.longitude || restaurant.lng || 121.5654;
        item.dataset.lat = restaurant.lat || restaurant.latitude || 25.0330;
        item.dataset.lng = restaurant.lng || restaurant.longitude || 121.5654;
        item.dataset.category = restaurant.category || restaurant.cuisine || '';
        item.dataset.cuisine = restaurant.cuisine || '';
        item.dataset.distance = restaurant.distanceMeters || '';

        if (Number(restaurant.id) === this.selectedRestaurantId) {
            item.classList.add('active');
        }

        const ratingDisplay = restaurant.rating ? `★ ${Number(restaurant.rating).toFixed(1)}` : '暫無評分';
        const distanceDisplay = restaurant.distanceMeters ? `${restaurant.distanceMeters}m` : '距離未定';
        const categoryDisplay = restaurant.category || restaurant.cuisine || '';

        item.innerHTML = `
            <div class="home-restaurant-name">${this.escapeHtml(restaurant.name)}</div>
            <div class="home-restaurant-cuisine">${this.escapeHtml(categoryDisplay)}</div>
            <div class="home-restaurant-meta">
                <span>${this.escapeHtml(ratingDisplay)}</span>
                <span>${this.escapeHtml(distanceDisplay)}</span>
            </div>
        `;

        return item;
    }

    updateMapMarkers() {
        if (typeof mapManager === 'undefined') return;

        mapManager.addMultipleMarkers(
            this.filteredRestaurants.map((restaurant) => ({
                ...restaurant,
                lat: Number(restaurant.lat ?? restaurant.latitude),
                lng: Number(restaurant.lng ?? restaurant.longitude),
                category: restaurant.category || restaurant.cuisine,
            }))
        );
    }

    updateResultsSummary(count) {
        if (!this.resultsSummary) return;
        this.resultsSummary.textContent = `找到 ${count} 間餐廳`;
    }

    updateUrl(filters) {
        const params = new URLSearchParams();

        Object.entries(filters).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        const query = params.toString();
        const nextUrl = query ? `index.php?${query}` : 'index.php';
        window.history.replaceState(null, '', nextUrl);
    }

    recordSearch(filters) {
        if (!window.apiRequest) return;

        const hasFilter = Object.values(filters).some((value) => String(value || '').trim() !== '');
        if (!hasFilter) return;

        window.apiRequest('history/record_search.php', {
            method: 'POST',
            body: {
                address: filters.search || '首頁搜尋',
                filter: {
                    cuisine: filters.cuisine || '',
                    distance_meters: filters.distance || '',
                },
            },
        }).catch((error) => {
            if (error.status !== 401) {
                console.warn('搜尋紀錄儲存失敗:', error);
            }
        });
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
