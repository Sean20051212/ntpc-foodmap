/* =====================================================================
   api.js — 前端唯一允許的「邏輯」：fetch wrapper + 統一錯誤處理（frontend-plan §6）
   ===================================================================== */
class ApiError extends Error {
  constructor(code, message, http) { super(message); this.code = code; this.http = http; }
}

// 將 params 物件展開成 PHP 風格的 query string（陣列用 key[]=v1&key[]=v2）
function buildQuery(params) {
  const parts = [];
  for (const [k, v] of Object.entries(params || {})) {
    if (v === undefined || v === null || v === "") continue;
    if (Array.isArray(v)) {
      v.forEach(item => parts.push(`${encodeURIComponent(k)}[]=${encodeURIComponent(item)}`));
    } else {
      parts.push(`${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
    }
  }
  return parts.join("&");
}

async function api(method, path, params) {
  params = params || {};
  // 前端用 "/api/xxx/yyy" 表達端點；實際 PHP 檔案為 "api/xxx/yyy.php"（相對於部署根）
  let url = path.replace(/^\//, "") + ".php";
  const opts = { method, credentials: "same-origin", headers: {} };

  if (method === "GET" || method === "DELETE") {
    const qs = buildQuery(params);
    if (qs) url += "?" + qs;
  } else {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(params);
  }

  let res;
  try {
    res = await fetch(url, opts);
  } catch (e) {
    throw new ApiError("network_error", "網路連線失敗，請稍後再試", 0);
  }

  let json;
  try {
    json = await res.json();
  } catch (e) {
    throw new ApiError("bad_response", `伺服器回應格式錯誤 (HTTP ${res.status})`, res.status);
  }

  if (!json.ok) {
    const err = json.error || { code: "unknown", message: "未知錯誤" };
    throw new ApiError(err.code, err.message, res.status);
  }
  return json.data;
}

/* ---------- LocalStorage：只存使用者本地偏好（frontend-plan §4） ---------- */
const Store = {
  // searchHistory: [{type:'keyword'|'address', value, at}]，FIFO 50
  pushSearch(type, value) {
    if (!value || !value.trim()) return;
    let arr = this.searchHistory();
    arr = arr.filter(x => !(x.type === type && x.value === value));
    arr.unshift({ type, value, at: Date.now() });
    if (arr.length > 50) arr = arr.slice(0, 50);
    localStorage.setItem("searchHistory", JSON.stringify(arr));
  },
  searchHistory() { try { return JSON.parse(localStorage.getItem("searchHistory")) || []; } catch (e) { return []; } },

  // geocodeCache: {[address]:{lat,lng,at}}，TTL 24h
  getGeocode(addr) {
    try { const c = JSON.parse(localStorage.getItem("geocodeCache")) || {}; const v = c[addr]; if (v && Date.now() - v.at < 864e5) return v; } catch (e) {}
    return null;
  },
  setGeocode(addr, lat, lng) {
    let c = {}; try { c = JSON.parse(localStorage.getItem("geocodeCache")) || {}; } catch (e) {}
    c[addr] = { lat, lng, at: Date.now() };
    const keys = Object.keys(c); if (keys.length > 50) delete c[keys[0]];
    localStorage.setItem("geocodeCache", JSON.stringify(c));
  }
};

window.api = api; window.ApiError = ApiError; window.Store = Store;
