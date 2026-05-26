const reviewForm = document.querySelector('#reviewForm');
const reviewList = document.querySelector('#reviewList');
const averageRating = document.querySelector('#averageRating');
const reviewCount = document.querySelector('#reviewCount');
const commentInput = document.querySelector('#commentInput');
const submitButton = reviewForm.querySelector('button[type="submit"]');
const restaurantId = Number(new URLSearchParams(window.location.search).get('restaurant_id') || 1);
const statusNode = document.createElement('p');

let reviews = [];

statusNode.style.margin = '0';
statusNode.style.color = '#7a6f61';
reviewForm.appendChild(statusNode);

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function stars(score) {
  const normalized = Math.max(0, Math.min(5, Number(score) || 0));
  return '★★★★★'.slice(0, normalized) + '☆☆☆☆☆'.slice(0, 5 - normalized);
}

function renderReviews() {
  if (reviews.length === 0) {
    reviewList.innerHTML = '<article class="review-card"><p>目前還沒有評論。</p></article>';
    averageRating.textContent = '0.0';
    reviewCount.textContent = '0 則評論';
    return;
  }

  reviewList.innerHTML = reviews.map((review) => `
    <article class="review-card">
      <div class="review-topline">
        <div>
          <strong>${escapeHtml(review.username || '匿名使用者')}</strong>
          <span>${escapeHtml(review.created_at || '')}</span>
        </div>
        <span class="score">${stars(review.rating)}</span>
      </div>
      <p>${escapeHtml(review.comment || '（沒有留下文字評論）')}</p>
    </article>
  `).join('');

  const total = reviews.reduce((sum, review) => sum + Number(review.rating || 0), 0);
  averageRating.textContent = (total / reviews.length).toFixed(1);
  reviewCount.textContent = `${reviews.length} 則評論`;
}

async function loadReviews() {
  statusNode.textContent = '讀取評論中…';
  try {
    const data = await apiRequest(`reviews/list.php?restaurant_id=${restaurantId}`);
    reviews = data.reviews || [];
    renderReviews();
    statusNode.textContent = '';
  } catch (error) {
    statusNode.textContent = readableError(error, '讀取評論失敗');
  }
}

reviewForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  const rating = Number(new FormData(reviewForm).get('rating'));
  const comment = commentInput.value.trim();

  if (!comment) {
    commentInput.focus();
    return;
  }

  submitButton.disabled = true;
  statusNode.textContent = '送出中…';

  try {
    await apiRequest('reviews/add.php', {
      method: 'POST',
      body: { restaurant_id: restaurantId, rating, comment },
    });
    commentInput.value = '';
    await loadReviews();
  } catch (error) {
    if (error.status === 401) {
      redirectToLogin(window.location.pathname + window.location.search);
      return;
    }
    statusNode.textContent = readableError(error, '送出評論失敗');
  } finally {
    submitButton.disabled = false;
  }
});

loadReviews();
