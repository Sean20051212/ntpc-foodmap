<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$userId = requireInt($input, 'user_id', 1);

if (isSuperAdmin($userId)) {
    jsonErr('forbidden', '不可刪除 super admin', 403);
}

adminEnsureUserExists($userId);

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('DELETE FROM reviews WHERE user_id = ?')->execute([$userId]);
    $delete = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
    $delete->execute([$userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

jsonOk(['deleted' => true]);
