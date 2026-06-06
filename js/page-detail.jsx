/* 詳情頁 — 全幅 hero gallery + 完整資訊 + 營業時間 + 評論(upsert) + 評論列表 */

function Gallery({ photos }) {
  const [i, setI] = useState(0);
  const one = photos.length <= 1;
  const cur = photos[i] || {};
  return <div className="gallery">
    {cur.url && <img className="gallery-main" src={cur.url} alt="" />}
    {!one && <>
      <div className="gal-count">{i + 1} / {photos.length}</div>
      <button className="gal-arrow l" onClick={() => setI(x => (x - 1 + photos.length) % photos.length)}>‹</button>
      <button className="gal-arrow r" onClick={() => setI(x => (x + 1) % photos.length)}>›</button>
      <div className="gal-thumbs">{photos.slice(0, 6).map((p, k) => <img key={k} className={"gal-thumb" + (k === i ? " on" : "")} src={p.url} onClick={() => setI(k)} alt="" />)}</div>
    </>}
  </div>;
}

function ReviewForm({ rid, me, existing, onSaved }) {
  const [rating, setRating] = useState(existing ? existing.rating : 0);
  const [hover, setHover] = useState(0);
  const [comment, setComment] = useState(existing ? existing.comment : "");
  const [open, setOpen] = useState(!!existing);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setRating(existing ? existing.rating : 0);
    setComment(existing ? existing.comment : "");
    setOpen(!!existing);
    setHover(0);
  }, [rid, existing]);

  const remove = async () => {
    if (!existing) return;
    if (!(await confirmDialog({ title: "刪除評論？", body: "刪除後會同步更新這家餐廳的評分。", ok: "刪除", danger: true }))) return;
    setBusy(true);
    try {
      await api("DELETE", "/api/reviews/delete", { restaurant_id: rid });
      toast("評論已刪除", "ok");
      setOpen(false);
      onSaved();
    } catch (e) {
      toast(e.message, "err");
    } finally {
      setBusy(false);
    }
  };

  if (!me) return <div className="card" style={{ padding: 18, textAlign: "center" }}>
    <div className="ink2" style={{ marginBottom: 10 }}>請先登入才能留下評論</div>
    <button className="btn btn-primary btn-sm" onClick={() => navigate("#/login")}>前往登入</button>
  </div>;
  if (!open) return <button className="btn btn-outline btn-block" onClick={() => setOpen(true)}>✍ 點此寫評論</button>;
  const submit = async () => {
    if (!rating) return toast("請先選擇星等", "err");
    setBusy(true);
    try { await api("POST", "/api/reviews/upsert", { restaurant_id: rid, rating, comment }); toast(existing ? "評論已更新" : "評論已送出", "ok"); onSaved(); }
    catch (e) { toast(e.message, "err"); } finally { setBusy(false); }
  };
  return <div className="card" style={{ padding: 18 }}>
    <div className="row center gap10" style={{ marginBottom: 12 }}>
      <span className="label">評分</span>
      <div className="star-pick" onMouseLeave={() => setHover(0)}>
        {[1, 2, 3, 4, 5].map(n => <span key={n} className={(hover || rating) >= n ? "on" : ""} onMouseEnter={() => setHover(n)} onClick={() => setRating(n)}>★</span>)}
      </div>
    </div>
    <textarea className="textarea" maxLength={1000} placeholder="分享你的用餐心得…（最多 1000 字）" value={comment} onChange={e => setComment(e.target.value)} />
    <div className="row between center" style={{ marginTop: 12 }}>
      <span className="tiny muted">{comment.length}/1000</span>
      <div className="row gap8">
        {existing && <button className="btn btn-ghost" style={{ color: "#c83b30" }} disabled={busy} onClick={remove}>刪除評論</button>}
        <button className="btn btn-primary" disabled={busy} onClick={submit}>{busy ? "處理中…" : existing ? "更新評論" : "送出評論"}</button>
      </div>
    </div>
  </div>;
}

function PageDetail({ me }) {
  const route = useRoute();
  const id = +route.query.id;
  const [d, setD] = useState(null);
  const [err, setErr] = useState(null);
  const [reviews, setReviews] = useState(null);
  const [daysDict, setDaysDict] = useState([]);

  const loadDetail = () => api("GET", "/api/restaurants/detail", { id }).then(r => setD(r.restaurant)).catch(e => setErr(e));
  const loadReviews = () => api("GET", "/api/reviews/by_restaurant", { restaurant_id: id, limit: 20, offset: 0 }).then(r => setReviews(r)).catch(() => setReviews({ total: 0, reviews: [] }));
  useEffect(() => { api("GET", "/api/dicts/days").then(r => setDaysDict(r.days)).catch(() => setDaysDict([])); }, []);
  useEffect(() => { setD(null); setReviews(null); setErr(null); loadDetail(); loadReviews(); if (!route.anchor) window.scrollTo(0, 0); }, [id]);
  useEffect(() => {
    if (!reviews || !route.anchor) return;
    const target = document.getElementById(route.anchor);
    if (target) setTimeout(() => target.scrollIntoView({ block: "center" }), 0);
  }, [reviews, route.anchor]);

  if (err) return <div className="container section"><Empty icon="🔍" title="找不到這家餐廳" action={<button className="btn btn-primary" onClick={() => navigate("#/explore")}>回探索</button>} /></div>;
  if (!d) return <Loading pad={80} />;

  const toggleFav = async () => {
    try { const res = await api("POST", "/api/favorites/toggle", { restaurant_id: id }); setD({ ...d, is_favorited: res.is_favorited }); toast(res.is_favorited ? "已加入收藏" : "已取消收藏", res.is_favorited ? "ok" : ""); }
    catch (e) { if (e.code === "unauthenticated") { toast("請先登入", "err"); navigate("#/login"); } else toast(e.message, "err"); }
  };
  const onSaved = () => { loadDetail(); loadReviews(); };

  return <div>
    <Gallery photos={d.photos.length ? d.photos : [{ url: null }]} />
    <div className="detail-wrap">
      <button className="btn btn-ghost btn-sm" style={{ marginBottom: 14, paddingLeft: 0 }} onClick={() => history.length > 1 ? history.back() : navigate("#/explore")}>‹ 返回</button>
      <div className="row between" style={{ alignItems: "flex-start", gap: 16 }}>
        <div className="grow">
          <h1 className="h1 serif">{d.restaurant_name}</h1>
          <div className="row center gap8 wrap" style={{ margin: "10px 0" }}>
            <span className="rating-num" style={{ fontSize: 18 }}>{d.rating_avg ? d.rating_avg.toFixed(1) : "—"}</span>
            <Stars value={d.rating_avg} size={18} />
            <span className="rating-cnt">{d.rating_count} 則評論</span>
            <span className="ink2">· {priceText(d.price_level)} · {d.district_name}</span>
            <OpenBadge open={d.is_open_now} />
          </div>
          <div className="row gap6 wrap" style={{ marginBottom: 12 }}>
            {d.tags.map(t => <span key={t.tag_id} className="tag">{t.tag_name}</span>)}
            {d.phones.map((p, i) => <span key={i} className="tag">📞 {p}</span>)}
          </div>
          <p className="ink2" style={{ lineHeight: 1.7, maxWidth: 640 }}>{d.description}</p>
          <a className="btn btn-outline btn-sm" href={googleMapsUrl(d)} target="_blank" rel="noopener noreferrer" style={{ marginTop: 10, marginBottom: 6 }}>Google Maps</a>
          <div className="small muted" style={{ marginTop: 6 }}>📍 {d.address}</div>
        </div>
        <button className={"btn " + (d.is_favorited ? "btn-primary" : "btn-outline")} onClick={toggleFav}>{d.is_favorited ? "♥ 已收藏" : "♡ 收藏"}</button>
      </div>

      <div className="detail-cols">
        <div>
          <div className="h3" style={{ marginBottom: 10 }}>營業時間</div>
          <div className="hours-table">
            {[1, 2, 3, 4, 5, 6, 0].map(day => {
              const rows = d.opentime_regular.filter(o => o.day === day);
              const today = new Date().getDay() === day;
              const dictEntry = daysDict.find(x => x.day_id === day);
              const dayLabel = dictEntry ? dictEntry.day_name_zh : (rows[0] && rows[0].day_name_zh) || "";
              return <div key={day} className={"hours-row" + (today ? " today" : "")}>
                <span className="d">{dayLabel}{today ? " · 今天" : ""}</span>
                <span className="ink2 tnum">{rows.length ? rows.map(r => r.start_time.slice(0, 5) + "–" + r.end_time.slice(0, 5)).join("、") : "公休"}</span>
              </div>;
            })}
          </div>
          {d.opentime_special.length > 0 && <div style={{ marginTop: 12 }}>
            <div className="small label" style={{ marginBottom: 6 }}>特殊營業資訊</div>
            <div className="row gap6 wrap">{d.opentime_special.map((s, i) => <span key={i} className="tag" style={{ background: "var(--brand-tint)", color: "var(--brand-deep)" }}>{s}</span>)}</div>
          </div>}
        </div>
        <div>
          <div className="h3" style={{ marginBottom: 10 }}>{d.user_review ? "你的評論" : "寫評論"}</div>
          <ReviewForm rid={id} me={me} existing={d.user_review} onSaved={onSaved} />
        </div>
      </div>

      <div style={{ marginTop: 30 }}>
        <div className="h2" style={{ marginBottom: 14 }}>全部評論 {reviews ? <span className="ink2" style={{ fontWeight: 600 }}>({reviews.total})</span> : ""}</div>
        {!reviews ? <Loading /> : reviews.reviews.length === 0 ? <Empty icon="💬" title="還沒有評論" sub="成為第一個分享心得的人" />
          : reviews.reviews.map((rv, i) => <div key={i} id={reviewAnchorId(rv.user_id)} className="review-item">
            <div className="row between center">
              <div className="row center gap10" style={{ cursor: "pointer" }} onClick={() => navigate("#/profile?id=" + rv.user_id)}>
                <Avatar name={rv.username} size={38} />
                <div><div style={{ fontWeight: 700 }}>{rv.username}</div><div className="tiny muted">{rv.reviewer_total_reviews} 則評論 · {fmtDate(rv.created_at)}</div></div>
              </div>
              <Stars value={rv.rating} size={15} />
            </div>
            {rv.comment && <p className="ink2" style={{ margin: "10px 0 0", lineHeight: 1.65 }}>{rv.comment}</p>}
          </div>)}
      </div>
    </div>
  </div>;
}
window.PageDetail = PageDetail;
