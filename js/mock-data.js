/* =====================================================================
   mock-data.js — 模擬「後端資料庫」的 seed 資料
   注意：這是站在 PHP+MariaDB 後端位置的假資料層，不是前端寫死。
   前端頁面一律透過 api() 取得，不直接讀這裡。
   ===================================================================== */
window.SEED = (function () {
  // 依料理分類給「美食」照片（loremflickr 依 lock 穩定回同一張；失敗時前端 Photo 元件有占位 fallback）
  const TAG_KW = {
    1: "taiwanese,streetfood", 2: "hotpot", 3: "sushi,japanese-food", 4: "korean-food",
    5: "brunch", 6: "cafe,dessert", 7: "barbecue,grill", 8: "beef-noodle,ramen",
    9: "vegetarian,salad", 10: "curry,thai-food", 11: "bubble-tea", 12: "noodles",
    13: "seafood", 14: "bento,rice-bowl"
  };
  const photo = (rid, i, kw) => `https://loremflickr.com/800/600/${kw || "food,restaurant"}?lock=${rid * 13 + i * 7 + 100}`;

  const districts = [
    { zipcode: "220", district_name: "板橋區", center_latitude: 25.0095, center_longitude: 121.4626 },
    { zipcode: "234", district_name: "永和區", center_latitude: 25.0078, center_longitude: 121.5132 },
    { zipcode: "235", district_name: "中和區", center_latitude: 24.9993, center_longitude: 121.4986 },
    { zipcode: "236", district_name: "土城區", center_latitude: 24.9722, center_longitude: 121.4439 },
    { zipcode: "241", district_name: "三重區", center_latitude: 25.0617, center_longitude: 121.4945 },
    { zipcode: "242", district_name: "新莊區", center_latitude: 25.0359, center_longitude: 121.4506 },
    { zipcode: "247", district_name: "蘆洲區", center_latitude: 25.0848, center_longitude: 121.4736 },
    { zipcode: "231", district_name: "新店區", center_latitude: 24.9714, center_longitude: 121.5418 }
  ];
  // CHECK (zipcode_a < zipcode_b) — 字串比較，雙向由後端展開
  const adjacency = [
    ["220", "234"], ["220", "235"], ["220", "242"], ["220", "236"], ["220", "241"],
    ["234", "235"], ["235", "236"], ["241", "242"], ["241", "247"], ["234", "231"], ["235", "231"]
  ];

  const tags = [
    "小吃／熱炒", "火鍋", "日式料理", "韓式料理", "早午餐", "咖啡／甜點", "燒烤",
    "牛肉麵", "素食", "異國料理", "手搖飲", "麵食", "海鮮", "便當／自助餐"
  ].map((tag_name, i) => ({ tag_id: i + 1, tag_name }));

  // 一般營業：週一~週日 11:00–21:00（週三公休示意），可帶特殊字串 sentinel(day=0,spec_rec)
  const wk = (closed = []) => {
    const rows = [];
    for (let d = 0; d <= 6; d++) {
      if (closed.includes(d)) continue;
      rows.push({ day: d, start_time: "11:00:00", end_time: "21:00:00", spec_rec: null });
    }
    return rows;
  };
  const wkLate = (closed = []) => {
    const rows = [];
    for (let d = 0; d <= 6; d++) {
      if (closed.includes(d)) continue;
      rows.push({ day: d, start_time: "17:00:00", end_time: "23:30:00", spec_rec: null });
    }
    return rows;
  };
  const allDay = (s, e) => { const r = []; for (let d = 0; d <= 6; d++) r.push({ day: d, start_time: s, end_time: e, spec_rec: null }); return r; };

  // 餐廳種子（座標皆為新北各區實際附近）
  const R = [
    ["蘇記排骨酥麵", "板橋在地30年老店，排骨酥湯頭清甜，麵條Q彈。", "新北市板橋區文化路一段188號", "220", 25.0148, 121.4682, 1, [1, 8, 12], ["02-2256-1788"], 4, wk([3]), 0],
    ["三角窗牛肉麵", "紅燒與清燉雙湯頭，肉大塊燉到入口即化。", "新北市板橋區中山路一段50號", "220", 25.0121, 121.4609, 2, [8, 12], ["02-2960-3322"], 5, wk(), 0],
    ["江子翠豆漿大王", "24小時現磨豆漿、現桿燒餅油條，早餐宵夜都行。", "新北市板橋區文化路二段30號", "220", 25.0286, 121.4701, 1, [5, 1], ["02-2253-0099"], 3, allDay("00:00:00", "23:59:00"), 0, "24h"],
    ["永和韓鄉石鍋", "道地韓式石鍋拌飯與部隊鍋，小菜免費續。", "新北市永和區永和路二段58號", "234", 25.0091, 121.5147, 2, [4, 2], ["02-2922-7654"], 6, wkLate([1]), 0],
    ["樂華夜市紅豆餅", "排隊名物，奶油與紅豆現烤酥脆。", "新北市永和區永平路1號", "234", 25.0009, 121.5141, 1, [6, 11], ["02-2231-0001"], 2, wkLate(), 0],
    ["中和南勢角米線", "雲南過橋米線，湯濃料多，微辣夠味。", "新北市中和區興南路一段100號", "235", 24.9925, 121.5043, 2, [12, 10], ["02-2945-8800"], 4, wk([2]), 0],
    ["錵鑶日式燒肉", "和牛炭火直燒，職人代烤，肉質油花漂亮。", "新北市中和區中山路二段200號", "235", 25.0001, 121.4979, 4, [7, 3], ["02-2223-6789"], 6, wkLate(), 0],
    ["土城手工水餃館", "高麗菜豬肉現包水餃，皮薄餡多，酸辣湯一絕。", "新北市土城區金城路二段155號", "236", 24.9738, 121.4451, 1, [12, 1], ["02-2262-3344"], 3, wk([1]), 0],
    ["三重蚵仔煎大王", "現煎蚵仔煎、蝦仁煎，醬料甜鹹平衡。", "新北市三重區重新路三段88號", "241", 25.0609, 121.4938, 1, [1, 13], ["02-2971-5566"], 4, wkLate(), 0],
    ["新莊老順香咖哩飯", "南洋風咖哩，醬汁濃郁，附冬瓜茶。", "新北市新莊區中正路72號", "242", 25.0361, 121.4519, 2, [10, 14], ["02-2992-1234"], 3, wk([3]), 0],
    ["蘆洲切仔麵", "湯頭用大骨熬煮，黑白切新鮮，平價份量足。", "新北市蘆洲區中正路120號", "247", 25.0851, 121.4742, 1, [1, 12], ["02-2281-9090"], 3, wk([4]), 0],
    ["新店碧潭咖啡", "面湖落地窗，手沖單品與肉桂捲，假日人潮多。", "新北市新店區新店路65號", "231", 24.9558, 121.5378, 3, [6, 5], ["02-2911-7788"], 6, wk([1]), 0],
    ["板橋肥前屋鰻魚飯", "備長炭烤鰻，醬汁三代配方，米飯粒粒分明。", "新北市板橋區館前西路18號", "220", 25.0136, 121.4628, 3, [3, 13], ["02-2968-4567"], 5, wk([2]), 0],
    ["永和世界豆漿", "鹹豆漿與蛋餅是招牌，老饕都加辣油。", "新北市永和區中正路189號", "234", 25.0123, 121.5119, 1, [5], ["02-2921-3030"], 3, allDay("04:00:00", "12:00:00"), 0],
    ["中和泰式小館", "打拋豬、月亮蝦餅、綠咖哩，份量大適合聚餐。", "新北市中和區景平路200號", "235", 24.9989, 121.5008, 2, [10, 13], ["02-2243-6611"], 5, wk(), 0],
    ["新莊牛排館", "夜市平價牛排，鐵板麵吃到飽，玉米濃湯免費。", "新北市新莊區幸福路300號", "242", 25.0402, 121.4458, 2, [10, 14], ["02-2906-2200"], 4, wkLate([2]), 0]
  ];

  let restaurants = R.map((r, i) => {
    const rid = i + 1;
    const [name, desc, address, zip, lat, lng, price, tagIds, phones, nPhotos, opentime, _z, special] = r;
    const photos = [];
    const kw = TAG_KW[tagIds[0]] || "food,restaurant";
    for (let p = 0; p < nPhotos; p++) photos.push({ photo_id: rid * 10 + p, restaurant_id: rid, url: photo(rid, p, kw), is_main: p === 0 ? 1 : 0, sort_order: p });
    const ot = opentime.map((o, k) => ({ opentime_id: rid * 100 + k, restaurant_id: rid, ...o }));
    if (special === "24h") { /* already a single all-day row */ }
    // 特殊營業字串（day=0 sentinel）
    const specials = [];
    if (i % 4 === 0) specials.push({ opentime_id: rid * 100 + 90, restaurant_id: rid, day: 0, start_time: "00:00:00", end_time: "00:00:00", spec_rec: "農曆春節休五天" });
    if (i % 5 === 0) specials.push({ opentime_id: rid * 100 + 91, restaurant_id: rid, day: 0, start_time: "00:00:00", end_time: "00:00:00", spec_rec: "颱風天視情況公休" });
    return {
      restaurant_id: rid, restaurant_name: name, description: desc, address, zipcode: zip,
      latitude: lat, longitude: lng, price_level: price, google_place_id: "ChIJ_seed_" + rid,
      tag_ids: tagIds, phones, photos, opentime: ot.concat(specials)
    };
  });

  // 使用者：user_id=1 為 super admin（不可改/刪）
  const users = [
    { user_id: 1, username: "admin", password: "admin1234", is_admin: 1, created_at: "2024-01-01 09:00:00" },
    { user_id: 2, username: "alice", password: "alice1234", is_admin: 0, created_at: "2024-03-12 10:00:00" },
    { user_id: 3, username: "bob", password: "bob12345", is_admin: 1, created_at: "2024-05-20 14:00:00" },
    { user_id: 4, username: "carol", password: "carol1234", is_admin: 0, created_at: "2024-08-01 19:00:00" }
  ];

  // 評論種子（PK: user_id+restaurant_id）
  const reviews = [
    [2, 1, 5, "排骨酥麵真的好吃，湯頭不油不膩，會再來！", "2025-11-02 12:30:00"],
    [3, 1, 4, "份量很足，價格實在。", "2025-10-18 13:10:00"],
    [4, 1, 4, "麵條偏軟一點點，但整體很棒。", "2025-12-01 18:45:00"],
    [2, 2, 5, "清燉牛肉麵肉超嫩，湯喝得到誠意。", "2025-11-20 19:00:00"],
    [4, 2, 4, "紅燒也不錯，辣度可以調。", "2025-11-25 12:00:00"],
    [3, 7, 5, "燒肉品質很好，代烤服務很貼心。", "2025-12-10 20:30:00"],
    [2, 7, 5, "和牛入口即化，約會首選。", "2025-12-15 19:20:00"],
    [4, 4, 4, "石鍋拌飯鍋巴香，小菜會續。", "2025-09-30 18:00:00"],
    [3, 13, 5, "鰻魚飯炭香十足，值得專程來。", "2025-12-20 12:40:00"],
    [2, 11, 3, "切仔麵普通，但黑白切新鮮。", "2025-08-14 11:30:00"],
    [4, 15, 4, "泰式份量大，適合多人。", "2025-10-05 19:30:00"],
    [2, 6, 4, "米線湯濃，微辣剛好。", "2025-11-11 13:00:00"]
  ].map(([uid, rid, rating, comment, created_at]) => ({ user_id: uid, restaurant_id: rid, rating, comment, created_at, updated_at: created_at }));

  // 收藏（user 2 / 4）
  const favorites = [
    { user_id: 2, restaurant_id: 1 }, { user_id: 2, restaurant_id: 7 }, { user_id: 2, restaurant_id: 13 },
    { user_id: 4, restaurant_id: 2 }, { user_id: 4, restaurant_id: 15 }
  ];

  return { districts, adjacency, tags, restaurants, users, reviews, favorites };
})();
