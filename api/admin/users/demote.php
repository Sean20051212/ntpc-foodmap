<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$userId = requireInt($input, 'user_id', 1);

if (isSuperAdmin($userId)) {
    jsonErr('forbidden', '不可降權 super admin', 403);
}

adminEnsureUserExists($userId);
$stmt = db()->prepare('UPDATE users SET is_admin = 0 WHERE user_id = ?');
$stmt->execute([$userId]);

jsonOk(['user' => adminEnsureUserExists($userId)]);
