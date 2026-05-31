<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>登入 | 新北美食地圖</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css?v=3"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" crossorigin="anonymous"></script>
<script type="text/babel" src="../assets/js/shared.jsx?v=3"></script>
<script type="text/babel">
const { useState } = React;

function LoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(e) {
    e.preventDefault();
    if (!username.trim() || !password) {
      setErr("請輸入帳號與密碼");
      return;
    }

    setLoading(true);
    setErr("");
    try {
      const res = await fetch("../api/auth/login.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        credentials: "same-origin",
        body: JSON.stringify({
          username: username.trim(),
          password,
        }),
      });
      const payload = await res.json();

      if (!payload.ok) {
        setErr(payload.error?.message || "登入失敗，請確認帳號密碼");
        return;
      }

      window.location.href = "profile.php";
    } catch (error) {
      setErr("無法連線到登入 API，請確認 PHP 伺服器正在執行");
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <Nav active="" loggedIn={false}/>
      <div className="auth-wrap">
        <form className="auth-card" onSubmit={submit} noValidate>
          <h1 className="auth-title">登入</h1>
          <p className="auth-sub">使用已註冊帳號登入，系統會建立 PHP session。</p>

          {err && (
            <div className="form-error-banner">
              <Icon name="error" size={16}/> {err}
            </div>
          )}

          <div className="field" style={{marginBottom: 16}}>
            <label className="label" htmlFor="li-user">帳號</label>
            <input id="li-user"
              className={"input" + (err && !username.trim() ? " is-error" : "")}
              type="text"
              autoComplete="username"
              placeholder="請輸入帳號"
              value={username}
              onChange={e => { setUsername(e.target.value); if (err) setErr(""); }}
            />
          </div>

          <div className="field" style={{marginBottom: 22}}>
            <label className="label" htmlFor="li-pw">密碼</label>
            <input id="li-pw"
              className={"input" + (err && !password ? " is-error" : "")}
              type="password"
              autoComplete="current-password"
              placeholder="請輸入密碼"
              value={password}
              onChange={e => { setPassword(e.target.value); if (err) setErr(""); }}
            />
          </div>

          <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={loading}>
            {loading ? "登入中..." : "登入"}
          </button>

          <div className="auth-divider">還沒有帳號？</div>
          <div className="auth-foot">
            <a href="register.php">前往註冊</a>
          </div>
        </form>
      </div>
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<LoginPage/>);
</script>
</body>
</html>
