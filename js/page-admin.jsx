/* Admin 後台 — 餐廳管理(CRUD+照片+營業時間) + 使用者管理(promote/demote/delete, 保護 user_id=1) */

// 把 HH:MM:SS 截成 HH:MM
const trimTime = (t) => (t || "").slice(0, 5);
// 24:00:00 是合法 TIME 值表示「當日午夜」，下拉沒這個選項 → 視為 00:00
const splitHM = (t) => {
  const v = !t ? "00:00" : (t === "24:00:00" || t === "24:00" ? "00:00" : trimTime(t));
  const [h, m] = v.split(":");
  return [h || "00", m || "00"];
};
// 寫回時轉成 HH:MM:SS
const toFullTime = (hhmm) => /^\d{2}:\d{2}$/.test(hhmm) ? hhmm + ":00" : hhmm;

// 24h "HH:MM" → { ampm, hh12, mm }；ampm = "AM" | "PM"
function to12h(value) {
  const [h, m] = splitHM(value);
  const H = parseInt(h, 10);
  return {
    ampm: H >= 12 ? "PM" : "AM",
    hh12: String(H === 0 ? 12 : (H > 12 ? H - 12 : H)).padStart(2, "0"),
    mm: m,
  };
}
function from12h(ampm, hh12, mm) {
  let H = parseInt(hh12, 10) || 0;
  if (H === 12) H = 0;
  if (ampm === "PM") H += 12;
  return String(H).padStart(2, "0") + ":" + (mm || "00");
}

function TimeSelect({ value, onChange }) {
  const { ampm, hh12, mm } = to12h(value);
  const [localHH, setLocalHH] = useState(hh12);
  const [localMM, setLocalMM] = useState(mm);
  // 上游 value 變動時（例如重新載入餐廳）同步進來
  useEffect(() => { setLocalHH(hh12); }, [hh12]);
  useEffect(() => { setLocalMM(mm); }, [mm]);

  const commitHH = () => {
    const n = parseInt(localHH, 10);
    if (Number.isNaN(n) || n < 1 || n > 12) { setLocalHH(hh12); return; }
    const padded = String(n).padStart(2, "0");
    onChange(from12h(ampm, padded, mm));
  };
  const commitMM = () => {
    const n = parseInt(localMM, 10);
    if (Number.isNaN(n) || n < 0 || n > 59) { setLocalMM(mm); return; }
    const padded = String(n).padStart(2, "0");
    onChange(from12h(ampm, hh12, padded));
  };

  return <span className="row gap4 center">
    <select className="select" style={{ width: 70 }} value={ampm} onChange={e => onChange(from12h(e.target.value, hh12, mm))}>
      <option value="AM">上午</option>
      <option value="PM">下午</option>
    </select>
    <input className="input tnum" style={{ width: 50, textAlign: "center" }} value={localHH}
      onChange={e => setLocalHH(e.target.value.replace(/\D/g, "").slice(0, 2))}
      onBlur={commitHH} />
    <span>:</span>
    <input className="input tnum" style={{ width: 50, textAlign: "center" }} value={localMM}
      onChange={e => setLocalMM(e.target.value.replace(/\D/g, "").slice(0, 2))}
      onBlur={commitMM} />
  </span>;
}

// 攤平到分鐘軸做 client overlap check（複刻後端 adminAssertHoursNoOverlap 邏輯）
function detectHourConflicts(hours) {
  const segs = [];
  hours.forEach((h, idx) => {
    if (h.spec_rec) return;
    if (!h.start_time || !h.end_time) return;
    const [sh, sm] = h.start_time.split(":").map(Number);
    const [eh, em] = h.end_time.split(":").map(Number);
    const sMin = h.day * 1440 + sh * 60 + sm;
    const eMin = h.day * 1440 + eh * 60 + em;
    if (sMin === eMin) return;
    if (eMin > sMin) {
      segs.push({ idx, s: sMin, e: eMin });
    } else {
      segs.push({ idx, s: sMin, e: (h.day + 1) * 1440 });
      const tailStart = ((h.day + 1) * 1440) % 10080;
      segs.push({ idx, s: tailStart, e: tailStart + eh * 60 + em });
    }
  });
  const conflicts = new Set();
  for (let i = 0; i < segs.length; i++) {
    for (let j = i + 1; j < segs.length; j++) {
      if (segs[i].idx === segs[j].idx) continue;
      if (Math.max(segs[i].s, segs[j].s) < Math.min(segs[i].e, segs[j].e)) {
        conflicts.add(segs[i].idx);
        conflicts.add(segs[j].idx);
      }
    }
  }
  return conflicts;
}

function OpenTimeEditor({ hours, onChange }) {
  const DAY_NAMES = ["日", "一", "二", "三", "四", "五", "六"];
  const conflicts = detectHourConflicts(hours);

  const addRow = (day) => onChange([...hours, { day, start_time: "11:00", end_time: "14:00", spec_rec: null }]);
  const updateRow = (idx, patch) => onChange(hours.map((h, i) => i === idx ? { ...h, ...patch } : h));
  const removeRow = (idx) => onChange(hours.filter((_, i) => i !== idx));

  const isCrossDay = (h) => {
    if (!h.start_time || !h.end_time) return false;
    return h.end_time <= h.start_time && h.end_time !== "00:00";
  };

  return <div className="col gap8">
    {DAY_NAMES.map((name, day) => {
      const dayRows = hours.map((h, idx) => ({ h, idx })).filter(({ h }) => h.day === day);
      return <div key={day} className="card" style={{ padding: 10 }}>
        <div className="row between center" style={{ marginBottom: dayRows.length ? 6 : 0 }}>
          <b>週{name}</b>
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => addRow(day)}>＋ 加時段</button>
        </div>
        {dayRows.length === 0 ? <div className="tiny muted">公休</div> : dayRows.map(({ h, idx }) => {
          const bad = conflicts.has(idx);
          return <div key={idx} className="row gap6 center wrap" style={{ marginTop: 4, padding: 6, background: bad ? "#fff0ee" : "transparent", border: bad ? "1px solid #c83b30" : "1px solid transparent", borderRadius: 6 }}>
            <TimeSelect value={h.start_time} onChange={v => updateRow(idx, { start_time: v })} />
            <span>–</span>
            <TimeSelect value={h.end_time} onChange={v => updateRow(idx, { end_time: v })} />
            <input className="input grow" style={{ minWidth: 120 }} placeholder="備註（spec_rec，可空）" value={h.spec_rec || ""} onChange={e => updateRow(idx, { spec_rec: e.target.value || null })} />
            <button type="button" className="btn btn-ghost btn-sm" style={{ color: "#c83b30" }} onClick={() => removeRow(idx)}>✕</button>
            {isCrossDay(h) && <span className="tiny" style={{ color: "var(--brand-deep)" }}>⚠ 跨至隔日 {h.end_time}</span>}
          </div>;
        })}
      </div>;
    })}
    {conflicts.size > 0 && <div className="small" style={{ color: "#c83b30" }}>⚠ 有時段重疊（已標紅），需修正才能儲存</div>}
  </div>;
}

function RestaurantForm({ dicts, editId, onClose, onSaved }) {
  const [form, setForm] = useState({ restaurant_name: "", description: "", address: "", zipcode: dicts.districts[0].zipcode, price_level: 2, latitude: 25.0095, longitude: 121.4626, tags: [], phones: "", opentime: [] });
  const [photos, setPhotos] = useState([]);
  const [loading, setLoading] = useState(!!editId);
  const [busy, setBusy] = useState(false);
  const set = (k, v) => setForm(s => ({ ...s, [k]: v }));

  useEffect(() => {
    if (!editId) return;
    api("GET", "/api/restaurants/detail", { id: editId }).then(r => {
      const d = r.restaurant;
      setForm({
        restaurant_name: d.restaurant_name, description: d.description, address: d.address,
        zipcode: d.zipcode, price_level: d.price_level, latitude: d.latitude, longitude: d.longitude,
        tags: d.tags.map(t => t.tag_id), phones: d.phones.join(", "),
        opentime: (d.opentime_regular || []).map(o => ({
          day: o.day,
          start_time: trimTime(o.start_time),
          end_time: trimTime(o.end_time),
          spec_rec: o.spec_rec,
        })),
      });
      setPhotos(d.photos); setLoading(false);
    });
  }, [editId]);

  const toggleTag = (id) => set("tags", form.tags.includes(id) ? form.tags.filter(x => x !== id) : [...form.tags, id]);
  const save = async () => {
    if (!form.restaurant_name.trim()) return toast("請輸入餐廳名稱", "err");
    const conflicts = detectHourConflicts(form.opentime);
    if (conflicts.size > 0) return toast("營業時間有重疊，請修正", "err");
    if (!(await confirmDialog({
      title: editId ? "確定要修改餐廳資料？" : "確定要新增餐廳？",
      body: editId ? `將儲存「${form.restaurant_name}」的最新內容。` : `將新增「${form.restaurant_name}」。`,
      ok: editId ? "確認修改" : "確認新增"
    }))) return;
    setBusy(true);
    try {
      const payload = {
        restaurant_id: editId, ...form,
        tags: form.tags,
        phones: form.phones.split(/[,，]/).map(s => s.trim()).filter(Boolean),
        opentime: form.opentime
          .filter(h => h.start_time && h.end_time)
          .map(h => ({ day: h.day, start_time: toFullTime(h.start_time), end_time: toFullTime(h.end_time), spec_rec: h.spec_rec || null })),
      };
      await api("POST", "/api/admin/restaurant/upsert", payload);
      toast(editId ? "已更新餐廳" : "已新增餐廳", "ok"); onSaved();
    } catch (e) {
      toast(e.code === "opentime_overlap" ? "營業時間有重疊：" + e.message : e.message, "err");
    } finally { setBusy(false); }
  };
  const addPhoto = async () => {
    const url = prompt("輸入照片網址 URL");
    if (!url) return;
    if (!(await confirmDialog({ title: "確定要新增照片？", body: "新增後會出現在這家餐廳的照片列表。", ok: "新增照片" }))) return;
    await api("POST", "/api/admin/photo/upsert", { restaurant_id: editId, url, is_main: photos.length === 0 ? 1 : 0 });
    const r = await api("GET", "/api/restaurants/detail", { id: editId }); setPhotos(r.restaurant.photos);
  };
  const setMain = async (ph) => {
    if (ph.is_main) return;
    if (!(await confirmDialog({ title: "確定要修改主圖？", body: "這張照片會成為餐廳列表與詳情頁的主要圖片。", ok: "設為主圖" }))) return;
    await api("POST", "/api/admin/photo/upsert", { photo_id: ph.photo_id, restaurant_id: editId, url: ph.url, is_main: 1 });
    const r = await api("GET", "/api/restaurants/detail", { id: editId }); setPhotos(r.restaurant.photos);
  };
  const delPhoto = async (ph) => {
    if (!(await confirmDialog({ title: "確定要刪除照片？", body: "刪除後不會影響餐廳資料，但照片會從列表移除。", ok: "刪除照片", danger: true }))) return;
    await api("POST", "/api/admin/photo/delete", { photo_id: ph.photo_id });
    const r = await api("GET", "/api/restaurants/detail", { id: editId }); setPhotos(r.restaurant.photos);
  };

  return <Modal title={editId ? "編輯餐廳" : "新增餐廳"} onClose={onClose} width={620}>
    {loading ? <Loading /> : <div className="col gap16">
      <div className="field"><label className="label">餐廳名稱</label><input className="input" value={form.restaurant_name} onChange={e => set("restaurant_name", e.target.value)} /></div>
      <div className="field"><label className="label">描述</label><textarea className="textarea" value={form.description} onChange={e => set("description", e.target.value)} /></div>
      <div className="field"><label className="label">地址</label><input className="input" value={form.address} onChange={e => set("address", e.target.value)} /></div>
      <div className="row gap16">
        <div className="field grow"><label className="label">區域 zipcode</label>
          <select className="select" value={form.zipcode} onChange={e => set("zipcode", e.target.value)}>{dicts.districts.map(d => <option key={d.zipcode} value={d.zipcode}>{d.district_name}（{d.zipcode}）</option>)}</select></div>
        <div className="field" style={{ width: 120 }}><label className="label">價位</label>
          <select className="select" value={form.price_level} onChange={e => set("price_level", +e.target.value)}>{[1, 2, 3, 4].map(n => <option key={n} value={n}>{"$".repeat(n)}</option>)}</select></div>
      </div>
      <div className="row gap16">
        <div className="field grow"><label className="label">緯度 latitude</label><input className="input tnum" type="number" step="0.0001" value={form.latitude} onChange={e => set("latitude", e.target.value)} /></div>
        <div className="field grow"><label className="label">經度 longitude</label><input className="input tnum" type="number" step="0.0001" value={form.longitude} onChange={e => set("longitude", e.target.value)} /></div>
      </div>
      <div className="field"><label className="label">電話（逗號分隔）</label><input className="input" value={form.phones} onChange={e => set("phones", e.target.value)} placeholder="02-1234-5678, 0912-345-678" /></div>
      <div className="field"><label className="label">分類 tags</label>
        <div className="row gap6 wrap">{dicts.tags.map(t => <span key={t.tag_id} className={"chip" + (form.tags.includes(t.tag_id) ? " on" : "")} onClick={() => toggleTag(t.tag_id)}>{t.tag_name}</span>)}</div></div>
      <div className="field"><label className="label">營業時間</label>
        <OpenTimeEditor hours={form.opentime} onChange={v => set("opentime", v)} />
        <div className="tiny muted" style={{ marginTop: 4 }}>跨午夜：直接填例如 22:00–02:00；備註用於特殊營業（如「過年休」），有備註的列不參與重疊檢查。</div>
      </div>
      {editId && <div className="field"><label className="label">照片管理</label>
        <div className="photo-cell">
          {photos.map(ph => <div key={ph.photo_id} className={"photo-thumb" + (ph.is_main ? " main" : "")}>
            <img src={photoSrc(ph)} alt="" onClick={() => setMain(ph)} title="設為主圖" />
            {ph.is_main ? <span className="pbadge">主圖</span> : null}
            <button className="pdel" onClick={() => delPhoto(ph)}>✕</button>
          </div>)}
          <button className="photo-thumb" style={{ border: "2px dashed var(--line-2)", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--muted)", fontSize: 22, background: "var(--surface)", cursor: "pointer" }} onClick={addPhoto}>＋</button>
        </div>
        <div className="tiny muted" style={{ marginTop: 4 }}>點圖設為主圖，✕ 刪除</div></div>}
      <div className="row gap8" style={{ justifyContent: "flex-end" }}>
        <button className="btn btn-ghost" onClick={onClose}>取消</button>
        <button className="btn btn-primary" disabled={busy} onClick={save}>{busy ? "儲存中…" : "儲存"}</button>
      </div>
    </div>}
  </Modal>;
}

function AdminReviewsModal({ restaurant, onClose, onChanged }) {
  const [data, setData] = useState(null);
  const [busyKey, setBusyKey] = useState(null);
  const load = () => api("GET", "/api/reviews/by_restaurant", { restaurant_id: restaurant.restaurant_id, limit: 100, offset: 0 }).then(setData);
  useEffect(() => { setData(null); load(); }, [restaurant.restaurant_id]);

  const delReview = async (rv) => {
    if (!(await confirmDialog({ title: "刪除評論？", body: `只會刪除「${rv.username}」對「${restaurant.restaurant_name}」的這筆評論，不會刪除餐廳資料。`, ok: "刪除評論", danger: true }))) return;
    setBusyKey(rv.user_id);
    try {
      await api("POST", "/api/admin/review/delete", { restaurant_id: restaurant.restaurant_id, user_id: rv.user_id });
      toast("評論已刪除", "ok");
      await load();
      onChanged();
    } catch (e) {
      toast(e.message, "err");
    } finally {
      setBusyKey(null);
    }
  };

  return <Modal title={`評論管理：${restaurant.restaurant_name}`} onClose={onClose} width={680}>
    {!data ? <Loading /> : data.reviews.length === 0 ? <Empty icon="☆" title="目前沒有評論" />
      : <div className="col gap12">
        <div className="small ink2">共 {data.total} 則評論。這裡刪除的是單一評論，不會刪除餐廳。</div>
        {data.reviews.map(rv => <div key={rv.user_id} className="card" style={{ padding: 14 }}>
          <div className="row between center gap12">
            <div className="grow">
              <div className="row center gap8 wrap">
                <b>{rv.username}</b>
                <Stars value={rv.rating} size={14} />
                <span className="tiny muted">{fmtDate(rv.updated_at || rv.created_at)}</span>
              </div>
              {rv.comment ? <p className="ink2 small" style={{ margin: "8px 0 0", lineHeight: 1.6 }}>{rv.comment}</p> : <div className="tiny muted" style={{ marginTop: 8 }}>沒有留下文字評論</div>}
            </div>
            <button className="btn btn-ghost btn-sm" style={{ color: "#c83b30" }} disabled={busyKey === rv.user_id} onClick={() => delReview(rv)}>
              {busyKey === rv.user_id ? "刪除中…" : "刪評論"}
            </button>
          </div>
        </div>)}
      </div>}
  </Modal>;
}

function AdminRestaurants({ dicts }) {
  const [rows, setRows] = useState(null);
  const [edit, setEdit] = useState(null); // {id} or {id:null} for new
  const [reviewTarget, setReviewTarget] = useState(null);
  const load = () => api("GET", "/api/restaurants/list", { limit: 1000, sort: "name_asc" }).then(r => setRows(r.restaurants));
  useEffect(() => { load(); }, []);
  const del = async (r) => { if (!(await confirmDialog({ title: "刪除整筆餐廳？", body: `這會刪除「${r.restaurant_name}」整筆餐廳資料，包含評論與收藏。若只想刪評論，請用「評論」按鈕。`, ok: "刪餐廳", danger: true }))) return; await api("POST", "/api/admin/restaurant/delete", { restaurant_id: r.restaurant_id }); toast("餐廳已刪除"); load(); };
  return <div>
    <div className="row between center" style={{ marginBottom: 14 }}>
      <div className="ink2 small">{rows ? rows.length : "…"} 家餐廳</div>
      <button className="btn btn-primary btn-sm" onClick={() => setEdit({ id: null })}>＋ 新增餐廳</button>
    </div>
    {!rows ? <Loading /> : <table className="dtable">
      <thead><tr><th>餐廳名稱</th><th className="hide-m">區域</th><th>評分</th><th style={{ textAlign: "right" }}>操作</th></tr></thead>
      <tbody>{rows.map(r => <tr key={r.restaurant_id} className="row-hover">
        <td><b>{r.restaurant_name}</b></td>
        <td className="hide-m ink2">{r.district_name}</td>
        <td className="tnum">{r.rating_avg ? r.rating_avg.toFixed(1) : "—"} <span className="muted tiny">({r.rating_count})</span></td>
        <td><div className="row gap6" style={{ justifyContent: "flex-end" }}>
          <button className="btn btn-outline btn-sm" onClick={() => setReviewTarget(r)}>評論</button>
          <button className="btn btn-outline btn-sm" onClick={() => setEdit({ id: r.restaurant_id })}>編輯</button>
          <button className="btn btn-ghost btn-sm" style={{ color: "#c83b30" }} onClick={() => del(r)}>刪餐廳</button>
        </div></td>
      </tr>)}</tbody>
    </table>}
    {edit && <RestaurantForm dicts={dicts} editId={edit.id} onClose={() => setEdit(null)} onSaved={() => { setEdit(null); load(); }} />}
    {reviewTarget && <AdminReviewsModal restaurant={reviewTarget} onClose={() => setReviewTarget(null)} onChanged={load} />}
  </div>;
}

function AdminUsers() {
  const [data, setData] = useState(null);
  const [kw, setKw] = useState("");
  const load = (k) => api("GET", "/api/admin/users/list", { keyword: k || "" }).then(setData);
  useEffect(() => { load(); }, []);
  const act = async (u, action, label, danger) => {
    if (!(await confirmDialog({ title: `${label}「${u.username}」？`, ok: label, danger }))) return;
    try { await api("POST", "/api/admin/users/" + action, { user_id: u.user_id }); toast("已" + label); load(kw); }
    catch (e) { toast(e.code === "forbidden" ? "不可變更原始管理員" : e.message, "err"); }
  };
  return <div>
    <div className="row between center wrap gap8" style={{ marginBottom: 14 }}>
      <div className="ink2 small">{data ? data.total : "…"} 位使用者</div>
      <input className="input" style={{ width: 220 }} placeholder="🔍 搜尋帳號" value={kw} onChange={e => { setKw(e.target.value); load(e.target.value); }} />
    </div>
    {!data ? <Loading /> : <table className="dtable">
      <thead><tr><th>ID</th><th>帳號</th><th className="hide-m">評論</th><th className="hide-m">收藏</th><th style={{ textAlign: "right" }}>操作</th></tr></thead>
      <tbody>{data.users.map(u => {
        const sa = u.user_id === 1;
        return <tr key={u.user_id} className="row-hover">
          <td className="tnum muted">{u.user_id}</td>
          <td><b>{u.username}</b> {u.is_admin ? <span className="badge" style={{ background: "var(--brand-tint)", color: "var(--brand-deep)", marginLeft: 4 }}>{sa ? "super" : "admin"}</span> : null}</td>
          <td className="hide-m tnum">{u.review_count}</td>
          <td className="hide-m tnum">{u.favorite_count}</td>
          <td><div className="row gap6" style={{ justifyContent: "flex-end" }}>
            {u.is_admin
              ? <button className="btn btn-outline btn-sm" disabled={sa} onClick={() => act(u, "demote", "降為一般")}>降權</button>
              : <button className="btn btn-outline btn-sm" disabled={sa} onClick={() => act(u, "promote", "設為管理員")}>升管理</button>}
            <button className="btn btn-ghost btn-sm" style={{ color: sa ? "var(--faint)" : "#c83b30" }} disabled={sa} onClick={() => act(u, "delete", "刪除", true)}>刪除</button>
          </div></td>
        </tr>;
      })}</tbody>
    </table>}
    <div className="tiny muted" style={{ marginTop: 10 }}>原始管理員（ID 1）受保護，三個操作皆停用（前端擋一層，後端再擋一層）。</div>
  </div>;
}

function PageAdmin({ me }) {
  const [tab, setTab] = useState("restaurants");
  const [dicts, setDicts] = useState(null);
  useEffect(() => { Promise.all([api("GET", "/api/dicts/districts"), api("GET", "/api/dicts/tags")]).then(([d, t]) => setDicts({ districts: d.districts, tags: t.tags })); }, []);
  if (!me || !me.is_admin) return <div className="container section"><Empty icon="🔒" title="需要管理員權限" action={<button className="btn btn-primary" onClick={() => navigate("#/")}>回首頁</button>} /></div>;
  return <div className="container section">
    <h1 className="h1" style={{ marginBottom: 6 }}>管理後台</h1>
    <div className="ink2" style={{ marginBottom: 18 }}>登入身分：{me.username}（管理員）</div>
    <div className="admin-tabs">
      <button className={tab === "restaurants" ? "on" : ""} onClick={() => setTab("restaurants")}>餐廳管理</button>
      <button className={tab === "users" ? "on" : ""} onClick={() => setTab("users")}>使用者管理</button>
    </div>
    {!dicts ? <Loading /> : tab === "restaurants" ? <AdminRestaurants dicts={dicts} /> : <AdminUsers />}
  </div>;
}
window.PageAdmin = PageAdmin;
