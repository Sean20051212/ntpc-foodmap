/* Admin 後台 — 餐廳管理(CRUD+照片) + 使用者管理(promote/demote/delete, 保護 user_id=1) */
function RestaurantForm({ dicts, editId, onClose, onSaved }) {
  const [form, setForm] = useState({ restaurant_name: "", description: "", address: "", zipcode: dicts.districts[0].zipcode, price_level: 2, latitude: 25.0095, longitude: 121.4626, tags: [], phones: "" });
  const [photos, setPhotos] = useState([]);
  const [loading, setLoading] = useState(!!editId);
  const [busy, setBusy] = useState(false);
  const set = (k, v) => setForm(s => ({ ...s, [k]: v }));

  useEffect(() => {
    if (!editId) return;
    api("GET", "/api/restaurants/detail", { id: editId }).then(r => {
      const d = r.restaurant;
      setForm({ restaurant_name: d.restaurant_name, description: d.description, address: d.address, zipcode: d.zipcode, price_level: d.price_level, latitude: d.latitude, longitude: d.longitude, tags: d.tags.map(t => t.tag_id), phones: d.phones.join(", ") });
      setPhotos(d.photos); setLoading(false);
    });
  }, [editId]);

  const toggleTag = (id) => set("tags", form.tags.includes(id) ? form.tags.filter(x => x !== id) : [...form.tags, id]);
  const save = async () => {
    if (!form.restaurant_name.trim()) return toast("請輸入餐廳名稱", "err");
    setBusy(true);
    try {
      await api("POST", "/api/admin/restaurant/upsert", { restaurant_id: editId, ...form, tags: form.tags, phones: form.phones.split(/[,，]/).map(s => s.trim()).filter(Boolean) });
      toast(editId ? "已更新餐廳" : "已新增餐廳", "ok"); onSaved();
    } catch (e) { toast(e.message, "err"); } finally { setBusy(false); }
  };
  const addPhoto = async () => {
    const url = prompt("輸入照片網址 URL");
    if (!url) return;
    await api("POST", "/api/admin/photo/upsert", { restaurant_id: editId, url, is_main: photos.length === 0 ? 1 : 0 });
    const r = await api("GET", "/api/restaurants/detail", { id: editId }); setPhotos(r.restaurant.photos);
  };
  const setMain = async (ph) => { await api("POST", "/api/admin/photo/upsert", { photo_id: ph.photo_id, restaurant_id: editId, url: ph.url, is_main: 1 }); const r = await api("GET", "/api/restaurants/detail", { id: editId }); setPhotos(r.restaurant.photos); };
  const delPhoto = async (ph) => { await api("POST", "/api/admin/photo/delete", { photo_id: ph.photo_id }); const r = await api("GET", "/api/restaurants/detail", { id: editId }); setPhotos(r.restaurant.photos); };

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
      {editId && <div className="field"><label className="label">照片管理</label>
        <div className="photo-cell">
          {photos.map(ph => <div key={ph.photo_id} className={"photo-thumb" + (ph.is_main ? " main" : "")}>
            <img src={ph.url} alt="" onClick={() => setMain(ph)} title="設為主圖" />
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

function AdminRestaurants({ dicts }) {
  const [rows, setRows] = useState(null);
  const [edit, setEdit] = useState(null); // {id} or {id:null} for new
  const load = () => api("GET", "/api/restaurants/list", { limit: 1000, sort: "name_asc" }).then(r => setRows(r.restaurants));
  useEffect(() => { load(); }, []);
  const del = async (r) => { if (!(await confirmDialog({ title: "刪除餐廳？", body: `「${r.restaurant_name}」及其評論、收藏將一併刪除。`, ok: "刪除", danger: true }))) return; await api("POST", "/api/admin/restaurant/delete", { restaurant_id: r.restaurant_id }); toast("已刪除"); load(); };
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
          <button className="btn btn-outline btn-sm" onClick={() => setEdit({ id: r.restaurant_id })}>編輯</button>
          <button className="btn btn-ghost btn-sm" style={{ color: "#c83b30" }} onClick={() => del(r)}>刪除</button>
        </div></td>
      </tr>)}</tbody>
    </table>}
    {edit && <RestaurantForm dicts={dicts} editId={edit.id} onClose={() => setEdit(null)} onSaved={() => { setEdit(null); load(); }} />}
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
