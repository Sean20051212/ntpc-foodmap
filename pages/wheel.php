<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$stmt = db()->query(
    'SELECT r.restaurant_id AS id, r.name, r.address, r.latitude, r.longitude,
            d.district_name,
            COALESCE(GROUP_CONCAT(DISTINCT t.tag_name ORDER BY t.tag_name SEPARATOR "、"), "餐廳") AS category,
            COALESCE(AVG(rv.rating), 0) AS rating
       FROM restaurants r
       LEFT JOIN districts d ON d.zipcode = r.zipcode
       LEFT JOIN restaurant_tags_mapping rtm ON rtm.restaurant_id = r.restaurant_id
       LEFT JOIN tags t ON t.tag_id = rtm.tag_id
       LEFT JOIN reviews rv ON rv.restaurant_id = r.restaurant_id
      GROUP BY r.restaurant_id, r.name, r.address, r.latitude, r.longitude, d.district_name
      ORDER BY r.restaurant_id'
);

$wheelPool = [];
$categories = [];
foreach ($stmt->fetchAll() as $restaurant) {
    $category = (string) ($restaurant['category'] ?: '餐廳');
    foreach (explode('、', $category) as $tag) {
        $tag = trim($tag);
        if ($tag !== '') {
            $categories[$tag] = $tag;
        }
    }

    $wheelPool[] = [
        'id' => (int) $restaurant['id'],
        'name' => (string) $restaurant['name'],
        'cat' => $category,
        'dist' => (string) ($restaurant['district_name'] ?? '新北市'),
        'lng' => (float) $restaurant['longitude'],
        'lat' => (float) $restaurant['latitude'],
        'rating' => round((float) $restaurant['rating'], 1),
        'addr' => (string) $restaurant['address'],
    ];
}
ksort($categories);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>不知道吃什麼？來轉吧 · 新北食指南</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assets/css/styles.css?v=2"/>
</head>
<body>
<div id="root"></div>

<script>
window.WHEEL_POOL = <?php echo json_encode($wheelPool, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.CATEGORIES = <?php echo json_encode(array_values($categories), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="../assets/js/api-client.js"></script>
<script type="text/babel" src="../assets/js/shared.jsx?v=2"></script>
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

function getUserLocation() {
  try {
    const raw = sessionStorage.getItem("userLocation");
    if (!raw) return null;
    const loc = JSON.parse(raw);
    if (typeof loc.lat !== "number" || typeof loc.lng !== "number") return null;
    return loc;
  } catch (e) { return null; }
}

function haversineKm(lat1, lng1, lat2, lng2) {
  const R = 6371;
  const toRad = d => d * Math.PI / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng/2)**2;
  return 2 * R * Math.asin(Math.sqrt(a));
}

function attachDistance(restaurants, userLoc) {
  if (!userLoc) return restaurants;
  return restaurants.map(r => ({
    ...r,
    distanceKm: haversineKm(userLoc.lat, userLoc.lng, r.lat, r.lng)
  }));
}

function categoryMatches(restaurant, catSet) {
  if (catSet.size === 0) return true;
  const cats = String(restaurant.cat || "").split("、");
  return cats.some(cat => catSet.has(cat));
}

function filterPool(catSet, kmSel, ratingMin, userLoc) {
  return window.WHEEL_POOL.filter(r => {
    if (!categoryMatches(r, catSet)) return false;
    if (ratingMin > 0 && (r.rating || 0) < ratingMin) return false;
    if (kmSel !== "不限" && userLoc) {
      const max = kmSel === "500m" ? 0.5 : kmSel === "1km" ? 1 : 3;
      const d = haversineKm(userLoc.lat, userLoc.lng, r.lat, r.lng);
      if (d > max) return false;
    }
    return true;
  });
}

function fillToSectors(matches, userLoc) {
  if (matches.length === 0) return [];
  const filled = [];
  for (let i = 0; i < SECTORS; i++) filled.push(matches[i % matches.length]);
  return attachDistance(shuffle(filled), userLoc);
}

const RATING_OPTIONS = [
  { label: "不限", value: 0 },
  { label: "兩星以上", value: 2 },
  { label: "三星以上", value: 3 },
  { label: "四星以上", value: 4 }
];

function MultiSelectCategories({ options, value, onChange, placeholder = "不限（全部類型）" }) {
  const [open, setOpen] = React.useState(false);
  const ref = React.useRef(null);

  React.useEffect(() => {
    function onDoc(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  function toggle(opt) {
    const next = new Set(value);
    if (next.has(opt)) next.delete(opt); else next.add(opt);
    onChange(next);
  }
  function clear() { onChange(new Set()); }
  function selectAll() { onChange(new Set(options)); }

  const arr = Array.from(value);
  const isEmpty = arr.length === 0;
  const shown = arr.slice(0, 2);
  const extra = arr.length - shown.length;

  return (
    <div className="multiselect" ref={ref}>
      <button type="button"
              className={"multiselect-trigger" + (open ? " is-open" : "")}
              onClick={() => setOpen(o => !o)}
              aria-expanded={open}>
        {isEmpty ? (
          <span className="multiselect-placeholder">{placeholder}</span>
        ) : (
          <>
            {shown.map(c => <span className="multiselect-chip" key={c}>{c}</span>)}
            {extra > 0 && <span className="multiselect-chip-more">+{extra}</span>}
          </>
        )}
      </button>
      {open && (
        <div className="multiselect-panel" role="listbox">
          <div className="multiselect-toolbar">
            <span>已選 {arr.length} / {options.length}</span>
            <div style={{display: "flex", gap: 12}}>
              <button type="button" onClick={selectAll}>全選</button>
              <button type="button" onClick={clear} style={{color: "var(--color-text-muted)"}}>清空</button>
            </div>
          </div>
          {options.map(opt => (
            <label className="multiselect-option" key={opt}>
              <input type="checkbox"
                     checked={value.has(opt)}
                     onChange={() => toggle(opt)}/>
              <span>{opt}</span>
            </label>
          ))}
        </div>
      )}
    </div>
  );
}

function WheelPage() {
  const userLoc = useMemo(() => getUserLocation(), []);
  const initialCandidates = useMemo(() => fillToSectors(window.WHEEL_POOL, userLoc), [userLoc]);
  const [cats, setCats] = useState(() => new Set());
  const [ratingMin, setRatingMin] = useState(0);
  const [km, setKm] = useState("不限");
  const [candidates, setCandidates] = useState(initialCandidates);
  const [spinning, setSpinning] = useState(false);
  const [cooling, setCooling] = useState(false);
  const [rotation, setRotation] = useState(0);
  const [result, setResult] = useState(null);
  const [highlightIdx, setHighlightIdx] = useState(-1);
  const [favorited, setFavorited] = useState(false);
  const [warn, setWarn] = useState("");
  const [actionMsg, setActionMsg] = useState("");
  const [spunHistory, setSpunHistory] = useState([]);
  const [excludeSpun, setExcludeSpun] = useState(false);

  const wheelRef = useRef(null);
  const spinIdRef = useRef(0);

  function findCandidates() {
    let matches = filterPool(cats, km, ratingMin, userLoc);
    if (excludeSpun && spunHistory.length > 0) {
      const seen = new Set(spunHistory.map(r => r.id));
      matches = matches.filter(r => !seen.has(r.id));
    }
    if (matches.length === 0) {
      setWarn("沒有符合條件的餐廳，請放寬條件再試試");
      return;
    }
    if (matches.length < SECTORS) {
      setWarn(`符合條件的餐廳只有 ${matches.length} 家，將重複填滿轉盤`);
      setCandidates(fillToSectors(matches, userLoc));
    } else {
      setWarn("");
      setCandidates(attachDistance(shuffle(matches).slice(0, SECTORS), userLoc));
    }
    setResult(null);
    setHighlightIdx(-1);
    setFavorited(false);
    setActionMsg("");
  }

  async function recordWheelResult(restaurant) {
    if (!restaurant?.id || !window.apiRequest) return;
    try {
      await apiRequest("history/record_wheel.php", {
        method: "POST",
        body: {
          restaurant_id: restaurant.id,
          conditions: {
            categories: Array.from(cats),
            rating_min: ratingMin,
            distance: km,
          }
        }
      });
    } catch (error) {
      if (error.status !== 401) {
        setActionMsg(readableError(error, "輪盤紀錄儲存失敗"));
      }
    }
  }

  async function toggleFavorite() {
    if (!result?.id) return;
    try {
      await apiRequest(favorited ? "favorites/remove.php" : "favorites/add.php", {
        method: "POST",
        body: { restaurant_id: result.id }
      });
      setFavorited(!favorited);
      setActionMsg(favorited ? "已移除收藏" : "已加入收藏");
    } catch (error) {
      if (error.status === 401) {
        redirectToLogin(window.location.pathname + window.location.search);
        return;
      }
      setActionMsg(readableError(error, "收藏操作失敗"));
    }
  }

  function spin() {
    if (spinning || cooling || candidates.length < SECTORS) return;
    const mySpinId = ++spinIdRef.current;
    setResult(null);
    setHighlightIdx(-1);
    setFavorited(false);
    setActionMsg("");
    setSpinning(true);

    const winner = Math.floor(Math.random() * SECTORS);
    const sectorDeg = 360 / SECTORS;
    const jitter = (Math.random() - 0.5) * (sectorDeg - 6);
    const baseSpins = 6 * 360;
    const currentMod = ((rotation % 360) + 360) % 360;
    const delta = baseSpins + ((360 - currentMod) % 360) - (winner * sectorDeg + sectorDeg/2) - jitter;
    const next = rotation + delta;
    setRotation(next);

    setTimeout(() => {
      setSpinning(false);
      setCooling(true);
      setHighlightIdx(winner);
      const won = candidates[winner];
      setResult(won);
      recordWheelResult(won);

      const nextHistory = [won, ...spunHistory.filter(r => r.id !== won.id)].slice(0, 30);
      setSpunHistory(nextHistory);

      setTimeout(() => {
        setCooling(false);
        if (spinIdRef.current !== mySpinId) return;
        setHighlightIdx(-1);
        if (excludeSpun) {
          const pool = filterPool(cats, km, ratingMin, userLoc);
          const historyIds = new Set(nextHistory.map(r => r.id));
          const currentIds = new Set(candidates.map(c => c.id));
          let eligible = pool.filter(r => !historyIds.has(r.id) && !currentIds.has(r.id));
          if (eligible.length === 0) eligible = pool.filter(r => !historyIds.has(r.id));
          if (eligible.length > 0) {
            const shuffled = shuffle(eligible);
            setCandidates(prev => {
              const updated = prev.slice();
              let pickIdx = 0;
              for (let i = 0; i < updated.length; i++) {
                if (updated[i].id === won.id) {
                  const pick = shuffled[pickIdx % shuffled.length];
                  updated[i] = userLoc
                    ? { ...pick, distanceKm: haversineKm(userLoc.lat, userLoc.lng, pick.lat, pick.lng) }
                    : pick;
                  pickIdx++;
                }
              }
              return updated;
            });
          }
        }
      }, 1200);
    }, 4050);
  }

  return (
    <>
      <Nav active="wheel"/>
      <main className="page">
        <h1 className="page-title">不知道吃什麼？來轉吧！</h1>
        <p className="page-sub">選幾個條件，把選擇權交給命運的轉盤</p>

        <p style={{margin: "-18px 0 24px"}}>
          {userLoc ? (
            <span className="tag">
              <Icon name="mapPin" size={12} style={{verticalAlign: "-1px", marginRight: 4}}/>
              以「{userLoc.address || "目前位置"}」為基準計算距離
            </span>
          ) : (
            <span className="tag tag-green">
              <Icon name="info" size={12} style={{verticalAlign: "-1px", marginRight: 4}}/>
              尚未設定地址，距離篩選暫不可用。先到<a href="index.php" style={{color: "inherit", textDecoration: "underline"}}>首頁</a>搜尋
            </span>
          )}
        </p>

        <div className="wheel-filter">
          <div className="field">
            <label className="label">想吃什麼類型</label>
            <MultiSelectCategories
              options={window.CATEGORIES}
              value={cats}
              onChange={setCats}
            />
          </div>
          <div className="field">
            <label className="label">評分</label>
            <select className="select" value={ratingMin} onChange={e => setRatingMin(Number(e.target.value))}>
              {RATING_OPTIONS.map(o => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </div>
          <div className="field">
            <label className="label">距離我多遠</label>
            <select className="select" value={km} onChange={e => setKm(e.target.value)} disabled={!userLoc}>
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

        {window.WHEEL_POOL.length === 0 ? (
          <div className="empty">
            <div className="empty-icon"><Icon name="target" size={42} stroke={1.5}/></div>
            <h3 className="empty-text">目前沒有餐廳資料</h3>
          </div>
        ) : (
        <div className="wheel-layout">
          <aside className="wheel-side">
            <button
              type="button"
              className={"wheel-side-toggle" + (excludeSpun ? " is-on" : "")}
              onClick={() => setExcludeSpun(v => !v)}
              aria-pressed={excludeSpun}
            >
              <span style={{display: "flex", alignItems: "center", gap: 8}}>
                <Icon name="refresh" size={15}/>
                排除抽取過餐廳
              </span>
              <span className="switch" aria-hidden="true"/>
            </button>

            <div className="wheel-side-head">
              <h3 className="wheel-side-title">已抽取餐廳</h3>
              <span className="wheel-side-count">{spunHistory.length}</span>
            </div>

            {spunHistory.length === 0 ? (
              <div className="wheel-side-empty">
                <Icon name="history" size={28} stroke={1.5}/>
                <div>還沒有抽中任何餐廳</div>
                <div style={{fontSize: 12, marginTop: 4}}>轉動輪盤後會記錄在這裡</div>
              </div>
            ) : (
              <>
                <div style={{display: "flex", justifyContent: "flex-end", marginBottom: 6}}>
                  <button className="wheel-side-clear" onClick={() => setSpunHistory([])}>
                    清空紀錄
                  </button>
                </div>
                <div className="wheel-side-list">
                  {spunHistory.map(r => (
                    <article className="rcard is-mini" key={r.id}>
                      <span className="tag">{r.cat}</span>
                      <h4 className="rcard-name">{r.name}</h4>
                      <div className="rcard-meta">
                        <div className="rcard-meta-row is-clamp">
                          <Icon name="mapPin" size={13}/>
                          <span>{r.dist} · {r.addr}</span>
                        </div>
                        {r.rating > 0 && (
                          <div className="rcard-meta-row">
                            <Icon name="sparkle" size={13}/>
                            <span>評分 {r.rating.toFixed(1)} / 5.0</span>
                          </div>
                        )}
                      </div>
                      <div className="rcard-foot">
                        <button
                          className="heart-btn"
                          aria-label="從紀錄移除"
                          title="從紀錄移除"
                          onClick={() => setSpunHistory(prev => prev.filter(x => x.id !== r.id))}
                          style={{color: "var(--color-text-muted)"}}
                        >
                          <Icon name="close" size={18}/>
                        </button>
                        <a className="btn btn-outline btn-sm" href={`restaurant_detail.php?id=${r.id}`}>查看詳情 →</a>
                      </div>
                    </article>
                  ))}
                </div>
              </>
            )}
          </aside>

          <div className="wheel-stage">
          <div className="wheel-wrap">
            <div className="wheel-pointer"/>
            <svg className="wheel-svg" ref={wheelRef}
                 viewBox="0 0 400 400"
                 style={{ transform: `rotate(${rotation}deg)` }}>
              <circle cx={CX} cy={CY} r={R} fill="#fff"/>
              {candidates.map((r, i) => {
                const midAngle = (i + 0.5) * (360 / SECTORS) - 90;
                const rad = midAngle * Math.PI / 180;
                const lx = CX + LABEL_R * Math.cos(rad);
                const ly = CY + LABEL_R * Math.sin(rad);
                const isHi = highlightIdx === i;
                const text = truncate(r?.name || "", 7);
                return (
                  <g key={`${r.id}-${i}`}>
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
            <button className="wheel-spin-btn" onClick={spin} disabled={spinning || cooling || candidates.length < SECTORS}>
              {spinning ? "轉動中…" : "轉動！"}
            </button>
          </div>

          {result && !spinning && (
            <div className="result-card">
              <p className="result-eyebrow">今天就吃這家！</p>
              <h2 className="result-name">{result.name}</h2>
              <div style={{display: "flex", justifyContent: "center", gap: 8, marginBottom: 14, flexWrap: "wrap"}}>
                <span className="tag">{result.cat}</span>
                <span className="tag tag-green">{result.dist}</span>
                {result.rating > 0 && (
                  <span className="tag" style={{background: "#FFF4DA", color: "#A06A12"}}>
                    ★ {result.rating.toFixed(1)}
                  </span>
                )}
              </div>
              <div className="result-meta">
                <span className="result-meta-item"><Icon name="mapPin" size={14}/> {result.addr}</span>
                {result.distanceKm !== undefined && (
                  <span className="result-meta-item"><Icon name="nav" size={14}/> 距離 {result.distanceKm.toFixed(1)} km</span>
                )}
              </div>
              <div className="result-actions">
                <a className="btn btn-secondary" href={`https://www.google.com/maps/dir/?api=1&destination=${result.lat},${result.lng}`} target="_blank" rel="noopener noreferrer">
                  <Icon name="nav" size={16}/> 導航
                </a>
                <button
                  className={favorited ? "btn btn-primary" : "btn btn-outline"}
                  onClick={toggleFavorite}
                  style={favorited ? {background: "var(--color-favorite)"} : null}
                >
                  <Icon name="heart" size={16}
                        style={{ fill: favorited ? "currentColor" : "none" }}/>
                  {favorited ? "已收藏" : "收藏"}
                </button>
                <a className="btn btn-outline" href={`restaurant_detail.php?id=${result.id}`}>
                  查看詳情
                </a>
                <button className="btn btn-outline" onClick={spin} disabled={spinning || cooling}>
                  <Icon name="refresh" size={16}/> 再轉一次
                </button>
              </div>
              {actionMsg && (
                <p style={{fontSize: 13, color: "var(--color-text-muted)", margin: "12px 0 0"}}>
                  {actionMsg}
                </p>
              )}
            </div>
          )}

          {!result && !spinning && (
            <p style={{color: "var(--color-text-light)", fontSize: 14, margin: 0}}>
              準備好就按下中間的「轉動！」鍵吧
            </p>
          )}
        </div>
        </div>
        )}
      </main>
      <Footer/>
    </>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<WheelPage/>);
</script>
</body>
</html>
