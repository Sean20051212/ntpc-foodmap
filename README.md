# 新北市美食地圖網站 (NTPC Food Map)

> 大學課程作業 · 五人協作 · PHP + MySQL + Google Maps API

以新北市政府觀光旅遊局餐飲業者開放資料（[dataset/123086](https://data.gov.tw/dataset/123086)）為基礎，提供地址搜尋、條件篩選、路徑規劃、隨機輪盤等功能。

---

## 📋 專案資訊

- **技術棧**：PHP 8.x + MySQL 8.x + 原生 HTML/CSS/JS + Google Maps API
- **開發環境**：XAMPP（Windows）
- **部署**：免費 PHP host（InfinityFree 或校內主機）
- **資料庫**：10 張表，完全符合 3NF
- **詳細規格**：見 `docs/` 資料夾

---

## 👥 分工

| 成員 | 角色 | 主要範圍 |
|---|---|---|
| A | DB + 部署 | schema、CSV 匯入、分類腳本、host |
| B | 後端 API（餐廳類） | 搜尋、篩選、詳情、輪盤池 |
| C | 後端 API（使用者類） | 註冊登入、收藏、評論、歷史、共用 lib |
| D | 前端 + Google Maps | 首頁、地圖、搜尋、路徑、API 代理 |
| E | 前端 + 簡報 | 輪盤、登入註冊頁、收藏歷史頁、簡報 |

---

## 🚀 快速開始

### 1. 環境準備

確認本機有裝：

- [Git for Windows](https://git-scm.com/download/win)
- [XAMPP](https://www.apachefriends.org/)（內含 PHP 8.x + MySQL + phpMyAdmin）
- [VS Code](https://code.visualstudio.com/)（推薦）
- [Postman](https://www.postman.com/)（B、C、D 測試 API 用）

### 2. Clone 專案

```bash
git clone https://github.com/[YOUR_ORG]/ntpc-foodmap.git
cd ntpc-foodmap
```

### 3. 設定 config.php

```bash
# 把範本複製成正式設定檔
copy config.php.example config.php
```

打開 `config.php`，填入你本機的 DB 帳密與 Google Maps API key。**這支檔案已加入 `.gitignore`，永遠不會被 commit**。

### 4. 建立資料庫

開啟 XAMPP，啟動 Apache + MySQL。打開 phpMyAdmin（http://localhost/phpmyadmin）：

```sql
CREATE DATABASE ntpc_foodmap DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

匯入 schema 與初始資料（A 提供後）：

```bash
# 在 XAMPP 的 mysql/bin 下執行
mysql -u root ntpc_foodmap < sql/schema.sql
mysql -u root ntpc_foodmap < sql/seed_districts.sql
mysql -u root ntpc_foodmap < sql/seed_dict.sql
php sql/import.php       # 匯入餐廳資料
php sql/classify.php     # 跑分類腳本
```

### 5. 放進 XAMPP 並開啟

把整個專案資料夾放到 `C:\xampp\htdocs\` 下，瀏覽器開 `http://localhost/ntpc-foodmap/pages/index.php`。

---

## 📁 資料夾結構

```
ntpc-foodmap/
├── api/                  # 後端 PHP API
│   ├── auth/             # 註冊登入登出（C）
│   ├── favorites/        # 收藏（C）
│   ├── reviews/          # 評論（C）
│   ├── history/          # 歷史紀錄（C）
│   ├── restaurants/      # 餐廳搜尋詳情（B）
│   ├── maps/             # Google Maps 代理（D）
│   └── dicts/            # 字典資料（B）
├── assets/
│   ├── js/               # 前端 JavaScript（D、E）
│   └── css/              # 樣式表（D、E）
├── pages/                # 前端頁面（D、E）
├── lib/                  # 共用 PHP（C）
│   ├── db.php            # PDO 連線
│   ├── auth_check.php    # 登入驗證
│   └── rate_limit.php    # API 限流（D）
├── sql/                  # SQL 與匯入腳本（A）
│   ├── schema.sql
│   ├── seed_dict.sql
│   ├── seed_districts.sql
│   ├── import.php
│   └── classify.php
├── docs/                 # 文件、規格、簡報
├── config.php            # （不在 Git 中，本機自建）
├── config.php.example    # 設定檔範本
├── .gitignore
└── README.md
```

---

## 🔀 Git 協作流程

### 重要原則

1. **絕對不能直接 push main**：所有改動必須走 Pull Request
2. **絕對不能 commit `config.php`**：含 API key 和密碼，會被偷刷錢
3. **每個功能開獨立 branch**：命名規則 `feat/角色字母-功能名`，例如 `feat/B-nearby-api`
4. **commit 前先 pull main**：避免基底是舊的，造成大量衝突
5. **commit message 要有意義**：用 `feat:` / `fix:` / `docs:` 等前綴

### 日常工作流程

```bash
# 1. 確保 main 是最新的
git checkout main
git pull origin main

# 2. 開自己的功能分支
git checkout -b feat/B-nearby-api

# 3. 寫 code、存檔、測試...

# 4. 確認改了什麼
git status
git diff

# 5. 提交變更
git add .
git commit -m "feat: 完成 nearby 搜尋 API"

# 6. 推到 GitHub
git push origin feat/B-nearby-api

# 7. 到 GitHub 網頁開 Pull Request → 找其他人 review → merge

# 8. 合併完成後刪除本機分支
git checkout main
git pull origin main
git branch -d feat/B-nearby-api
```

### Commit Message 格式

| 前綴 | 用途 | 範例 |
|---|---|---|
| `feat:` | 新功能 | `feat: 完成輪盤動畫` |
| `fix:` | 修 bug | `fix: nearby API 經緯度順序錯誤` |
| `docs:` | 改文件 | `docs: 更新 API 規格` |
| `refactor:` | 重構但功能不變 | `refactor: 抽出共用查詢函式` |
| `style:` | 純樣式調整 | `style: 統一按鈕圓角` |
| `chore:` | 雜項（建構、依賴） | `chore: 更新 .gitignore` |

### 處理衝突

當兩人改到同一檔案會衝突：

```bash
git checkout feat/B-nearby-api
git pull origin main           # 拉最新 main 進你的分支
# 若有衝突，Git 會在檔案中標出 <<<<<<< ======= >>>>>>>
# 用 VS Code 開啟，會有「Accept Current / Incoming / Both」按鈕，點選後存檔
git add .
git commit -m "merge: 解決與 main 的衝突"
git push
```

### Pull Request 規範

開 PR 時請填：

- **標題**：與 commit message 一致的格式
- **內容**：簡述改了什麼、為什麼改、怎麼測試
- **Reviewer**：至少指派 1 位組員 review
- **Linked Issue**（若有）：關聯到 GitHub Issue

---

## ⚠️ 絕對不能做的事

| 動作 | 後果 | 怎麼避免 |
|---|---|---|
| commit `config.php` | API key 外洩、Google 帳單爆掉 | 加 `.gitignore`、commit 前 `git status` 檢查 |
| commit `node_modules/` | repo 變幾百 MB、pull 不動 | 加 `.gitignore` |
| 直接 push main | 跳過 review、可能弄壞所有人 | 分支保護已開啟，會被擋下 |
| force push（`git push -f`） | 蓋掉別人的提交 | 永遠不要對共用 branch 用 |
| 大檔案塞進 repo | 整個 repo 變慢 | DB 匯出檔放 Google Drive 分享連結 |

**萬一不小心 commit 了 `config.php`**：

1. 立刻把 Google Maps API key 在 GCP Console 刪掉、重新申請
2. 改 DB 密碼
3. 通知所有組員不要 pull 那個 commit
4. 由負責人處理 Git 歷史清除（用 `git filter-branch` 或 BFG Repo-Cleaner）

---

## 📋 文件與規格

所有規格文件放在 `docs/`：

- `docs/spec.pdf`：五人完整規格文件
- `docs/api-spec.md`：API 規格（B、C 維護）
- `docs/er-diagram.png`：ER 圖（A 維護）

---

## 🛠️ 推薦工具

| 用途 | 工具 |
|---|---|
| Code 編輯 | VS Code + GitLens 擴充 |
| API 測試 | Postman |
| DB GUI | phpMyAdmin（XAMPP 內建）或 HeidiSQL |
| 即時溝通 | Discord |
| 任務追蹤 | GitHub Projects（看板） |
| ER 圖 | dbdiagram.io |

---

## 🆘 常見問題

**Q: pull 出現「Your local changes would be overwritten」？**  
A: 先 `git stash` 把本機改動暫存，pull 完再 `git stash pop`。

**Q: 不小心在 main 上改了 code 怎麼辦？**  
A: `git stash` → `git checkout -b feat/xxx-yyy` → `git stash pop` → 在新分支 commit。

**Q: VS Code 解衝突按了 Accept Current/Incoming，分別是哪邊？**  
A: Current = 你的版本，Incoming = 對方版本。看清楚再點。

**Q: 我的 commit 訊息打錯了怎麼辦？**  
A: 若還沒 push，`git commit --amend -m "新訊息"`。已 push 就算了，不要 force push。

---

## 📞 聯絡

有問題先丟 Discord 頻道，緊急情況再 @ 負責人。
