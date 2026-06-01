<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>我的歷史紀錄 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css?v=3"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script type="text/babel" src="../assets/js/shared.jsx?v=3"></script>
<script type="text/babel">
const { useEffect, useRef, useState } = React;

const WHEEL_HISTORY_KEY = "ntpcFoodmapWheelHistory";
const SEARCH_HISTORY_KEY = "ntpcFoodmapSearchHistory";

const TABS = [
  { key: "search", label: "搜尋紀錄" },
  { key: "wheel", label: "輪盤紀錄" }
];

function readHistory(key) {
  try {
    const data = JSON.parse(localStorage.getItem(key) || "[]");
    return Array.isArray(data) ? data : [];
  } catch {
    return [];
  }
}

function formatTime(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const pad = number => String(number).padStart(2, "0");
  return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function HistoryPage() {
  const [tab, setTab] = useState("search");
  const [searchHistory, setSearchHistory] = useState([]);
  const [wheelHistory, setWheelHistory] = useState([]);
  const tabsRef = useRef(null);
  const [underline, setUnderline] = useState({ left: 0, width: 0 });

  function reloadHistory() {
    setSearchHistory(readHistory(SEARCH_HISTORY_KEY));
    setWheelHistory(readHistory(WHEEL_HISTORY_KEY));
  }

  useEffect(() => {
    reloadHistory();

    function onStorage(event) {
      if (!event.key || event.key === WHEEL_HISTORY_KEY || event.key === SEARCH_HISTORY_KEY) {
        reloadHistory();
      }
    }
    window.addEventListener("storage", onStorage);
    return () => window.removeEventListener("storage", onStorage);
  }, []);

  useEffect(() => {
    const el = tabsRef.current?.querySelector('[data-active="true"]');
    if (el) {
      const parent = el.parentElement.getBoundingClientRect();
      const r = el.getBoundingClientRect();
      setUnderline({ left: r.left - parent.left, width: r.width });
    }
  }, [tab, searchHistory.length, wheelHistory.length]);

  function clearCurrent() {
    if (tab === "search") {
      localStorage.removeItem(SEARCH_HISTORY_KEY);
    } else {
      localStorage.removeItem(WHEEL_HISTORY_KEY);
    }
    reloadHistory();
  }

  const list = tab === "search" ? searchHistory : wheelHistory;

  return (
    <>
      <Nav active="history"/>
      <main className="page">
        <div style={{display: "flex", justifyContent: "space-between", alignItems: "flex-end", gap: 12, flexWrap: "wrap"}}>
          <div>
            <h1 className="page-title">我的歷史紀錄</h1>
            <p className="page-sub">回顧最近的搜尋與輪盤結果</p>
          </div>
          {list.length > 0 && (
            <button className="btn btn-outline btn-sm" onClick={clearCurrent}>
              <Icon name="close" size={14}/> 清空目前紀錄
            </button>
          )}
        </div>

        <div className="tabs" ref={tabsRef}>
          {TABS.map(t => (
            <button
              key={t.key}
              data-active={tab === t.key}
              className={"tab" + (tab === t.key ? " active" : "")}
              onClick={() => setTab(t.key)}
            >
              {t.label}
              <span style={{
                marginLeft: 8,
                background: tab === t.key ? "var(--color-primary-light)" : "var(--color-bg)",
                color: tab === t.key ? "var(--color-primary)" : "var(--color-text-muted)",
                fontSize: 12, padding: "2px 8px", borderRadius: 999,
                fontFamily: "var(--font-en)", fontWeight: 600
              }}>
                {t.key === "search" ? searchHistory.length : wheelHistory.length}
              </span>
            </button>
          ))}
          <span className="tab-underline" style={{ left: underline.left, width: underline.width }}/>
        </div>

        {list.length === 0 ? (
          <div className="empty">
            <div className="empty-icon"><Icon name="history" size={42} stroke={1.5}/></div>
            <h3 className="empty-text">尚無紀錄</h3>
            <p className="empty-sub">
              {tab === "wheel" ? "轉動輪盤後，抽中的餐廳會自動記錄在這裡。" : "目前尚未儲存搜尋紀錄。"}
            </p>
          </div>
        ) : (
          <div className="timeline">
            {tab === "search" && searchHistory.map(item => (
              <div className="tl-item" key={item.id}>
                <div>
                  <div className="tl-time">{formatTime(item.time)}</div>
                  <div className="tl-content">
                    搜尋 <strong>{item.area || "新北市"}</strong>
                    {(item.filters || []).map((f, i) => (
                      <span className="pill" key={i}>{f}</span>
                    ))}
                  </div>
                </div>
                <a className="btn btn-outline btn-sm" href="index.php">
                  <Icon name="refresh" size={14}/> 重新搜尋
                </a>
              </div>
            ))}
            {tab === "wheel" && wheelHistory.map(item => (
              <div className="tl-item" key={item.id || `${item.restaurant_id}-${item.time}`}>
                <div>
                  <div className="tl-time">{formatTime(item.time)}</div>
                  <div className="tl-content">
                    <Icon name="target" size={15} style={{verticalAlign: "-2px", color: "var(--color-primary)", marginRight: 6}}/>
                    輪盤抽中 <strong>{item.name}</strong>
                    {item.cat && <span className="pill">{item.cat}</span>}
                  </div>
                </div>
                {item.restaurant_id ? (
                  <a className="btn btn-outline btn-sm" href={`restaurant_detail.php?id=${item.restaurant_id}`}>
                    查看詳細
                  </a>
                ) : (
                  <a className="btn btn-outline btn-sm" href="wheel.php">
                    再轉一次
                  </a>
                )}
              </div>
            ))}
          </div>
        )}
      </main>
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<HistoryPage/>);
</script>
</body>
</html>
