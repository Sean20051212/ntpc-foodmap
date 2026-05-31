<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>註冊 | 新北美食地圖</title>
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

function RegisterPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [done, setDone] = useState(false);

  const usernameError = (submitted || username)
    && (username.trim().length < 3 || username.trim().length > 50)
    ? "帳號需要 3 到 50 個字元"
    : "";
  const passwordError = (submitted || password) && password.length < 8
    ? "密碼至少需要 8 個字元"
    : "";
  const confirmError = (submitted || confirmPassword) && confirmPassword !== password
    ? "兩次密碼不一致"
    : "";

  async function submit(e) {
    e.preventDefault();
    setSubmitted(true);
    setError("");

    if (usernameError || passwordError || confirmError) return;

    setLoading(true);
    try {
      const res = await fetch("../api/auth/register.php", {
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
        setError(payload.error?.message || "註冊失敗，請稍後再試");
        return;
      }

      setDone(true);
      setTimeout(() => {
        window.location.href = "profile.php";
      }, 900);
    } catch (err) {
      setError("無法連線到註冊 API，請確認 PHP 伺服器正在執行");
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <Nav active="" loggedIn={false}/>
      <div className="auth-wrap">
        {done ? (
          <div className="auth-card" style={{textAlign: "center"}}>
            <h1 className="auth-title" style={{textAlign: "center"}}>註冊成功</h1>
            <p className="auth-sub" style={{textAlign: "center"}}>帳號已寫入資料庫，正在前往會員資料頁。</p>
          </div>
        ) : (
          <form className="auth-card" onSubmit={submit} noValidate>
            <h1 className="auth-title">註冊帳號</h1>
            <p className="auth-sub">建立帳號後，系統會直接寫入 users 資料表。</p>

            {error && (
              <div className="form-error-banner">
                <Icon name="error" size={16}/> {error}
              </div>
            )}

            <div className="field" style={{marginBottom: 14}}>
              <div className="field-row">
                <label className="label" htmlFor="rg-u">帳號</label>
                <span className="label-hint">3-50 字元</span>
              </div>
              <input id="rg-u"
                className={"input" + (usernameError ? " is-error" : "")}
                type="text"
                value={username}
                placeholder="例如 foodie_2026"
                autoComplete="username"
                onChange={e => setUsername(e.target.value)}
              />
              <div className="field-msg">{usernameError}</div>
            </div>

            <div className="field" style={{marginBottom: 14}}>
              <div className="field-row">
                <label className="label" htmlFor="rg-p">密碼</label>
                <span className="label-hint">至少 8 字元</span>
              </div>
              <input id="rg-p"
                className={"input" + (passwordError ? " is-error" : "")}
                type="password"
                value={password}
                placeholder="請輸入密碼"
                autoComplete="new-password"
                onChange={e => setPassword(e.target.value)}
              />
              <div className="field-msg">{passwordError}</div>
            </div>

            <div className="field" style={{marginBottom: 22}}>
              <label className="label" htmlFor="rg-c">確認密碼</label>
              <input id="rg-c"
                className={"input" + (confirmError ? " is-error" : "")}
                type="password"
                value={confirmPassword}
                placeholder="請再次輸入密碼"
                autoComplete="new-password"
                onChange={e => setConfirmPassword(e.target.value)}
              />
              <div className="field-msg">{confirmError}</div>
            </div>

            <button className="btn btn-primary btn-block btn-lg" type="submit" disabled={loading}>
              {loading ? "註冊中..." : "註冊"}
            </button>

            <div className="auth-divider">已經有帳號？</div>
            <div className="auth-foot">
              <a href="login.php">前往登入</a>
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
