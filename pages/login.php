<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>登入 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script type="text/babel" src="../assets/js/shared.jsx"></script>
<script type="text/babel">
const { useState } = React;

function LoginPage() {
  const [user, setUser] = useState("");
  const [pw, setPw] = useState("");
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

  function submit(e) {
    e.preventDefault();
    if (!user.trim() || !pw) {
      setErr("請輸入帳號和密碼");
      return;
    }
    setLoading(true);
    setErr("");
    setTimeout(() => {
      if (pw === "wrong") {
        setErr("帳號或密碼錯誤");
        setLoading(false);
      } else {
        window.location.href = "favorites.php";
      }
    }, 700);
  }

  return (
    <>
      <Nav active="" loggedIn={false}/>
      <div className="auth-wrap">
        <form className="auth-card" onSubmit={submit} noValidate>
          <h1 className="auth-title">登入</h1>
          <p className="auth-sub">歡迎回來，繼續探索新北美食</p>

          {err && (
            <div className="form-error-banner">
              <Icon name="error" size={16}/> {err}
            </div>
          )}

          <div className="field" style={{marginBottom: 16}}>
            <label className="label" htmlFor="li-user">帳號</label>
            <input id="li-user"
              className={"input" + (err && !user.trim() ? " is-error" : "")}
              type="text"
              autoComplete="username"
              placeholder="請輸入帳號"
              value={user}
              onChange={e => { setUser(e.target.value); if (err) setErr(""); }}
            />
          </div>

          <div className="field" style={{marginBottom: 22}}>
            <label className="label" htmlFor="li-pw">密碼</label>
            <input id="li-pw"
              className={"input" + (err && !pw ? " is-error" : "")}
              type="password"
              autoComplete="current-password"
              placeholder="請輸入密碼"
              value={pw}
              onChange={e => { setPw(e.target.value); if (err) setErr(""); }}
            />
          </div>

          <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={loading}>
            {loading ? "登入中…" : "登入"}
          </button>

          <div className="auth-divider">還沒有帳號？</div>
          <div className="auth-foot">
            <a href="register.php">立即註冊 →</a>
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
