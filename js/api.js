/* =====================================================================
   api.js — 前端唯一允許的「邏輯」：fetch wrapper + 統一錯誤處理（frontend-plan §6）
   原型版：body 改呼叫 MockBackend；正式版只要把 MockBackend.handle 換成
   fetch(path,{method,credentials:'same-origin',...}) 即可，介面不變。
   ===================================================================== */
class ApiError extends Error {
  constructor(code, message, http) { super(message); this.code = code; this.http = http; }
}

async function api(method, path, params) {
  // 正式：const res = await fetch(path+query, {method, credentials:'same-origin', headers, body});
  //       const json = await res.json();
  const json = await window.MockBackend.handle(method, path, params);
  if (!json.ok) throw new ApiError(json.error.code, json.error.message);
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
