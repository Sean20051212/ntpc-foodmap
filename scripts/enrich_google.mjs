// Google API 補強腳本
//
// 用 Places API (New) Text Search + Geocoding API 把 restaurants 表補齊：
//   - google_place_id
//   - price_level（1~4，Places 回 PRICE_LEVEL_INEXPENSIVE..VERY_EXPENSIVE）
//   - 每家最多 5 張 restaurant_photos
//   - 用 Geocoding 驗證 latitude / longitude（差距 > 200m 只警告，預設不覆蓋）
//
// 設計重點：
//   - 可斷點續跑：已有 google_place_id 的店預設跳過
//   - 失敗單筆不中斷整體
//   - 直接寫 DB（mysql2），不再產 SQL 中間檔
//
// 用法：
//   node scripts/enrich_google.mjs                    # 全跑（693 筆）
//   node scripts/enrich_google.mjs --limit 10         # 只跑前 10 家未補強的
//   node scripts/enrich_google.mjs --dry-run          # 不寫 DB，只 log
//   node scripts/enrich_google.mjs --force            # 不跳過已有 place_id 的（重新補）
//   node scripts/enrich_google.mjs --update-latlng    # 經緯度差距大時自動覆蓋（預設只警告）
//
// 需先 npm install。需要 .env 內 DB_* 與 GOOGLE_MAPS_KEY_BACKEND。

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');

// ---------- 參數 ----------
const args = process.argv.slice(2);
const flag = (name) => args.includes(name);
const opt = (name, def) => {
  const i = args.indexOf(name);
  return i >= 0 ? args[i + 1] : def;
};
const LIMIT = parseInt(opt('--limit', '0'), 10) || 0;
const DRY = flag('--dry-run');
const FORCE = flag('--force');
const UPDATE_LATLNG = flag('--update-latlng');
const PHOTOS_PER_RESTAURANT = 5;
const LATLNG_WARN_METERS = 200;
const SLEEP_MS = 120;
const PHOTO_MAX_WIDTH = 800;

// ---------- 讀 .env ----------
function loadEnv() {
  const envPath = path.join(ROOT, '.env');
  if (!fs.existsSync(envPath)) {
    throw new Error('找不到 .env，請先建立並填入 DB_* 與 GOOGLE_MAPS_KEY_BACKEND');
  }
  const env = {};
  for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*?)\s*$/);
    if (!m) continue;
    if (line.trim().startsWith('#')) continue;
    env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
  return env;
}
const ENV = loadEnv();
const KEY = ENV.GOOGLE_MAPS_KEY_BACKEND;
if (!KEY) throw new Error('.env 缺 GOOGLE_MAPS_KEY_BACKEND');

// ---------- 工具 ----------
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function haversine(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const toRad = (d) => (d * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
  return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}

function priceLevelToInt(pl) {
  // Places API (New) 回字串列舉：PRICE_LEVEL_FREE / INEXPENSIVE / MODERATE / EXPENSIVE / VERY_EXPENSIVE
  const map = {
    PRICE_LEVEL_FREE: 1,
    PRICE_LEVEL_INEXPENSIVE: 1,
    PRICE_LEVEL_MODERATE: 2,
    PRICE_LEVEL_EXPENSIVE: 3,
    PRICE_LEVEL_VERY_EXPENSIVE: 4,
  };
  return map[pl] ?? null;
}

// ---------- Google API 呼叫 ----------
async function placesTextSearch({ name, address, lat, lng }) {
  const url = 'https://places.googleapis.com/v1/places:searchText';
  const body = {
    textQuery: `${name} ${address}`,
    languageCode: 'zh-TW',
    regionCode: 'TW',
    maxResultCount: 1,
    locationBias: {
      circle: {
        center: { latitude: lat, longitude: lng },
        radius: 500.0,
      },
    },
  };
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Goog-Api-Key': KEY,
      'X-Goog-FieldMask':
        'places.id,places.displayName,places.location,places.priceLevel,places.photos',
    },
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    throw new Error(`Places Text Search ${res.status}: ${await res.text()}`);
  }
  const json = await res.json();
  return json.places?.[0] || null;
}

async function geocode(address) {
  const url = new URL('https://maps.googleapis.com/maps/api/geocode/json');
  url.searchParams.set('address', address);
  url.searchParams.set('region', 'tw');
  url.searchParams.set('language', 'zh-TW');
  url.searchParams.set('key', KEY);
  const res = await fetch(url);
  if (!res.ok) throw new Error(`Geocoding ${res.status}`);
  const json = await res.json();
  if (json.status !== 'OK' || !json.results?.length) return null;
  const loc = json.results[0].geometry.location;
  return { lat: loc.lat, lng: loc.lng };
}

async function resolvePhotoUri(photoResourceName) {
  // photoResourceName 形如 "places/XXX/photos/YYY"
  // Photo Media 端點：?skipHttpRedirect=true 會回 JSON 而不是 302
  const url = new URL(`https://places.googleapis.com/v1/${photoResourceName}/media`);
  url.searchParams.set('maxWidthPx', String(PHOTO_MAX_WIDTH));
  url.searchParams.set('skipHttpRedirect', 'true');
  url.searchParams.set('key', KEY);
  const res = await fetch(url);
  if (!res.ok) throw new Error(`Photo Media ${res.status}`);
  const json = await res.json();
  // 回傳 { name, photoUri }；photoUri 是 googleusercontent 直鏈
  return json.photoUri || null;
}

// ---------- DB ----------
async function connectDb() {
  return mysql.createConnection({
    host: ENV.DB_HOST || 'localhost',
    user: ENV.DB_USER || 'root',
    password: ENV.DB_PASS || '',
    database: ENV.DB_NAME || 'ntpc_foodmap',
    charset: ENV.DB_CHARSET || 'utf8mb4',
  });
}

async function fetchTargets(conn) {
  const where = FORCE ? '' : 'WHERE google_place_id IS NULL';
  const limit = LIMIT > 0 ? `LIMIT ${LIMIT}` : '';
  const [rows] = await conn.execute(
    `SELECT r.restaurant_id, r.restaurant_name, r.address, r.latitude, r.longitude,
            d.district_name
       FROM restaurants r
       LEFT JOIN districts d ON d.zipcode = r.zipcode
       ${where.replace(/google_place_id/g, 'r.google_place_id')}
       ORDER BY r.restaurant_id ${limit}`
  );
  return rows;
}

async function hasMainPhoto(conn, restaurantId) {
  const [rows] = await conn.execute(
    'SELECT 1 FROM restaurant_photos WHERE restaurant_id = ? AND is_main = 1 LIMIT 1',
    [restaurantId]
  );
  return rows.length > 0;
}

async function countPhotos(conn, restaurantId) {
  const [rows] = await conn.execute(
    'SELECT COUNT(*) AS n FROM restaurant_photos WHERE restaurant_id = ?',
    [restaurantId]
  );
  return rows[0].n;
}

// ---------- 主流程 ----------
async function processOne(conn, r, stats) {
  const id = r.restaurant_id;
  const lat = Number(r.latitude);
  const lng = Number(r.longitude);
  const label = `[${id}] ${r.restaurant_name}`;

  // 1. Places Text Search
  let place;
  try {
    place = await placesTextSearch({
      name: r.restaurant_name,
      address: `新北市${r.district_name || ''}${r.address}`,
      lat,
      lng,
    });
  } catch (e) {
    console.error(`${label} ❌ Places Search: ${e.message}`);
    stats.failPlaces++;
    return;
  }
  if (!place) {
    console.warn(`${label} ⚠ Places 查無結果`);
    stats.notFound++;
    return;
  }
  const placeId = place.id;
  const priceInt = priceLevelToInt(place.priceLevel);
  const placeLoc = place.location
    ? { lat: place.location.latitude, lng: place.location.longitude }
    : null;

  // 2. Geocoding 驗證（補上「新北市 + 區名」前綴，避免 Google 解析到其他縣市）
  const fullAddress = `新北市${r.district_name || ''}${r.address}`;
  let geo;
  try {
    geo = await geocode(fullAddress);
  } catch (e) {
    console.warn(`${label} ⚠ Geocoding: ${e.message}`);
  }
  let latlngToWrite = null;
  if (geo) {
    const dist = haversine(lat, lng, geo.lat, geo.lng);
    if (dist > LATLNG_WARN_METERS) {
      console.warn(`${label} ⚠ Geocoding 與 DB 經緯度差 ${dist}m`);
      stats.latlngMismatch++;
      if (UPDATE_LATLNG) latlngToWrite = geo;
    }
  }

  // 3. UPDATE restaurants
  if (!DRY) {
    const sets = ['google_place_id = ?', 'price_level = ?'];
    const vals = [placeId, priceInt];
    if (latlngToWrite) {
      sets.push('latitude = ?', 'longitude = ?');
      vals.push(latlngToWrite.lat, latlngToWrite.lng);
    }
    vals.push(id);
    try {
      await conn.execute(
        `UPDATE restaurants SET ${sets.join(', ')} WHERE restaurant_id = ?`,
        vals
      );
    } catch (e) {
      // place_id 撞 UNIQUE（同店被識別成已存在 place_id）
      console.error(`${label} ❌ UPDATE 失敗: ${e.message}`);
      stats.failUpdate++;
      return;
    }
  }

  // 4. 抓 5 張照片
  const photos = (place.photos || []).slice(0, PHOTOS_PER_RESTAURANT);
  if (photos.length > 0) {
    const existingMain = await hasMainPhoto(conn, id);
    const existingCount = await countPhotos(conn, id);
    let inserted = 0;
    for (let i = 0; i < photos.length; i++) {
      try {
        const uri = await resolvePhotoUri(photos[i].name);
        if (!uri) continue;
        const isMain = !existingMain && i === 0 ? 1 : 0;
        const sortOrder = existingCount + i;
        if (!DRY) {
          await conn.execute(
            'INSERT INTO restaurant_photos (restaurant_id, url, is_main, sort_order) VALUES (?, ?, ?, ?)',
            [id, uri, isMain, sortOrder]
          );
        }
        inserted++;
      } catch (e) {
        console.warn(`${label} ⚠ 第 ${i + 1} 張照片: ${e.message}`);
      }
      await sleep(SLEEP_MS);
    }
    stats.photosInserted += inserted;
  }

  stats.ok++;
  if (stats.ok % 10 === 0) {
    console.log(
      `  進度：成功 ${stats.ok} / 查無 ${stats.notFound} / 經緯不符 ${stats.latlngMismatch} / 照片 ${stats.photosInserted}`
    );
  }
}

async function main() {
  console.log('=== Google API 補強腳本 ===');
  console.log(`模式：${DRY ? 'DRY-RUN（不寫 DB）' : '寫入 DB'}`);
  console.log(`策略：${FORCE ? '全部重新補強' : '跳過已有 google_place_id'}`);
  if (LIMIT) console.log(`限制：前 ${LIMIT} 筆`);
  console.log();

  const conn = await connectDb();
  try {
    const targets = await fetchTargets(conn);
    console.log(`待處理：${targets.length} 家\n`);
    if (targets.length === 0) {
      console.log('沒有需要補強的店家。');
      return;
    }

    const stats = {
      ok: 0,
      notFound: 0,
      failPlaces: 0,
      failUpdate: 0,
      latlngMismatch: 0,
      photosInserted: 0,
    };
    const t0 = Date.now();
    for (const r of targets) {
      await processOne(conn, r, stats);
      await sleep(SLEEP_MS);
    }
    const sec = ((Date.now() - t0) / 1000).toFixed(1);

    console.log('\n=== 完成 ===');
    console.log(`耗時 ${sec}s`);
    console.log(`成功更新：${stats.ok}`);
    console.log(`Places 查無：${stats.notFound}`);
    console.log(`Places 失敗：${stats.failPlaces}`);
    console.log(`UPDATE 失敗：${stats.failUpdate}`);
    console.log(`經緯度差距 > ${LATLNG_WARN_METERS}m：${stats.latlngMismatch}`);
    console.log(`新增照片：${stats.photosInserted}`);
  } finally {
    await conn.end();
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
