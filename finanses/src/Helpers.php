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
        $logFile = self::projectPath(Config::getNested('paths.logs', 'storage/logs/app.log'));
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $record = sprintf('[%s] %s: %s %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        $result = @file_put_contents($logFile, $record . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            error_log('[FINANSES] Unable to write to application log: ' . $logFile . ' :: ' . $record);
        }
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

        $path = '';

        $projectRoot = Config::basePath();
        $publicRealPath = realpath($projectRoot . DIRECTORY_SEPARATOR . 'public');
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRootReal = $documentRoot !== '' ? realpath($documentRoot) : false;

        if ($publicRealPath && $documentRootReal && strpos($publicRealPath, $documentRootReal) === 0) {
            $relative = trim(str_replace('\\', '/', substr($publicRealPath, strlen($documentRootReal))), '/');
            $path = $relative === '' ? '' : '/' . $relative;
        }

        if ($path === '') {
            $appUrl = Config::get('app_url', '');
            if ($appUrl !== '') {
                $parsed = parse_url($appUrl, PHP_URL_PATH);
                if (is_string($parsed)) {
                    $parsed = rtrim($parsed, '/');
                    if ($parsed !== '' && ($pos = strpos($parsed, '/public')) !== false) {
                        $parsed = substr($parsed, 0, $pos + strlen('/public'));
                    }
                    if ($parsed === '/' || $parsed === '.' || $parsed === '\\') {
                        $parsed = '';
                    }
                    $path = $parsed;
                }
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

    public static function projectPath(string $path = ''): string
    {
        $base = Config::basePath();
        $base = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);

        if ($path === '' || $path === DIRECTORY_SEPARATOR) {
            return $base;
        }

        if (self::isAbsoluteFilesystemPath($path)) {
            return rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($path, "\\/"));

        return $base . DIRECTORY_SEPARATOR . $normalized;
    }

    public static function relativeProjectPath(string $path): string
    {
        $root = str_replace('\\', '/', self::projectPath());
        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, $root)) {
            $normalized = substr($normalized, strlen($root));
        }

        return ltrim($normalized, '/');
    }

    private static function isAbsoluteFilesystemPath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ||
            preg_match('#^[a-zA-Z]:[\\/]#', $path) === 1;
    }
}
