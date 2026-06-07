-- DB-3: 移除 districts.center_latitude / center_longitude
-- 新北判斷改用「地址比對 district_name」（BE-2，見 lib/geo.php）。
-- 前端 focusDistrict 用的中心座標改由 /api/dicts/districts 動態 AVG 算出（見 api/dicts/districts.php）。

ALTER TABLE `districts`
  DROP COLUMN `center_latitude`,
  DROP COLUMN `center_longitude`;
