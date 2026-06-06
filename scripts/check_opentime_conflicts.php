<?php
declare(strict_types=1);

// 一次性離線工具：掃出 opentime 表中「時段重疊」的餐廳
// 用法（CMD 或 PowerShell 均可）：
//   php scripts/check_opentime_conflicts.php
// 只讀取，不修改資料。

require_once __DIR__ . '/../lib/db.php';

function flattenSegments(array $hours): array
{
    $segments = [];
    foreach ($hours as $h) {
        if ($h['spec_rec'] !== null) {
            continue;
        }
        [$sh, $sm] = explode(':', $h['start_time']);
        [$eh, $em] = explode(':', $h['end_time']);
        $startMin = (int) $h['day'] * 1440 + (int) $sh * 60 + (int) $sm;
        $endMin   = (int) $h['day'] * 1440 + (int) $eh * 60 + (int) $em;

        if ($startMin === $endMin) {
            $segments[] = ['id' => $h['opentime_id'], 's' => $startMin, 'e' => $endMin, 'raw' => $h];
            continue;
        }

        if ($endMin > $startMin) {
            $segments[] = ['id' => $h['opentime_id'], 's' => $startMin, 'e' => $endMin, 'raw' => $h];
        } else {
            $segments[] = ['id' => $h['opentime_id'], 's' => $startMin, 'e' => ((int) $h['day'] + 1) * 1440, 'raw' => $h];
            $tailStart = ((((int) $h['day']) + 1) * 1440) % 10080;
            $tailEnd   = $tailStart + (int) $eh * 60 + (int) $em;
            $segments[] = ['id' => $h['opentime_id'], 's' => $tailStart, 'e' => $tailEnd, 'raw' => $h];
        }
    }
    return $segments;
}

function findConflicts(array $segments): array
{
    $conflicts = [];
    $n = count($segments);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $a = $segments[$i];
            $b = $segments[$j];
            if ($a['id'] === $b['id']) {
                continue;
            }
            if (max($a['s'], $b['s']) < min($a['e'], $b['e'])) {
                $key = min($a['id'], $b['id']) . '-' . max($a['id'], $b['id']);
                $conflicts[$key] = [$a['raw'], $b['raw']];
            }
        }
    }
    return array_values($conflicts);
}

$pdo = db();

$rows = $pdo->query(
    'SELECT ot.opentime_id, ot.restaurant_id, r.restaurant_name,
            ot.day, ot.start_time, ot.end_time, ot.spec_rec
     FROM opentime ot
     JOIN restaurants r ON r.restaurant_id = ot.restaurant_id
     ORDER BY ot.restaurant_id, ot.day, ot.start_time'
)->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($rows as $row) {
    $grouped[$row['restaurant_id']]['name'] = $row['restaurant_name'];
    $grouped[$row['restaurant_id']]['hours'][] = $row;
}

$totalRestaurants = count($grouped);
$dirtyCount = 0;
$dayName = ['日', '一', '二', '三', '四', '五', '六'];

foreach ($grouped as $rid => $info) {
    $segments = flattenSegments($info['hours']);
    $conflicts = findConflicts($segments);
    if (!$conflicts) {
        continue;
    }
    $dirtyCount++;
    echo "==== 餐廳 #{$rid}  {$info['name']} ====\n";
    foreach ($conflicts as $pair) {
        [$a, $b] = $pair;
        printf(
            "  衝突：週%s %s-%s (id=%d)  vs  週%s %s-%s (id=%d)\n",
            $dayName[(int) $a['day']],
            substr($a['start_time'], 0, 5),
            substr($a['end_time'], 0, 5),
            $a['opentime_id'],
            $dayName[(int) $b['day']],
            substr($b['start_time'], 0, 5),
            substr($b['end_time'], 0, 5),
            $b['opentime_id']
        );
    }
    echo "\n";
}

echo "---- 統計 ----\n";
echo "掃描餐廳數：{$totalRestaurants}\n";
echo "有衝突的餐廳：{$dirtyCount}\n";
