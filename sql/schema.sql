-- ntpc-foodmap 資料庫 schema
-- [LOCAL DEV ONLY] (僅限本地開發)
-- DROP DATABASE IF EXISTS ntpc_foodmap;
-- CREATE DATABASE ntpc_foodmap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE ntpc_foodmap;
--
-- 日期慣例：opentime.day 採 0=週日 .. 6=週六（對應 JavaScript Date.getDay()）

-- ==========================================
-- 0. 砍掉重練（含舊版本殘留 table 與 trigger）
-- ==========================================
DROP TRIGGER IF EXISTS `trg_reviews_after_insert`;
DROP TRIGGER IF EXISTS `trg_reviews_after_update`;
DROP TRIGGER IF EXISTS `trg_reviews_after_delete`;

SET FOREIGN_KEY_CHECKS = 0;
-- 新 schema 的 tables
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `restaurant_tags_mapping`;
DROP TABLE IF EXISTS `tags`;
DROP TABLE IF EXISTS `opentime`;
DROP TABLE IF EXISTS `restaurant_photos`;
DROP TABLE IF EXISTS `restaurant_phones`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `district_adjacency`;
DROP TABLE IF EXISTS `districts`;
DROP TABLE IF EXISTS `users`;
-- 舊 schema 殘留（已從新版本移除）
DROP TABLE IF EXISTS `api_rate_log`;
DROP TABLE IF EXISTS `search_history`;
DROP TABLE IF EXISTS `wheel_history`;
SET FOREIGN_KEY_CHECKS = 1;


-- ==========================================
-- 1. 主表（無外鍵）
-- ==========================================

CREATE TABLE `users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `districts` (
  `zipcode` CHAR(3) NOT NULL,
  `district_name` VARCHAR(20) NOT NULL,
  `center_latitude` DECIMAL(10,7) NOT NULL,
  `center_longitude` DECIMAL(10,7) NOT NULL,
  PRIMARY KEY (`zipcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tags` (
  `tag_id` INT NOT NULL AUTO_INCREMENT,
  `tag_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `uk_tags_name` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 2. 依賴表
-- ==========================================

-- 行政區相鄰關係（無向圖，存單向以 zipcode_a < zipcode_b 為正規化）
CREATE TABLE `district_adjacency` (
  `zipcode_a` CHAR(3) NOT NULL,
  `zipcode_b` CHAR(3) NOT NULL,
  PRIMARY KEY (`zipcode_a`, `zipcode_b`),
  CONSTRAINT `chk_adjacency_order` CHECK (`zipcode_a` < `zipcode_b`),
  CONSTRAINT `fk_adjacency_a` FOREIGN KEY (`zipcode_a`) REFERENCES `districts` (`zipcode`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_adjacency_b` FOREIGN KEY (`zipcode_b`) REFERENCES `districts` (`zipcode`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurants` (
  `restaurant_id` INT NOT NULL AUTO_INCREMENT,
  `restaurant_name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `address` VARCHAR(255) NOT NULL COMMENT '區與之前部分已去頭，例：保安街87號',
  `zipcode` CHAR(3) NOT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `price_level` TINYINT DEFAULT NULL COMMENT '1: ~200 / 2: 200~600 / 3: 600~1500 / 4: 1500+',
  `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'trigger 維護',
  `rating_count` INT NOT NULL DEFAULT 0 COMMENT 'trigger 維護',
  `google_place_id` VARCHAR(100) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`restaurant_id`),
  UNIQUE KEY `uk_restaurants_google_place` (`google_place_id`),
  KEY `idx_restaurants_latlng` (`latitude`, `longitude`),
  KEY `idx_restaurants_rating_avg` (`rating_avg`),
  KEY `idx_restaurants_zipcode` (`zipcode`),
  CONSTRAINT `chk_restaurants_price_level` CHECK (`price_level` IS NULL OR `price_level` BETWEEN 1 AND 4),
  CONSTRAINT `chk_restaurants_rating_avg` CHECK (`rating_avg` BETWEEN 0 AND 5),
  CONSTRAINT `fk_restaurants_zipcode` FOREIGN KEY (`zipcode`) REFERENCES `districts` (`zipcode`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_phones` (
  `phone_id` INT NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT NOT NULL,
  `phone_number` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`phone_id`),
  KEY `idx_phones_restaurant` (`restaurant_id`),
  CONSTRAINT `fk_phones_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- restaurant_photos：每店僅一張 is_main=true 用 generated column + UNIQUE 強制
CREATE TABLE `restaurant_photos` (
  `photo_id` INT NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT NOT NULL,
  `url` VARCHAR(500) NOT NULL COMMENT '絕對網址',
  `is_main` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `main_marker` INT GENERATED ALWAYS AS (IF(`is_main` = 1, `restaurant_id`, NULL)) VIRTUAL,
  PRIMARY KEY (`photo_id`),
  KEY `idx_photos_restaurant` (`restaurant_id`),
  UNIQUE KEY `uk_one_main_per_restaurant` (`main_marker`),
  CONSTRAINT `fk_photos_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `opentime` (
  `opentime_id` INT NOT NULL AUTO_INCREMENT,
  `restaurant_id` INT NOT NULL,
  `day` TINYINT NOT NULL COMMENT '0=週日 .. 6=週六',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `spec_rec` VARCHAR(255) DEFAULT NULL COMMENT '顯示用文字（特殊備註）',
  PRIMARY KEY (`opentime_id`),
  KEY `idx_opentime_restaurant` (`restaurant_id`),
  CONSTRAINT `chk_opentime_day` CHECK (`day` BETWEEN 0 AND 6),
  CONSTRAINT `fk_opentime_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_tags_mapping` (
  `restaurant_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`restaurant_id`, `tag_id`),
  KEY `idx_mapping_tag` (`tag_id`),
  CONSTRAINT `fk_mapping_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mapping_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reviews` (
  `user_id` INT NOT NULL,
  `restaurant_id` INT NOT NULL,
  `rating` TINYINT NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `restaurant_id`),
  KEY `idx_reviews_restaurant` (`restaurant_id`),
  CONSTRAINT `chk_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5),
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reviews_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `favorites` (
  `user_id` INT NOT NULL,
  `restaurant_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `restaurant_id`),
  KEY `idx_favorites_user` (`user_id`),
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorites_restaurant` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 3. Triggers：reviews insert/update/delete 自動重算 restaurants.rating_avg / rating_count
-- ==========================================

DELIMITER $$

CREATE TRIGGER `trg_reviews_after_insert`
AFTER INSERT ON `reviews`
FOR EACH ROW
BEGIN
  UPDATE `restaurants`
  SET `rating_count` = (SELECT COUNT(*) FROM `reviews` WHERE `restaurant_id` = NEW.`restaurant_id`),
      `rating_avg`   = COALESCE((SELECT AVG(`rating`) FROM `reviews` WHERE `restaurant_id` = NEW.`restaurant_id`), 0)
  WHERE `restaurant_id` = NEW.`restaurant_id`;
END$$

CREATE TRIGGER `trg_reviews_after_update`
AFTER UPDATE ON `reviews`
FOR EACH ROW
BEGIN
  -- 對應新店重算
  UPDATE `restaurants`
  SET `rating_count` = (SELECT COUNT(*) FROM `reviews` WHERE `restaurant_id` = NEW.`restaurant_id`),
      `rating_avg`   = COALESCE((SELECT AVG(`rating`) FROM `reviews` WHERE `restaurant_id` = NEW.`restaurant_id`), 0)
  WHERE `restaurant_id` = NEW.`restaurant_id`;
  -- 若評論被搬到另一家店（罕見），舊店也要重算
  IF OLD.`restaurant_id` <> NEW.`restaurant_id` THEN
    UPDATE `restaurants`
    SET `rating_count` = (SELECT COUNT(*) FROM `reviews` WHERE `restaurant_id` = OLD.`restaurant_id`),
        `rating_avg`   = COALESCE((SELECT AVG(`rating`) FROM `reviews` WHERE `restaurant_id` = OLD.`restaurant_id`), 0)
    WHERE `restaurant_id` = OLD.`restaurant_id`;
  END IF;
END$$

CREATE TRIGGER `trg_reviews_after_delete`
AFTER DELETE ON `reviews`
FOR EACH ROW
BEGIN
  UPDATE `restaurants`
  SET `rating_count` = (SELECT COUNT(*) FROM `reviews` WHERE `restaurant_id` = OLD.`restaurant_id`),
      `rating_avg`   = COALESCE((SELECT AVG(`rating`) FROM `reviews` WHERE `restaurant_id` = OLD.`restaurant_id`), 0)
  WHERE `restaurant_id` = OLD.`restaurant_id`;
END$$

DELIMITER ;


-- ==========================================
-- 4. 測試假資料 (Mock Data)
-- ==========================================

-- 4.1 行政區（含相鄰示範用的 5 個區）
INSERT INTO `districts` (`zipcode`, `district_name`, `center_latitude`, `center_longitude`) VALUES
('220', '板橋區', 25.0220400, 121.4677700),
('231', '新店區', 24.9825800, 121.5420900),
('234', '永和區', 25.0073800, 121.5151700),
('235', '中和區', 24.9990500, 121.4988800),
('251', '淡水區', 25.1683500, 121.4441100);

-- 4.2 相鄰關係（zipcode_a < zipcode_b 正規化）
INSERT INTO `district_adjacency` (`zipcode_a`, `zipcode_b`) VALUES
('220', '234'), -- 板橋 ↔ 永和
('220', '235'), -- 板橋 ↔ 中和
('231', '235'), -- 新店 ↔ 中和
('234', '235'); -- 永和 ↔ 中和

-- 4.3 標籤
INSERT INTO `tags` (`tag_id`, `tag_name`) VALUES
(1, '港式'),
(2, '咖啡'),
(3, '日式'),
(4, '老街美食'),
(5, '無菜單料理');

-- 4.4 使用者（user_id=1 為管理員）
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `is_admin`, `created_at`, `updated_at`) VALUES
(1, 'admin',       '$2y$10$abcdefghijklmnopqrstuv', 1, '2026-05-01 10:00:00', '2026-05-01 10:00:00'),
(2, 'foodie_mary', '$2y$10$1234567890abcdefghijkl', 0, '2026-05-02 14:30:00', '2026-05-02 14:30:00'),
(3, 'tech_guru',   '$2y$10$zyxwvutsrqponmlkjihgfe', 0, '2026-05-15 09:15:00', '2026-05-15 09:15:00');

-- 4.5 餐廳（address 已去掉「新北市XXX區」前綴）
INSERT INTO `restaurants` (`restaurant_id`, `restaurant_name`, `description`, `address`, `zipcode`, `latitude`, `longitude`, `price_level`, `google_place_id`, `updated_at`) VALUES
(1, '永哥港式點心坊', '主廚曾待過神旺飯店、京星港式飲茶等知名餐廳，廚藝經歷二十餘年，料理屬於水準之上。',
    '寶安街36號', '231', 24.9825800, 121.5420900, 2, NULL, '2026-05-01 10:00:00'),
(2, 'MATTER CAFÉ', '位在捷運新埔站附近，主打早午餐和舒芙蕾，工業風簡約裝潢。',
    '文化路一段316號1樓', '220', 25.0220400, 121.4677700, 2, NULL, '2026-05-01 10:00:00'),
(3, '壽司屋', '隱身在淡水老街，藏著五星級美味的握壽司，提供無菜單料理。',
    '公明街87號', '251', 25.1683500, 121.4441100, 3, NULL, '2026-05-01 10:00:00');

-- 4.6 電話（壽司屋示範 2 隻電話）
INSERT INTO `restaurant_phones` (`restaurant_id`, `phone_number`) VALUES
(1, '886-2-29132986'),
(2, '886-2-22562800'),
(3, '886-2-26292873'),
(3, '886-9-12345678');

-- 4.7 照片（每店一張主圖）
INSERT INTO `restaurant_photos` (`restaurant_id`, `url`, `is_main`, `sort_order`) VALUES
(1, 'https://example.com/photos/1-main.jpg', 1, 0),
(2, 'https://example.com/photos/2-main.jpg', 1, 0),
(3, 'https://example.com/photos/3-main.jpg', 1, 0),
(3, 'https://example.com/photos/3-extra.jpg', 0, 1);

-- 4.8 營業時間（範例：永哥週三/週六 兩時段；MATTER 平日/假日；壽司屋週二/週六）
-- 完整資料未來由 import script 處理
INSERT INTO `opentime` (`restaurant_id`, `day`, `start_time`, `end_time`, `spec_rec`) VALUES
(1, 3, '11:00:00', '14:00:00', '週一、二公休'),
(1, 3, '17:00:00', '21:00:00', '週一、二公休'),
(1, 6, '11:00:00', '14:00:00', '週一、二公休'),
(1, 6, '17:00:00', '21:00:00', '週一、二公休'),
(2, 1, '09:00:00', '20:20:00', '平日'),
(2, 6, '08:00:00', '20:30:00', '假日'),
(3, 2, '13:30:00', '14:00:00', '週一公休、關店前半小時不接客'),
(3, 2, '17:00:00', '21:00:00', '週一公休、關店前半小時不接客');

-- 4.9 餐廳貼標籤
INSERT INTO `restaurant_tags_mapping` (`restaurant_id`, `tag_id`) VALUES
(1, 1), -- 永哥 -> 港式
(2, 2), -- MATTER -> 咖啡
(3, 3), -- 壽司屋 -> 日式
(3, 4), -- 壽司屋 -> 老街美食
(3, 5); -- 壽司屋 -> 無菜單料理

-- 4.10 收藏
INSERT INTO `favorites` (`user_id`, `restaurant_id`, `created_at`) VALUES
(1, 1, '2026-05-05 12:00:00'),
(1, 2, '2026-05-10 18:00:00'),
(2, 1, '2026-05-12 13:15:00');

-- 4.11 評論（trigger 會自動更新 restaurants.rating_avg 與 rating_count）
INSERT INTO `reviews` (`user_id`, `restaurant_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(1, 1, 5, '燒賣跟黑糖麻糬蛋塔絕配，熬夜寫 code 完的大餐首選！', '2026-05-05 12:30:00', '2026-05-05 12:30:00'),
(2, 1, 4, '料多實在，假日去要候位。',                            '2026-05-08 13:00:00', '2026-05-08 13:00:00'),
(2, 2, 4, '舒芙蕾超蓬鬆！環境安靜且不收服務費。',                 '2026-05-13 15:00:00', '2026-05-13 15:00:00'),
(3, 3, 5, '老街隱藏版握壽司，新鮮度破表。',                       '2026-05-19 20:45:00', '2026-05-19 20:45:00');
