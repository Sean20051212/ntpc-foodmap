# 新北市美食地圖網站 (NTPC Food Map)

> 大學課程作業 · 五人協作 · PHP + MySQL + React 原型前端

以新北市政府觀光旅遊局餐飲業者開放資料（[dataset/123086](https://data.gov.tw/dataset/123086)）為基礎，提供地址搜尋、條件篩選、路徑規劃、隨機輪盤等功能。

---

## 📋 專案資訊

- **後端**：PHP 8.x + MySQL 8.x（XAMPP）
- **前端**：React 18 + Babel（瀏覽器即時編譯）+ Leaflet 地圖
- **資料庫**：11 張表 + 3 個觸發器自動維護 `rating_avg` / `rating_count`
- **規格文件**：[docs/design-audit.md](docs/design-audit.md)、[docs/backend-plan.md](docs/backend-plan.md)、[docs/frontend-plan.md](docs/frontend-plan.md)

---

## 🚦 目前實作狀態

| 區塊 | 狀態 |
|---|---|
| `sql/database.sql` | ✅ schema + 觸發器 + 29 區 / 46 鄰接 / 14 分類 / ~690 家餐廳 |
| `index.html` + `js/*.jsx` + `assets/app.css` | ✅ Claude Design 高保真原型（React via Babel） |
| `api/**/*.php` 後端 API | ✅ 全部 27 支端點完成（auth / restaurants / favorites / reviews / dicts / geo / users / admin） |
| `lib/*.php` 共用模組 | ✅ db / response / input / auth / bootstrap / rate_limit / geocode / restaurants / admin / geo |
| Google Maps API 串接 | 🟡 後端代打 `/api/geo/geocode` 已完成；前端 Maps JS API 尚未整合 |

> 整合進度與分支：目前所有改動在 `feat/integrate-prototype` 分支上，main 還是舊狀態。等 review 通過再合進 main。

---

## 👥 分工

| 成員 | 角色 | 主要範圍 |
|---|---|---|
| A | DB + 部署 | schema、CSV 匯入、分類腳本、host |
| B | 後端 API（餐廳類） | 搜尋、篩選、詳情、輪盤池 |
| C | 後端 API（使用者類） | 註冊登入、收藏、評論、共用 lib |
| D | 前端 + Google Maps | 地圖、搜尋、路徑、API 代理 |
| E | 前端 + 簡報 | 輪盤、登入註冊頁、收藏歷史頁、簡報 |

---

## 🚀 快速開始

### 1. 環境準備

- [Git for Windows](https://git-scm.com/download/win)
- [XAMPP](https://www.apachefriends.org/)（含 PHP 8.x + MySQL + phpMyAdmin）
- [VS Code](https://code.visualstudio.com/)
- 網路連線（前端從 CDN 載 React / Babel / Leaflet）

### 2. Clone 專案

```bash
git clone https://github.com/Sean20051212/ntpc-foodmap.git
cd ntpc-foodmap
```

### 3. 設定 `config.php`

```bash
copy config.php.example config.php
```

打開 `config.php`，填入：
- DB 帳密（XAMPP 預設 `root` / 無密碼）
- `GOOGLE_MAPS_KEY_BACKEND`（後端 `/api/geo/geocode` 用，建議鎖 IP）
- `GOOGLE_MAPS_KEY_FRONTEND`（前端 Maps JS 用，建議鎖 referrer）
- `DEBUG_MODE = true`（開發時開啟，server_error 才會回真實訊息）

> ⚠️ `config.php` 永遠不會被 commit（已在 `.gitignore`），含 API key 跟密碼。

### 4. 建立資料庫

phpMyAdmin (http://localhost/phpmyadmin) 執行：

```sql
CREATE DATABASE ntpc_foodmap DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

然後匯入 [sql/database.sql](sql/database.sql)（含 schema + 觸發器 + 全部 seed 資料）。

### 5. 部署到 XAMPP

**注意：** `htdocs` 下是專案的 **複製**（不是 symlink）。每次改 code 都要重新覆製：

```powershell
$src = "C:\Users\User\Documents\ntpc-foodmap"
$dst = "C:\xampp\htdocs\ntpc-foodmap"
Remove-Item -Recurse -Force $dst -ErrorAction SilentlyContinue
Copy-Item -Recurse $src $dst -Exclude @("node_modules",".git","ntpc.zip")
```

確認 XAMPP Apache + MySQL 都啟動後，瀏覽器開：
**http://localhost/ntpc-foodmap/**（入口是根目錄的 `index.html`，不是 `/pages/...`）

每次改完前端 / 後端 → 重新部署 → 瀏覽器按 **Ctrl+F5**（不是 F5）強制清快取。
或開 DevTools「Disable cache」開發期間都用這個。

### 6. 預設 admin 帳號

匯入 `sql/database.sql` 後，DB 內只有一筆預設管理員：

| 欄位 | 值 |
|---|---|
| username | `admin` |
| password | `admin123` |
| is_admin | 1 |

> ⚠️ 上線 / 公開 demo 前請至少把密碼改掉。改密碼方法：以 admin 登入 → 個人頁 → 修改密碼，或直接 SQL：
> ```powershell
> & "C:\xampp\php\php.exe" -r "echo password_hash('新密碼', PASSWORD_BCRYPT);"
> ```
> ```sql
> UPDATE users SET password_hash='<貼上 hash>' WHERE username='admin';
> ```

要新增一般使用者：用前端 `/register` 註冊即可。要把某帳號晉升為 admin：
```sql
UPDATE users SET is_admin=1 WHERE username='<該帳號>';
```

---

## 📁 資料夾結構

```
ntpc-foodmap/
├── index.html               # 前端入口（React via Babel）
├── js/                      # 前端 JSX
│   ├── api.js               # fetch wrapper（唯一允許的前端邏輯）
│   ├── ui.jsx               # 共用元件
│   ├── app.jsx              # router + 主入口
│   ├── page-*.jsx           # 各頁面（index / explore / detail / profile / auth / admin）
│   ├── mock-backend.js      # 保留供參考，目前不載入
│   └── mock-data.js         # 同上
├── assets/
│   └── app.css              # 全站樣式
├── api/                     # 後端 PHP API（27 支端點）
│   ├── auth/                # register / login / logout / me / change_password
│   ├── restaurants/         # list / count / detail / recommendations / carousel / nearby_ntpc / wheel_*
│   ├── favorites/           # toggle / list
│   ├── reviews/             # upsert / delete / by_restaurant / by_user
│   ├── dicts/               # districts / tags
│   ├── geo/                 # locate / geocode
│   ├── users/               # profile
│   ├── admin/               # restaurant/* / photo/* / users/*
│   └── history/             # （後端額外做的，前端目前未使用）
├── lib/                     # 共用 PHP 模組
│   ├── bootstrap.php        # 啟動：載入 db / response / input / auth
│   ├── db.php               # PDO 連線
│   ├── response.php         # jsonOk / jsonErr / requireMethod
│   ├── input.php            # getInput / requireString / requireInt 等
│   ├── auth.php             # requireLogin / publicUser
│   ├── rate_limit.php       # rateLimitCheck
│   ├── geocode.php          # Google Geocoding 代打
│   ├── restaurants.php      # 共用查詢與篩選邏輯
│   ├── geo.php / admin.php  # 對應領域 helper
├── sql/
│   └── database.sql         # 完整 schema + 觸發器 + 全部 seed
├── scripts/
│   ├── import_restaurants.mjs   # CSV → INSERT
│   └── enrich_google.mjs        # Google Places 補欄位
├── docs/                    # 規格與計畫
├── config.php               # （不在 Git）DB / API key
├── config.php.example       # 設定檔範本
└── README.md
```

---

## ⚠️ 已知問題 / 待修

| 編號 | 問題 | 影響 | 應由誰修 | 短期 workaround |
|---|---|---|---|---|
| #1 | **CASCADE 刪除不觸發 trigger** — MySQL InnoDB 不會在 `ON DELETE CASCADE` 時跑 trigger，所以從 `users` 刪人 → `reviews` 連動刪 → 餐廳的 `rating_avg` / `rating_count` **不會被自動重算**，留著舊值 | 從 admin 刪用戶（或 SQL 直接 `DELETE FROM users`）後，被該用戶評過的餐廳評分變成舊資料。同理刪 `restaurants` 連動刪 `reviews` 時，rating 統計也對不上（但餐廳本身已被刪，影響較小） | C（後端使用者類） / A（DB） | 跑 SQL 重算所有餐廳評分： `UPDATE restaurants r LEFT JOIN (SELECT restaurant_id, AVG(rating) avg_rating, COUNT(*) cnt FROM reviews GROUP BY restaurant_id) rv ON rv.restaurant_id = r.restaurant_id SET r.rating_avg = COALESCE(rv.avg_rating, 0), r.rating_count = COALESCE(rv.cnt, 0);` |
| #2 | **前端透過 CDN 載 React/Babel/Leaflet** — `index.html` 從 unpkg.com 抓 | 沒網路時前端整個跑不起來；正式 demo 前要考慮改本機檔案 | D / E | 短期沒網路改用 hotspot；正式 demo 前改自帶 |
| #3 | **`/api/history/*` 後端做了但前端沒在用** — backend-plan §4 沒規格此區，後端組員自行加的 | 多了 3 支沒人打的端點 | C | 跟 C 確認是否要保留或刪掉 |

---

## 🔀 Git 協作流程

### 重要原則

1. **絕對不能直接 push main**：所有改動走 Pull Request
2. **絕對不能 commit `config.php`**：含 API key
3. **每個功能開獨立 branch**：命名 `feat/角色字母-功能名` 或 `feat/功能描述`
4. **commit 前先 pull main**
5. **commit message 用英文**，前綴 `feat:` / `fix:` / `docs:` / `refactor:` / `style:` / `chore:`

### 日常流程

```bash
git checkout main
git pull origin main
git checkout -b feat/B-something

# 寫 code、測試...

git status
git diff
git add .
git commit -m "feat: 完成某功能"
git push origin feat/B-something
# 到 GitHub 開 PR → 指派 reviewer → merge

# 合併後清理本機分支
git checkout main
git pull origin main
git branch -d feat/B-something
```

---

## ⚠️ 絕對不能做的事

| 動作 | 後果 | 怎麼避免 |
|---|---|---|
| commit `config.php` / `.env` | API key 外洩、Google 帳單爆掉 | `.gitignore` 已設、commit 前 `git status` 檢查 |
| commit `node_modules/` | repo 變幾百 MB | `.gitignore` 已設 |
| 直接 push main | 跳過 review | 分支保護 |
| force push 共用 branch | 蓋掉別人提交 | 永遠不要 |
| 大檔案塞進 repo | repo 變慢 | 二進制檔（zip、dump）放外部 |

**萬一 commit 了密鑰：**
1. 立刻去 GCP Console 刪 / 重新申請 key
2. 改 DB 密碼
3. 通知所有人不要 pull 那個 commit
4. 由負責人清 Git 歷史（`git filter-branch` 或 BFG）

---

## 📋 文件與規格

| 檔案 | 給誰 | 內容 |
|---|---|---|
| [docs/design-audit.md](docs/design-audit.md) | 共用 | schema 相容性審查 + 7 項已確認決策 |
| [docs/backend-plan.md](docs/backend-plan.md) | B、C | 27 支 API 端點完整規格 + lib/ + curl 驗收 |
| [docs/frontend-plan.md](docs/frontend-plan.md) | D、E | 嚴禁清單 + 各頁面 wireframe + API 呼叫表 |

> **原則：** 所有運算寫在後端，前端只負責顯示。任何距離計算、篩選邏輯、抽選邏輯一律不准寫在前端。

---

## 🛠️ 推薦工具

| 用途 | 工具 |
|---|---|
| Code 編輯 | VS Code + GitLens |
| API 測試 | Postman / DevTools Network |
| DB GUI | phpMyAdmin（XAMPP 內建）或 HeidiSQL |
| 即時溝通 | Discord |

---

## 🆘 常見問題

**Q: 改了 code 但網頁沒變？**
A: (1) 沒重新部署到 `htdocs`（複製不是 symlink）。(2) 沒按 Ctrl+F5 強制清快取。

**Q: 登入時跳「伺服器回應格式錯誤 (HTTP 200)」？**
A: 後端 PHP fatal 噴成 response body。看 `C:\xampp\apache\logs\error.log` 最後幾行找原因；最常見是 `config.php` 路徑不對或 DB 連不上。

**Q: 後台餐廳列表只顯示 50 家？**
A: 已修正成上限 1000。沒看到改動要重新部署 + Ctrl+F5。

**Q: 刪了用戶後餐廳評分不對？**
A: 見「已知問題 #1」，跑那段 UPDATE 重算即可。

**Q: pull 出現「Your local changes would be overwritten」？**
A: `git stash` → `git pull` → `git stash pop`。

**Q: 不小心在 main 改了 code？**
A: `git stash` → `git checkout -b feat/xxx` → `git stash pop` → commit。

---

## 📞 聯絡

有問題先丟 Discord 頻道，緊急情況再 @ 負責人。
