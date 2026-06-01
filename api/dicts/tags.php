<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

requireMethod('GET');

$stmt = db()->query(
    'SELECT tag_id, tag_name
     FROM tags
     ORDER BY tag_id ASC'
);

$tags = array_map(static function (array $row): array {
    return [
        'tag_id' => (int) $row['tag_id'],
        'tag_name' => $row['tag_name'],
    ];
}, $stmt->fetchAll());

jsonOk(['tags' => $tags]);
