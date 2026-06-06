<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$stmt = db()->query(
    'SELECT price_level_id, symbol, label_zh, label_en
     FROM price_levels
     ORDER BY price_level_id ASC'
);

$levels = array_map(static function (array $row): array {
    return [
        'price_level_id' => (int) $row['price_level_id'],
        'symbol' => $row['symbol'],
        'label_zh' => $row['label_zh'],
        'label_en' => $row['label_en'],
    ];
}, $stmt->fetchAll());

jsonOk(['price_levels' => $levels]);
