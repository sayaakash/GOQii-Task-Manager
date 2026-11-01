<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): array
    {
        if (!isset($_SESSION['user_id'], $_SESSION['role'])) Api::json(['error' => 'Authentication required.'], 401);
        if (time() - (int)($_SESSION['last_activity'] ?? 0) > 1800) {
            $_SESSION = [];
            session_destroy();
            Api::json(['error' => 'Session expired. Please sign in again.'], 401);
        }
        $_SESSION['last_activity'] = time();
        return ['id' => (int)$_SESSION['user_id'], 'name' => $_SESSION['name'], 'email' => $_SESSION['email'], 'role' => $_SESSION['role']];
    }

    public static function admin(array $user): void
    {
        if ($user['role'] !== 'admin') Api::json(['error' => 'Administrator access required.'], 403);
    }
}
