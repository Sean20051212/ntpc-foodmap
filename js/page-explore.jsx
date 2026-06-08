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
  const SPIN_MS = 2600;
  const SLICE_COUNT = 8;
  const SLICE_COLORS = ["#F6C0C8", "#F8E5A1", "#C9E4A6", "#A8D8E0", "#B7C8E8", "#C9B6DD", "#D6A8C8", "#F2C8A7"];
  const [pool, setPool] = useState(null), [candidates, setCandidates] = useState([]), [result, setResult] = useState(null), [spinning, setSpinning] = useState(false), [exhausted, setExhausted] = useState(false);
  const [rotation, setRotation] = useState(0);
  const drawnRef = useRef([]);
  const loadPool = async () => {
    drawnRef.current = [];
    const d = await api("GET", "/api/restaurants/wheel_pool", { ...params, count: SLICE_COUNT });
    setPool(Math.max(0, (d.restaurant_ids?.length ?? 0) - (d.candidates?.length ?? 0)));
    setCandidates((d.candidates || []).slice(0, SLICE_COUNT));
  };
  useEffect(() => { loadPool().catch(() => { setPool(0); setCandidates([]); }); }, []);
  function truncateLabel(text) {
    return text.length > 6
      ? text.slice(0, 6) + "..."
      : text;
  };

  // 候選有 N 家就切 N 格（最少 1 格給單一候選用）。避免「8 格固定但只有 3 個 label」造成的空白困惑。
  // 分隔線以白色細條夾在色塊邊界（取代 CSS::before 固定 45 度版本）。
  const sliceCount = Math.max(candidates.length, 1);
  const sliceAngle = 360 / sliceCount;
  const DIV = 0.5; // 半條分隔線寬（deg）
  const wheelBg = candidates.length > 0
    ? `conic-gradient(from 0deg, ${candidates.map((_, i) => {
        const start = i * sliceAngle + DIV;
        const end = (i + 1) * sliceAngle - DIV;
        const color = SLICE_COLORS[i % SLICE_COLORS.length];
        return `transparent ${i * sliceAngle}deg ${start}deg, ${color} ${start}deg ${end}deg, transparent ${end}deg ${(i + 1) * sliceAngle}deg`;
      }).join(", ")}), white`
    : undefined;

  const draw = async () => {
    if (spinning || !candidates.length) return;
    setSpinning(true); setExhausted(false); setResult(null);
    const winIndex = Math.floor(Math.random() * candidates.length);
    const winner = candidates[winIndex];
    const winCenterDeg = winIndex * sliceAngle + sliceAngle / 2;
    const base = rotation;
    const currentMod = ((base % 360) + 360) % 360;
    const targetMod = ((-winCenterDeg) % 360 + 360) % 360;
    let delta = targetMod - currentMod;
    if (delta <= 0) delta += 360;
    const target = base + 360 * 5 + delta;
    setRotation(target);
    setTimeout(async () => {
      setSpinning(false);
      setResult(winner);
      drawnRef.current = [...drawnRef.current, winner.restaurant_id];
      try { await api("POST", "/api/restaurants/wheel_draw", { ...params, restaurant_id: winner.restaurant_id }); } catch (e) { /* server tracking optional */ }
      try {
        const visibleIds = candidates.filter((_, i) => i !== winIndex).map(c => c.restaurant_id);
        const excludeIds = [...new Set([...visibleIds, ...drawnRef.current])];
        const r = await api("GET", "/api/restaurants/wheel_pool", { ...params, count: 1, exclude: excludeIds.join(",") });
        const replacement = r.candidates && r.candidates[0];
        const newPool = Math.max(0, (r.restaurant_ids?.length ?? 0) - (replacement ? 1 : 0));
        setPool(newPool);
        setCandidates(prev => {
          const copy = prev.slice();
          if (replacement) copy[winIndex] = replacement;
          else copy.splice(winIndex, 1);
          if (copy.length === 0) setExhausted(true);
          return copy;
        });
      } catch (e) { toast(e.message, "err"); }
    }, SPIN_MS);
  };
  const reset = async () => { await api("POST", "/api/restaurants/wheel_reset", params); setExhausted(false); setResult(null); setRotation(0); await loadPool(); toast("已重設輪盤"); };
  return <div className="wheel-dock">
    <div className="row between center" style={{ padding: "16px 18px", borderBottom: "1px solid var(--line)" }}>
      <div className="h3">🎯 吃什麼輪盤</div><button className="btn btn-ghost btn-icon" onClick={onClose}>✕</button>
    </div>
    <div style={{ padding: 18, overflow: "auto", flex: 1 }}>
      <div className="wheel-stage">
        <div className="wheel-ptr">▼</div>
        <div className={"wheel-spin" + (spinning ? " spinning" : "")} style={{ transform: `rotate(${rotation}deg)`, ...(wheelBg ? { background: wheelBg } : {}) }}>
          {candidates.length > 0 ? candidates.map((r, i) => {
            const deg = i * sliceAngle + sliceAngle / 2;
            const label = truncateLabel(String(r.restaurant_name || "候選餐廳").trim());
            return <div className="wheel-label" style={{ transform: `translate(-50%, -50%) rotate(${deg}deg) translateY(calc(-1 * var(--wheel-label-radius)))` }} key={r.restaurant_id || i}>
              <span style={{ transform: `rotate(${-rotation - deg}deg)` }} title={r.restaurant_name}>{label}</span>
            </div>;
          }) : <span>?</span>}
        </div>
        <button className="wheel-center-btn" disabled={spinning || !candidates.length} onClick={draw}>
          {spinning ? "抽選中" : "轉動！"}
        </button>
      </div>
      {exhausted ? <Empty icon="🪹" title="候選都抽完了！" sub="重設後可重新抽過" action={<button className="btn btn-primary" onClick={reset}>重設輪盤</button>} />
        : result ? <div className="card row" style={{ marginBottom: 14, cursor: "pointer" }} onClick={() => navigate("#/detail?id=" + result.restaurant_id)}>
          <Photo url={result.photos?.[0]?.url || result.main_photo_url} alt={result.restaurant_name} style={{ flex: "0 0 84px", alignSelf: "stretch" }} />
          <div style={{ padding: "10px 12px" }}><div className="h3">{result.restaurant_name}</div>
            <div className="row center gap6 small ink2"><span className="rating-num">{result.rating_avg.toFixed(1)}</span><Stars value={result.rating_avg} size={12} /><span>· {result.district_name}</span></div>
            {result.distance_m != null && <div className="tiny muted">約 {result.distance_m >= 1000 ? (result.distance_m / 1000).toFixed(1) + "km" : result.distance_m + "m"}</div>}</div>
        </div>
          : <div className="count-pill" style={{ marginBottom: 14 }}>依目前篩選 <b>{pool == null ? "…" : (candidates.length + pool)}</b> 家候選</div>}
      {!exhausted && <div className="col gap8">
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

  // BE-3：嘗試取得 GPS 位置（瀏覽器會自動跳權限對話框）；拒絕 / 失敗 / timeout 則用 BANCHIAO fallback。
  // URL 帶 user_lat（從首頁搜地址跳過來）視為使用者明確指定，優先採用。
  const resolveFromUrlOrGps = (preferUrl = true) => new Promise(resolve => {
    if (preferUrl && q.user_lat) { resolve({ lat: +q.user_lat, lng: +q.user_lng }); return; }
    if (!navigator.geolocation) { resolve(BANCHIAO); return; }
    navigator.geolocation.getCurrentPosition(
      pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => resolve(BANCHIAO),
      { timeout: 6000, maximumAge: 60000 }
    );
  });

  // 球面距離 (km)，用來挑「最接近 loc 的 N 個新北區」
  const haversineKm = (lat1, lng1, lat2, lng2) => {
    const R = 6371;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
  };

  // 對 loc 挑最近的 N 個新北區（用 /api/dicts/districts 帶來的 AVG 中心）
  const nearestNtpcDistricts = (loc, dictsObj, n = 4) => {
    if (!dictsObj) return [];
    return [...dictsObj.districts]
      .filter(d => Number.isFinite(d.center_latitude) && Number.isFinite(d.center_longitude))
      .map(d => ({ zipcode: d.zipcode, dist: haversineKm(loc.lat, loc.lng, d.center_latitude, d.center_longitude) }))
      .sort((a, b) => a.dist - b.dist)
      .slice(0, n)
      .map(d => d.zipcode);
  };

  // 套用某座標：呼叫 /api/geo/locate、若無 keyword 則自動勾選對應區（in_ntpc → 該區+鄰接；不在新北 → 最近 N 區）
  const applyLocation = async (newLoc, dictsObj, hasKeyword) => {
    setLoc(newLoc);
    try {
      const info = await api("POST", "/api/geo/locate", newLoc);
      setLocInfo(info);
      if (!hasKeyword) {
        const districts = info.in_ntpc
          ? [info.district.zipcode, ...info.adjacent.map(a => a.zipcode)]
          : nearestNtpcDistricts(newLoc, dictsObj, 4);
        setF(s => ({ ...s, districts }));
      }
      return info;
    } catch (e) {
      toast(e.message, "err");
      return null;
    }
  };

  // 初始化：dict + 解析座標 + locate + 自動套區
  useEffect(() => {
    (async () => {
      try {
        const [d, t, initLoc] = await Promise.all([
          api("GET", "/api/dicts/districts"),
          api("GET", "/api/dicts/tags"),
          resolveFromUrlOrGps(true),
        ]);
        const dictsObj = { districts: d.districts, tags: t.tags };
        setDicts(dictsObj);
        await applyLocation(initLoc, dictsObj, !!(q.tag || q.keyword));
      } catch (e) { toast(e.message, "err"); }
      ready.current = true; reload();
    })();
  }, []);

  const params = (extra) => ({
    district: f.districts, tag: f.tags,
    min_rating: f.minRating || undefined, max_distance_m: f.maxDist || undefined,
    user_lat: loc.lat, user_lng: loc.lng,
    // BE-4：把使用者所在區帶給後端，當作距離搜尋的候選池前置過濾（無 district filter + 有 max_distance_m 時生效）
    user_zipcode: locInfo?.district?.zipcode || undefined,
    keyword: f.keyword || undefined,
    sort: f.sort, bbox: appliedBbox ? appliedBbox.join(",") : undefined, ...extra
  });

  // BE-1：新 keyword → 清 filter；keyword 模式不要用地址做篩選（清掉 districts）
  useEffect(() => {
    if (!ready.current) return;
    setF(s => ({ ...s, keyword: q.keyword || "", districts: [], tags: [], minRating: 0, maxDist: 0 }));
    setAppliedBbox(null);
    setPendBbox(null);
    setShowResq(false);
  }, [q.keyword]);

  // URL user_lat 變動（新地址 / clearAll 清掉地址）→ 重新解析座標 + 重 locate + 重新套區
  useEffect(() => {
    if (!ready.current) return;
    setAppliedBbox(null);
    setPendBbox(null);
    setShowResq(false);
    (async () => {
      const newLoc = await resolveFromUrlOrGps(true);
      setF(s => ({ ...s, tags: [], minRating: 0, maxDist: 0, keyword: q.keyword || "" }));
      await applyLocation(newLoc, dicts, !!q.keyword);
    })();
  }, [q.user_lat, q.user_lng]);

  const reload = async () => {
    setListLoading(true);
    try {
      const [cnt, data] = await Promise.all([
        api("GET", "/api/restaurants/count", params()),
        api("GET", "/api/restaurants/list", params({ limit: 60 })),
      ]);
      setCount(cnt.total);
      setList(data.restaurants);
    } catch (e) { toast(e.message, "err"); } finally { setListLoading(false); }
  };

  // 條件變動 → debounce 重新查詢（count 即時 + 清單）
  // 加 locInfo?.in_ntpc：當新地址 reverse-geocode 回來改變 in_ntpc 狀態時也要重 fire，
  // 否則 reload 會用舊的 locInfo 走 list endpoint 拿到 687 家。
  useEffect(() => {
    if (!ready.current) return;
    const t = setTimeout(reload, 320);
    return () => clearTimeout(t);
  }, [f.districts, f.tags, f.minRating, f.maxDist, f.sort, f.keyword, appliedBbox, loc.lat, loc.lng, locInfo?.in_ntpc]);

  const onFav = (r) => window.favToggle(r, setList);
  const onIdle = (bbox) => { setPendBbox(bbox); setShowResq(true); };
  const reSearch = () => { setAppliedBbox(pendBbox); setShowResq(false); requestAnimationFrame(() => window.dispatchEvent(new Event("resize"))); };
  const clearKeywordRoute = () => {
    if (route.query.keyword != null) navigate("#/explore");
  };
  // 清除所有篩選 + URL 上的地址/關鍵字參數 → 觸發 q.user_lat / q.keyword effect 重新解析座標、自動套區
  const clearAll = () => {
    setF({ districts: [], tags: [], minRating: 0, maxDist: 0, sort: f.sort, keyword: "" });
    setAppliedBbox(null);
    setPendBbox(null);
    setShowResq(false);
    if (route.query.user_lat != null || route.query.keyword != null || route.query.addr != null || route.query.tag != null) {
      navigate("#/explore");
    }
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
        {locInfo && (() => {
  // 定位地址：URL 帶的 addr 優先（使用者明確輸入），其次 in_ntpc 區名，其次「目前位置」
  const where = q.addr || (locInfo.in_ntpc && locInfo.district ? `新北市 ${locInfo.district.district_name}` : "目前位置");
  return <div style={{ margin: 14, padding: "10px 12px", background: "var(--brand-tint)", borderRadius: 12, fontSize: 13, color: "var(--brand-deep)" }}>
    📍 目前定位：<b>{where}</b>
    {!locInfo.in_ntpc && <span> · 不在新北市，已自動勾選最近的新北區</span>}
    {f.keyword && <span> · 關鍵字搜尋中（不套用地址篩選）</span>}
  </div>;
})()}
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
