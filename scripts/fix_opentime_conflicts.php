<?php
declare(strict_types=1);

// 修復 opentime 衝突（含 dry-run）
// 用法：
//   php scripts/fix_opentime_conflicts.php            # dry-run，不改 DB，印計畫
//   php scripts/fix_opentime_conflicts.php --apply    # 真的執行
//
// 規則（只處理 spec_rec IS NULL 的列；spec_rec 有值的一律保留）：
//   Step A：偵測 catchall 合併段 — 若 S 包含 ≥2 個彼此不重疊的其他段，
//           且這些被包含段的最小 start == S.start、最大 end == S.end → 刪 S
//   Step B：保留外層 — 若 A 完全包含 B（且 A 未被 Step A 刪）→ 刪 B
//   Step C：真重疊無包含 — sweep 合併成 union
// 跨日段：start>end 視為延伸到隔天，攤平到 [day*1440+start, day*1440+1440+end] 區間
// 重組：merged 區間若跨 day boundary → 一列保留 cross-midnight 形式

require_once __DIR__ . '/../lib/db.php';

$apply = in_array('--apply', $argv, true);

// 把 echo 全部攔截寫到 UTF-8 檔案，避開 shell stdout 編碼問題
$outPath = __DIR__ . '/../sql/snapshots/opentime_fix_plan.txt';
ob_start(function ($buf) use ($outPath) {
    file_put_contents($outPath, $buf, FILE_APPEND);
    return '';
});
file_put_contents($outPath, ''); // 清空

function timeToMin(string $t): int
{
    [$h, $m] = explode(':', $t);
    return (int) $h * 60 + (int) $m;
}

function minToTime(int $min): string
{
    $min = $min % 1440;
    if ($min < 0) $min += 1440;
    return sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);
}

function flatten(array $row): array
{
    $startMin = $row['day'] * 1440 + timeToMin($row['start_time']);
    $endRaw = timeToMin($row['end_time']);
    $endMin = $row['day'] * 1440 + $endRaw;
    if ($endMin <= $startMin) {
        $endMin += 1440;
    }
    return ['s' => $startMin, 'e' => $endMin, 'origs' => [$row]];
}

// 把 [s, e] 切回 opentime 列。若這段對應到單一原始列（origs 只有 1 筆且 s/e 一致），
// 用原始字串保留 24:00:00 / 24:30:00 等寫法；否則依分鐘重算。
function decompose(int $s, int $e, array $origs = []): array
{
    if (count($origs) === 1) {
        $orig = $origs[0];
        $origStart = $orig['day'] * 1440 + timeToMin($orig['start_time']);
        $endRaw = timeToMin($orig['end_time']);
        $origEnd = $orig['day'] * 1440 + $endRaw;
        if ($origEnd <= $origStart) $origEnd += 1440;
        if ($origStart === $s && $origEnd === $e) {
            return [
                'day' => (int) $orig['day'],
                'start_time' => $orig['start_time'],
                'end_time' => $orig['end_time'],
            ];
        }
    }
    $day = intdiv($s, 1440);
    $startTime = minToTime($s - $day * 1440);
    $endRel = $e - $day * 1440;
    if ($endRel <= 1440) {
        $endTime = $endRel === 1440 ? '24:00:00' : minToTime($endRel);
    } else {
        $endTime = minToTime($endRel - 1440);
    }
    return ['day' => $day, 'start_time' => $startTime, 'end_time' => $endTime];
}

function processRestaurant(array $rows): array
{
    $specials = [];
    $segs = [];
    foreach ($rows as $row) {
        if ($row['spec_rec'] !== null) {
            $specials[] = $row;
            continue;
        }
        $segs[] = flatten($row);
    }

    // Step A: catchall detection
    $deleted = array_fill(0, count($segs), false);
    foreach ($segs as $i => $S) {
        $contained = [];
        foreach ($segs as $j => $T) {
            if ($i === $j) continue;
            if ($T['s'] >= $S['s'] && $T['e'] <= $S['e'] && ($T['s'] > $S['s'] || $T['e'] < $S['e'])) {
                $contained[] = $T;
            }
        }
        if (count($contained) < 2) continue;
        // 找一對不重疊、端點對齊 S 的被包含段
        $catchall = false;
        foreach ($contained as $a) {
            if ($a['s'] !== $S['s']) continue;
            foreach ($contained as $b) {
                if ($b['e'] !== $S['e']) continue;
                if ($a['e'] <= $b['s']) {
                    $catchall = true; break 2;
                }
            }
        }
        if ($catchall) {
            $deleted[$i] = true;
        }
    }

    $remaining = [];
    foreach ($segs as $i => $seg) {
        if (!$deleted[$i]) $remaining[] = $seg;
    }

    // Step B: containment reduction
    $keep = array_fill(0, count($remaining), true);
    foreach ($remaining as $i => $A) {
        if (!$keep[$i]) continue;
        foreach ($remaining as $j => $B) {
            if ($i === $j || !$keep[$j]) continue;
            if ($A['s'] <= $B['s'] && $A['e'] >= $B['e'] && ($A['s'] < $B['s'] || $A['e'] > $B['e'])) {
                $keep[$j] = false;
            }
        }
    }
    $remaining = array_values(array_filter($remaining, fn($_, $i) => $keep[$i], ARRAY_FILTER_USE_BOTH));

    // Step C: sweep merge partial overlaps
    usort($remaining, fn($a, $b) => $a['s'] <=> $b['s']);
    $merged = [];
    foreach ($remaining as $seg) {
        if ($merged && $seg['s'] < $merged[count($merged) - 1]['e']) {
            $last = &$merged[count($merged) - 1];
            $last['e'] = max($last['e'], $seg['e']);
            $last['origs'] = array_merge($last['origs'], $seg['origs']);
            unset($last);
        } else {
            $merged[] = $seg;
        }
    }

    // 重組成 opentime 列
    $finalRows = [];
    foreach ($merged as $m) {
        $finalRows[] = decompose($m['s'], $m['e'], $m['origs']);
    }

    return ['specials' => $specials, 'final' => $finalRows, 'originalNonSpecial' => $segs];
}

function rowsEqual(array $original, array $finalRows): bool
{
    if (count($original) !== count($finalRows)) return false;
    $orig = array_map(fn($r) => $r['day'] . '|' . substr($r['start_time'], 0, 5) . '|' . substr($r['end_time'], 0, 5), $original);
    $fin = array_map(fn($r) => $r['day'] . '|' . substr($r['start_time'], 0, 5) . '|' . substr($r['end_time'], 0, 5), $finalRows);
    sort($orig); sort($fin);
    return $orig === $fin;
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
    $grouped[$row['restaurant_id']]['rows'][] = $row;
}

$changed = 0;
$totalDeleted = 0;
$totalInserted = 0;

foreach ($grouped as $rid => $info) {
    $result = processRestaurant($info['rows']);
    $originalNonSpecial = array_values(array_filter($info['rows'], fn($r) => $r['spec_rec'] === null));
    if (rowsEqual($originalNonSpecial, $result['final'])) {
        continue;
    }
    $changed++;
    $deleteIds = array_column($originalNonSpecial, 'opentime_id');
    $totalDeleted += count($deleteIds);
    $totalInserted += count($result['final']);

    echo "==== #{$rid}  {$info['name']} ====\n";
    echo "  BEFORE (" . count($originalNonSpecial) . " rows, spec_rec=NULL):\n";
    foreach ($originalNonSpecial as $r) {
        echo "    id={$r['opentime_id']}  day={$r['day']}  {$r['start_time']}-{$r['end_time']}\n";
    }
    echo "  AFTER  (" . count($result['final']) . " rows):\n";
    foreach ($result['final'] as $r) {
        echo "    day={$r['day']}  {$r['start_time']}-{$r['end_time']}\n";
    }
    echo "\n";

    if ($apply) {
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM opentime WHERE restaurant_id = ? AND spec_rec IS NULL');
            $del->execute([$rid]);
            $ins = $pdo->prepare('INSERT INTO opentime (restaurant_id, day, start_time, end_time, spec_rec) VALUES (?, ?, ?, ?, NULL)');
            foreach ($result['final'] as $r) {
                $ins->execute([$rid, $r['day'], $r['start_time'], $r['end_time']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo "  !! ERROR: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}

echo "---- 統計 ----\n";
echo "會修改的餐廳數：{$changed}\n";
echo "預計刪除：{$totalDeleted} 列\n";
echo "預計新增：{$totalInserted} 列\n";
echo $apply ? "*** 已套用到 DB ***\n" : "*** dry-run（未改 DB）；加 --apply 真正執行 ***\n";
