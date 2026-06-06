/* =====================================================================
   ui.jsx — 共用元件與工具（hooks 在此宣告一次，其他檔案直接使用）
   ===================================================================== */
const { useState, useEffect, useRef, useMemo, useCallback } = React;

/* ---------- helpers ---------- */
const priceText = (lv) => lv ? "$".repeat(lv) : "—";
// photo render：優先用 sync_photos 下載到本機的 local_path，缺則 fallback 到外部 url
const photoSrc = (p) => (p && (p.local_path || p.url)) || null;
const photoFallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='400' viewBox='0 0 640 400'%3E%3Crect width='640' height='400' fill='%23FAF6F1'/%3E%3Crect y='246' width='640' height='154' fill='%23FCEBE2'/%3E%3Ccircle cx='128' cy='96' r='48' fill='%23F4EEE7'/%3E%3Ccircle cx='526' cy='92' r='60' fill='%23F7DDD0'/%3E%3Cg transform='translate(220 72)' fill='none' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M48 132c0 54 28 84 64 84s64-30 64-84H48z' fill='%23fff' stroke='%23B23C18' stroke-width='8'/%3E%3Cpath d='M66 134h92' stroke='%23E1542A' stroke-width='10'/%3E%3Cpath d='M76 104c-16-18-12-38 4-56M112 100c-14-18-10-40 8-58M148 104c-14-16-12-36 2-50' stroke='%236E635A' stroke-width='8'/%3E%3C/g%3E%3Ctext x='320' y='342' text-anchor='middle' font-family='Noto Sans TC, Microsoft JhengHei, sans-serif' font-size='24' font-weight='700' fill='%23B23C18'%3E新北美食地圖%3C/text%3E%3C/svg%3E";
function fmtDate(s) { return (s || "").slice(0, 10); }
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
function reviewAnchorId(userId) { return "review-" + userId; }
function detailReviewRoute(restaurantId, userId) { return "#/detail?id=" + restaurantId + "#" + reviewAnchorId(userId); }
function googleMapsUrl(r) {
  if (!r) return "https://www.google.com/maps";
  if (r.google_place_id) return "https://www.google.com/maps/place/?q=place_id:" + encodeURIComponent(r.google_place_id);
  const q = r.latitude != null && r.longitude != null
    ? `${r.latitude},${r.longitude}`
    : [r.restaurant_name, r.address].filter(Boolean).join(" ");
  return "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(q);
}

/* ---------- router ---------- */
function parseRoute() {
  const h = location.hash.replace(/^#/, "") || "/";
  const hashAt = h.indexOf("#");
  const routeText = hashAt >= 0 ? h.slice(0, hashAt) : h;
  const anchor = hashAt >= 0 ? decodeURIComponent(h.slice(hashAt + 1)) : "";
  const [path, qs] = routeText.split("?");
  const query = {};
  new URLSearchParams(qs || "").forEach((v, k) => { query[k] = v; });
  return { path, query, anchor };
}
function navigate(to) { location.hash = to; }
function useRoute() {
  const [r, setR] = useState(parseRoute());
  useEffect(() => { const f = () => setR(parseRoute()); window.addEventListener("hashchange", f); return () => window.removeEventListener("hashchange", f); }, []);
  return r;
}

/* ---------- toast ---------- */
let _toastPush = null;
function toast(message, kind) { if (_toastPush) _toastPush(message, kind); }
function ToastHost() {
  const [items, setItems] = useState([]);
  useEffect(() => { _toastPush = (message, kind) => { const id = Date.now() + Math.random(); setItems(x => [...x, { id, message, kind }]); setTimeout(() => setItems(x => x.filter(i => i.id !== id)), 2600); }; }, []);
  return <div className="toast-wrap">{items.map(i => <div key={i.id} className={"toast " + (i.kind || "")}>{i.message}</div>)}</div>;
}

/* ---------- confirm dialog ---------- */
let _confirmOpen = null;
function confirmDialog(opts) { return new Promise(res => { _confirmOpen && _confirmOpen(opts, res); }); }
function ConfirmHost() {
  const [st, setSt] = useState(null);
  useEffect(() => { _confirmOpen = (opts, res) => setSt({ opts, res }); }, []);
  if (!st) return null;
  const close = (v) => { st.res(v); setSt(null); };
  const o = st.opts;
  return <div className="overlay" onClick={() => close(false)}>
    <div className="modal" style={{ maxWidth: 380 }} onClick={e => e.stopPropagation()}>
      <div className="modal-pad">
        <div className="h3" style={{ marginBottom: 8 }}>{o.title}</div>
        {o.body && <div className="ink2" style={{ marginBottom: 18, lineHeight: 1.6 }}>{o.body}</div>}
        <div className="row gap8" style={{ justifyContent: "flex-end" }}>
          <button className="btn btn-ghost" onClick={() => close(false)}>{o.cancel || "取消"}</button>
          <button className={"btn " + (o.danger ? "btn-primary" : "btn-primary")} style={o.danger ? { background: "#c83b30", boxShadow: "none" } : null} onClick={() => close(true)}>{o.ok || "確定"}</button>
        </div>
      </div>
    </div>
  </div>;
}

/* ---------- Modal ---------- */
function Modal({ title, onClose, children, width }) {
  useEffect(() => { const f = e => e.key === "Escape" && onClose(); window.addEventListener("keydown", f); return () => window.removeEventListener("keydown", f); }, [onClose]);
  return <div className="overlay" onClick={onClose}>
    <div className="modal" style={width ? { maxWidth: width } : null} onClick={e => e.stopPropagation()}>
      <div className="modal-pad">
        <div className="modal-head">
          <div className="h2">{title}</div>
          <button className="btn btn-ghost btn-icon" onClick={onClose}>✕</button>
        </div>
        {children}
      </div>
    </div>
  </div>;
}

/* ---------- atoms ---------- */
function Stars({ value = 0, size = 15 }) {
  const full = Math.round(value);
  return <span className="stars" style={{ fontSize: size }}>{[1, 2, 3, 4, 5].map(i => <span key={i} className={i <= full ? "" : "e"}>★</span>)}</span>;
}
function Avatar({ name = "?", size = 36, onClick, style }) {
  return <div className="avatar" onClick={onClick} style={{ width: size, height: size, fontSize: size * 0.42, cursor: onClick ? "pointer" : "default", ...style }}>{(name || "?").trim().charAt(0).toUpperCase()}</div>;
}
function Photo({ url, alt, className, style }) {
  const [err, setErr] = useState(false);
  const src = String(url || "").trim();
  return <div className={"ph-img " + (className || "")} style={style}>
    <img src={!err && src ? src : photoFallback} alt={alt || ""} loading="lazy" onError={() => setErr(true)} />
  </div>;
}
function Loading({ pad }) { return <div className="loading" style={pad ? { padding: pad } : null}><div className="spinner" /></div>; }
function Empty({ icon = "🍽", title, sub, action }) {
  return <div className="empty"><div className="big">{icon}</div><div className="h3" style={{ color: "var(--ink)" }}>{title}</div>{sub && <div className="small" style={{ marginTop: 4 }}>{sub}</div>}{action && <div style={{ marginTop: 14 }}>{action}</div>}</div>;
}
function OpenBadge({ open }) { return open ? <span className="badge badge-open"><span className="dot" />營業中</span> : <span className="badge badge-closed">已打烊</span>; }

/* ---------- data hook ---------- */
function useApi(fn, deps, opts) {
  const [state, setState] = useState({ loading: true, data: null, error: null });
  const optsRef = useRef(opts || {});
  useEffect(() => {
    let live = true; setState(s => ({ ...s, loading: true, error: null }));
    Promise.resolve(fn()).then(d => { if (live) setState({ loading: false, data: d, error: null }); })
      .catch(e => { if (live) setState({ loading: false, data: null, error: e }); });
    return () => { live = false; };
  }, deps || []);
  return state;
}

/* ---------- RestaurantCard ---------- */
function Tags({ items, max = 3 }) {
  const arr = Array.isArray(items) ? items : [];
  return <div className="row gap6 wrap">{arr.slice(0, max).map(t => <span key={t.tag_id} className="tag">{t.tag_name}</span>)}</div>;
}
function RestaurantCard({ r, variant = "grid", onFav, me }) {
  const go = () => navigate("#/detail?id=" + r.restaurant_id);
  const fav = (e) => { e.stopPropagation(); onFav && onFav(r); };
  const meta = <div className="row center gap6 small ink2 tnum">
    <span className="rating-num">{r.rating_avg ? r.rating_avg.toFixed(1) : "—"}</span>
    <Stars value={r.rating_avg} size={13} />
    <span className="rating-cnt">({r.rating_count})</span>
    {r.distance_m != null && <span>· {r.distance_m >= 1000 ? (r.distance_m / 1000).toFixed(1) + "km" : r.distance_m + "m"}</span>}
    <span>· {priceText(r.price_level)}</span>
  </div>;

  if (variant === "row" || variant === "mini") {
    const mini = variant === "mini";
    return <div className="card card-hover row" onClick={go} style={{ gap: 0 }}>
      <Photo url={r.main_photo_url} style={{ flex: `0 0 ${mini ? 92 : 130}px`, alignSelf: "stretch" }} />
      <div className="grow" style={{ padding: mini ? "10px 12px" : "13px 15px", display: "flex", flexDirection: "column", gap: 5 }}>
        <div className="row between center">
          <div className={mini ? "h3 clamp1" : "h3"} style={{ fontSize: mini ? 15 : 17 }}>{r.restaurant_name}</div>
          {onFav && <button className={"heart-btn" + (r.is_favorited ? " on" : "")} style={{ width: 30, height: 30, fontSize: 15, boxShadow: "none", background: "transparent" }} onClick={fav}>{r.is_favorited ? "♥" : "♡"}</button>}
        </div>
        {meta}
        <div className="row center gap6"><OpenBadge open={r.is_open_now} />{!mini && <span className="small ink2 clamp1">{r.district_name}</span>}</div>
        {!mini && <Tags items={r.tags} />}
      </div>
    </div>;
  }
  // grid
  return <div className="card card-hover" onClick={go}>
    <div style={{ position: "relative" }}>
      <Photo url={r.main_photo_url} style={{ height: 150 }} />
      <div style={{ position: "absolute", top: 10, left: 10 }}><OpenBadge open={r.is_open_now} /></div>
      {onFav && <button className={"heart-btn" + (r.is_favorited ? " on" : "")} style={{ position: "absolute", top: 8, right: 8 }} onClick={fav}>{r.is_favorited ? "♥" : "♡"}</button>}
    </div>
    <div style={{ padding: 14, display: "flex", flexDirection: "column", gap: 7 }}>
      <div className="h3 clamp1">{r.restaurant_name}</div>
      {meta}
      <div className="small ink2 clamp2" style={{ minHeight: 38, lineHeight: 1.5 }}>{r.description}</div>
      <Tags items={r.tags} />
    </div>
  </div>;
}

/* ---------- SearchBar ---------- */
function SearchBar({ initial = "", autoFocus, onKeywordClear }) {
  const [mode, setMode] = useState("keyword");
  const [val, setVal] = useState(initial);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const boxRef = useRef();
  const hist = useMemo(() => Store.searchHistory().slice(0, 5), [open]);

  useEffect(() => { setVal(initial); }, [initial]);
  useEffect(() => { const f = e => { if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false); }; document.addEventListener("mousedown", f); return () => document.removeEventListener("mousedown", f); }, []);

  const submitKeyword = (v) => {
    const q = (v ?? val).trim();
    if (!q) {
      setOpen(false);
      if (onKeywordClear) onKeywordClear();
      else if (parseRoute().path === "/explore") navigate("#/explore");
      return;
    }
    Store.pushSearch("keyword", q); setOpen(false); navigate("#/explore?keyword=" + encodeURIComponent(q));
  };
  const submitAddress = async () => {
    const addr = val.trim(); if (!addr) return;
    setBusy(true);
    try {
      let geo = Store.getGeocode(addr);
      if (!geo) { const d = await api("POST", "/api/geo/geocode", { address: addr }); geo = { lat: d.lat, lng: d.lng }; Store.setGeocode(addr, d.lat, d.lng); }
      Store.pushSearch("address", addr); setOpen(false);
      navigate(`#/explore?user_lat=${geo.lat.toFixed(5)}&user_lng=${geo.lng.toFixed(5)}&addr=${encodeURIComponent(addr)}`);
    } catch (e) { toast(e.message || "找不到地址", "err"); } finally { setBusy(false); }
  };

  return <div ref={boxRef} style={{ position: "relative", width: "100%" }}>
    <div className="searchbar">
      <div className="seg">
        <button className={mode === "keyword" ? "on" : ""} onClick={() => setMode("keyword")}>關鍵字</button>
        <button className={mode === "address" ? "on" : ""} onClick={() => setMode("address")}>地址</button>
      </div>
      <input className="search-input" autoFocus={autoFocus} value={val} placeholder={mode === "keyword" ? "搜尋餐廳、料理…" : "輸入地址，找附近餐廳"}
        onChange={e => { const next = e.target.value; setVal(next); setOpen(true); if (mode === "keyword" && !next.trim() && onKeywordClear) onKeywordClear(); }} onFocus={() => setOpen(true)}
        onKeyDown={e => { if (e.key === "Enter") { mode === "keyword" ? submitKeyword() : submitAddress(); } }} />
      <button className="btn btn-primary btn-sm" style={{ borderRadius: 999, minWidth: 44 }} disabled={busy} onClick={() => mode === "keyword" ? submitKeyword() : submitAddress()}>{busy ? "…" : "🔍"}</button>
    </div>
    {open && hist.length > 0 && <div className="ac-pop">
      {hist.map((h, i) => <div key={i} className="ac-item" onClick={() => { setVal(h.value); h.type === "keyword" ? submitKeyword(h.value) : (setMode("address")); }}>
        <span className="muted">{h.type === "keyword" ? "🔍" : "📍"}</span><span className="grow ellip">{h.value}</span><span className="tiny muted">最近</span>
      </div>)}
    </div>}
  </div>;
}

/* ---------- Navbar ---------- */
function Navbar({ me, onAuth }) {
  const [menu, setMenu] = useState(false);
  const ref = useRef();
  const route = useRoute();
  useEffect(() => { const f = e => { if (ref.current && !ref.current.contains(e.target)) setMenu(false); }; document.addEventListener("mousedown", f); return () => document.removeEventListener("mousedown", f); }, []);
  const logout = async () => { setMenu(false); try { await api("POST", "/api/auth/logout"); } catch (e) {} onAuth(); toast("已登出"); navigate("#/login"); };
  return <header className={"nav" + (route.path === "/explore" ? " nav-explore" : "")}>
    <div className="nav-inner">
      <div className="brand nav-brand" onClick={() => navigate("#/")}><span className="brand-mark">🍜</span><span>新北美食地圖</span></div>
      {route.path !== "/explore" && <div className="nav-search" style={{ maxWidth: 520, width: "100%", justifySelf: "center" }}><SearchBar /></div>}
      <div className="nav-avatar" ref={ref} style={{ position: "relative" }}>
        {me ? <Avatar name={me.username} onClick={() => setMenu(m => !m)} /> :
          <button className="btn btn-primary btn-sm" onClick={() => navigate("#/login")}>登入</button>}
        {menu && me && <div className="menu-pop">
          <div style={{ padding: "12px 14px", display: "flex", alignItems: "center", gap: 10 }}><Avatar name={me.username} size={34} /><div><div style={{ fontWeight: 700 }}>{me.username}</div>{me.is_admin ? <div className="tiny" style={{ color: "var(--brand)" }}>管理員</div> : null}</div></div>
          <div className="menu-sep" />
          <div className="menu-item" onClick={() => { setMenu(false); navigate("#/profile?id=" + me.user_id); }}>👤 個人頁</div>
          {me.is_admin ? <div className="menu-item" onClick={() => { setMenu(false); navigate("#/admin"); }}>🛠 管理後台</div> : null}
          <div className="menu-sep" />
          <div className="menu-item danger" onClick={logout}>↩ 登出</div>
        </div>}
      </div>
    </div>
  </header>;
}

Object.assign(window, { useState, useEffect, useRef, useMemo, useCallback, navigate, useRoute, parseRoute, toast, ToastHost, ConfirmHost, confirmDialog, Modal, Stars, Avatar, Photo, Loading, Empty, OpenBadge, useApi, RestaurantCard, SearchBar, Navbar, Tags, priceText, fmtDate, debounce, reviewAnchorId, detailReviewRoute, googleMapsUrl });
