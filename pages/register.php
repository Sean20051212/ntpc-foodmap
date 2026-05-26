<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>註冊 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="../assets/js/api-client.js"></script>
<script type="text/babel" src="../assets/js/shared.jsx?v=2"></script>
<script type="text/babel">
const { useState } = React;

function RegisterPage() {
  const [u, setU] = useState("");
  const [p, setP] = useState("");
  const [c, setC] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [done, setDone] = useState(false);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

  const usernamePattern = /^[A-Za-z0-9_]{3,50}$/;
  const uErr = (submitted || u) && !usernamePattern.test(u) ? "帳號需為 3-50 字元，只能使用英文、數字或底線" : "";
  const pErr = (submitted || p) && p && p.length < 8 ? "密碼至少需 8 字元" : "";
  const cErr = (submitted || c) && c && c !== p ? "兩次密碼不一致" : "";

  async function submit(e) {
    e.preventDefault();
    setSubmitted(true);
    setErr("");
    if (!usernamePattern.test(u)) return;
    if (p.length < 8) return;
    if (c !== p) return;
    setLoading(true);
    try {
      await apiRequest("auth/register.php", {
        method: "POST",
        body: { username: u.trim(), password: p }
      });
      setDone(true);
      setTimeout(() => { window.location.href = "profile.php"; }, 1200);
    } catch (error) {
      setErr(readableError(error, "註冊失敗，請稍後再試"));
      setLoading(false);
    }
  }

  return (
    <>
      <Nav active="" loggedIn={false}/>
      <div className="auth-wrap">
        {done ? (
          <div className="auth-card" style={{textAlign: "center"}}>
            <div style={{
              width: 64, height: 64, margin: "0 auto 16px",
              background: "#E4EDE6", borderRadius: "50%",
              display: "grid", placeItems: "center", color: "var(--color-secondary)"
            }}>
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="4 12 10 18 20 6"/>
              </svg>
            </div>
            <h1 className="auth-title" style={{textAlign: "center"}}>註冊成功！</h1>
            <p className="auth-sub" style={{textAlign: "center"}}>即將為你跳轉到個人資料頁…</p>
          </div>
        ) : (
          <form className="auth-card" onSubmit={submit} noValidate>
            <h1 className="auth-title">註冊新帳號</h1>
            <p className="auth-sub">加入新北食指南，收藏你的最愛餐廳</p>

            {err && (
              <div className="form-error-banner">
                <Icon name="error" size={16}/> {err}
              </div>
            )}

            <div className="field" style={{marginBottom: 14}}>
              <div className="field-row">
                <label className="label" htmlFor="rg-u">帳號</label>
                <span className="label-hint">3-50 字元</span>
              </div>
              <input id="rg-u"
                className={"input" + (uErr ? " is-error" : "")}
                type="text" value={u} placeholder="例如：foodie_2026"
                onChange={e => setU(e.target.value)}
              />
              <div className="field-msg">{uErr}</div>
            </div>

            <div className="field" style={{marginBottom: 14}}>
              <div className="field-row">
                <label className="label" htmlFor="rg-p">密碼</label>
                <span className="label-hint">至少 8 字元</span>
              </div>
              <input id="rg-p"
                className={"input" + (pErr ? " is-error" : "")}
                type="password" value={p} placeholder="請設定密碼"
                onChange={e => setP(e.target.value)}
              />
              <div className="field-msg">{pErr}</div>
            </div>

            <div className="field" style={{marginBottom: 22}}>
              <label className="label" htmlFor="rg-c">確認密碼</label>
              <input id="rg-c"
                className={"input" + (cErr ? " is-error" : "")}
                type="password" value={c} placeholder="請再次輸入密碼"
                onChange={e => setC(e.target.value)}
              />
              <div className="field-msg">{cErr}</div>
            </div>

            <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={loading}>
              {loading ? "註冊中…" : "註冊"}
            </button>

            <div className="auth-divider">已有帳號？</div>
            <div className="auth-foot">
              <a href="login.php">立即登入 →</a>
            </div>
          </form>
        )}
      </div>
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<RegisterPage/>);
</script>
</body>
</html>
