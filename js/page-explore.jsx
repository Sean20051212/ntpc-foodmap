/* 探索頁 — 搜尋/列表 + 地圖 合併。篩選(可收合)、小卡清單、Leaflet 地圖、輪盤、手機清單/地圖切換 */
const DIST_OPTS = [{ v: 0, label: "不限" }, { v: 100, label: "100m" }, { v: 300, label: "300m" }, { v: 500, label: "500m" }, { v: 800, label: "800m" }, { v: 1000, label: "1km" }];
const RATE_OPTS = [{ v: 0, label: "不限" }, { v: 1, label: "1★" }, { v: 2, label: "2★" }, { v: 3, label: "3★" }, { v: 4, label: "4★" }];
const SORTS = [{ v: "rating_desc", label: "評分最高" }, { v: "distance_asc", label: "距離最近" }, { v: "name_asc", label: "名稱" }];
const BANCHIAO = { lat: 25.0095, lng: 121.4626 };

function Seg2({ options, value, onChange }) {
  return <div className="seg2">{options.map(o => <button key={o.v} className={value === o.v ? "on" : ""} onClick={() => onChange(o.v)}>{o.label}</button>)}</div>;
}

function FilterPanel({ dicts, f, set, count, onClear, onDistrictPick }) {
  if (!dicts) return <Loading pad={20} />;
  const toggle = (key, id) => {
    const cur = f[key];
    const willSelect = !cur.includes(id);
    set(key, willSelect ? [...cur, id] : cur.filter(x => x !== id));
    if (key === "districts" && willSelect && onDistrictPick) {
      const district = dicts.districts.find(d => d.zipcode === id);
      if (district) onDistrictPick(district);
    }
  };
  return <div>
    <div className="filter-group">
      <h4>區域 {f.districts.length > 0 && <span className="v">已選 {f.districts.length}</span>}</h4>
      <div className="row gap6 wrap">
        {dicts.districts.map(d => <span key={d.zipcode} className={"chip" + (f.districts.includes(d.zipcode) ? " on" : "")} onClick={() => toggle("districts", d.zipcode)}>{d.district_name}</span>)}
      </div>
    </div>
    <div className="filter-group">
      <h4>分類 {f.tags.length > 0 && <span className="v">已選 {f.tags.length}</span>}</h4>
      <div className="row gap6 wrap">
        {dicts.tags.map(t => <span key={t.tag_id} className={"chip" + (f.tags.includes(t.tag_id) ? " on" : "")} onClick={() => toggle("tags", t.tag_id)}>{t.tag_name}</span>)}
      </div>
    </div>
    <div className="filter-group">
      <h4>距離 <span className="v">{f.maxDist ? "≤" + (f.maxDist >= 1000 ? "1km" : f.maxDist + "m") : ""}</span></h4>
      <Seg2 options={DIST_OPTS} value={f.maxDist} onChange={v => set("maxDist", v)} />
    </div>
    <div className="filter-group">
      <h4>評分 <span className="v">{f.minRating ? "≥" + f.minRating + "★" : ""}</span></h4>
      <Seg2 options={RATE_OPTS} value={f.minRating} onChange={v => set("minRating", v)} />
    </div>
    <div className="count-pill">符合條件 <b>{count == null ? "…" : count}</b> 家</div>
    <button className="btn btn-ghost btn-sm btn-block" style={{ marginTop: 10 }} onClick={onClear}>清除全部條件</button>
  </div>;
}

function ActiveFilters({ dicts, f, appliedBbox, count, onRemove, onClear }) {
  if (!dicts) return null;
  const labels = [];
  const districtMap = new Map(dicts.districts.map(d => [d.zipcode, d.district_name]));
  const tagMap = new Map(dicts.tags.map(t => [t.tag_id, t.tag_name]));
  if (f.keyword.trim()) labels.push({ key: "keyword", label: f.keyword.trim() });
  f.districts.forEach(id => labels.push({ key: "district:" + id, label: districtMap.get(id) || id, onRemove: () => onRemove("district", id) }));
  f.tags.forEach(id => labels.push({ key: "tag:" + id, label: tagMap.get(id) || id, onRemove: () => onRemove("tag", id) }));
  if (f.minRating) labels.push({ key: "minRating", label: "評分 ≥ " + f.minRating + "★" });
  if (f.maxDist) labels.push({ key: "maxDist", label: "距離 ≤ " + (f.maxDist >= 1000 ? "1km" : f.maxDist + "m") });
  if (appliedBbox) labels.push({ key: "bbox", label: "目前地圖區域" });
  if (!labels.length) return null;
  return <div className="active-filters">
    <div className="active-filter-row">
      <span className="active-filter-title">目前篩選</span>
      {labels.map(item => <button key={item.key} className="filter-token" onClick={item.onRemove || (() => onRemove(item.key))}>
        <span>{item.label}</span><b>×</b>
      </button>)}
      <button className="btn btn-ghost btn-sm reset-filters" onClick={onClear}>重設篩選</button>
    </div>
    <div className="tiny muted">符合條件 {count == null ? "…" : count} 家，點擊條件可立即解除。</div>
  </div>;
}

function ExploreMap({ restaurants, center, onFav, onIdle }) {
  const elRef = useRef(), mapRef = useRef(), layerRef = useRef(), favRef = useRef(onFav);
  favRef.current = onFav;
  const invalidateMap = useCallback(() => {
    const el = elRef.current, map = mapRef.current;
    if (!el || !map) return;
    const rect = el.getBoundingClientRect();
    if (rect.width > 0 && rect.height > 0) {
      requestAnimationFrame(() => map.invalidateSize({ pan: false }));
    }
  }, []);
  useEffect(() => {
    const map = L.map(elRef.current, { zoomControl: true, attributionControl: false }).setView([center.lat, center.lng], 14);
    L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", { maxZoom: 20, subdomains: "abcd" }).addTo(map);
    layerRef.current = L.layerGroup().addTo(map);
    map.on("moveend", () => { const b = map.getBounds(); onIdle([b.getSouth(), b.getWest(), b.getNorth(), b.getEast()]); });
    mapRef.current = map;
    const t = setTimeout(invalidateMap, 120);
    return () => { clearTimeout(t); map.remove(); mapRef.current = null; };
  }, []);
  useEffect(() => {
    const el = elRef.current;
    if (!el) return;
    const onResize = () => invalidateMap();
    const ro = typeof ResizeObserver !== "undefined" ? new ResizeObserver(onResize) : null;
    if (ro) ro.observe(el);
    window.addEventListener("resize", onResize);
    const t = setTimeout(onResize, 250);
    return () => {
      clearTimeout(t);
      if (ro) ro.disconnect();
      window.removeEventListener("resize", onResize);
    };
  }, [invalidateMap]);
  useEffect(() => { if (mapRef.current) mapRef.current.setView([center.lat, center.lng], mapRef.current.getZoom()); }, [center.lat, center.lng]);
  useEffect(() => {
    const lg = layerRef.current; if (!lg) return; lg.clearLayers();
    restaurants.forEach(r => {
      const icon = L.divIcon({ className: "", iconSize: [48, 34], iconAnchor: [24, 34], html: `<div class="map-pin ${r.is_favorited ? "fav" : ""}">${r.is_favorited ? '<span class="pin-h">♥</span>' : ""}<span>${r.rating_avg ? r.rating_avg.toFixed(1) : "·"}</span></div>` });
      const m = L.marker([r.latitude, r.longitude], { icon }).addTo(lg);
      m.bindPopup(() => buildPopup(r, favRef.current), { closeButton: true, offset: [0, -28] });
    });
  }, [restaurants]);
  useEffect(() => { invalidateMap(); }, [restaurants, invalidateMap]);
  return <div ref={elRef} className="map-canvas" />;
}
function buildPopup(r, onFav) {
  const div = document.createElement("div"); div.className = "map-info";
  div.innerHTML = `<div class="mi-photo" style="background-image:url('${r.main_photo_url || ""}')"></div>
    <div class="mi-body"><div class="mi-name">${r.restaurant_name}</div>
    <div class="mi-meta">★ ${r.rating_avg ? r.rating_avg.toFixed(1) : "—"} · ${r.is_open_now ? "營業中" : "已打烊"} · ${priceText(r.price_level)}</div>
    <div class="mi-desc">${(r.description || "").slice(0, 30)}…</div>
    <div class="mi-actions"><button class="mi-fav ${r.is_favorited ? "on" : ""}">${r.is_favorited ? "♥ 已收藏" : "♡ 收藏"}</button><button class="mi-detail">看詳情</button></div></div>`;
  div.querySelector(".mi-fav").onclick = () => onFav(r);
  div.querySelector(".mi-detail").onclick = () => navigate("#/detail?id=" + r.restaurant_id);
  return div;
}

function WheelDock({ params, onClose }) {
  const SPIN_MS = 1250;
  const [pool, setPool] = useState(null), [candidates, setCandidates] = useState([]), [result, setResult] = useState(null), [spinning, setSpinning] = useState(false), [exhausted, setExhausted] = useState(false), [spinId, setSpinId] = useState(0);
  const loadPool = async () => {
    const d = await api("GET", "/api/restaurants/wheel_pool", params);
    setPool(d.restaurant_ids.length);
    setCandidates((d.candidates || []).slice(0, 8));
  };
  useEffect(() => { loadPool().catch(() => { setPool(0); setCandidates([]); }); }, []);
  function truncateLabel(text) {
    return text.length > 6
      ? text.slice(0, 6) + "..."
      : text;
  };

  const draw = async () => {
    if (spinning || pool === 0) return;
    const startedAt = performance.now();
    setSpinId(id => id + 1);
    setSpinning(true); setExhausted(false); setResult(null);
    try {
      const d = await api("POST", "/api/restaurants/wheel_draw", params);
      const delay = Math.max(0, SPIN_MS - (performance.now() - startedAt));
      setTimeout(() => {
        setSpinning(false);
        if (d.exhausted) { setExhausted(true); setResult(null); }
        else setResult(d.restaurant);
      }, delay);
    } catch (e) { setSpinning(false); toast(e.message, "err"); }
  };
  const reset = async () => { await api("POST", "/api/restaurants/wheel_reset", params); setExhausted(false); setResult(null); await loadPool(); toast("已重設輪盤"); };
  return <div className="wheel-dock">
    <div className="row between center" style={{ padding: "16px 18px", borderBottom: "1px solid var(--line)" }}>
      <div className="h3">🎯 吃什麼輪盤</div><button className="btn btn-ghost btn-icon" onClick={onClose}>✕</button>
    </div>
    <div style={{ padding: 18, overflow: "auto", flex: 1 }}>
      <div className="wheel-stage">
        <div className="wheel-ptr">▼</div>
        <div key={spinId} className={"wheel-spin" + (spinning ? " spinning" : "")}>
          {candidates.length > 0 ? candidates.map((r, i) => {
            const deg = i * 45 + 22.5;
            const label = truncateLabel(String(r.restaurant_name || "候選餐廳").trim());
            return <div className="wheel-label" style={{ transform: `translate(-50%, -50%) rotate(${deg}deg) translateY(calc(-1 * var(--wheel-label-radius)))` }} key={r.restaurant_id || i}>
              <span title={r.restaurant_name}><b>{label}</b></span>
            </div>;
          }) : <span>?</span>}
        </div>
      </div>
      {exhausted ? <Empty icon="🪹" title="候選都抽完了！" sub="重設後可重新抽過" action={<button className="btn btn-primary" onClick={reset}>重設輪盤</button>} />
        : result ? <div className="card row" style={{ marginBottom: 14, cursor: "pointer" }} onClick={() => navigate("#/detail?id=" + result.restaurant_id)}>
          <Photo url={result.photos?.[0]?.url} alt={result.restaurant_name} style={{ flex: "0 0 84px", alignSelf: "stretch" }} />
          <div style={{ padding: "10px 12px" }}><div className="h3">{result.restaurant_name}</div>
            <div className="row center gap6 small ink2"><span className="rating-num">{result.rating_avg.toFixed(1)}</span><Stars value={result.rating_avg} size={12} /><span>· {result.district_name}</span></div>
            {result.distance_m != null && <div className="tiny muted">約 {result.distance_m >= 1000 ? (result.distance_m / 1000).toFixed(1) + "km" : result.distance_m + "m"}</div>}</div>
        </div>
          : <div className="count-pill" style={{ marginBottom: 14 }}>依目前篩選 <b>{pool == null ? "…" : pool}</b> 家候選</div>}
      {!exhausted && <div className="col gap8">
        <button className="btn btn-primary btn-lg btn-block" disabled={spinning || pool === 0} onClick={draw}>{spinning ? "抽選中…" : result ? "再抽一次" : "開始抽 🎲"}</button>
        {result && <button className="btn btn-outline btn-block" onClick={() => navigate("#/detail?id=" + result.restaurant_id)}>看餐廳詳情</button>}
        <div className="tiny muted" style={{ textAlign: "center", lineHeight: 1.6 }}>抽過的不會再抽到<br /><span onClick={reset} style={{ color: "var(--brand)", cursor: "pointer", fontWeight: 700 }}>重設輪盤</span></div>
      </div>}
    </div>
  </div>;
}

function PageExplore({ me }) {
  const route = useRoute();
  const q = route.query;
  const [dicts, setDicts] = useState(null);
  const [loc, setLoc] = useState(q.user_lat ? { lat: +q.user_lat, lng: +q.user_lng } : BANCHIAO);
  const [locInfo, setLocInfo] = useState(null);
  const [f, setF] = useState({ districts: [], tags: q.tag ? [+q.tag] : [], minRating: 0, maxDist: 0, sort: "rating_desc", keyword: q.keyword || "" });
  const [count, setCount] = useState(null);
  const [list, setList] = useState([]);
  const [listLoading, setListLoading] = useState(true);
  const [collapsed, setCollapsed] = useState(false);
  const [mtab, setMtab] = useState("list");
  const [wheel, setWheel] = useState(false);
  const [mFilter, setMFilter] = useState(false);
  const [pendBbox, setPendBbox] = useState(null);
  const [appliedBbox, setAppliedBbox] = useState(null);
  const [showResq, setShowResq] = useState(false);
  const ready = useRef(false);
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));

  // 初始化：dict + locate
  useEffect(() => {
    (async () => {
      try {
        const [d, t] = await Promise.all([api("GET", "/api/dicts/districts"), api("GET", "/api/dicts/tags")]);
        setDicts({ districts: d.districts, tags: t.tags });
        const info = await api("POST", "/api/geo/locate", { lat: loc.lat, lng: loc.lng });
        setLocInfo(info);
        if (info.in_ntpc && !q.tag && !q.keyword) {
          setF(s => ({ ...s, districts: [info.district.zipcode, ...info.adjacent.map(a => a.zipcode)] }));
        }
      } catch (e) { toast(e.message, "err"); }
      ready.current = true; reload();
    })();
  }, []);

  const params = (extra) => ({
    district: f.districts, tag: f.tags,
    min_rating: f.minRating || undefined, max_distance_m: f.maxDist || undefined,
    user_lat: loc.lat, user_lng: loc.lng, keyword: f.keyword || undefined,
    sort: f.sort, bbox: appliedBbox ? appliedBbox.join(",") : undefined, ...extra
  });

  // 從導覽列/首頁帶 keyword 或 地址座標進來時同步
  useEffect(() => { if (ready.current) setF(s => ({ ...s, keyword: q.keyword || "" })); }, [q.keyword]);
  useEffect(() => { if (q.user_lat) { setLoc({ lat: +q.user_lat, lng: +q.user_lng }); setAppliedBbox(null); } }, [q.user_lat, q.user_lng]);

  const reload = async () => {
    setListLoading(true);
    try {
      const cnt = await api("GET", "/api/restaurants/count", params());
      setCount(cnt.total);
      let data;
      if (locInfo && !locInfo.in_ntpc) data = await api("GET", "/api/restaurants/nearby_ntpc", { lat: loc.lat, lng: loc.lng, limit: 20 });
      else data = await api("GET", "/api/restaurants/list", params({ limit: 60 }));
      setList(data.restaurants);
    } catch (e) { toast(e.message, "err"); } finally { setListLoading(false); }
  };

  // 條件變動 → debounce 重新查詢（count 即時 + 清單）
  useEffect(() => {
    if (!ready.current) return;
    const t = setTimeout(reload, 320);
    return () => clearTimeout(t);
  }, [f.districts, f.tags, f.minRating, f.maxDist, f.sort, f.keyword, appliedBbox, loc.lat, loc.lng]);

  const onFav = (r) => window.favToggle(r, setList);
  const onIdle = (bbox) => { setPendBbox(bbox); setShowResq(true); };
  const reSearch = () => { setAppliedBbox(pendBbox); setShowResq(false); requestAnimationFrame(() => window.dispatchEvent(new Event("resize"))); };
  const clearKeywordRoute = () => {
    if (route.query.keyword != null) navigate("#/explore");
  };
  const clearAll = () => {
    setF({ districts: [], tags: [], minRating: 0, maxDist: 0, sort: f.sort, keyword: "" });
    setAppliedBbox(null);
    setPendBbox(null);
    setShowResq(false);
    clearKeywordRoute();
  };
  const removeFilter = (key, value) => {
    if (key === "district") set("districts", f.districts.filter(id => id !== value));
    else if (key === "tag") set("tags", f.tags.filter(id => id !== value));
    else if (key === "keyword") { set("keyword", ""); clearKeywordRoute(); }
    else if (key === "minRating") set("minRating", 0);
    else if (key === "maxDist") set("maxDist", 0);
    else if (key === "bbox") { setAppliedBbox(null); setPendBbox(null); setShowResq(false); }
  };
  const focusDistrict = (district) => {
    const lat = Number(district.center_latitude);
    const lng = Number(district.center_longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    setLoc({ lat, lng });
    setAppliedBbox(null);
    setPendBbox(null);
    setShowResq(false);
  };
  const activeCount = f.districts.length + f.tags.length + (f.minRating ? 1 : 0) + (f.maxDist ? 1 : 0) + (f.keyword.trim() ? 1 : 0) + (appliedBbox ? 1 : 0);

  return <div className="explore-root">
    <div className="explore-toolbar">
      <button className="btn btn-ghost btn-sm hide-m" onClick={() => setCollapsed(c => !c)} title="收合篩選">{collapsed ? "☰ 篩選" : "« 收合"}</button>
      <div className="grow" style={{ maxWidth: 460 }}><SearchBar initial={f.keyword} onKeywordClear={() => removeFilter("keyword")} /></div>
      <select className="select" style={{ width: "auto", padding: "8px 12px" }} value={f.sort} onChange={e => set("sort", e.target.value)}>
        {SORTS.map(s => <option key={s.v} value={s.v}>{s.label}</option>)}
      </select>
      <button className={"btn btn-sm wheel-toggle " + (wheel ? "btn-primary" : "btn-outline")} onClick={() => setWheel(w => !w)}>🎯 輪盤</button>
      <button className="btn btn-outline btn-sm show-m" onClick={() => setMFilter(true)}>⚙ 篩選{activeCount ? " (" + activeCount + ")" : ""}</button>
    </div>

    <ActiveFilters dicts={dicts} f={f} appliedBbox={appliedBbox} count={count} onRemove={removeFilter} onClear={clearAll} />

    {/* mobile list/map toggle */}
    <div className="exp-mtoggle">
      <div className="seg" style={{ width: "100%", maxWidth: 300 }}>
        <button style={{ flex: 1 }} className={mtab === "list" ? "on" : ""} onClick={() => setMtab("list")}>☰ 清單</button>
        <button style={{ flex: 1 }} className={mtab === "map" ? "on" : ""} onClick={() => setMtab("map")}>🗺 地圖</button>
      </div>
    </div>

    <div className={"explore-body mtab-" + mtab}>
      <div className={"exp-filter" + (collapsed ? " collapsed" : "")}><FilterPanel dicts={dicts} f={f} set={set} count={count} onClear={clearAll} onDistrictPick={focusDistrict} /></div>

      <div className="exp-list">
        {locInfo && !locInfo.in_ntpc && <div style={{ margin: 14, padding: "10px 12px", background: "var(--brand-tint)", borderRadius: 12, fontSize: 13, color: "var(--brand-deep)" }}>你目前不在新北市，以下顯示最近的 20 家新北餐廳。</div>}
        <div className="explore-sub" style={{ borderTop: "none" }}>
          <div className="small ink2"><b className="ink2" style={{ color: "var(--ink)" }}>{count == null ? "…" : count}</b> 家{locInfo && locInfo.in_ntpc ? " · " + (locInfo.district ? locInfo.district.district_name : "") + " 周邊" : ""}</div>
        </div>
        <div className="exp-list-inner">
          {listLoading ? <Loading pad={30} /> : list.length === 0 ? <Empty title="沒有符合的餐廳" sub="試著放寬篩選條件" />
            : list.map(r => <RestaurantCard key={r.restaurant_id} r={r} variant="mini" onFav={onFav} me={me} />)}
        </div>
      </div>

      <div className="exp-map">
        {dicts && <ExploreMap restaurants={list} center={loc} onFav={onFav} onIdle={onIdle} />}
        {showResq && <button className="btn btn-primary btn-sm resq-btn" onClick={reSearch}>↻ 搜尋此區域</button>}
        {!appliedBbox && <div className="geo-note">📍 預設位置：{locInfo && locInfo.district ? locInfo.district.district_name : "板橋區"}<br />可用上方地址搜尋變更</div>}
        {wheel && <WheelDock params={params()} onClose={() => setWheel(false)} />}
      </div>
    </div>

    {mFilter && <Modal title="篩選" onClose={() => setMFilter(false)}>
      <FilterPanel dicts={dicts} f={f} set={set} count={count} onClear={clearAll} onDistrictPick={focusDistrict} />
      <button className="btn btn-primary btn-block" style={{ marginTop: 8 }} onClick={() => setMFilter(false)}>查看 {count} 家結果</button>
    </Modal>}
  </div>;
}
window.PageExplore = PageExplore;
