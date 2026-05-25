<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>新北食指南 · 設計總覽</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
  html, body { margin: 0; padding: 0; background: #EFE9DC; font-family: "Noto Sans TC", sans-serif; }
  .frame-iframe {
    border: 0;
    display: block;
    background: #FAF6F0;
  }
  .quick-links {
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    background: #fff;
    border-radius: 999px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.10);
    padding: 6px;
    display: flex;
    gap: 4px;
    z-index: 1000;
    border: 1px solid #E8E2D6;
  }
  .quick-links a {
    text-decoration: none;
    color: #2D2D2D;
    font-size: 13px;
    font-weight: 500;
    padding: 7px 14px;
    border-radius: 999px;
    transition: background 0.15s, color 0.15s;
  }
  .quick-links a:hover { background: #FBE5DA; color: #E87A4F; }
  .quick-links .brand {
    background: #E87A4F;
    color: #fff;
    padding: 7px 14px 7px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .quick-links .brand:hover { background: #D66A40; color: #fff; }
  .quick-links .sep { width: 1px; background: #E8E2D6; margin: 4px 2px; }
</style>
</head>
<body>

<div class="quick-links">
  <a class="brand" href="login.php" target="_blank">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 20 L19 6"/><path d="M5 14 L13 6"/><path d="M3 22 L7 18"/></svg>
    新北食指南
  </a>
  <div class="sep"></div>
  <a href="login.php" target="_blank">登入</a>
  <a href="register.php" target="_blank">註冊</a>
  <a href="favorites.php" target="_blank">收藏</a>
  <a href="history.php" target="_blank">歷史</a>
  <a href="wheel.php" target="_blank">輪盤</a>
  <a href="profile.php" target="_blank">個人資料</a>
</div>

<div id="canvas-root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script type="text/babel" src="../assets/js/design-canvas.jsx"></script>
<script type="text/babel">
const { DesignCanvas, DCSection, DCArtboard } = window;

// Each page presented at desktop (1280) and mobile (375).
// Heights tuned to show full content; iframes scroll if needed but artboards are sized to fit nicely.
const PAGES = [
  { id: "login",      file: "login.php",      label: "login.php" },
  { id: "register",   file: "register.php",   label: "register.php" },
  { id: "favorites",  file: "favorites.php",  label: "favorites.php" },
  { id: "history",    file: "history.php",    label: "history.php" },
  { id: "wheel",      file: "wheel.php",      label: "wheel.php" },
  { id: "profile",    file: "profile.php",    label: "profile.php" }
];

const DESKTOP_W = 1280;
const MOBILE_W = 375;

const HEIGHTS = {
  login:     { d: 820,  m: 700 },
  register:  { d: 820,  m: 880 },
  favorites: { d: 1280, m: 1180 },
  history:   { d: 820,  m: 980 },
  wheel:     { d: 1340, m: 1320 },
  profile:   { d: 720,  m: 760 }
};

function Frame({ file, w, h }) {
  return (
    <iframe
      className="frame-iframe"
      src={file}
      style={{ width: w, height: h }}
      title={file}
      loading="lazy"
    />
  );
}

function Showcase() {
  return (
    <DesignCanvas title="新北食指南 — 設計總覽" subtitle="5 個頁面 × Desktop 1280 / Mobile 375 · 點擊任一畫板可開全螢幕預覽">
      <DCSection id="overview-desktop" title="Desktop  ·  1280 px">
        {PAGES.map(p => (
          <DCArtboard
            key={"d-" + p.id}
            id={"d-" + p.id}
            label={p.label}
            width={DESKTOP_W}
            height={HEIGHTS[p.id].d}
          >
            <Frame file={p.file} w={DESKTOP_W} h={HEIGHTS[p.id].d}/>
          </DCArtboard>
        ))}
      </DCSection>

      <DCSection id="overview-mobile" title="Mobile  ·  375 px">
        {PAGES.map(p => (
          <DCArtboard
            key={"m-" + p.id}
            id={"m-" + p.id}
            label={p.label}
            width={MOBILE_W}
            height={HEIGHTS[p.id].m}
          >
            <Frame file={p.file} w={MOBILE_W} h={HEIGHTS[p.id].m}/>
          </DCArtboard>
        ))}
      </DCSection>
    </DesignCanvas>
  );
}

ReactDOM.createRoot(document.getElementById("canvas-root")).render(<Showcase/>);
</script>
</body>
</html>
