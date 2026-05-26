<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>我的歷史紀錄 · 新北食指南</title>
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
const { useState, useRef, useEffect } = React;

const TABS = [
  { key: "search", label: "搜尋紀錄" },
  { key: "wheel", label: "輪盤紀錄" }
];

function filtersFromJson(value) {
  const filter = parseMaybeJson(value, {});
  const entries = Object.entries(filter).filter(([, v]) => v !== null && v !== "" && v !== undefined);
  if (entries.length === 0) return ["未設定篩選"];
  return entries.map(([key, value]) => {
    if (Array.isArray(value)) return `${key}: ${value.join("、")}`;
    if (typeof value === "object") return `${key}: ${JSON.stringify(value)}`;
    return `${key}: ${value}`;
  });
}

function mapSearch(item) {
  return {
    id: `s${item.search_id}`,
    time: formatDateTime(item.searched_at),
    area: item.address || "未提供地址",
    filters: filtersFromJson(item.filter_json),
  };
}

function mapWheel(item) {
  const conditions = parseMaybeJson(item.conditions_json, {});
  return {
    id: `w${item.wheel_id}`,
    time: formatDateTime(item.spun_at),
    name: item.name || `餐廳 #${item.restaurant_id}`,
    cat: conditions.category || conditions.cat || conditions.district || "輪盤結果",
    restaurantId: item.restaurant_id,
  };
}

function HistoryPage() {
  const [tab, setTab] = useState("search");
  const [searchHistory, setSearchHistory] = useState([]);
  const [wheelHistory, setWheelHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const tabsRef = useRef(null);
  const [underline, setUnderline] = useState({ left: 0, width: 0 });

  useEffect(() => {
    async function loadHistory() {
      setLoading(true);
      setErr("");
      try {
        await requireCurrentUser();
        const data = await apiRequest("history/list.php?limit=50");
        setSearchHistory((data.search_history || []).map(mapSearch));
        setWheelHistory((data.wheel_history || []).map(mapWheel));
      } catch (error) {
        if (error.status !== 401) setErr(readableError(error, "讀取歷史紀錄失敗"));
      } finally {
        setLoading(false);
      }
    }
    loadHistory();
  }, []);

  useEffect(() => {
    const el = tabsRef.current?.querySelector('[data-active="true"]');
    if (el) {
      const parent = el.parentElement.getBoundingClientRect();
      const r = el.getBoundingClientRect();
      setUnderline({ left: r.left - parent.left, width: r.width });
    }
  }, [tab]);

  const list = tab === "search" ? searchHistory : wheelHistory;

  return (
    <>
      <Nav active="history"/>
      <main className="page">
        <h1 className="page-title">我的歷史紀錄</h1>
        <p className="page-sub">回顧最近的搜尋與輪盤結果</p>

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

        {err && (
          <div className="warn-banner">
            <Icon name="warn" size={18}/> {err}
          </div>
        )}

        {loading ? (
          <div className="empty">
            <div className="empty-icon"><Icon name="history" size={42} stroke={1.5}/></div>
            <h3 className="empty-text">讀取紀錄中…</h3>
          </div>
        ) : list.length === 0 ? (
          <div className="empty">
            <div className="empty-icon"><Icon name="history" size={42} stroke={1.5}/></div>
            <h3 className="empty-text">尚無紀錄</h3>
            <p className="empty-sub">使用搜尋或輪盤功能後，會自動記錄在此</p>
          </div>
        ) : (
          <div className="timeline">
            {tab === "search" && searchHistory.map(item => (
              <div className="tl-item" key={item.id}>
                <div>
                  <div className="tl-time">{item.time}</div>
                  <div className="tl-content">
                    搜尋了「<strong>{item.area}</strong>」
                    {item.filters.map((f, i) => (
                      <span className="pill" key={i}>{f}</span>
                    ))}
                  </div>
                </div>
                <button className="btn btn-outline btn-sm">
                  <Icon name="refresh" size={14}/> 再次搜尋
                </button>
              </div>
            ))}
            {tab === "wheel" && wheelHistory.map(item => (
              <div className="tl-item" key={item.id}>
                <div>
                  <div className="tl-time">{item.time}</div>
                  <div className="tl-content">
                    <Icon name="target" size={15} style={{verticalAlign: "-2px", color: "var(--color-primary)", marginRight: 6}}/>
                    抽中了「<strong>{item.name}</strong>」<span className="pill">{item.cat}</span>
                  </div>
                </div>
                <button className="btn btn-outline btn-sm">查看餐廳 →</button>
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
