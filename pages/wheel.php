<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>不知道吃什麼？來轉吧 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css"/>
</head>
<body>
<div id="root"></div>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="../assets/js/restaurants.js"></script>
<script type="text/babel" src="../assets/js/shared.jsx"></script>
<script type="text/babel">
const { useState, useMemo, useRef } = React;

const SECTORS = 8;
const CX = 200, CY = 200, R = 200, LABEL_R = 128;

function sectorPath(i, total = SECTORS) {
  const a0 = (i / total) * 2*Math.PI - Math.PI/2;
  const a1 = ((i+1) / total) * 2*Math.PI - Math.PI/2;
  const x0 = CX + R*Math.cos(a0), y0 = CY + R*Math.sin(a0);
  const x1 = CX + R*Math.cos(a1), y1 = CY + R*Math.sin(a1);
  return `M${CX},${CY} L${x0.toFixed(2)},${y0.toFixed(2)} A${R},${R} 0 0 1 ${x1.toFixed(2)},${y1.toFixed(2)} Z`;
}

function sectorColor(i) {
  // warm-leaning HSL palette, evenly spaced
  const hue = (i * 360 / SECTORS + 18) % 360;
  return `hsl(${hue}, 58%, 74%)`;
}

function truncate(s, n) {
  return s.length > n ? s.slice(0, n) + "…" : s;
}

function shuffle(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function filterPool(catSel, distSel, kmSel) {
  return window.WHEEL_POOL.filter(r => {
    if (catSel !== "不限" && r.cat !== catSel) return false;
    if (distSel !== "不限" && r.dist !== distSel) return false;
    if (kmSel !== "不限") {
      const max = kmSel === "500m" ? 0.5 : kmSel === "1km" ? 1 : 3;
      if (r.km > max) return false;
    }
    return true;
  });
}

function WheelPage() {
  const [cat, setCat] = useState("不限");
  const [dist, setDist] = useState("不限");
  const [km, setKm] = useState("不限");

  const [candidates, setCandidates] = useState(() => shuffle(window.WHEEL_POOL).slice(0, 8));
  const [spinning, setSpinning] = useState(false);
  const [rotation, setRotation] = useState(0);
  const [result, setResult] = useState(null);
  const [highlightIdx, setHighlightIdx] = useState(-1);
  const [favorited, setFavorited] = useState(false);
  const [warn, setWarn] = useState("");

  const wheelRef = useRef(null);

  function findCandidates() {
    const matches = filterPool(cat, dist, km);
    if (matches.length === 0) {
      setWarn("沒有符合條件的餐廳，請放寬條件再試試");
      return;
    }
    if (matches.length < 8) {
      setWarn(`符合條件的餐廳只有 ${matches.length} 家，請放寬條件以獲得 8 個選項`);
      // still fill 8 slots by repeating
      const filled = [];
      for (let i = 0; i < 8; i++) filled.push(matches[i % matches.length]);
      setCandidates(shuffle(filled));
    } else {
      setWarn("");
      setCandidates(shuffle(matches).slice(0, 8));
    }
    setResult(null);
    setHighlightIdx(-1);
    setFavorited(false);
  }

  function spin() {
    if (spinning || candidates.length < 8) return;
    setResult(null);
    setHighlightIdx(-1);
    setFavorited(false);
    setSpinning(true);

    const winner = Math.floor(Math.random() * 8);
    const sectorDeg = 360 / 8;
    // jitter inside the sector ±18°
    const jitter = (Math.random() - 0.5) * (sectorDeg - 6);
    // Need to bring center of `winner` sector under top pointer.
    // Sector i center (CW from top) = (i + 0.5) * sectorDeg.
    // We rotate the wheel CCW by that amount → negative.
    const baseSpins = 6 * 360;
    const target = baseSpins - (winner * sectorDeg + sectorDeg/2) - jitter;
    // ensure monotonic increase from current rotation
    const currentMod = ((rotation % 360) + 360) % 360;
    const delta = baseSpins + ((360 - currentMod) % 360) - (winner * sectorDeg + sectorDeg/2) - jitter;
    const next = rotation + delta;
    setRotation(next);

    setTimeout(() => {
      setSpinning(false);
      setHighlightIdx(winner);
      setResult(candidates[winner]);
      // pulse highlight
      setTimeout(() => setHighlightIdx(-1), 1200);
    }, 4050);
  }

  function spinAgain() {
    spin();
  }

  return (
    <>
      <Nav active="wheel"/>
      <main className="page">
        <h1 className="page-title">不知道吃什麼？來轉吧！</h1>
        <p className="page-sub">選幾個條件，把選擇權交給命運的轉盤</p>

        <div className="wheel-filter">
          <div className="field">
            <label className="label">想吃什麼類型</label>
            <select className="select" value={cat} onChange={e => setCat(e.target.value)}>
              <option>不限</option>
              {window.CATEGORIES.map(c => <option key={c}>{c}</option>)}
            </select>
          </div>
          <div className="field">
            <label className="label">在哪個區</label>
            <select className="select" value={dist} onChange={e => setDist(e.target.value)}>
              <option>不限</option>
              {window.DISTRICTS.map(d => <option key={d}>{d}</option>)}
            </select>
          </div>
          <div className="field">
            <label className="label">距離我多遠</label>
            <select className="select" value={km} onChange={e => setKm(e.target.value)}>
              <option>不限</option>
              <option>500m</option>
              <option>1km</option>
              <option>3km</option>
            </select>
          </div>
          <button className="btn btn-secondary btn-lg" onClick={findCandidates}>
            <Icon name="search" size={16}/> 找出候選餐廳
          </button>
        </div>

        {warn && (
          <div className="warn-banner">
            <Icon name="warn" size={18}/> {warn}
          </div>
        )}

        <div className="wheel-stage">
          <div className="wheel-wrap">
            <div className="wheel-pointer"/>
            <svg className="wheel-svg" ref={wheelRef}
                 viewBox="0 0 400 400"
                 style={{ transform: `rotate(${rotation}deg)` }}>
              <defs>
                <filter id="hi" x="-20%" y="-20%" width="140%" height="140%">
                  <feGaussianBlur stdDeviation="2"/>
                </filter>
              </defs>
              <circle cx={CX} cy={CY} r={R} fill="#fff"/>
              {candidates.map((r, i) => {
                const midAngle = (i + 0.5) * (360 / SECTORS) - 90;
                const rad = midAngle * Math.PI / 180;
                const lx = CX + LABEL_R * Math.cos(rad);
                const ly = CY + LABEL_R * Math.sin(rad);
                const isHi = highlightIdx === i;
                const text = truncate(r?.name || "", 7);
                return (
                  <g key={i}>
                    <path d={sectorPath(i)}
                          fill={sectorColor(i)}
                          stroke="#fff"
                          strokeWidth="3"
                          style={{
                            filter: isHi ? "brightness(1.18) saturate(1.3)" : "none",
                            transition: "filter 0.2s"
                          }}/>
                    <text x={lx} y={ly}
                          fill="#2D2D2D"
                          fontSize="15"
                          fontWeight="600"
                          fontFamily="Noto Sans TC"
                          textAnchor="middle"
                          dominantBaseline="middle"
                          transform={`rotate(${midAngle + 90}, ${lx}, ${ly})`}
                          style={{ pointerEvents: "none" }}>
                      {text}
                    </text>
                  </g>
                );
              })}
              <circle cx={CX} cy={CY} r="44" fill="#fff" stroke="#FBE5DA" strokeWidth="2"/>
            </svg>
            <button className="wheel-spin-btn" onClick={spin} disabled={spinning || candidates.length < 8}>
              {spinning ? "轉動中…" : "轉動！"}
            </button>
          </div>

          {result && !spinning && (
            <div className="result-card">
              <p className="result-eyebrow">🎉 今天就吃這家！</p>
              <h2 className="result-name">{result.name}</h2>
              <div style={{display: "flex", justifyContent: "center", gap: 8, marginBottom: 14, flexWrap: "wrap"}}>
                <span className="tag">{result.cat}</span>
                <span className="tag tag-green">{result.dist}</span>
              </div>
              <div className="result-meta">
                <span className="result-meta-item"><Icon name="mapPin" size={14}/> {result.addr}</span>
                <span className="result-meta-item"><Icon name="nav" size={14}/> 距離 {result.km} km</span>
              </div>
              <div className="result-actions">
                <button className="btn btn-secondary">
                  <Icon name="nav" size={16}/> 導航
                </button>
                <button
                  className={favorited ? "btn btn-primary" : "btn btn-outline"}
                  onClick={() => setFavorited(f => !f)}
                  style={favorited ? {background: "var(--color-favorite)"} : null}
                >
                  <Icon name="heart" size={16}
                        style={{ fill: favorited ? "currentColor" : "none" }}/>
                  {favorited ? "已收藏" : "收藏"}
                </button>
                <button className="btn btn-outline" onClick={spinAgain} disabled={spinning}>
                  <Icon name="refresh" size={16}/> 再轉一次
                </button>
              </div>
            </div>
          )}

          {!result && !spinning && (
            <p style={{color: "var(--color-text-light)", fontSize: 14, margin: 0}}>
              準備好就按下中間的「轉動！」鍵吧 ✨
            </p>
          )}
        </div>
      </main>
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<WheelPage/>);
</script>
</body>
</html>
