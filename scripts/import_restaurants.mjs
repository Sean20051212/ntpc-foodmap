// 從 restaurants.csv (699 筆) 生成 sql/seed.sql
//
// 處理：
//  1. 解析 CSV（含引號內 comma / 換行）
//  2. address 去掉「新北市XXX區」前綴
//  3. Tel split → restaurant_phones
//  4. TravelImg ~/path → https://newtaipei.travel/path
//  5. Opentime 啟發式 parse：平日/假日/週X-週Y/週X、Y/公休、跨午夜
//  6. 14 個 tag 關鍵字分類，無命中默認「其他／伴手禮」

import { readFile, writeFile } from 'node:fs/promises';

// ---------- helpers ----------
function parseCsv(text) {
  const rows = [];
  let row = [], cell = '', inQ = false;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i], nx = text[i + 1];
    if (inQ) {
      if (ch === '"' && nx === '"') { cell += '"'; i++; }
      else if (ch === '"') inQ = false;
      else cell += ch;
    } else {
      if (ch === '"') inQ = true;
      else if (ch === ',') { row.push(cell); cell = ''; }
      else if (ch === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; }
      else if (ch === '\r') {}
      else cell += ch;
    }
  }
  if (cell || row.length) { row.push(cell); rows.push(row); }
  return rows;
}

function sql(s) {
  if (s === null || s === undefined || s === '') return 'NULL';
  return "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "''").replace(/\r?\n/g, ' ').trim() + "'";
}

// 地址清理：去掉「新北市XXX區」前綴 / 純「XX區」前綴 / 括號註解；
// 若清完無「號」字 → 視為非正規地址，回傳 null 由 caller 整筆 skip
function cleanAddress(addr) {
  if (!addr) return null;
  let s = String(addr).trim();
  // 1. 「新北市XXX區」或裸「XX區」前綴
  s = s.replace(/^[\d\s]*新北市[\d\s]*[一-鿿]{1,4}區\s*/, '');
  s = s.replace(/^[\d\s]*[一-鿿]{1,4}區\s*/, '');
  s = s.trim();
  // 2. 括號註解（半 / 全形）
  s = s.replace(/[\(（][^)）]*[\)）]/g, '').trim();
  // 3. 驗證：必須含「號」才算正規街道地址
  if (!s.includes('號')) return null;
  return s;
}

function travelImgToUrl(p) {
  if (!p) return null;
  return p.replace(/^~\//, 'https://newtaipei.travel/');
}

// ---------- Opentime parser ----------
const CHAR_TO_DAY = { '日': 0, '天': 0, '一': 1, '二': 2, '三': 3, '四': 4, '五': 5, '六': 6 };

function parseDays(segment) {
  // priority order: explicit range > list > 平日/假日 > 全年
  // returns Set of days 0-6, or null if unspecified

  // 全年 / 每日 / 天天 / 每天 → all 7
  if (/全年|每[日天]|天天|無休/.test(segment)) return new Set([0, 1, 2, 3, 4, 5, 6]);

  const days = new Set();
  let found = false;

  // 範圍：週X 至/-/~/到 週Y
  const rangeRe = /[週周禮拜]\s*([日天一二三四五六])\s*[至~\-到–—]\s*[週周禮拜]?\s*([日天一二三四五六])/g;
  let m;
  while ((m = rangeRe.exec(segment)) !== null) {
    const a = CHAR_TO_DAY[m[1]], b = CHAR_TO_DAY[m[2]];
    if (a <= b) for (let d = a; d <= b; d++) days.add(d);
    else { for (let d = a; d <= 6; d++) days.add(d); for (let d = 0; d <= b; d++) days.add(d); }
    found = true;
  }

  // 列表：週X、Y、Z（這些字元緊鄰 「、」 連接）
  const listRe = /[週周禮拜]\s*([日天一二三四五六](?:\s*[、,]\s*[日天一二三四五六])+)/g;
  while ((m = listRe.exec(segment)) !== null) {
    const chars = m[1].match(/[日天一二三四五六]/g) || [];
    for (const c of chars) days.add(CHAR_TO_DAY[c]);
    found = true;
  }

  // 單一週X（避免重複處理已被 range/list 抓到的）
  if (!found) {
    const singleRe = /[週周禮拜]\s*([日天一二三四五六])/g;
    while ((m = singleRe.exec(segment)) !== null) {
      days.add(CHAR_TO_DAY[m[1]]);
      found = true;
    }
  }

  if (/平日/.test(segment)) { [1, 2, 3, 4, 5].forEach(d => days.add(d)); found = true; }
  if (/假日/.test(segment)) { [6, 0].forEach(d => days.add(d)); found = true; }

  return found ? days : null;
}

function parseOffDays(text) {
  // 找「週X、Y、... 公休/休」
  const off = new Set();
  // 公休前可能有列表或範圍
  const offMatches = [...text.matchAll(/[週周禮拜]\s*([日天一二三四五六](?:\s*[、,至\-~到–—]\s*[週周禮拜]?\s*[日天一二三四五六])*)\s*(公休|休|不營業)/g)];
  for (const m of offMatches) {
    const fragment = m[0];
    // 處理範圍
    const rangeRe = /[週周禮拜]\s*([日天一二三四五六])\s*[至\-~到–—]\s*[週周禮拜]?\s*([日天一二三四五六])/;
    const r = rangeRe.exec(fragment);
    if (r) {
      const a = CHAR_TO_DAY[r[1]], b = CHAR_TO_DAY[r[2]];
      if (a <= b) for (let d = a; d <= b; d++) off.add(d);
      else { for (let d = a; d <= 6; d++) off.add(d); for (let d = 0; d <= b; d++) off.add(d); }
    } else {
      const chars = fragment.match(/[日天一二三四五六]/g) || [];
      for (const c of chars) off.add(CHAR_TO_DAY[c]);
    }
  }
  return off;
}

function parseOpentime(rawText) {
  if (!rawText || !rawText.trim()) return [];
  // 正規化
  let s = rawText.normalize('NFKC')
    .replace(/：/g, ':')
    .replace(/[–—]/g, '-')
    .replace(/～|〜|~/g, '~')
    .replace(/，/g, ',')
    .trim();

  // 抽出括號內文字當 spec_rec
  const parens = [];
  s = s.replace(/[\(（]([^)）]*)[\)）]/g, (_, c) => { parens.push(c.trim()); return ' '; });
  const baseSpec = parens.filter(Boolean).join('；') || null;

  // 先抽出 off days（從原始字串）
  const offDays = parseOffDays(s);

  // 重要修正：把「週X公休」「週X、Y休」整段從 s 移除，
  // 否則 parseDays 會把同一個 週X 同時當成 open day（誤判反向）
  s = s.replace(/[週周禮拜]\s*[日天一二三四五六](?:\s*[、,至\-~到–—]\s*[週周禮拜]?\s*[日天一二三四五六])*\s*(公休|休整天|不營業|休息|休)/g, ' ');
  s = s.replace(/(平日|假日|全年)\s*(公休|休)/g, ' ');

  // 句段分隔：'/' 和 '；' 都當段
  const segments = s.split(/[\/／；;]/).map(x => x.trim()).filter(Boolean);

  const rows = [];
  // 時間範圍：支援 -, ~, 至, 到 作為分隔
  const timeRe = /(\d{1,2}):(\d{2})\s*[-~至到]\s*(?:凌晨)?(\d{1,2}):(\d{2})/g;

  for (const seg of segments) {
    let days = parseDays(seg);
    // 找時間 ranges
    const ranges = [];
    timeRe.lastIndex = 0;
    let m;
    while ((m = timeRe.exec(seg)) !== null) {
      const sh = String(m[1]).padStart(2, '0'), sm = m[2];
      const eh = String(m[3]).padStart(2, '0'), em = m[4];
      ranges.push({ start: `${sh}:${sm}:00`, end: `${eh}:${em}:00` });
    }
    if (ranges.length === 0) continue;
    if (days === null) days = new Set([0, 1, 2, 3, 4, 5, 6]);
    // 扣 off days
    for (const od of offDays) days.delete(od);
    const dayList = [...days].sort();
    for (const d of dayList) {
      for (const r of ranges) {
        rows.push({ day: d, start: r.start, end: r.end, spec: baseSpec });
      }
    }
  }

  if (rows.length === 0) {
    // 完全沒解到時間 → sentinel row
    return [{ day: 0, start: '00:00:00', end: '00:00:00', spec: rawText.trim() }];
  }
  return rows;
}

// ---------- Classifier ----------
const TAGS = [
  [1, '小吃／熱炒'], [2, '麵食（牛肉麵類）'], [3, '中式／合菜'], [4, '日式'],
  [5, '海鮮'], [6, '火鍋'], [7, '燒烤燒肉'], [8, '咖啡廳'],
  [9, '烘焙甜點'], [10, '冰品飲料'], [11, '早午餐'], [12, '異國料理'],
  [13, '素食健康'], [14, '其他／伴手禮']
];

const KW = [
  [6, ['火鍋', '鍋物', '涮涮鍋', '麻辣鍋', '麻辣燙', '薑母鴨', '羊肉爐', '燒酒雞', '酸菜白肉鍋', '鴛鴦鍋', '小火鍋', '麻油雞', '當歸鴨']],
  [7, ['燒烤', '烤肉', 'BBQ', 'bbq', '燒肉', '焼肉', '韓式烤肉', '串燒', '炭烤', '炭火', '燒鳥']],
  [4, ['壽司', '丼', '日式', '居酒屋', '懷石', '蒲燒', '鰻魚', '天婦羅', '大阪燒', '章魚燒', '日本料理', '日式料理', '拉麵', 'らーめん', 'ラーメン', '握壽司', '迴轉壽司', '生魚片', '鐵板燒']],
  [5, ['海鮮', '漁港', '海港', '海產店', '生海鮮', '活魚', '活蝦', '活蟹', '魚翅', '鮑魚', '鮮魚', '海魚', '魚料理', '海鮮料理']],
  [2, ['麵店', '麵堂', '麵舖', '麵食', '牛肉麵', '涼麵', '乾麵', '湯麵', '麵屋', '麵館', '陽春麵', '炸醬麵', '油麵', '意麵', '担仔麵', '担担麵', '切仔麵', '貢丸麵', '麻醬麵', '紅燒牛肉麵', '清燉牛肉麵', '麵線', '米線', '麵家', '麵屋', '製麵', '餛飩', '麵攤', '麵嫂', '陽春']],
  [8, ['咖啡', 'café', 'cafe', 'coffee', 'Café', 'CAFÉ', 'CAFE', 'Coffee', 'COFFEE', '咖啡館', '咖啡店', '咖啡坊', '咖啡廳']],
  [9, ['麵包', '烘焙', '蛋糕', '吐司', '甜點', 'dessert', '馬卡龍', '牛軋糖', '巧克力', '麻糬', '布丁', '泡芙', '糕餅', '酥餅', '鳳梨酥', '太陽餅', '銅鑼燒', '可頌', '蛋塔', '蛋撻', '布朗尼', '司康', '可麗餅', '舒芙蕾', '餅鋪', '餅舖', '餅店', '糕餅店', '餅家', '中秋月餅', '伴手禮', '禮盒', '糕點', '魚酥', '糖果', '甜不辣', '糕渣', '麵茶']],
  [10, ['冰品', '冰淇淋', '雪花冰', '剉冰', '芋圓', '豆花', '飲料', '茶飲', '果汁', '珍珠奶茶', '茶坊', '茶莊', '茶店', '茶行', '茶屋', '飲品', '泡沫紅茶', '茶葉', '青茶', '烏龍茶', '高山茶', '冰店', '冷飲', '紅茶', '冰菓室', '冰菓店', '冰棒', '石花凍', '雪糕', '飲冰室', '愛玉', '仙草', '冰沙']],
  [11, ['早午餐', 'brunch', 'Brunch', 'BRUNCH', '美而美', '丹丹漢堡', '永和豆漿', '蛋餅', '燒餅油條', '早餐店', '豆漿店']],
  [12, ['義式', '美式', '法式', '韓式', '泰式', '越式', '印度', '西班牙', '義大利', '韓國', '泰國', '越南', '印尼', '墨西哥', '地中海', '南洋', '異國', '漢堡', '披薩', '義大利麵', 'pasta', 'pizza', 'Pizza', 'Pasta', 'Italian', '炸雞', '英式', '牛排', '豬排', 'Steak', 'steak', '咖哩', 'Curry', 'curry', '法國', '法式麵包', '東南亞']],
  [13, ['素食', '蔬食', '養生', 'vegan', 'vegetarian', '蔬果']],
  [3, ['合菜', '中華', '客家', '川菜', '江浙', '港式', '飲茶', '點心', '宴會', '熱炒店', '北方', '上海菜', '北平', '台菜', '台式', '台灣料理', '台灣美食', '北京菜', '中式料理', '中式美食', '餐廳', '土雞城', '鄉土', '風味餐廳', '庭園餐廳', '山莊', '飯店', '園外園', '酒家菜']],
  [1, ['小吃', '滷味', '滷肉飯', '雞排', '炒飯', '炒麵', '炒米粉', '蚵仔煎', '鹹酥雞', '鹽酥雞', '黑白切', '米粉湯', '大腸麵線', '蚵仔麵線', '肉羹', '魚羹', '快炒', '熱炒', '小吃店', '臭豆腐', '油飯', '粽子', '碗粿', '米糕', '排骨飯', '肉圓', '潤餅', '車輪餅', '蘿蔔糕', '包子', '煎包', '饅頭', '鵝肉', '鴨肉', '羊肉', '豬腳', '鴨肉冬粉', '鍋貼', '水餃', '刈包', '煎餃', '湯包', '肉粽', '便當', '飲食店', '火雞肉飯', '冬粉', '黑輪', '田不辣', '炸物', '小館', '魚丸', '貢丸', '魯肉飯', '米苔目', '鹹水雞', '豆腐', '雞肉飯', '香腸', '鴨血', '蝦捲', '雙胞胎', '胡椒餅', '刀削麵', '肉粳', '肉舖', '豆干', '燒雞', '花枝', '美食店', '雜貨鋪', '草仔粿', '粿', '肉鬆', '甘蔗', '玉米']],
];

function classify(name, desc, summary) {
  const text = `${name} ${desc || ''} ${summary || ''}`;
  const matched = new Set();
  for (const [id, kws] of KW) {
    for (const kw of kws) {
      if (text.includes(kw)) { matched.add(id); break; }
    }
  }
  if (matched.size === 0) return { tags: [14], uncertain: true };
  return { tags: [...matched].slice(0, 3), uncertain: false };
}

// ---------- main ----------
const csv = await readFile('restaurants.csv', 'utf8');
const rows = parseCsv(csv.replace(/^﻿/, ''));
const head = rows[0];
const col = {};
head.forEach((h, i) => col[h] = i);

const lines = [];
const stats = { restaurants: 0, phones: 0, photos: 0, opentime: 0, mappings: 0, uncertainTags: 0, skippedNoAddr: 0 };
const tagCounts = Object.fromEntries(TAGS.map(([id]) => [id, 0]));
const uncertainList = [];
const skippedList = [];

// ---- header ----
lines.push('-- ============================================');
lines.push('-- sql/seed.sql — 新北市 699 筆真實餐廳資料');
lines.push('-- 來源：restaurants.csv（591 官方 ∩ travel + 108 travel 獨家）');
lines.push('-- 跑法：mysql < sql/schema.sql 後 mysql < sql/seed.sql');
lines.push('-- 注意：此 script 會 DELETE schema.sql §4 的 mock 資料，再灌入真實資料');
lines.push('-- ============================================');
lines.push('');
lines.push('SET FOREIGN_KEY_CHECKS = 0;');
lines.push('DELETE FROM `restaurant_tags_mapping`;');
lines.push('DELETE FROM `opentime`;');
lines.push('DELETE FROM `restaurant_photos`;');
lines.push('DELETE FROM `restaurant_phones`;');
lines.push('DELETE FROM `favorites`;');
lines.push('DELETE FROM `reviews`;');
lines.push('DELETE FROM `restaurants`;');
lines.push('DELETE FROM `tags`;');
lines.push('ALTER TABLE `restaurants` AUTO_INCREMENT = 1;');
lines.push('ALTER TABLE `tags` AUTO_INCREMENT = 1;');
lines.push('ALTER TABLE `restaurant_phones` AUTO_INCREMENT = 1;');
lines.push('ALTER TABLE `restaurant_photos` AUTO_INCREMENT = 1;');
lines.push('ALTER TABLE `opentime` AUTO_INCREMENT = 1;');
lines.push('SET FOREIGN_KEY_CHECKS = 1;');
lines.push('');

// ---- tags ----
lines.push('-- 1. 14 個分類');
lines.push('INSERT INTO `tags` (`tag_id`, `tag_name`) VALUES');
lines.push(TAGS.map(([id, name], i) =>
  `(${id}, ${sql(name)})${i === TAGS.length - 1 ? ';' : ','}`
).join('\n'));
lines.push('');

// ---- restaurants ----
lines.push('-- 2. 699 筆餐廳');
const restaurantValues = [];
const phoneRows = [];
const photoRows = [];
const opentimeRows = [];
const mappingRows = [];

let nextRestaurantId = 0;
for (let i = 1; i < rows.length; i++) {
  const r = rows[i];
  if (!r[col['Name']] || !r[col['Zipcode']]) continue;
  const name = r[col['Name']];
  const address = cleanAddress(r[col['Add']]);
  if (address === null) {
    stats.skippedNoAddr++;
    if (skippedList.length < 30) skippedList.push({ name, rawAddr: r[col['Add']] });
    continue;
  }
  const restaurantId = ++nextRestaurantId;
  const description = r[col['Description']] || '';
  const tel = r[col['Tel']] || '';
  const zipcode = r[col['Zipcode']];
  const lng = parseFloat(r[col['Px']]) || 0;
  const lat = parseFloat(r[col['Py']]) || 0;
  const opentimeRaw = r[col['Opentime']] || '';
  const travelUrl = r[col['TravelUrl']] || null;
  const travelImg = travelImgToUrl(r[col['TravelImg']]);
  const summary = r[col['TravelSummary']] || '';

  // restaurants row
  restaurantValues.push(
    `(${restaurantId}, ${sql(name)}, ${sql(description)}, ${sql(address)}, ${sql(zipcode)}, ${lat.toFixed(7)}, ${lng.toFixed(7)}, NULL, NULL)`
  );
  stats.restaurants++;

  // phones split by comma
  for (const p of tel.split(/[,，]/).map(x => x.trim()).filter(Boolean)) {
    phoneRows.push(`(${restaurantId}, ${sql(p)})`);
    stats.phones++;
  }

  // main photo
  if (travelImg) {
    photoRows.push(`(${restaurantId}, ${sql(travelImg)}, 1, 0)`);
    stats.photos++;
  }

  // opentime
  const ots = parseOpentime(opentimeRaw);
  for (const o of ots) {
    opentimeRows.push(`(${restaurantId}, ${o.day}, ${sql(o.start)}, ${sql(o.end)}, ${sql(o.spec)})`);
    stats.opentime++;
  }

  // tags
  const cls = classify(name, description, summary);
  for (const tagId of cls.tags) {
    mappingRows.push(`(${restaurantId}, ${tagId})`);
    stats.mappings++;
    tagCounts[tagId]++;
  }
  if (cls.uncertain) {
    stats.uncertainTags++;
    if (uncertainList.length < 30) uncertainList.push({ id: restaurantId, name });
  }
}

lines.push('INSERT INTO `restaurants` (`restaurant_id`, `restaurant_name`, `description`, `address`, `zipcode`, `latitude`, `longitude`, `price_level`, `google_place_id`) VALUES');
lines.push(restaurantValues.join(',\n') + ';');
lines.push('');

lines.push('-- 3. 電話');
if (phoneRows.length) {
  lines.push('INSERT INTO `restaurant_phones` (`restaurant_id`, `phone_number`) VALUES');
  lines.push(phoneRows.join(',\n') + ';');
}
lines.push('');

lines.push('-- 4. 主圖（来自 newtaipei.travel）');
if (photoRows.length) {
  lines.push('INSERT INTO `restaurant_photos` (`restaurant_id`, `url`, `is_main`, `sort_order`) VALUES');
  lines.push(photoRows.join(',\n') + ';');
}
lines.push('');

lines.push('-- 5. 營業時間');
if (opentimeRows.length) {
  // chunk by 500 rows per INSERT to keep things readable
  for (let i = 0; i < opentimeRows.length; i += 500) {
    const chunk = opentimeRows.slice(i, i + 500);
    lines.push('INSERT INTO `opentime` (`restaurant_id`, `day`, `start_time`, `end_time`, `spec_rec`) VALUES');
    lines.push(chunk.join(',\n') + ';');
    lines.push('');
  }
}

lines.push('-- 6. 餐廳 ↔ 分類');
if (mappingRows.length) {
  lines.push('INSERT INTO `restaurant_tags_mapping` (`restaurant_id`, `tag_id`) VALUES');
  lines.push(mappingRows.join(',\n') + ';');
}
lines.push('');

// §7 sample favorites / reviews 已移除 — 真實 699 筆餐廳保持 rating_avg=0、rating_count=0
// trigger 邏輯改在 schema.sql §4.11 的 mock 資料中測試（schema.sql 單獨跑時可驗證）

await writeFile('sql/seed.sql', lines.join('\n'), 'utf8');

// ---- 統計輸出 ----
console.log('=== 寫出 sql/seed.sql ===');
console.log('  restaurants : ', stats.restaurants);
console.log('  phones      : ', stats.phones);
console.log('  photos      : ', stats.photos);
console.log('  opentime    : ', stats.opentime, ' (含 sentinel 列)');
console.log('  mappings    : ', stats.mappings);
console.log('  無命中默認 其他／伴手禮：', stats.uncertainTags, ' 筆 (需 spot-check)');
console.log('  非正規地址被跳過：', stats.skippedNoAddr, ' 筆');
console.log('');
console.log('=== 各分類筆數 ===');
for (const [id, name] of TAGS) {
  console.log(`  [${String(id).padStart(2)}] ${name.padEnd(12, '　')} : ${tagCounts[id]}`);
}
console.log('');
console.log('=== 前 30 筆無法分類的餐廳（默認其他／伴手禮）===');
for (const u of uncertainList) {
  console.log(`  #${u.id} ${u.name}`);
}
console.log('');
console.log('=== 被 skip 的非正規地址（前 30）===');
for (const s of skippedList) {
  console.log(`  ${s.name.padEnd(30)} 原 Add: ${s.rawAddr}`);
}
