<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>個人資料 · 新北食指南</title>
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
// TODO(C): 替換為 fetch('../api/auth/me.php')
const MOCK_USER = {
  username: "chen_xiaomei",
  displayName: "陳小美",
  createdAt: "2026-01-15 09:00:00"
};

function InfoRow({ label, value, mono }) {
  return (
    <div style={{
      display: "flex",
      justifyContent: "space-between",
      alignItems: "center",
      padding: "14px 0",
      borderBottom: "1px solid var(--color-border)",
      gap: 16
    }}>
      <span style={{fontSize: 14, color: "var(--color-text-muted)", flexShrink: 0}}>{label}</span>
      <span style={{
        fontSize: 15,
        color: "var(--color-text)",
        fontFamily: mono ? "var(--font-en)" : "inherit",
        letterSpacing: mono ? "0.04em" : "normal",
        textAlign: "right",
        wordBreak: "break-all"
      }}>{value}</span>
    </div>
  );
}

function ProfilePage() {
  const u = MOCK_USER;
  const avatarChar = u.displayName.slice(-2, -1) || "美";
  const joinedDate = u.createdAt.split(" ")[0].replace(/-/g, "/");

  function logout() {
    // TODO(C): call ../api/auth/logout.php
    sessionStorage.removeItem("userLocation");
    window.location.href = "login.php";
  }

  return (
    <>
      <Nav active="" loggedIn={true} userName={u.displayName}/>
      <main className="page">
        <h1 className="page-title">個人資料</h1>
        <p className="page-sub">查看你的帳號資訊</p>

        <div className="auth-card" style={{maxWidth: 560, margin: "0 auto", padding: "32px"}}>
          <div style={{
            display: "flex",
            alignItems: "center",
            gap: 20,
            marginBottom: 28,
            paddingBottom: 24,
            borderBottom: "1px solid var(--color-border)"
          }}>
            <div style={{
              width: 72,
              height: 72,
              borderRadius: "50%",
              background: "var(--color-secondary)",
              color: "#fff",
              display: "grid",
              placeItems: "center",
              fontSize: 28,
              fontWeight: 600,
              flexShrink: 0
            }}>{avatarChar}</div>
            <div style={{minWidth: 0}}>
              <h2 style={{fontSize: 22, margin: 0, fontWeight: 600, letterSpacing: "0.02em"}}>
                {u.displayName}
              </h2>
              <p style={{
                fontSize: 14,
                color: "var(--color-text-muted)",
                margin: "4px 0 0",
                fontFamily: "var(--font-en)"
              }}>@{u.username}</p>
            </div>
          </div>

          <div>
            <InfoRow label="帳號" value={u.username} mono/>
            <InfoRow label="密碼" value="••••••••"/>
            <InfoRow label="加入時間" value={joinedDate} mono/>
          </div>

          <div className="warn-banner" style={{
            background: "#F3F0E8",
            border: "1px solid var(--color-border)",
            color: "var(--color-text-muted)",
            marginTop: 22,
            marginBottom: 0,
            fontSize: 13
          }}>
            <Icon name="info" size={16}/>
            為了帳號安全，密碼僅以雜湊形式儲存，無法顯示原文。
          </div>

          <div style={{
            marginTop: 24,
            display: "flex",
            justifyContent: "flex-end"
          }}>
            <button className="btn btn-outline" onClick={logout}>
              <Icon name="logout" size={16}/>
              登出
            </button>
          </div>
        </div>
      </main>
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<ProfilePage/>);
</script>
</body>
</html>
