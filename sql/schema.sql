-- 0. 每次執行都把整棟大樓炸掉，重新蓋一棟全新的!
-- [LOCAL DEV ONLY] (僅限本地開發)
-- DROP DATABASE IF EXISTS ntpc_foodmap;
-- CREATE DATABASE ntpc_foodmap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE ntpc_foodmap;

-- ==========================================
-- 0. 清除舊資料表 (注意順序：必須先刪除有 FK 依賴的子表)
-- ==========================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `restaurant_tags_mapping`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `api_rate_log`;
DROP TABLE IF EXISTS `wheel_history`;
DROP TABLE IF EXISTS `search_history`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `districts`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 1. 建立獨立主表 (Independent Tables)
-- ==========================================

-- 使用者資料表
CREATE TABLE `users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `idx_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 行政區資料表
CREATE TABLE `districts` (
  `zipcode` VARCHAR(10) NOT NULL,
  `district_name` VARCHAR(20) NOT NULL,
  `center_lng` DECIMAL(10,7) NOT NULL,
  `center_lat` DECIMAL(10,7) NOT NULL,
  PRIMARY KEY (`zipcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 標籤主表
CREATE TABLE `tags` (
  `tag_id` INT NOT NULL AUTO_INCREMENT,
  `tag_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 獨立的 API 流量 Log 表
CREATE TABLE `api_rate_log` (
  `log_id` INT NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(50) NOT NULL,
  `endpoint` VARCHAR(255) NOT NULL,
  `called_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 2. 建立依賴主表 (Dependent Tables)
-- ==========================================

-- 餐廳資料表 (依賴 districts)
CREATE TABLE `restaurants` (
  `restaurant_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `tel` VARCHAR(50) DEFAULT NULL,
  `address` VARCHAR(255) NOT NULL,
  `zipcode` VARCHAR(10) NOT NULL,
  `opentime` TEXT DEFAULT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `changetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`restaurant_id`),
  CONSTRAINT `fk_restaurants_zipcode` FOREIGN KEY (`zipcode`) REFERENCES `districts` (`zipcode`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 3. 建立關聯表與紀錄表 (Relationship & Log Tables)
-- ==========================================

-- 收藏夾 (使用者 M:N 餐廳)
CREATE TABLE `favorites` (
  `favorite_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `restaurant_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`favorite_id`),
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_favorites_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 評論表 (使用者 M:N 餐廳，帶有額外屬性)
CREATE TABLE `reviews` (
  `review_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `restaurant_id` INT NOT NULL,
  `rating` TINYINT NOT NULL COMMENT '星等 (1-5)',
  `comment` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_reviews_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 搜尋歷史紀錄
CREATE TABLE `search_history` (
  `search_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `filter_json` JSON DEFAULT NULL,
  `searched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`search_id`),
  CONSTRAINT `fk_search_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 幸運大輪盤抽中紀錄
CREATE TABLE `wheel_history` (
  `wheel_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `restaurant_id` INT NOT NULL,
  `conditions_json` JSON DEFAULT NULL,
  `spun_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`wheel_id`),
  CONSTRAINT `fk_wheel_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wheel_history_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 餐廳與標籤的中介表 (多對多關聯)
CREATE TABLE `restaurant_tags_mapping` (
  `restaurant_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`restaurant_id`, `tag_id`),
  CONSTRAINT `fk_mapping_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mapping_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 4. 插入 3 筆測試假資料 (Mock Data)
-- ==========================================

-- 4.1 系統基礎資料：行政區 (districts)
-- 經緯度資料直接對應真實新北市餐飲業者資料庫的中心位置
INSERT INTO `districts` (`zipcode`, `district_name`, `center_lng`, `center_lat`) VALUES
('231', '新店區', 121.5420900, 24.9825800),
('220', '板橋區', 121.4677700, 25.0220400),
('251', '淡水區', 121.4441100, 25.1683500);

-- 4.2 系統基礎資料：標籤 (tags)
-- 根據真實店家的特色重新定義 tags
INSERT INTO `tags` (`tag_id`, `tag_name`) VALUES
(1, '港式'),
(2, '咖啡'),
(3, '日式');

-- 4.3 使用者資料 (users) - 密碼維持 dummy hash 供開發測試
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `created_at`) VALUES
(1, 'heping_peace', '$2y$10$abcdefghijklmnopqrstuv', '2026-05-01 10:00:00'),
(2, 'foodie_mary', '$2y$10$1234567890abcdefghijkl', '2026-05-02 14:30:00'),
(3, 'tech_guru',  '$2y$10$zyxwvutsrqponmlkjihgfe', '2026-05-15 09:15:00');

-- 4.4 餐廳資料 (restaurants)
-- 完全採用新北市餐飲業者真實資料：永哥港式點心坊、MATTER CAFÉ、壽司屋
INSERT INTO `restaurants` (`restaurant_id`, `name`, `description`, `tel`, `address`, `zipcode`, `opentime`, `longitude`, `latitude`, `changetime`) VALUES
(1, '永哥港式點心坊', '菜色選擇豐富、店家服務良好是網路上大推的餐廳，位於捷運大坪林旁的永哥港式點心坊是聚餐的好選擇，主廚曾待過神旺飯店、京星港式飲茶等知名餐廳，廚藝經歷二十餘年，料理絕對屬於水準之上。', '886-2-29132986', '新北市231新店區寶安街36號', '231', '11:00–14:00、17:00–21:00 / 週一、二公休', 121.5420900, 24.9825800, '2020-07-28 14:06:00'),
(2, 'MATTER CAFÉ', 'MATTER CAFÉ位在捷運新埔站附近，主打早午餐和舒芙蕾，工業風簡約裝潢，佐以乾燥花和小雲朵裝飾，讓整個空間亮起來，浪漫又可愛。來到這必點舒芙蕾鬆餅，網路上好評不斷。', '886-2-22562800', '新北市220板橋區文化路一段316號1樓', '220', '平日09:00 - 20:20 / 假日08:00 - 20:30', 121.4677700, 25.0220400, '2020-07-28 14:27:00'),
(3, '壽司屋', '可別看這間壽司屋外表毫不起眼，隱身在人來人往的老街上，裡頭可是藏著五星級美味的握壽司，除一般定食、單點，還提供無菜單料理，老饕級吃客懂得趁早搶占壽司吧檯前的位子。', '886-2-26292873', '新北市251淡水區公明街87號', '251', '13:30-14:00 17:00-21:00 (關店前半小時不接客，週一公休)', 121.4441100, 25.1683500, '2019-10-27 17:43:00');

-- 4.5 對餐廳貼標籤 (restaurant_tags_mapping)
INSERT INTO `restaurant_tags_mapping` (`restaurant_id`, `tag_id`) VALUES
(1, 1), -- 永哥港式點心坊 -> 港式
(2, 2), -- MATTER CAFÉ -> 咖啡
(3, 3); -- 壽司屋 -> 日式

-- 4.6 使用者互動：收藏 (favorites)
INSERT INTO `favorites` (`user_id`, `restaurant_id`, `created_at`) VALUES
(1, 1, '2026-05-05 12:00:00'), -- 平和 收藏了 永哥港式點心坊
(1, 2, '2026-05-10 18:00:00'), -- 平和 收藏了 MATTER CAFÉ
(2, 1, '2026-05-12 13:15:00'); -- Mary 收藏了 永哥港式點心坊

-- 4.7 使用者互動：評論 (reviews)
INSERT INTO `reviews` (`user_id`, `restaurant_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 5, '燒賣跟黑糖麻糬蛋塔絕配，轉資工熬夜寫 code 完的大餐首選！', '2026-05-05 12:30:00'),
(2, 2, 4, '舒芙蕾超蓬鬆！環境安靜且不收服務費，很適合帶筆電來寫 project。', '2026-05-13 15:00:00'),
(3, 3, 5, '老街隱藏版握壽司，新鮮度破表，無菜單料理非常對得起這個價格。', '2026-05-19 20:45:00');

-- 4.8 功能紀錄：搜尋歷史 (search_history)
-- 嚴格限縮 filter_json 的 keys，只保留分類 (categories)、區域 (districts)、距離 (distance_meters)
INSERT INTO `search_history` (`user_id`, `address`, `filter_json`, `searched_at`) VALUES
(1, '大坪林捷運站', '{}', '2026-05-20 10:00:00'),
(2, '板橋車站', '{"categories": ["咖啡"], "districts": ["板橋區"]}', '2026-05-20 11:30:00'),
(3, '淡水老街', '{"categories": ["日式", "飯"], "distance_meters": 1500}', '2026-05-20 15:45:00');

-- 4.9 功能紀錄：大輪盤歷史 (wheel_history)
-- 嚴格限縮 conditions_json，只存放純粹的食物分類，如：火鍋、壽司、飯、麵
INSERT INTO `wheel_history` (`user_id`, `restaurant_id`, `conditions_json`, `spun_at`) VALUES
(1, 1, '{"categories": ["麵"]}', '2026-05-20 12:05:00'),
(2, 2, '{"categories": ["飯", "火鍋"]}', '2026-05-20 14:00:00'),
(3, 3, '{"categories": ["壽司"]}', '2026-05-20 17:00:00');

-- 4.10 系統 Log：API 流量紀錄 (API Rate Log)
INSERT INTO `api_rate_log` (`ip`, `endpoint`, `called_at`) VALUES
('127.0.0.1', '/api/v1/restaurants', '2026-05-20 17:30:00'),
('192.168.1.5', '/api/v1/wheel/spin', '2026-05-20 17:31:02'),
('127.0.0.1', '/api/v1/reviews/add', '2026-05-20 17:32:00');
