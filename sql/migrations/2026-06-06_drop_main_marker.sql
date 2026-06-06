-- DB-5: 移除 main_marker generated column，改用 trigger 強制「每店至多一張主圖」
-- 套用前請先快照。一次性 migration，套用後將 schema 同步寫回 sql/database.sql。

ALTER TABLE `restaurant_photos` DROP KEY `uk_one_main_per_restaurant`;
ALTER TABLE `restaurant_photos` DROP COLUMN `main_marker`;
ALTER TABLE `restaurant_photos` MODIFY `is_main` BOOLEAN NOT NULL DEFAULT 0;
ALTER TABLE `restaurant_photos` ADD CONSTRAINT `chk_is_main` CHECK (`is_main` IN (0, 1));

DROP TRIGGER IF EXISTS `trg_photos_one_main_ins`;
DROP TRIGGER IF EXISTS `trg_photos_one_main_upd`;

DELIMITER $$

CREATE TRIGGER `trg_photos_one_main_ins`
BEFORE INSERT ON `restaurant_photos`
FOR EACH ROW
BEGIN
  IF NEW.is_main = 1 AND EXISTS (
    SELECT 1 FROM `restaurant_photos`
    WHERE `restaurant_id` = NEW.restaurant_id AND `is_main` = 1
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'restaurant already has a main photo';
  END IF;
END$$

CREATE TRIGGER `trg_photos_one_main_upd`
BEFORE UPDATE ON `restaurant_photos`
FOR EACH ROW
BEGIN
  IF NEW.is_main = 1 AND EXISTS (
    SELECT 1 FROM `restaurant_photos`
    WHERE `restaurant_id` = NEW.restaurant_id
      AND `is_main` = 1
      AND `photo_id` <> NEW.photo_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'restaurant already has a main photo';
  END IF;
END$$

DELIMITER ;
