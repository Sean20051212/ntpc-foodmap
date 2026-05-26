/* restaurants.js — Shared restaurant dataset (Real data from 新北市政府觀光旅遊局).
   Categories assigned based on description / name. */
window.RESTAURANTS = [
  { id: 1, name: "鼎泰豐 (板橋大遠百店)", category: "中式料理", catColor: "orange",
    address: "新北市板橋區新站路28號 B1", hours: "11:00 – 21:30 (週末至 22:00)", dist: "0.8 km" },
  { id: 2, name: "大紅袍麻辣鴛鴦鍋", category: "鍋物", catColor: "orange",
    address: "新北市新店區中興路三段1-8號 (新店家樂福1F)", hours: "10:00 – 22:00 (除夕至 18:00)", dist: "2.4 km" },
  { id: 3, name: "壽司屋", category: "日式料理", catColor: "orange",
    address: "新北市淡水區公明街87號", hours: "13:30 – 14:00、17:00 – 21:00 (週一公休)", dist: "5.1 km" },
  { id: 4, name: "Gino Pizza 義式窯烤", category: "西式料理", catColor: "orange",
    address: "新北市蘆洲區長安街108巷27號", hours: "週一至五 11:30 – 22:00、週末 11:30 – 22:00", dist: "3.2 km" },
  { id: 5, name: "大胖子豆腐冰淇淋", category: "甜點", catColor: "orange",
    address: "新北市深坑區深坑街62號", hours: "09:00 – 19:00", dist: "6.8 km" },
  { id: 6, name: "萬香烤鴨", category: "中式料理", catColor: "orange",
    address: "新北市板橋區忠孝路116號", hours: "10:00 – 20:00", dist: "1.1 km" },
  { id: 7, name: "阿婆魚羹", category: "台式小吃", catColor: "green",
    address: "新北市瑞芳區基山街9號 (九份老街)", hours: "08:30 – 17:00 (售完為止)", dist: "12.3 km" },
  { id: 8, name: "三隻小豬早餐", category: "早午餐", catColor: "green",
    address: "新北市板橋區後埔街54號", hours: "07:00 – 15:00 (週一公休)", dist: "1.6 km" },
  { id: 9, name: "清真雲南小吃", category: "異國料理", catColor: "orange",
    address: "新北市中和區華新街62號", hours: "06:00 – 16:00 (週五公休)", dist: "2.9 km" },
  { id: 10, name: "老爹滷味 (永和店)", category: "台式小吃", catColor: "green",
    address: "新北市永和區福和路326號", hours: "週一至四 10:00 – 21:00、週五六至 22:30 (週日休)", dist: "1.9 km" },
  { id: 11, name: "玉麵堂 江浙菜", category: "中式料理", catColor: "orange",
    address: "新北市新店區中華路113號1樓", hours: "11:30 – 14:00、17:30 – 21:00 (週一公休)", dist: "2.7 km" },
  { id: 12, name: "翡翠谷飲食店", category: "山產料理", catColor: "green",
    address: "新北市烏來區烏來街26-28號", hours: "09:00 – 19:00", dist: "8.4 km" }
];

/* For wheel page — broader pool with district + category.
   lng/lat 為各餐廳座標，距離由 wheel.php 用 Haversine 即時計算（讀 sessionStorage 的 userLocation）。*/
window.WHEEL_POOL = [
  { name: "鼎泰豐", cat: "中式料理", dist: "板橋區", lng: 121.4678, lat: 25.0220, rating: 4.3, addr: "新站路28號B1" },
  { name: "大紅袍麻辣鍋", cat: "鍋物", dist: "新店區", lng: 121.5421, lat: 24.9826, rating: 4.5, addr: "中興路三段1-8號" },
  { name: "壽司屋", cat: "日式料理", dist: "淡水區", lng: 121.4441, lat: 25.1684, rating: 3.5, addr: "公明街87號" },
  { name: "Gino Pizza", cat: "西式料理", dist: "蘆洲區", lng: 121.4737, lat: 25.0858, rating: 3.4, addr: "長安街108巷27號" },
  { name: "大胖子豆腐冰淇淋", cat: "甜點", dist: "深坑區", lng: 121.6155, lat: 24.9970, rating: 4.6, addr: "深坑街62號" },
  { name: "萬香烤鴨", cat: "中式料理", dist: "板橋區", lng: 121.4690, lat: 25.0210, rating: 4.4, addr: "忠孝路116號" },
  { name: "阿婆魚羹", cat: "台式小吃", dist: "瑞芳區", lng: 121.8101, lat: 25.1086, rating: 4.7, addr: "基山街9號" },
  { name: "三隻小豬早餐", cat: "早午餐", dist: "板橋區", lng: 121.4665, lat: 25.0235, rating: 4.9, addr: "後埔街54號" },
  { name: "清真雲南小吃", cat: "異國料理", dist: "中和區", lng: 121.4995, lat: 25.0066, rating: 3.4, addr: "華新街62號" },
  { name: "老爹滷味", cat: "台式小吃", dist: "永和區", lng: 121.5147, lat: 25.0070, rating: 3.5, addr: "福和路326號" },
  { name: "玉麵堂", cat: "中式料理", dist: "新店區", lng: 121.5430, lat: 24.9810, rating: 3.7, addr: "中華路113號" },
  { name: "翡翠谷飲食店", cat: "山產料理", dist: "烏來區", lng: 121.5511, lat: 24.8651, rating: 4.1, addr: "烏來街26-28號" },
  { name: "金山長益餅店", cat: "甜點", dist: "金山區", lng: 121.6362, lat: 25.2196, rating: 3.6, addr: "金包里街57號" },
  { name: "蘇義興餐廳", cat: "台菜", dist: "雙溪區", lng: 121.8651, lat: 25.0376, rating: 4.4, addr: "中華路77號" },
  { name: "大團圓餐廳", cat: "台菜", dist: "深坑區", lng: 121.6170, lat: 24.9985, rating: 4.0, addr: "阿柔里25-1號" },
  { name: "登峰魚丸博物館", cat: "台式小吃", dist: "淡水區", lng: 121.4452, lat: 25.1675, rating: 4.5, addr: "中正路117號" },
  { name: "宜安路麵線", cat: "台式小吃", dist: "中和區", lng: 121.5012, lat: 25.0050, rating: 3.4, addr: "宜安路117號" },
  { name: "老楊煎包", cat: "早午餐", dist: "新店區", lng: 121.5450, lat: 24.9800, rating: 3.7, addr: "中興路一段112號" },
  { name: "廣泰香西點麵包", cat: "甜點", dist: "淡水區", lng: 121.4435, lat: 25.1695, rating: 4.0, addr: "淡金路三段347號" },
  { name: "美養莊園餐飲", cat: "西式料理", dist: "新店區", lng: 121.5400, lat: 24.9840, rating: 4.5, addr: "二十張路11巷1-1號" }
];

window.DISTRICTS = [
  "板橋區","三重區","中和區","永和區","新莊區","新店區","土城區","蘆洲區","樹林區",
  "汐止區","鶯歌區","三峽區","淡水區","瑞芳區","五股區","泰山區","林口區","深坑區",
  "石碇區","坪林區","三芝區","石門區","八里區","平溪區","雙溪區","貢寮區","金山區",
  "萬里區","烏來區"
];

window.CATEGORIES = ["中式料理","日式料理","西式料理","鍋物","甜點","早午餐","台式小吃","異國料理","台菜","山產料理"];
