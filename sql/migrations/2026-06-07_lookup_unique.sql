-- DB-8: 補強 lookup 表的 name 欄位 UNIQUE，徹底達 BCNF
-- 註：tags.uk_tags_name 早於 DB-8 已存在於 schema，本檔不重複加。

ALTER TABLE `districts` ADD UNIQUE KEY `uk_districts_name` (`district_name`);
ALTER TABLE `days_of_week`
  ADD UNIQUE KEY `uk_days_name_zh` (`day_name_zh`),
  ADD UNIQUE KEY `uk_days_name_en` (`day_name_en`);
