<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>我的收藏 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="../assets/js/restaurants.js"></script>
<script src="../assets/js/api-client.js"></script>
<script type="text/babel" src="../assets/js/shared.jsx?v=2"></script>
<script type="text/babel">
const { useEffect, useState } = React;

function mapFavorite(item) {
  return {
    id: Number(item.restaurant_id),
    favoriteId: Number(item.favorite_id),
    name: item.name,
    address: item.address || "尚無地址",
    hours: item.opentime || item.tel || "尚無營業時間",
    category: item.category || "餐廳",
    catColor: item.category === "咖啡" ? "green" : "orange",
  };
}

function RCard({ r, onRemove, removing }) {
  return (
    <article className={"rcard" + (removing ? " is-removing" : "")}>
      <span className={"tag" + (r.catColor === "green" ? " tag-green" : "")}>{r.category}</span>
      <h3 className="rcard-name">{r.name}</h3>
      <div className="rcard-meta">
        <div className="rcard-meta-row is-clamp">
          <Icon name="mapPin" size={14}/> <span>{r.address}</span>
        </div>
        <div className="rcard-meta-row">
          <Icon name="clock" size={14}/> <span>{r.hours}</span>
        </div>
      </div>
      <div className="rcard-foot">
        <button className="heart-btn" aria-label="移除收藏" onClick={() => onRemove(r.id)}>
          <Icon name="heart" size={22}/>
        </button>
        <button className="btn btn-outline btn-sm">查看詳情 →</button>
      </div>
    </article>
  );
}

function ConfirmModal({ restaurant, onCancel, onConfirm }) {
  return (
    <div className="modal-backdrop" onClick={onCancel}>
      <div className="modal" onClick={e => e.stopPropagation()}>
        <h3 className="modal-title">確定要移除收藏嗎？</h3>
        <p className="modal-body">
          將會從收藏清單中移除「<strong style={{color: "var(--color-text)"}}>{restaurant?.name}</strong>」。
        </p>
        <div className="modal-actions">
          <button className="btn btn-outline" onClick={onCancel}>取消</button>
          <button className="btn btn-primary" onClick={onConfirm}>確定移除</button>
        </div>
      </div>
    </div>
  );
}

function FavoritesPage() {
  const [items, setItems] = useState([]);
  const [removingId, setRemovingId] = useState(null);
  const [confirmTarget, setConfirmTarget] = useState(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  async function loadFavorites() {
    setLoading(true);
    setErr("");
    try {
      await requireCurrentUser();
      const data = await apiRequest("favorites/list.php");
      setItems((data.favorites || []).map(mapFavorite));
    } catch (error) {
      if (error.status !== 401) setErr(readableError(error, "讀取收藏失敗"));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadFavorites();
  }, []);

  function askRemove(id) {
    const t = items.find(i => i.id === id);
    setConfirmTarget(t);
  }
  async function doRemove() {
    const id = confirmTarget.id;
    setConfirmTarget(null);
    setRemovingId(id);
    try {
      await apiRequest("favorites/remove.php", {
        method: "POST",
        body: { restaurant_id: id }
      });
      setItems(prev => prev.filter(i => i.id !== id));
    } catch (error) {
      setErr(readableError(error, "移除收藏失敗"));
    } finally {
      setRemovingId(null);
    }
  }
  async function clearAll() {
    setErr("");
    setRemovingId("all");
    try {
      await Promise.all(items.map(item =>
        apiRequest("favorites/remove.php", {
          method: "POST",
          body: { restaurant_id: item.id }
        })
      ));
      setItems([]);
    } catch (error) {
      setErr(readableError(error, "清空收藏失敗"));
      loadFavorites();
    } finally {
      setRemovingId(null);
    }
  }

  return (
    <>
      <Nav active="favorites"/>
      <main className="page">
        <div style={{display: "flex", justifyContent: "space-between", alignItems: "flex-end", marginBottom: 28, flexWrap: "wrap", gap: 12}}>
          <div>
            <h1 className="page-title">我的收藏</h1>
            <p className="page-sub" style={{margin: 0}}>共 {items.length} 家餐廳</p>
          </div>
          {items.length > 0 && (
            <button className="btn btn-ghost btn-sm" onClick={clearAll} disabled={removingId === "all"}>清空收藏</button>
          )}
        </div>

        {err && (
          <div className="warn-banner">
            <Icon name="warn" size={18}/> {err}
          </div>
        )}

        {loading ? (
          <div className="empty">
            <div className="empty-icon">
              <Icon name="heart" size={42} stroke={1.5}/>
            </div>
            <h3 className="empty-text">讀取收藏中…</h3>
          </div>
        ) : items.length === 0 ? (
          <div className="empty">
            <div className="empty-icon">
              <Icon name="inbox" size={42} stroke={1.5}/>
            </div>
            <h3 className="empty-text">還沒有收藏任何餐廳</h3>
            <p className="empty-sub">逛逛首頁地圖，把喜歡的店點愛心加入收藏吧</p>
            <a href="index.php" className="btn btn-primary">
              <Icon name="search" size={16}/> 去搜尋餐廳
            </a>
          </div>
        ) : (
          <div className="card-grid">
            {items.map(r => (
              <RCard key={r.id} r={r} onRemove={askRemove} removing={removingId === r.id || removingId === "all"}/>
            ))}
          </div>
        )}
      </main>
      {confirmTarget && (
        <ConfirmModal
          restaurant={confirmTarget}
          onCancel={() => setConfirmTarget(null)}
          onConfirm={doRemove}
        />
      )}
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<FavoritesPage/>);
</script>
</body>
</html>
