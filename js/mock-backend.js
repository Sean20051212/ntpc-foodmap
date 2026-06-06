/* =====================================================================
   mock-backend.js — 站在 PHP+MariaDB 後端位置的模擬實作
   所有運算（Haversine 距離、is_open_now、篩選/排序/分頁、輪盤抽選、
   評分平均、推薦、geocode）一律在此完成；前端只 render 回傳 JSON。
   回傳格式：{ok:true,data} 或 {ok:false,error:{code,message}}（對齊 design-audit §8）
   ===================================================================== */
window.MockBackend = (function () {
  const DBKEY = "ntpc_proto_db_v4", SKEY = "ntpc_proto_session_v4";
  let db, session;

  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function load() {
    try { db = JSON.parse(localStorage.getItem(DBKEY)); } catch (e) { db = null; }
    if (!db) { db = clone(window.SEED); localStorage.setItem(DBKEY, JSON.stringify(db)); }
    try { session = JSON.parse(localStorage.getItem(SKEY)) || {}; } catch (e) { session = {}; }
    if (!session.wheel) session.wheel = {};
  }
  function save() { localStorage.setItem(DBKEY, JSON.stringify(db)); }
  function saveSession() { localStorage.setItem(SKEY, JSON.stringify(session)); }

  const ok = (data) => ({ ok: true, data });
  const err = (code, message) => ({ ok: false, error: { code, message } });

  // ---------- helpers (= 後端 SQL / 觸發器 的職責) ----------
  function haversine(la1, lo1, la2, lo2) {
    const R = 6371000, t = Math.PI / 180;
    const dLa = (la2 - la1) * t, dLo = (lo2 - lo1) * t;
    const a = Math.sin(dLa / 2) ** 2 + Math.cos(la1 * t) * Math.cos(la2 * t) * Math.sin(dLo / 2) ** 2;
    return Math.round(R * 2 * Math.asin(Math.sqrt(a)));
  }
  function ratingStats(rid) {
    const rs = db.reviews.filter(r => r.restaurant_id === rid);
    if (!rs.length) return { rating_avg: 0, rating_count: 0 };
    const avg = rs.reduce((s, r) => s + r.rating, 0) / rs.length;
    return { rating_avg: Math.round(avg * 10) / 10, rating_count: rs.length };
  }
  function isOpenNow(r) {
    const now = new Date();
    const day = now.getDay();                  // 0=日..6=六（對齊 schema）
    const sec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
    const toSec = (t) => { const [h, m, s] = t.split(":").map(Number); return h * 3600 + m * 60 + (s || 0); };
    // 排除 sentinel：spec_rec 非 null 不參與營業判斷
    return r.opentime.some(o => o.spec_rec == null && o.day === day && sec >= toSec(o.start_time) && sec <= toSec(o.end_time));
  }
  function mainPhoto(r) { const p = r.photos.find(x => x.is_main) || r.photos[0]; return p ? p.url : null; }
  function tagsOf(r) { return r.tag_ids.map(id => db.tags.find(t => t.tag_id === id)).filter(Boolean).map(t => ({ tag_id: t.tag_id, tag_name: t.tag_name })); }
  function districtName(zip) { const d = db.districts.find(x => x.zipcode === zip); return d ? d.district_name : null; }
  function isFav(rid) { return !!(session.user_id && db.favorites.some(f => f.user_id === session.user_id && f.restaurant_id === rid)); }

  function listCard(r, userLat, userLng) {
    const st = ratingStats(r.restaurant_id);
    const card = {
      restaurant_id: r.restaurant_id, restaurant_name: r.restaurant_name, description: r.description,
      address: r.address, zipcode: r.zipcode, district_name: districtName(r.zipcode),
      latitude: r.latitude, longitude: r.longitude, price_level: r.price_level,
      rating_avg: st.rating_avg, rating_count: st.rating_count,
      main_photo_url: mainPhoto(r), is_open_now: isOpenNow(r), is_favorited: isFav(r.restaurant_id),
      tags: tagsOf(r), distance_m: null
    };
    if (userLat != null && userLng != null) card.distance_m = haversine(+userLat, +userLng, r.latitude, r.longitude);
    return card;
  }

  function asArray(v) { return v == null ? [] : (Array.isArray(v) ? v : String(v).split(",")); }

  // 套用 list / count / wheel 共用的篩選
  function applyFilters(p) {
    const districts = asArray(p.district).map(String).filter(Boolean);
    const tagIds = asArray(p.tag).map(Number).filter(Boolean);
    const minRating = p.min_rating != null ? +p.min_rating : 0;
    const maxDist = p.max_distance_m != null ? +p.max_distance_m : 0;
    const kw = (p.keyword || "").trim();
    let bbox = null;
    if (p.bbox) { const b = String(p.bbox).split(",").map(Number); if (b.length === 4) bbox = b; }
    const uLat = p.user_lat != null ? +p.user_lat : null, uLng = p.user_lng != null ? +p.user_lng : null;

    let rows = db.restaurants.filter(r => {
      if (districts.length && !districts.includes(r.zipcode)) return false;
      if (tagIds.length && !tagIds.some(t => r.tag_ids.includes(t))) return false;
      if (minRating > 0 && ratingStats(r.restaurant_id).rating_avg < minRating) return false;
      if (kw) { const hay = (r.restaurant_name + r.description + r.address).toLowerCase(); if (!hay.includes(kw.toLowerCase())) return false; }
      if (bbox) { const [swLa, swLo, neLa, neLo] = bbox; if (r.latitude < swLa || r.latitude > neLa || r.longitude < swLo || r.longitude > neLo) return false; }
      if (maxDist > 0 && uLat != null && uLng != null) { if (haversine(uLat, uLng, r.latitude, r.longitude) > maxDist) return false; }
      return true;
    });
    return { rows, uLat, uLng };
  }
  function sortRows(cards, sort) {
    const s = sort || "rating_desc";
    if (s === "name_asc") cards.sort((a, b) => a.restaurant_name.localeCompare(b.restaurant_name, "zh-Hant"));
    else if (s === "distance_asc") cards.sort((a, b) => (a.distance_m ?? 9e9) - (b.distance_m ?? 9e9));
    else cards.sort((a, b) => b.rating_avg - a.rating_avg || b.rating_count - a.rating_count);
    return cards;
  }

  // ---------- auth ----------
  function currentUser() { return session.user_id ? db.users.find(u => u.user_id === session.user_id) : null; }
  function publicUser(u) { return u ? { user_id: u.user_id, username: u.username, is_admin: u.is_admin } : null; }

  // ---------- 路由表 ----------
  const routes = {
    "POST /api/auth/register": (p) => {
      const u = (p.username || "").trim(), pw = (p.password || "").trim();
      if (u.length < 3 || u.length > 50) return err("invalid_input", "username 需 3–50 字");
      if (pw.length < 8) return err("invalid_input", "password 至少 8 字");
      if (db.users.some(x => x.username === u)) return err("conflict", "帳號已存在");
      const id = Math.max(...db.users.map(x => x.user_id)) + 1;
      db.users.push({ user_id: id, username: u, password: pw, is_admin: 0, created_at: now() }); save();
      session.user_id = id; saveSession();
      return ok({ user: { user_id: id, username: u, is_admin: 0 } });
    },
    "POST /api/auth/login": (p) => {
      const u = (p.username || "").trim(), pw = (p.password || "").trim();
      const row = db.users.find(x => x.username === u && x.password === pw);
      if (!row) return err("invalid_input", "帳號或密碼錯誤");
      session.user_id = row.user_id; saveSession();
      return ok({ user: publicUser(row) });
    },
    "POST /api/auth/logout": () => { if (!currentUser()) return err("unauthenticated", "請先登入", 401); session.user_id = null; session.wheel = {}; saveSession(); return ok(null); },
    "GET /api/auth/me": () => ok({ user: publicUser(currentUser()) }),
    "POST /api/auth/change_password": (p) => {
      const me = currentUser(); if (!me) return err("unauthenticated", "請先登入");
      if (me.password !== (p.old_password || "")) return err("forbidden", "舊密碼不正確");
      if ((p.new_password || "").length < 8) return err("invalid_input", "新密碼至少 8 字");
      me.password = p.new_password; save(); return ok(null);
    },

    "GET /api/restaurants/list": (p) => {
      const { rows, uLat, uLng } = applyFilters(p);
      let cards = rows.map(r => listCard(r, uLat, uLng));
      sortRows(cards, p.sort);
      const total = cards.length;
      const limit = Math.min(+p.limit || 50, 200), offset = +p.offset || 0;
      return ok({ total, restaurants: cards.slice(offset, offset + limit) });
    },
    "GET /api/restaurants/count": (p) => ok({ total: applyFilters(p).rows.length }),
    "GET /api/restaurants/detail": (p) => {
      const r = db.restaurants.find(x => x.restaurant_id === +p.id);
      if (!r) return err("not_found", "餐廳不存在");
      const st = ratingStats(r.restaurant_id);
      const me = currentUser();
      const mine = me ? db.reviews.find(rv => rv.user_id === me.user_id && rv.restaurant_id === r.restaurant_id) : null;
      const regular = r.opentime.filter(o => o.spec_rec == null).map(o => ({ day: o.day, start_time: o.start_time, end_time: o.end_time }));
      const special = r.opentime.filter(o => o.spec_rec != null).map(o => o.spec_rec);
      return ok({
        restaurant: {
          restaurant_id: r.restaurant_id, restaurant_name: r.restaurant_name, description: r.description,
          address: r.address, zipcode: r.zipcode, district_name: districtName(r.zipcode),
          latitude: r.latitude, longitude: r.longitude, rating_avg: st.rating_avg, rating_count: st.rating_count,
          price_level: r.price_level, google_place_id: r.google_place_id,
          is_open_now: isOpenNow(r), is_favorited: isFav(r.restaurant_id),
          user_review: mine ? { rating: mine.rating, comment: mine.comment } : null,
          photos: clone(r.photos), phones: clone(r.phones),
          opentime_regular: regular, opentime_special: special, tags: tagsOf(r)
        }
      });
    },
    "GET /api/restaurants/recommendations": (p) => {
      let cards = db.restaurants.map(r => listCard(r));
      cards.sort((a, b) => b.rating_avg - a.rating_avg || b.rating_count - a.rating_count);
      return ok({ restaurants: cards.slice(0, +p.limit || 3) });
    },
    "GET /api/restaurants/carousel": (p) => {
      const pics = [];
      db.restaurants.forEach(r => { const m = r.photos.find(x => x.is_main); if (m) pics.push({ url: m.url, restaurant_id: r.restaurant_id, restaurant_name: r.restaurant_name }); });
      shuffle(pics);
      return ok({ photos: pics.slice(0, +p.limit || 10) });
    },
    "GET /api/restaurants/nearby_ntpc": (p) => {
      const lat = +p.lat, lng = +p.lng;
      let cards = db.restaurants.map(r => listCard(r, lat, lng));
      cards.sort((a, b) => a.distance_m - b.distance_m);
      return ok({ restaurants: cards.slice(0, +p.limit || 20) });
    },
    "GET /api/restaurants/wheel_pool": (p) => ok({ restaurant_ids: applyFilters(p).rows.map(r => r.restaurant_id) }),
    "POST /api/restaurants/wheel_draw": (p) => {
      const ids = applyFilters(p).rows.map(r => r.restaurant_id);
      const key = "k" + hash(JSON.stringify([p.district, p.tag, p.min_rating, p.max_distance_m, p.bbox]));
      const drawn = session.wheel[key] || [];
      const pool = ids.filter(id => !drawn.includes(id));
      if (!pool.length) return ok({ exhausted: true, restaurant: null });
      const pick = pool[Math.floor(Math.random() * pool.length)];
      drawn.push(pick); session.wheel[key] = drawn; saveSession();
      const r = db.restaurants.find(x => x.restaurant_id === pick);
      return ok({ exhausted: false, restaurant: listCard(r, p.user_lat, p.user_lng) });
    },
    "POST /api/restaurants/wheel_reset": (p) => {
      const key = "k" + hash(JSON.stringify([p.district, p.tag, p.min_rating, p.max_distance_m, p.bbox]));
      delete session.wheel[key]; saveSession(); return ok(null);
    },

    "POST /api/geo/locate": (p) => locate(+p.lat, +p.lng),
    "POST /api/geo/geocode": (p) => {
      const addr = (p.address || "").trim();
      if (!addr) return err("invalid_input", "缺少地址");
      // 模擬 Google geocode：比對區名，否則預設板橋；前端 LocalStorage 也會快取
      let d = db.districts.find(x => addr.includes(x.district_name)) || db.districts[0];
      const jit = () => (Math.random() - 0.5) * 0.01;
      const lat = d.center_latitude + jit(), lng = d.center_longitude + jit();
      const loc = locate(lat, lng);
      return ok(Object.assign({ lat, lng }, loc.data));
    },

    "GET /api/dicts/districts": () => ok({
      districts: db.districts.map(d => ({
        ...d, adjacent_zipcodes: adjacentOf(d.zipcode)
      }))
    }),
    "GET /api/dicts/tags": () => ok({ tags: clone(db.tags) }),

    "POST /api/favorites/toggle": (p) => {
      const me = currentUser(); if (!me) return err("unauthenticated", "請先登入");
      const rid = +p.restaurant_id;
      const i = db.favorites.findIndex(f => f.user_id === me.user_id && f.restaurant_id === rid);
      let fav; if (i >= 0) { db.favorites.splice(i, 1); fav = false; } else { db.favorites.push({ user_id: me.user_id, restaurant_id: rid }); fav = true; }
      save(); return ok({ is_favorited: fav });
    },
    "GET /api/favorites/list": () => {
      const me = currentUser(); if (!me) return err("unauthenticated", "請先登入");
      const ids = db.favorites.filter(f => f.user_id === me.user_id).map(f => f.restaurant_id);
      const cards = db.restaurants.filter(r => ids.includes(r.restaurant_id)).map(r => listCard(r));
      return ok({ restaurants: cards });
    },

    "POST /api/reviews/upsert": (p) => {
      const me = currentUser(); if (!me) return err("unauthenticated", "請先登入");
      const rid = +p.restaurant_id, rating = +p.rating, comment = (p.comment || "").slice(0, 1000);
      if (!(rating >= 1 && rating <= 5)) return err("invalid_input", "rating 需為 1–5");
      if (!db.restaurants.some(r => r.restaurant_id === rid)) return err("not_found", "餐廳不存在");
      let rv = db.reviews.find(x => x.user_id === me.user_id && x.restaurant_id === rid);
      if (rv) { rv.rating = rating; rv.comment = comment; rv.updated_at = now(); }
      else { rv = { user_id: me.user_id, restaurant_id: rid, rating, comment, created_at: now(), updated_at: now() }; db.reviews.push(rv); }
      save(); return ok({ review: clone(rv) });
    },
    "DELETE /api/reviews/delete": (p) => {
      const me = currentUser(); if (!me) return err("unauthenticated", "請先登入");
      db.reviews = db.reviews.filter(x => !(x.user_id === me.user_id && x.restaurant_id === +p.restaurant_id));
      save(); return ok(null);
    },
    "GET /api/reviews/by_restaurant": (p) => {
      const rid = +p.restaurant_id;
      const all = db.reviews.filter(r => r.restaurant_id === rid).sort((a, b) => b.created_at.localeCompare(a.created_at));
      const limit = +p.limit || 20, offset = +p.offset || 0;
      const reviews = all.slice(offset, offset + limit).map(r => {
        const u = db.users.find(x => x.user_id === r.user_id);
        return { user_id: r.user_id, username: u ? u.username : "(已刪除)", reviewer_total_reviews: db.reviews.filter(x => x.user_id === r.user_id).length, rating: r.rating, comment: r.comment, created_at: r.created_at };
      });
      return ok({ total: all.length, reviews });
    },
    "GET /api/reviews/by_user": (p) => {
      const uid = +p.user_id;
      const all = db.reviews.filter(r => r.user_id === uid).sort((a, b) => b.created_at.localeCompare(a.created_at));
      const limit = +p.limit || 20, offset = +p.offset || 0;
      const reviews = all.slice(offset, offset + limit).map(r => {
        const rest = db.restaurants.find(x => x.restaurant_id === r.restaurant_id);
        return { restaurant_id: r.restaurant_id, restaurant_name: rest ? rest.restaurant_name : "(已刪除)", main_photo_url: rest ? mainPhoto(rest) : null, rating: r.rating, comment: r.comment, created_at: r.created_at };
      });
      return ok({ total: all.length, reviews });
    },

    "GET /api/users/profile": (p) => {
      const u = db.users.find(x => x.user_id === +p.user_id);
      if (!u) return err("not_found", "使用者不存在");
      return ok({ user: { user_id: u.user_id, username: u.username, is_admin: u.is_admin, review_count: db.reviews.filter(r => r.user_id === u.user_id).length, created_at: u.created_at } });
    },

    // ---------- admin ----------
    "POST /api/admin/restaurant/upsert": (p) => adminGuard(() => {
      let r = p.restaurant_id ? db.restaurants.find(x => x.restaurant_id === +p.restaurant_id) : null;
      const fields = ["restaurant_name", "description", "address", "zipcode", "latitude", "longitude", "price_level", "google_place_id"];
      if (!r) {
        const id = Math.max(0, ...db.restaurants.map(x => x.restaurant_id)) + 1;
        r = { restaurant_id: id, photos: [], phones: [], opentime: [], tag_ids: [] };
        db.restaurants.push(r);
      }
      fields.forEach(f => { if (p[f] !== undefined) r[f] = (f === "latitude" || f === "longitude") ? +p[f] : (f === "price_level" ? +p[f] : p[f]); });
      if (p.tags) r.tag_ids = asArray(p.tags).map(Number);
      if (p.phones) r.phones = asArray(p.phones).filter(Boolean);
      save(); return ok({ restaurant_id: r.restaurant_id });
    }),
    "POST /api/admin/restaurant/delete": (p) => adminGuard(() => {
      db.restaurants = db.restaurants.filter(r => r.restaurant_id !== +p.restaurant_id);
      db.reviews = db.reviews.filter(r => r.restaurant_id !== +p.restaurant_id);
      db.favorites = db.favorites.filter(f => f.restaurant_id !== +p.restaurant_id);
      save(); return ok(null);
    }),
    "POST /api/admin/photo/upsert": (p) => adminGuard(() => {
      const r = db.restaurants.find(x => x.restaurant_id === +p.restaurant_id);
      if (!r) return err("not_found", "餐廳不存在");
      if (+p.is_main) r.photos.forEach(ph => ph.is_main = 0);
      if (p.photo_id) { const ph = r.photos.find(x => x.photo_id === +p.photo_id); if (ph) { ph.url = p.url; ph.is_main = +p.is_main ? 1 : 0; } }
      else r.photos.push({ photo_id: Date.now(), restaurant_id: r.restaurant_id, url: p.url, is_main: +p.is_main ? 1 : 0 });
      if (!r.photos.some(ph => ph.is_main) && r.photos.length) r.photos[0].is_main = 1;
      save(); return ok(null);
    }),
    "POST /api/admin/photo/delete": (p) => adminGuard(() => {
      db.restaurants.forEach(r => { r.photos = r.photos.filter(ph => ph.photo_id !== +p.photo_id); if (!r.photos.some(x => x.is_main) && r.photos.length) r.photos[0].is_main = 1; });
      save(); return ok(null);
    }),
    "GET /api/admin/users/list": (p) => adminGuard(() => {
      const kw = (p.keyword || "").trim().toLowerCase();
      let us = db.users.filter(u => !kw || u.username.toLowerCase().includes(kw));
      const users = us.map(u => ({ user_id: u.user_id, username: u.username, is_admin: u.is_admin, review_count: db.reviews.filter(r => r.user_id === u.user_id).length, favorite_count: db.favorites.filter(f => f.user_id === u.user_id).length }));
      return ok({ total: users.length, users });
    }),
    "POST /api/admin/users/promote": (p) => adminGuard(() => { const u = db.users.find(x => x.user_id === +p.user_id); if (!u) return err("not_found", "不存在"); u.is_admin = 1; save(); return ok(null); }),
    "POST /api/admin/users/demote": (p) => adminGuard(() => { if (+p.user_id === 1) return err("forbidden", "不可變更原始管理員"); const u = db.users.find(x => x.user_id === +p.user_id); if (!u) return err("not_found", "不存在"); u.is_admin = 0; save(); return ok(null); }),
    "POST /api/admin/users/delete": (p) => adminGuard(() => {
      if (+p.user_id === 1) return err("forbidden", "不可刪除原始管理員");
      db.users = db.users.filter(x => x.user_id !== +p.user_id);
      db.reviews = db.reviews.filter(r => r.user_id !== +p.user_id);
      db.favorites = db.favorites.filter(f => f.user_id !== +p.user_id);
      save(); return ok(null);
    })
  };

  function adminGuard(fn) { const me = currentUser(); if (!me) return err("unauthenticated", "請先登入"); if (!me.is_admin) return err("forbidden", "需要管理員權限"); return fn(); }
  function locate(lat, lng) {
    let best = null, bestD = Infinity;
    db.districts.forEach(d => { const dist = haversine(lat, lng, d.center_latitude, d.center_longitude); if (dist < bestD) { bestD = dist; best = d; } });
    if (bestD > 15000) return ok({ in_ntpc: false, district: null, adjacent: [] });
    const adj = adjacentOf(best.zipcode).map(z => { const d = db.districts.find(x => x.zipcode === z); return d ? { zipcode: d.zipcode, district_name: d.district_name } : null; }).filter(Boolean);
    return ok({ in_ntpc: true, district: { zipcode: best.zipcode, district_name: best.district_name, center_latitude: best.center_latitude, center_longitude: best.center_longitude }, adjacent: adj });
  }
  function adjacentOf(zip) {
    const out = [];
    db.adjacency.forEach(([a, b]) => { if (a === zip) out.push(b); else if (b === zip) out.push(a); });
    return out;
  }
  function shuffle(a) { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1));[a[i], a[j]] = [a[j], a[i]]; } }
  function hash(s) { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return Math.abs(h); }
  function now() { const d = new Date(); const z = n => String(n).padStart(2, "0"); return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())} ${z(d.getHours())}:${z(d.getMinutes())}:${z(d.getSeconds())}`; }

  load();

  // 對外：handle(method, path, params) → Promise（模擬網路延遲）
  return {
    handle(method, path, params) {
      const key = method.toUpperCase() + " " + path;
      const fn = routes[key];
      const latency = 90 + Math.random() * 220;
      return new Promise(resolve => setTimeout(() => {
        if (!fn) return resolve(err("not_found", "端點不存在：" + key));
        try { resolve(fn(params || {})); } catch (e) { console.error(e); resolve(err("internal", "後端錯誤：" + e.message)); }
      }, latency));
    },
    _reset() { localStorage.removeItem(DBKEY); localStorage.removeItem(SKEY); load(); }
  };
})();
