<?php

namespace Finanses;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static string $basePath = '';

    public static function load(string $basePath): void
    {
        if (!empty(self::$config)) {
            return;
        }

        $realBase = realpath($basePath) ?: $basePath;
        self::$basePath = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $realBase), DIRECTORY_SEPARATOR);

        $envFile = self::$basePath . '/.env';
        if (is_readable($envFile)) {
            $dotenv = Dotenv::createImmutable(self::$basePath);
            $dotenv->safeLoad();
        } elseif (is_readable(self::$basePath . '/.env.example')) {
            $dotenv = Dotenv::createImmutable(self::$basePath, ['.env.example']);
            $dotenv->safeLoad();
        }

        self::$config = [
            'base_path' => self::$basePath,
            'app_env' => $_ENV['APP_ENV'] ?? 'prod',
            'app_debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
            'app_url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
            'session_name' => $_ENV['SESSION_NAME'] ?? 'finanses_session',
            'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 1800),
            'use_jwt' => filter_var($_ENV['USE_JWT'] ?? false, FILTER_VALIDATE_BOOL),
            'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'change_me',
            'db' => [
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'name' => $_ENV['DB_NAME'] ?? 'finanses',
                'user' => $_ENV['DB_USER'] ?? 'root',
                'pass' => $_ENV['DB_PASS'] ?? '',
                'charset' => 'utf8mb4',
            ],
            'paths' => [
                'files' => $_ENV['FILE_STORAGE_PATH'] ?? 'storage/uploads',
                'pdf' => $_ENV['PDF_STORAGE_PATH'] ?? 'storage/pdf',
                'logs' => $_ENV['LOG_PATH'] ?? 'storage/logs/app.log',
            ],
            'upload' => [
                'max_size' => (int) ($_ENV['MAX_UPLOAD_SIZE'] ?? 5 * 1024 * 1024),
                'allowed_mime' => array_map('trim', explode(',', $_ENV['ALLOWED_UPLOAD_MIME'] ?? 'application/pdf,image/png,image/jpeg')),
            ],
            'password_policy' => [
                'min_length' => (int) ($_ENV['PASSWORD_POLICY_MIN_LENGTH'] ?? 8),
            ],
            'pdf' => [
                'org_name' => $_ENV['PDF_ORG_NAME'] ?? 'Муниципальное бюджетное учреждение "Комбинат благоустройства"',
                'director_name' => $_ENV['PDF_DIRECTOR_NAME'] ?? 'Кравченко К.И.',
                'director_position' => $_ENV['PDF_DIRECTOR_POSITION'] ?? 'Директор',
                'signature_title' => $_ENV['PDF_SIGNATURE_TITLE'] ?? 'Заместитель директора',
            ],
            'i18n_default' => $_ENV['I18N_DEFAULT'] ?? 'ru',
        ];
    }

    public static function get(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    public static function getNested(string $path, $default = null)
    {
        $segments = explode('.', $path);
        $value = self::$config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function basePath(): string
    {
        if (self::$basePath !== '') {
            return self::$basePath;
        }

        return dirname(__DIR__);
    }
}
