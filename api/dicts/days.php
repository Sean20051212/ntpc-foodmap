<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$stmt = db()->query(
    'SELECT day_id, day_name_zh, day_name_en
     FROM days_of_week
     ORDER BY day_id ASC'
);

$days = array_map(static function (array $row): array {
    return [
        'day_id' => (int) $row['day_id'],
        'day_name_zh' => $row['day_name_zh'],
        'day_name_en' => $row['day_name_en'],
    ];
}, $stmt->fetchAll());

jsonOk(['days' => $days]);
