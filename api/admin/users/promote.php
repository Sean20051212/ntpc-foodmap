<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../lib/admin.php';

requireMethod('POST');
requireAdmin();

$input = getInput();
$userId = requireInt($input, 'user_id', 1);
adminEnsureUserExists($userId);

$stmt = db()->prepare('UPDATE users SET is_admin = 1 WHERE user_id = ?');
$stmt->execute([$userId]);

jsonOk(['user' => adminEnsureUserExists($userId)]);
