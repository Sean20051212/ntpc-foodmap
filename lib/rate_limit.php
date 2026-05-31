<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function rateLimitCheck(string $bucket): void
{
    ensureSession();

    $key = $_SESSION['user_id'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'anon');
    $dir = sys_get_temp_dir() . '/ntpc_foodmap_rl';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $now = time();
    $windows = [
        ['min', 60, RATE_LIMIT_PER_MINUTE],
        ['day', 86400, RATE_LIMIT_PER_DAY],
    ];

    foreach ($windows as [$tag, $seconds, $limit]) {
        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $bucket) . '_' . $tag . '_' . md5((string) $key);
        $hits = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $hits = is_array($hits) ? $hits : [];
        $hits = array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $seconds));

        if (count($hits) >= $limit) {
            jsonErr('rate_limited', '請稍後再試', 429);
        }

        $hits[] = $now;
        file_put_contents($file, json_encode($hits), LOCK_EX);
    }
}

