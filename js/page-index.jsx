/* 首頁 — 搜尋優先 hero（跑馬燈背景）+ 探索分類 + 三家推薦 */
function PageIndex({ me }) {
  const car = useApi(() => api("GET", "/api/restaurants/carousel", { limit: 8 }), []);
  const tagsRes = useApi(() => api("GET", "/api/dicts/tags"), []);
  const [recs, setRecs] = useState(null);
  useEffect(() => { api("GET", "/api/restaurants/recommendations", { limit: 3 }).then(d => setRecs(d.restaurants)).catch(() => setRecs([])); }, []);

  const photos = car.data ? car.data.photos : [];
  const [idx, setIdx] = useState(0);
  useEffect(() => { if (photos.length < 2) return; const t = setInterval(() => setIdx(i => (i + 1) % photos.length), 8000); return () => clearInterval(t); }, [photos.length]);

  const onFav = (r) => window.favToggle(r, setRecs);

  return <div>
    {/* hero */}
    <section className="home-hero">
      {photos.map((p, i) => <div key={i} onClick={() => navigate("#/detail?id=" + p.restaurant_id)} style={{ position: "absolute", inset: 0, opacity: i === idx ? 1 : 0, transition: "opacity 1.1s ease", cursor: "pointer" }}>
        <img src={p.url} alt="" style={{ width: "100%", height: "100%", objectFit: "cover" }} />
        <div style={{ position: "absolute", inset: 0, background: "linear-gradient(180deg, rgba(34,26,20,.30) 0%, rgba(34,26,20,.20) 40%, rgba(34,26,20,.72) 100%)" }} />
      </div>)}
      <div className="container home-hero-inner">
        <div>
          <div className="eyebrow" style={{ color: "#fff", opacity: .85 }}>NEW TAIPEI CITY · FOOD MAP</div>
          <h1 className="h1 serif home-hero-title">探索新北的好味道</h1>
        </div>
        <div style={{ width: "100%", maxWidth: 560 }}><SearchBar /></div>
        {photos.length > 1 && <div className="row gap6">{photos.map((_, i) => <span key={i} onClick={() => setIdx(i)} style={{ width: i === idx ? 22 : 8, height: 8, borderRadius: 99, background: i === idx ? "#fff" : "rgba(255,255,255,.5)", cursor: "pointer", transition: "all .3s" }} />)}</div>}
      </div>
    </section>

    {/* categories */}
    <section className="container" style={{ paddingTop: 26 }}>
      <div className="row between center" style={{ marginBottom: 14 }}><div className="h2">探索分類</div><a className="small" style={{ color: "var(--brand)", fontWeight: 700, cursor: "pointer" }} onClick={() => navigate("#/explore")}>看全部 →</a></div>
      <div className="row gap8 wrap">
        {(tagsRes.data ? tagsRes.data.tags : []).map(t => <span key={t.tag_id} className="chip" onClick={() => navigate("#/explore?tag=" + t.tag_id)}>{t.tag_name}</span>)}
      </div>
    </section>

    {/* recommendations */}
    <section className="container section">
      <div className="row between center" style={{ marginBottom: 16 }}>
        <div><div className="eyebrow">為你推薦</div><div className="h2" style={{ marginTop: 2 }}>本週高分精選</div></div>
        <button className="btn btn-outline btn-sm" onClick={() => navigate("#/explore")}>探索更多</button>
      </div>
      {!recs ? <div className="rec-grid">{[0, 1, 2].map(i => <div key={i} className="card"><div className="skel" style={{ height: 150 }} /><div style={{ padding: 14 }}><div className="skel" style={{ height: 18, width: "60%" }} /><div className="skel" style={{ height: 12, marginTop: 10 }} /><div className="skel" style={{ height: 12, width: "80%", marginTop: 6 }} /></div></div>)}</div>
        : <div className="rec-grid">{recs.map(r => <RestaurantCard key={r.restaurant_id} r={r} onFav={me ? onFav : null} />)}</div>}
    </section>
  </div>;
}
window.PageIndex = PageIndex;
