<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$photoId = requireInt($input, 'photo_id', 1);

$stmt = db()->prepare('DELETE FROM restaurant_photos WHERE photo_id = ?');
$stmt->execute([$photoId]);

if ($stmt->rowCount() === 0) {
    jsonErr('not_found', '找不到照片', 404);
}

jsonOk(['deleted' => true]);
