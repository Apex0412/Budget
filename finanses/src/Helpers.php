<?php

namespace Finanses;

class Helpers
{
    private static ?string $basePath = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $name = Config::get('session_name', 'finanses_session');
            ini_set('session.gc_maxlifetime', (string) Config::get('session_lifetime', 1800));
            session_name($name);
            session_set_cookie_params([
                'lifetime' => Config::get('session_lifetime', 1800),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $path = Config::getNested('paths.logs', 'storage/logs/app.log');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $record = sprintf('[%s] %s: %s %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        file_put_contents($path, $record . PHP_EOL, FILE_APPEND);
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
    }

    public static function getCurrentUserId(): ?int
    {
        self::startSession();
        return $_SESSION['user']['id'] ?? null;
    }

    public static function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $appUrl = Config::get('app_url', '');
        $path = '';
        if ($appUrl !== '') {
            $parsed = parse_url($appUrl, PHP_URL_PATH);
            if (is_string($parsed)) {
                $path = rtrim($parsed, '/');
            }
        }

        self::$basePath = $path;

        return self::$basePath;
    }

    public static function asset(string $path = ''): string
    {
        $cleanPath = ltrim($path, '/');
        $base = self::basePath();

        if ($cleanPath === '') {
            return $base === '' ? '/' : $base;
        }

        return ($base === '' ? '' : $base) . '/' . $cleanPath;
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }
}
