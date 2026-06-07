# ERD 正規化審查（DB-8）

審查日期：2026-06-07
範圍：13 張表（含 DB-1/4/5/6/10/11/12 改動後的最終 schema）

---

## 表清單

| 表 | PK | 角色 |
|---|---|---|
| `users` | user_id | 使用者帳號 |
| `restaurants` | restaurant_id | 餐廳主表 |
| `districts` | zipcode | 行政區查找 |
| `district_adjacency` | (zipcode_a, zipcode_b) | 區之間相鄰關係（無向圖） |
| `tags` | tag_id | 分類定義 |
| `restaurant_tags_mapping` | (restaurant_id, tag_id) | 餐廳 ↔ 分類 M:N |
| `restaurant_phones` | phone_id | 多電話（餐廳 1:N） |
| `restaurant_photos` | photo_id | 多照片（餐廳 1:N） |
| `opentime` | opentime_id | 多營業時段（餐廳 1:N） |
| `days_of_week` | day_id | 星期查找（DB-11 抽出） |
| `price_levels` | price_level_id | 價位查找（DB-10 抽出） |
| `reviews` | (user_id, restaurant_id) | 評論（每人每店一則） |
| `favorites` | (user_id, restaurant_id) | 收藏 |

---

## 1NF（屬性原子化、無重複群組）

**結論：✅ 全部通過**

- 多值欄位均已抽出獨立表：電話 → `restaurant_phones`、分類 → `restaurant_tags_mapping`、照片 → `restaurant_photos`、營業時段 → `opentime`
- 無 CSV / JSON-in-string / 重複欄位（如 `phone1, phone2, phone3`）

---

## 2NF（無對部分鍵的依賴）

只有 4 張表使用複合主鍵：

| 表 | PK | 非鍵屬性 | 評估 |
|---|---|---|---|
| `reviews` | (user_id, restaurant_id) | rating, comment, created_at, updated_at | rating/comment 是「這個 user 對這家店」的意見 → 對完整 PK 依賴 ✅ |
| `favorites` | (user_id, restaurant_id) | created_at | 「這個 user 收藏這家店的時間」依賴完整 PK ✅ |
| `restaurant_tags_mapping` | (restaurant_id, tag_id) | 無 | 無非鍵屬性，自然滿足 ✅ |
| `district_adjacency` | (zipcode_a, zipcode_b) | 無 | 無非鍵屬性 ✅ |

**結論：✅ 全部通過**

---

## 3NF（無傳遞依賴）

主要檢查 `restaurants`：

| 屬性 | 是否傳遞依賴？ | 處理 |
|---|---|---|
| zipcode | restaurants → districts → district_name / center_lat / center_lng | ✅ 透過 FK 取，restaurants 不重複存 district_name 等 |
| price_level | restaurants → price_levels → symbol / label_zh / label_en | ✅ FK 取（DB-10 抽出後） |
| rating_avg, rating_count | 由 `reviews` 聚合而來 | ⚠ **計算性冗餘**，由 trigger 維護（見下方「合理的反正規化」） |
| google_place_id | 外部系統識別碼，UNIQUE | ✅ 直接屬性，無傳遞 |

其他表：

- `opentime.day` → FK `days_of_week.day_id` → day_name_zh / day_name_en（DB-1/11 抽出） ✅
- `restaurant_photos.url` 與 `local_path` 並存：兩者皆為直接屬性（url=外部來源、local_path=本機快取），非互相推導 ✅
- `users.is_admin` 是直接權限旗標，無依賴問題

**結論：✅ 全部 3NF**，唯一例外 `rating_avg / rating_count` 是刻意的反正規化。

---

## BCNF（每個非平凡函數依賴的決定子都是候選鍵）

掃過所有 candidate determinant：

| 表 | 候選鍵 | 評估 |
|---|---|---|
| `restaurants` | restaurant_id (PK), google_place_id (UNIQUE) | ✅ 兩者皆 candidate key |
| `tags` | tag_id (PK), tag_name (UNIQUE `uk_tags_name`) | ✅ |
| `districts` | zipcode (PK), district_name (UNIQUE `uk_districts_name`) | ✅ |
| `days_of_week` | day_id (PK), day_name_zh (UNIQUE), day_name_en (UNIQUE) | ✅ |
| `price_levels` | price_level_id (PK), symbol (UNIQUE) | ✅ |
| `users` | user_id (PK), username (UNIQUE) | ✅ 兩者皆 candidate key |

**結論：3NF 完全達成、BCNF 完全達成**（已補上 districts / days_of_week 缺漏的 UNIQUE）。

---

## 4NF / 5NF

- **4NF（無多值依賴）**：所有多值關係都已用獨立表處理（phones / tags mapping / photos / opentime）✅
- **5NF（無 join 依賴）**：本 schema 無三方以上的 join 依賴問題 ✅

---

## 合理的反正規化（明知違反但有理由）

| 欄位 | 違反 | 理由 |
|---|---|---|
| `restaurants.rating_avg` | 可由 reviews 計算 | 列表頁要顯示 + 排序，每次 AVG() 太慢；trigger 在 reviews INSERT/UPDATE/DELETE 時同步維護，consistency 由 DB 層保證 |
| `restaurants.rating_count` | 同上 | 同上 |
| `restaurant_photos.local_path` | 可由 url + sync 狀態推導 | 兩者並存是為了 fallback（local_path NULL 時用 url），且 local_path 是檔案系統位置不能即時推導，必須持久化 |

---

## 改善紀錄

本次審查順手補強 BCNF：
- `districts.district_name` 加 `uk_districts_name`
- `days_of_week.day_name_zh` 加 `uk_days_name_zh`
- `days_of_week.day_name_en` 加 `uk_days_name_en`
- `tags.uk_tags_name` 早已存在
- `users.uk_users_username` 早已存在

對應 migration：`sql/migrations/2026-06-07_lookup_unique.sql`

---

## 結論

**整體 schema 達 3NF，實質達 BCNF/4NF/5NF。** 反正規化處皆有明確理由與一致性保障（trigger / fallback 設計）。針對教授可能提的問題（「為什麼 rating 不算？」「為什麼存兩個照片欄位？」）已有解釋依據。

DB-1 ~ DB-12 的逐步改動已系統性消除歷史正規化問題（day / price_level 抽表、main_marker 拿掉、opentime 重疊清理等），目前無 schema 結構性債務。
