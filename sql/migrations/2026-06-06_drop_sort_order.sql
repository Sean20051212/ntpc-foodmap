-- DB-4: 移除 restaurant_photos.sort_order
-- 排序改由 is_main DESC + photo_id ASC（自然新增順序）取代，避免新增/刪除時要維護 sort_order 序列。

ALTER TABLE `restaurant_photos` DROP COLUMN `sort_order`;
