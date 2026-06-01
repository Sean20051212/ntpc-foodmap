class DetailManager {
    constructor() {
        this.restaurant = window.restaurantData || {};
        this.favoriteBtn = document.querySelector('.detail-favorite-btn');
        this.reviewForm = document.querySelector('#reviewForm');
        this.reviewMessage = document.querySelector('#reviewMessage');

        this.bindEvents();
        this.updateFavoriteLabel(Boolean(this.restaurant.is_favorited));
    }

    bindEvents() {
        this.favoriteBtn?.addEventListener('click', () => {
            this.toggleFavorite();
        });

        this.reviewForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitReview();
        });
    }

    toggleFavorite() {
        if (!this.restaurant.id) return;

        fetch('../api/favorites/toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ restaurant_id: this.restaurant.id }),
        })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload.ok) {
                    throw new Error(payload.error?.message || 'favorite failed');
                }

                const isFavorited = Boolean(payload.data?.is_favorited);
                this.restaurant.is_favorited = isFavorited;
                this.favoriteBtn?.classList.toggle('is-favorited', isFavorited);
                this.updateFavoriteLabel(isFavorited);
            })
            .catch((error) => {
                console.error('收藏餐廳失敗:', error);
                alert('請先登入後再收藏餐廳。');
            });
    }

    updateFavoriteLabel(isFavorited) {
        if (!this.favoriteBtn) return;

        this.favoriteBtn.textContent = isFavorited ? '♥' : '♡';
        this.favoriteBtn.setAttribute('aria-label', isFavorited ? '取消收藏' : '加入收藏');
        this.favoriteBtn.setAttribute('title', isFavorited ? '取消收藏' : '加入收藏');
    }

    submitReview() {
        if (!this.restaurant.id || !this.reviewForm) return;

        const submitBtn = this.reviewForm.querySelector('button[type="submit"]');
        const rating = Number(this.reviewForm.querySelector('#reviewRating')?.value || 0);
        const comment = this.reviewForm.querySelector('#reviewComment')?.value || '';

        this.setReviewMessage('');
        if (submitBtn) submitBtn.disabled = true;

        fetch('../api/reviews/upsert.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                restaurant_id: this.restaurant.id,
                rating,
                comment,
            }),
        })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload.ok) {
                    throw new Error(payload.error?.message || 'review failed');
                }

                this.setReviewMessage('評論已送出，正在更新畫面...', true);
                window.setTimeout(() => window.location.reload(), 400);
            })
            .catch((error) => {
                console.error('送出評論失敗:', error);
                this.setReviewMessage('評論送出失敗，請確認已登入後再試。', false);
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    setReviewMessage(message, ok = false) {
        if (!this.reviewMessage) return;

        this.reviewMessage.textContent = message;
        this.reviewMessage.classList.toggle('is-ok', ok);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.detailManager = new DetailManager();
});
