/* 個人頁 — 看自己(修改密碼/登出/我的評論/我的收藏可收合) vs 看別人(只評論) */
function Collapse({ title, count, defaultOpen = true, children }) {
  const [open, setOpen] = useState(defaultOpen);
  return <div style={{ marginTop: 22 }}>
    <div className="row between center" style={{ cursor: "pointer", padding: "6px 0" }} onClick={() => setOpen(o => !o)}>
      <div className="h2">{title} {count != null && <span className="ink2" style={{ fontWeight: 600 }}>({count})</span>}</div>
      <span style={{ fontSize: 18, color: "var(--ink-2)", transition: "transform .15s", transform: open ? "none" : "rotate(-90deg)" }}>▾</span>
    </div>
    {open && <div style={{ marginTop: 12 }}>{children}</div>}
  </div>;
}

function ChangePwModal({ onClose }) {
  const [oldp, setOld] = useState(""), [n1, setN1] = useState(""), [n2, setN2] = useState(""), [busy, setBusy] = useState(false);
  const submit = async () => {
    if (n1.length < 8) return toast("新密碼至少 8 字", "err");
    if (n1 !== n2) return toast("兩次新密碼不一致", "err");
    setBusy(true);
    try { await api("POST", "/api/auth/change_password", { old_password: oldp, new_password: n1 }); toast("密碼已更新", "ok"); onClose(); }
    catch (e) { toast(e.code === "forbidden" ? "舊密碼不正確" : e.message, "err"); } finally { setBusy(false); }
  };
  return <Modal title="修改密碼" onClose={onClose} width={400}>
    <div className="col gap16">
      <div className="field"><label className="label">舊密碼</label><input className="input" type="password" value={oldp} onChange={e => setOld(e.target.value)} /></div>
      <div className="field"><label className="label">新密碼</label><input className="input" type="password" value={n1} onChange={e => setN1(e.target.value)} placeholder="至少 8 字" /></div>
      <div className="field"><label className="label">確認新密碼</label><input className="input" type="password" value={n2} onChange={e => setN2(e.target.value)} /></div>
      <button className="btn btn-primary btn-block" disabled={busy} onClick={submit}>{busy ? "更新中…" : "確認修改"}</button>
    </div>
  </Modal>;
}

function FavRow({ r, onRemove }) {
  return <div className="card row" style={{ gap: 0 }}>
    <Photo url={r.main_photo_url} style={{ flex: "0 0 110px", alignSelf: "stretch", cursor: "pointer" }} />
    <div className="grow" style={{ padding: "12px 14px", cursor: "pointer" }} onClick={() => navigate("#/detail?id=" + r.restaurant_id)}>
      <div className="row between center"><div className="h3">{r.restaurant_name}</div><OpenBadge open={r.is_open_now} /></div>
      <div className="row center gap6 small ink2" style={{ margin: "4px 0" }}><span className="rating-num">{r.rating_avg.toFixed(1)}</span><Stars value={r.rating_avg} size={13} /><span>· {priceText(r.price_level)} · {r.district_name}</span></div>
      <Tags items={r.tags} />
    </div>
    <div style={{ padding: 12, display: "flex", alignItems: "center" }}>
      <button className="btn btn-ghost btn-sm" style={{ color: "var(--brand)" }} onClick={() => onRemove(r)}>♥ 取消收藏</button>
    </div>
  </div>;
}

function PageProfile({ me, onAuth }) {
  const route = useRoute();
  const uid = +route.query.id;
  const isSelf = me && me.user_id === uid;
  const [prof, setProf] = useState(null), [reviews, setReviews] = useState(null), [favs, setFavs] = useState(null);
  const [pw, setPw] = useState(false), [err, setErr] = useState(false);

  useEffect(() => {
    setProf(null); setReviews(null); setFavs(null); setErr(false); window.scrollTo(0, 0);
    api("GET", "/api/users/profile", { user_id: uid }).then(r => setProf(r.user)).catch(() => setErr(true));
    api("GET", "/api/reviews/by_user", { user_id: uid, limit: 20 }).then(r => setReviews(r)).catch(() => setReviews({ total: 0, reviews: [] }));
    if (me && me.user_id === uid) api("GET", "/api/favorites/list").then(r => setFavs(r.restaurants)).catch(() => setFavs([]));
  }, [uid, me]);

  const logout = async () => { if (!(await confirmDialog({ title: "確定要登出？", ok: "登出" }))) return; try { await api("POST", "/api/auth/logout"); } catch (e) {} onAuth(); toast("已登出"); navigate("#/login"); };
  const removeFav = async (r) => { try { await api("POST", "/api/favorites/toggle", { restaurant_id: r.restaurant_id }); setFavs(fs => fs.filter(x => x.restaurant_id !== r.restaurant_id)); toast("已取消收藏"); } catch (e) { toast(e.message, "err"); } };

  if (err) return <div className="container section"><Empty icon="👤" title="找不到這位使用者" /></div>;
  if (!prof) return <Loading pad={80} />;

  const ReviewList = ({ items }) => items.length === 0 ? <Empty icon="💬" title="還沒有評論" />
    : <div className="col gap12">{items.map((rv, i) => <div key={i} className="card row" style={{ gap: 0, cursor: "pointer" }} onClick={() => navigate(detailReviewRoute(rv.restaurant_id, uid))}>
      <Photo url={rv.main_photo_url} style={{ flex: "0 0 96px", alignSelf: "stretch" }} />
      <div className="grow" style={{ padding: "12px 14px" }}>
        <div className="row between center"><div className="h3">{rv.restaurant_name}</div><Stars value={rv.rating} size={14} /></div>
        {rv.comment && <p className="ink2 small clamp2" style={{ margin: "6px 0 0", lineHeight: 1.6 }}>{rv.comment}</p>}
        <div className="tiny muted" style={{ marginTop: 6 }}>{fmtDate(rv.created_at)}</div>
      </div>
    </div>)}</div>;

  return <div className="container section" style={{ maxWidth: 860 }}>
    {/* header card */}
    <div className="card" style={{ padding: 22, display: "grid", gridTemplateColumns: "auto 1fr auto", gap: 18, alignItems: "center" }}>
      <Avatar name={prof.username} size={76} style={{ fontSize: 32 }} />
      <div style={{ minWidth: 0 }}>
        <div className="row center gap8 wrap"><h1 className="h1" style={{ fontSize: 26 }}>{prof.username}</h1>{prof.is_admin ? <span className="badge" style={{ background: "var(--brand-tint)", color: "var(--brand-deep)" }}>管理員</span> : null}</div>
        <div className="row center gap10 ink2 small wrap" style={{ marginTop: 6 }}>
          <span>{prof.review_count} 則評論</span>{isSelf && favs ? <span>· {favs.length} 收藏</span> : null}<span>· 加入於 {fmtDate(prof.created_at)}</span>
        </div>
        {isSelf && <div className="row gap8 wrap" style={{ marginTop: 12 }}>
          <button className="btn btn-outline btn-sm" onClick={() => setPw(true)}>修改密碼</button>
          <button className="btn btn-ghost btn-sm" onClick={logout}>登出</button>
        </div>}
      </div>
      <div />
    </div>

    {isSelf ? <>
      <Collapse title="我的評論" count={reviews ? reviews.total : null}>{reviews ? <ReviewList items={reviews.reviews} /> : <Loading />}</Collapse>
      <Collapse title="我的收藏" count={favs ? favs.length : null}>{!favs ? <Loading /> : favs.length === 0 ? <Empty icon="♡" title="還沒有收藏" sub="在地圖或卡片點愛心即可收藏" action={<button className="btn btn-primary btn-sm" onClick={() => navigate("#/explore")}>去探索</button>} /> : <div className="col gap12">{favs.map(r => <FavRow key={r.restaurant_id} r={r} onRemove={removeFav} />)}</div>}</Collapse>
    </> : <div style={{ marginTop: 24 }}>
      <div className="h2" style={{ marginBottom: 14 }}>{prof.username} 的評論</div>
      {reviews ? <ReviewList items={reviews.reviews} /> : <Loading />}
    </div>}

    {pw && <ChangePwModal onClose={() => setPw(false)} />}
  </div>;
}
window.PageProfile = PageProfile;
