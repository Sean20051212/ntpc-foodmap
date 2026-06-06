-- DB-6: 照片支援本機儲存
-- url 仍為必填的外部來源；local_path 由 sync 腳本下載後填入。
-- 前端 render 優先用 local_path，缺則 fallback 到 url。

ALTER TABLE `restaurant_photos`
  ADD COLUMN `local_path` VARCHAR(500) NULL DEFAULT NULL AFTER `url`;
