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

    loadFavoriteStatus() {
        const favorites = this.getFavorites();
        const isFavorited = favorites.includes(this.restaurant.id);

        this.favoriteBtn?.classList.toggle('is-favorited', isFavorited);
        this.updateFavoriteLabel(isFavorited);
    }

    toggleFavorite() {
        const favorites = this.getFavorites();
        const isFavorited = favorites.includes(this.restaurant.id);
        const nextFavorites = isFavorited
            ? favorites.filter((id) => id !== this.restaurant.id)
            : [...favorites, this.restaurant.id];

        localStorage.setItem('favorites', JSON.stringify(nextFavorites));
        this.favoriteBtn?.classList.toggle('is-favorited', !isFavorited);
        this.updateFavoriteLabel(!isFavorited);
    }

    getFavorites() {
        try {
            const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            return Array.isArray(favorites) ? favorites.map(Number) : [];
        } catch (error) {
            return [];
        }
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
