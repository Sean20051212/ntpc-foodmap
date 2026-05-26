<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>我的歷史紀錄 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css?v=2"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script type="text/babel" src="../assets/js/shared.jsx?v=2"></script>
<script type="text/babel">
const { useState, useRef, useEffect } = React;

const SEARCH_HISTORY = [
  { id: "s1", time: "2026/05/24 14:30", area: "板橋區", filters: ["中式料理", "1km 內"] },
  { id: "s2", time: "2026/05/23 19:12", area: "新店區", filters: ["鍋物", "3km 內"] },
  { id: "s3", time: "2026/05/22 12:45", area: "永和區", filters: ["台式小吃"] },
  { id: "s4", time: "2026/05/20 18:03", area: "淡水區", filters: ["日式料理", "不限距離"] },
  { id: "s5", time: "2026/05/18 13:50", area: "中和區", filters: ["異國料理", "500m 內"] },
  { id: "s6", time: "2026/05/15 11:22", area: "蘆洲區", filters: ["西式料理", "1km 內"] }
];

const WHEEL_HISTORY = [
  { id: "w1", time: "2026/05/24 12:08", name: "鼎泰豐 (板橋大遠百店)", cat: "中式料理" },
  { id: "w2", time: "2026/05/22 18:40", name: "大紅袍麻辣鴛鴦鍋", cat: "鍋物" },
  { id: "w3", time: "2026/05/19 13:15", name: "壽司屋", cat: "日式料理" },
  { id: "w4", time: "2026/05/17 19:55", name: "Gino Pizza 義式窯烤", cat: "西式料理" },
  { id: "w5", time: "2026/05/14 12:30", name: "大胖子豆腐冰淇淋", cat: "甜點" }
];

const TABS = [
  { key: "search", label: "搜尋紀錄" },
  { key: "wheel", label: "輪盤紀錄" }
];

function HistoryPage() {
  const [tab, setTab] = useState("search");
  const tabsRef = useRef(null);
  const [underline, setUnderline] = useState({ left: 0, width: 0 });

  useEffect(() => {
    const el = tabsRef.current?.querySelector('[data-active="true"]');
    if (el) {
      const parent = el.parentElement.getBoundingClientRect();
      const r = el.getBoundingClientRect();
      setUnderline({ left: r.left - parent.left, width: r.width });
    }
  }, [tab]);

  const list = tab === "search" ? SEARCH_HISTORY : WHEEL_HISTORY;

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
                {t.key === "search" ? SEARCH_HISTORY.length : WHEEL_HISTORY.length}
              </span>
            </button>
          ))}
          <span className="tab-underline" style={{ left: underline.left, width: underline.width }}/>
        </div>

        {list.length === 0 ? (
          <div className="empty">
            <div className="empty-icon"><Icon name="history" size={42} stroke={1.5}/></div>
            <h3 className="empty-text">尚無紀錄</h3>
            <p className="empty-sub">使用搜尋或輪盤功能後，會自動記錄在此</p>
          </div>
        ) : (
          <div className="timeline">
            {tab === "search" && SEARCH_HISTORY.map(item => (
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
            {tab === "wheel" && WHEEL_HISTORY.map(item => (
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
