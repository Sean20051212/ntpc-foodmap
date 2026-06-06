-- DB-10: price_level 抽出獨立查找表 price_levels
-- 對應 DB-11 (days_of_week) 同樣模式：消除 CHECK 寫死、支援 i18n 與未來 metadata 擴充。

CREATE TABLE IF NOT EXISTS `price_levels` (
  `price_level_id` TINYINT NOT NULL,
  `symbol` VARCHAR(8) NOT NULL,
  `label_zh` VARCHAR(20) NOT NULL,
  `label_en` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`price_level_id`),
  UNIQUE KEY `uk_price_levels_symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `price_levels` (`price_level_id`, `symbol`, `label_zh`, `label_en`) VALUES
  (1, '$',    '便宜',  'Cheap'),
  (2, '$$',   '平價',  'Affordable'),
  (3, '$$$',  '中價',  'Mid-range'),
  (4, '$$$$', '高價',  'Expensive');

ALTER TABLE `restaurants` DROP CONSTRAINT `chk_restaurants_price_level`;
ALTER TABLE `restaurants`
  ADD CONSTRAINT `fk_restaurants_price_level`
  FOREIGN KEY (`price_level`) REFERENCES `price_levels` (`price_level_id`)
  ON UPDATE CASCADE;
