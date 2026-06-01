<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

function ensureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function publicUser(array $row): array
{
    return [
        'user_id' => (int) $row['user_id'],
        'username' => $row['username'],
        'is_admin' => (int) $row['is_admin'],
        'created_at' => $row['created_at'] ?? null,
    ];
}

function currentUser(): ?array
{
    ensureSession();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT user_id, username, is_admin, created_at
         FROM users
         WHERE user_id = ?'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return publicUser($user);
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        jsonErr('unauthenticated', '請先登入', 401);
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();
    if ((int) $user['is_admin'] !== 1) {
        jsonErr('forbidden', '沒有管理員權限', 403);
    }

    return $user;
}

function isSuperAdmin(int $userId): bool
{
    return $userId === 1;
}

