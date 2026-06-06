// sync_photos.mjs — 把 restaurant_photos 表中 local_path IS NULL 的照片
// 從外部 url 下載到本機 uploads/photos/，並回寫 local_path。
//
// 用法：
//   node scripts/sync_photos.mjs                # 全跑（每張間隔 1 秒）
//   node scripts/sync_photos.mjs --limit 50     # 只跑前 50 張未下載的
//   node scripts/sync_photos.mjs --dry-run      # 不寫檔不改 DB
//   node scripts/sync_photos.mjs --sleep 500    # 自訂間隔（毫秒）
//   node scripts/sync_photos.mjs --redo         # 連已下載的也重抓（清空 local_path 再跑）
//
// 設計：
//   - 檔名以 photo_id 為主，避免衝突；副檔名依 Content-Type
//   - 失敗單筆不中斷整體；錯誤集中印在最後
//   - 跑完 log 統計：嘗試/成功/失敗
//   - 預期 demo 第一次 backfill 約 4015 張 × 1 秒 ≈ 70 分鐘
//
// 需 .env 內 DB_*

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const UPLOAD_DIR = path.join(ROOT, 'uploads', 'photos');

const argv = process.argv.slice(2);
const arg = (k, def) => {
  const i = argv.indexOf(k);
  return i >= 0 ? argv[i + 1] : def;
};
const LIMIT = parseInt(arg('--limit', '0'), 10) || null;
const SLEEP_MS = parseInt(arg('--sleep', '1000'), 10);
const DRY = argv.includes('--dry-run');
const REDO = argv.includes('--redo');

// 載入 .env
const envPath = path.join(ROOT, '.env');
if (fs.existsSync(envPath)) {
  for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^([A-Z_][A-Z0-9_]*)=(.*)$/);
    if (m) process.env[m[1]] = m[2].replace(/^"(.*)"$/, '$1');
  }
}

const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const extFromContentType = (ct) => {
  if (!ct) return 'jpg';
  if (ct.includes('jpeg') || ct.includes('jpg')) return 'jpg';
  if (ct.includes('png')) return 'png';
  if (ct.includes('webp')) return 'webp';
  if (ct.includes('gif')) return 'gif';
  return 'jpg';
};

async function main() {
  if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

  const conn = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT || '3306', 10),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'ntpc_foodmap',
    charset: 'utf8mb4',
  });

  if (REDO) {
    if (!DRY) {
      await conn.execute('UPDATE restaurant_photos SET local_path = NULL');
      console.log('[redo] 已清空所有 local_path');
    } else {
      console.log('[redo] dry-run：略過 UPDATE local_path = NULL');
    }
  }

  const limitSql = LIMIT ? `LIMIT ${LIMIT}` : '';
  const [rows] = await conn.execute(
    `SELECT photo_id, url FROM restaurant_photos
     WHERE local_path IS NULL AND url IS NOT NULL AND url <> ''
     ORDER BY photo_id ASC ${limitSql}`
  );

  console.log(`待下載：${rows.length} 張（sleep=${SLEEP_MS}ms, dry-run=${DRY}）\n`);

  let ok = 0, fail = 0;
  const errors = [];

  for (let i = 0; i < rows.length; i++) {
    const { photo_id, url } = rows[i];
    const label = `[${i + 1}/${rows.length}] id=${photo_id}`;
    try {
      const res = await fetch(url, { redirect: 'follow' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const ct = res.headers.get('content-type') || '';
      if (!ct.startsWith('image/')) throw new Error(`非圖片 (${ct})`);
      const buf = Buffer.from(await res.arrayBuffer());
      if (buf.length < 1024) throw new Error(`size too small: ${buf.length}`);

      const ext = extFromContentType(ct);
      const fname = `${photo_id}.${ext}`;
      const fullPath = path.join(UPLOAD_DIR, fname);
      const localPath = `uploads/photos/${fname}`;

      if (!DRY) {
        fs.writeFileSync(fullPath, buf);
        await conn.execute('UPDATE restaurant_photos SET local_path = ? WHERE photo_id = ?', [localPath, photo_id]);
      }
      ok++;
      console.log(`${label} ✅ ${ct} ${(buf.length / 1024).toFixed(1)}KB → ${localPath}`);
    } catch (e) {
      fail++;
      const msg = `${label} ❌ ${e.message}  url=${url}`;
      console.warn(msg);
      errors.push(msg);
    }
    if (i < rows.length - 1) await sleep(SLEEP_MS);
  }

  console.log(`\n---- 統計 ----`);
  console.log(`嘗試 ${rows.length}，成功 ${ok}，失敗 ${fail}`);
  if (errors.length) {
    console.log(`\n---- 失敗清單（前 20）----`);
    errors.slice(0, 20).forEach(m => console.log(m));
  }

  await conn.end();
}

main().catch(e => { console.error(e); process.exit(1); });
