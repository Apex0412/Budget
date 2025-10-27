<?php

namespace Finanses\Middleware;

use Finanses\Helpers;

class AuthMiddleware
{
    public static function requireAuth(): void
    {
        Helpers::startSession();
        if (empty($_SESSION['user'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireAuth();
        $role = $_SESSION['user']['role'] ?? 'user';
        if (!in_array($role, $roles, true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
        }
    }
}
