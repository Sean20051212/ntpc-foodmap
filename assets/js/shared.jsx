/* shared.jsx — Nav, Footer, Icons used across all pages.
   Loaded via <script type="text/babel" src="shared.jsx"></script>
   Exposes globals on window so per-page scripts can use them. */

const { useState, useEffect, useRef, useMemo } = React;

/* ---------- Icons ---------- */
const Icon = ({ name, size = 18, stroke = 2, className = "", style }) => {
  const paths = {
    chopsticks: <g><path d="M5 20 L19 6"/><path d="M5 14 L13 6"/><path d="M3 22 L7 18"/></g>,
    home: <g><path d="M3 11 L12 3 L21 11"/><path d="M5 10 L5 21 L19 21 L19 10"/></g>,
    target: <g><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></g>,
    heart: <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>,
    history: <g><path d="M3 12 a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 9 8 9"/><polyline points="12 7 12 12 15.5 14"/></g>,
    user: <g><circle cx="12" cy="8" r="4"/><path d="M4 21 c0-4.4 3.6-8 8-8 s8 3.6 8 8"/></g>,
    chev: <polyline points="6 9 12 15 18 9"/>,
    menu: <g><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></g>,
    close: <g><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></g>,
    mapPin: <g><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></g>,
    clock: <g><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></g>,
    inbox: <g><polyline points="3 13 8 13 10 16 14 16 16 13 21 13"/><path d="M5 3 L19 3 L21 13 L21 19 L3 19 L3 13 Z"/></g>,
    search: <g><circle cx="11" cy="11" r="7"/><line x1="16" y1="16" x2="21" y2="21"/></g>,
    refresh: <g><polyline points="21 4 21 10 15 10"/><path d="M20.5 15 A9 9 0 1 1 18.5 6.4 L21 9"/></g>,
    nav: <g><polygon points="3 11 22 2 13 21 11 13 3 11"/></g>,
    sparkle: <g><path d="M12 3 L13.5 9 L20 10.5 L13.5 12 L12 18 L10.5 12 L4 10.5 L10.5 9 Z"/></g>,
    logout: <g><path d="M9 21 H5 a2 2 0 0 1-2-2 V5 a2 2 0 0 1 2-2 h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></g>,
    warn: <g><path d="M12 3 L22 20 L2 20 Z"/><line x1="12" y1="10" x2="12" y2="14"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></g>,
    info: <g><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="8" r="0.5" fill="currentColor"/></g>,
    error: <g><circle cx="12" cy="12" r="9"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></g>
  };
  return (
    <svg
      width={size} height={size} viewBox="0 0 24 24"
      fill={name === "heart" ? "currentColor" : "none"}
      stroke="currentColor" strokeWidth={stroke}
      strokeLinecap="round" strokeLinejoin="round"
      className={className} style={style}
    >
      {paths[name]}
    </svg>
  );
};

/* ---------- Nav ---------- */
const NAV_LINKS = [
  { href: "index.php", label: "首頁", key: "home", icon: "home" },
  { href: "wheel.php", label: "輪盤", key: "wheel", icon: "target" },
  { href: "favorites.php", label: "我的收藏", key: "favorites", icon: "heart" },
  { href: "history.php", label: "我的歷史", key: "history", icon: "history" }
];

function Nav({ active, loggedIn = true, userName = "陳小美" }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const menuRef = useRef(null);

  async function logout() {
    try {
      if (window.apiRequest) {
        await window.apiRequest("auth/logout.php", { method: "POST" });
      }
    } finally {
      sessionStorage.removeItem("userLocation");
      window.location.href = "login.php";
    }
  }

  useEffect(() => {
    function onClick(e) {
      if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false);
    }
    document.addEventListener("click", onClick);
    return () => document.removeEventListener("click", onClick);
  }, []);

  return (
    <header className="nav">
      <div className="nav-inner">
        <a className="nav-logo" href="index.php">
          <span className="nav-logo-mark"><Icon name="chopsticks" size={18} stroke={2.2}/></span>
          <span>新北食指南</span>
        </a>

        <nav className="nav-links">
          {NAV_LINKS.map(l => (
            <a key={l.key}
               href={l.href}
               className={"nav-link" + (active === l.key ? " active" : "")}>
              {l.label}
            </a>
          ))}
        </nav>

        <div className="nav-auth">
          {loggedIn ? (
            <div className="nav-user" onClick={() => setMenuOpen(o => !o)} ref={menuRef}>
              <span className="nav-user-avatar">{userName.slice(-2,-1) || "美"}</span>
              <span>{userName}</span>
              <Icon name="chev" size={14} stroke={2.5}/>
              {menuOpen && (
                <div className="nav-user-menu" onClick={e => e.stopPropagation()}>
                  <div className="nav-user-menu-item" style={{color: "var(--color-text-muted)", fontSize: 12}}>
                    已登入帳號
                  </div>
                  <a href="profile.php" className="nav-user-menu-item" onClick={(e) => e.stopPropagation()}>
                    <Icon name="user" size={15}/>個人資料
                  </a>
                  <button type="button" className="nav-user-menu-item" onClick={logout} style={{color: "var(--color-error)", width: "100%", background: "transparent", border: 0, textAlign: "left"}}>
                    <Icon name="logout" size={15}/>登出
                  </button>
                </div>
              )}
            </div>
          ) : (
            <>
              <a href="login.php" className="nav-auth-link">登入</a>
              <div className="nav-auth-divider"/>
              <a href="register.php" className="nav-auth-primary">註冊</a>
            </>
          )}
        </div>

        <button className="nav-burger" onClick={() => setMobileOpen(o => !o)} aria-label="menu">
          <Icon name={mobileOpen ? "close" : "menu"} size={22}/>
        </button>
      </div>

      {mobileOpen && (
        <div className="nav-mobile-menu">
          {NAV_LINKS.map(l => (
            <a key={l.key}
               href={l.href}
               className={"nav-link" + (active === l.key ? " active" : "")}>
              {l.label}
            </a>
          ))}
          <div className="nav-mobile-auth">
            {loggedIn ? (
              <>
                <a href="profile.php" className="btn btn-outline btn-block">
                  <Icon name="user" size={16}/>個人資料
                </a>
                <button className="btn btn-outline btn-block" onClick={logout}>
                  <Icon name="logout" size={16}/>登出 ({userName})
                </button>
              </>
            ) : (
              <>
                <a href="login.php" className="btn btn-outline">登入</a>
                <a href="register.php" className="btn btn-primary">註冊</a>
              </>
            )}
          </div>
        </div>
      )}
    </header>
  );
}

/* ---------- Footer ---------- */
function Footer() {
  return (
    <footer className="footer">
      <div className="footer-inner">
        <span>© 2026 新北食指南 · New Taipei Food Guide</span>
        <span>資料來源：新北市政府觀光旅遊局開放資料</span>
      </div>
    </footer>
  );
}

Object.assign(window, { Nav, Footer, Icon });
