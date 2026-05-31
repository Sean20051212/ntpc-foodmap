<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>個人資料 | 新北美食地圖</title>
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
const { useEffect, useState } = React;

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
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function loadProfile() {
      try {
        const res = await fetch("../api/users/profile.php", {
          credentials: "same-origin",
        });
        const payload = await res.json();

        if (!payload.ok) {
          if (payload.error?.code === "invalid_input") {
            window.location.href = "login.php";
            return;
          }
          setError(payload.error?.message || "讀取個人資料失敗");
          return;
        }

        setUser(payload.data.user);
      } catch (err) {
        setError("無法連線到會員資料 API");
      } finally {
        setLoading(false);
      }
    }

    loadProfile();
  }, []);

  async function logout() {
    try {
      await fetch("../api/auth/logout.php", {
        method: "POST",
        credentials: "same-origin",
      });
    } finally {
      sessionStorage.removeItem("userLocation");
      window.location.href = "login.php";
    }
  }

  const displayName = user?.username || "會員";
  const avatarChar = displayName.slice(0, 1).toUpperCase();
  const joinedDate = user?.created_at ? user.created_at.split(" ")[0].replace(/-/g, "/") : "-";

  return (
    <>
      <Nav active="" loggedIn={!!user} userName={displayName} onLogout={logout}/>
      <main className="page">
        <h1 className="page-title">個人資料</h1>
        <p className="page-sub">查看目前登入帳號與資料庫中的會員資訊。</p>

        <div className="auth-card" style={{maxWidth: 560, margin: "0 auto", padding: "32px"}}>
          {loading ? (
            <p className="auth-sub" style={{textAlign: "center", margin: 0}}>讀取中...</p>
          ) : error ? (
            <div className="form-error-banner">
              <Icon name="error" size={16}/> {error}
            </div>
          ) : (
            <>
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
                    {displayName}
                  </h2>
                  <p style={{
                    fontSize: 14,
                    color: "var(--color-text-muted)",
                    margin: "4px 0 0",
                    fontFamily: "var(--font-en)"
                  }}>@{user.username}</p>
                </div>
              </div>

              <div>
                <InfoRow label="使用者 ID" value={user.user_id} mono/>
                <InfoRow label="帳號" value={user.username} mono/>
                <InfoRow label="權限" value={Number(user.is_admin) === 1 ? "管理員" : "一般會員"}/>
                <InfoRow label="評論數" value={user.review_count ?? 0} mono/>
                <InfoRow label="註冊日期" value={joinedDate} mono/>
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
                此頁資料由目前登入 session 讀取，不再使用前端假資料。
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
            </>
          )}
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
