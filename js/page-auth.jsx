/* Auth — 登入 / 註冊（強制登入才能用站） */
function PageAuth({ onAuth }) {
  const [tab, setTab] = useState("login");
  const [u, setU] = useState(""); const [p, setP] = useState("");
  const [err, setErr] = useState(""); const [busy, setBusy] = useState(false);

  const submit = async () => {
    setErr("");
    if (u.trim().length < 3) return setErr("帳號需 3–50 字");
    if (p.length < 8) return setErr("密碼至少 8 字");
    setBusy(true);
    try {
      await api("POST", tab === "login" ? "/api/auth/login" : "/api/auth/register", { username: u.trim(), password: p });
      localStorage.removeItem("searchHistory");
      await onAuth(); toast(tab === "login" ? "歡迎回來！" : "註冊成功，已登入", "ok"); navigate("#/");
    } catch (e) {
      setErr(e.code === "conflict" ? "帳號已存在，請換一個" : (e.message || "操作失敗"));
    } finally { setBusy(false); }
  };

  return <div style={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", padding: 20, background: "radial-gradient(120% 80% at 50% -10%, var(--brand-tint) 0%, var(--bg) 55%)" }}>
    <div className="card" style={{ width: 410, padding: 30, boxShadow: "var(--sh-3)", border: "1px solid var(--line)" }}>
      <div style={{ textAlign: "center", marginBottom: 20 }}>
        <div className="brand-mark" style={{ width: 52, height: 52, fontSize: 28, margin: "0 auto 12px", borderRadius: 16 }}>🍜</div>
        <div className="h1" style={{ fontSize: 24 }}>{tab === "login" ? "歡迎回來 👋" : "建立你的帳號"}</div>
        <div className="ink2 small" style={{ marginTop: 4 }}>登入新北美食地圖，開始探索在地美味</div>
      </div>
      <div className="seg" style={{ width: "100%", marginBottom: 20, background: "var(--surface-2)" }}>
        <button style={{ flex: 1 }} className={tab === "login" ? "on" : ""} onClick={() => { setTab("login"); setErr(""); }}>登入</button>
        <button style={{ flex: 1 }} className={tab === "register" ? "on" : ""} onClick={() => { setTab("register"); setErr(""); }}>註冊</button>
      </div>
      <div className="col gap16">
        <div className="field"><label className="label">帳號</label>
          <input className={"input" + (err ? " input-err" : "")} value={u} onChange={e => setU(e.target.value)} onKeyDown={e => e.key === "Enter" && submit()} /></div>
        <div className="field"><label className="label">密碼</label>
          <input className={"input" + (err ? " input-err" : "")} type="password" value={p} onChange={e => setP(e.target.value)} onKeyDown={e => e.key === "Enter" && submit()} /></div>
        {err && <div className="err-text">{err}</div>}
        <button className="btn btn-primary btn-lg btn-block" disabled={busy} onClick={submit}>{busy ? "處理中…" : (tab === "login" ? "登入" : "註冊並登入")}</button>
      </div>
    </div>
  </div>;
}
window.PageAuth = PageAuth;
