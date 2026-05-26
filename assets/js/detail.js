class DetailManager {
    constructor() {
        this.restaurant = window.restaurantData || {};
        this.favoriteBtn = document.querySelector('.detail-favorite-btn');

        this.bindEvents();
        this.loadFavoriteStatus();
    }

    bindEvents() {
        this.favoriteBtn?.addEventListener('click', () => {
            this.toggleFavorite();
        });
    }

    async loadFavoriteStatus() {
        if (!window.apiRequest || !this.restaurant.id) {
            this.updateFavoriteLabel(false);
            return;
        }

        try {
            const data = await window.apiRequest('favorites/list.php');
            const favorites = Array.isArray(data.favorites) ? data.favorites : [];
            const isFavorited = favorites.some((item) => Number(item.restaurant_id) === Number(this.restaurant.id));
            this.setFavoriteState(isFavorited);
        } catch (error) {
            if (error.status !== 401) {
                console.warn('讀取收藏狀態失敗:', error);
            }
            this.updateFavoriteLabel(false);
        }
    }

    async toggleFavorite() {
        if (!window.apiRequest || !this.restaurant.id) return;

        const isFavorited = this.favoriteBtn?.classList.contains('is-favorited') || false;
        this.favoriteBtn.disabled = true;

        try {
            await window.apiRequest(isFavorited ? 'favorites/remove.php' : 'favorites/add.php', {
                method: 'POST',
                body: { restaurant_id: this.restaurant.id },
            });
            this.setFavoriteState(!isFavorited);
        } catch (error) {
            if (error.status === 401 && window.redirectToLogin) {
                window.redirectToLogin(window.location.pathname + window.location.search);
                return;
            }
            alert(window.readableError ? window.readableError(error, '收藏操作失敗') : '收藏操作失敗');
        } finally {
            this.favoriteBtn.disabled = false;
        }
    }

    setFavoriteState(isFavorited) {
        this.favoriteBtn?.classList.toggle('is-favorited', isFavorited);
        this.updateFavoriteLabel(isFavorited);
    }

    updateFavoriteLabel(isFavorited) {
        if (!this.favoriteBtn) return;

        this.favoriteBtn.textContent = isFavorited ? '♥' : '♡';
        this.favoriteBtn.setAttribute('aria-label', isFavorited ? '取消收藏' : '加入收藏');
        this.favoriteBtn.setAttribute('title', isFavorited ? '取消收藏' : '加入收藏');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.detailManager = new DetailManager();
});
