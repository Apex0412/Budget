<?php

namespace Finanses\Controllers;

use Finanses\Config;
use Finanses\Database;
use Finanses\Helpers;
use PDO;

class AuthController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function login(array $data): void
    {
        Helpers::startSession();
        $login = strtolower(trim($data['login'] ?? ''));
        $password = $data['password'] ?? '';
        $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        if ($login === '' || $password === '') {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Логин и пароль обязательны'], 422);
        }

        $stmt = $this->db->prepare('SELECT * FROM users WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Helpers::log('warn', 'Failed login', ['login' => $login, 'ip' => Helpers::getClientIp()]);
            Helpers::jsonResponse(['ok' => false, 'error' => 'Неверный логин или пароль'], 401);
        }

        if (!(bool) $user['is_active']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Учетная запись деактивирована'], 403);
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'fio' => $user['fio'],
            'role' => $user['role'],
            'must_change_password' => (bool) $user['must_change_password'],
            'department' => $user['department'],
            'position' => $user['position'],
            'theme' => $user['theme'] ?? 'light',
            'locale' => $user['locale'] ?? Config::get('i18n_default', 'ru')
        ];

        $this->db->prepare('INSERT INTO audit_log (user_id, action, entity, meta, ip) VALUES (:user_id, :action, :entity, :meta, :ip)')
            ->execute([
                'user_id' => $user['id'],
                'action' => 'login',
                'entity' => 'auth',
                'meta' => json_encode(['login' => $login], JSON_UNESCAPED_UNICODE),
                'ip' => Helpers::getClientIp(),
            ]);

        Helpers::jsonResponse(['ok' => true, 'data' => $_SESSION['user']]);
    }

    public function logout(): void
    {
        Helpers::startSession();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        if (!empty($_SESSION['user'])) {
            $this->db->prepare('INSERT INTO audit_log (user_id, action, entity, meta, ip) VALUES (:user_id, :action, :entity, :meta, :ip)')
                ->execute([
                    'user_id' => $_SESSION['user']['id'],
                    'action' => 'logout',
                    'entity' => 'auth',
                    'meta' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'ip' => Helpers::getClientIp(),
                ]);
        }
        $_SESSION = [];
        session_destroy();
        Helpers::jsonResponse(['ok' => true]);
    }

    public function me(): void
    {
        Helpers::startSession();
        if (empty($_SESSION['user'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        Helpers::jsonResponse(['ok' => true, 'data' => $_SESSION['user']]);
    }

    public function changePassword(array $data): void
    {
        Helpers::startSession();
        if (empty($_SESSION['user'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        $current = $data['current_password'] ?? '';
        $new = $data['new_password'] ?? '';
        $confirm = $data['confirm_password'] ?? '';
        if ($new !== $confirm) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Пароли не совпадают'], 422);
        }
        if (strlen($new) < Config::getNested('password_policy.min_length', 8)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Пароль слишком короткий'], 422);
        }

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['user']['id']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($current, $user['password_hash'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Текущий пароль неверный'], 401);
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        $this->db->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id')
            ->execute(['hash' => $hash, 'id' => $_SESSION['user']['id']]);
        $_SESSION['user']['must_change_password'] = false;

        $this->db->prepare('INSERT INTO audit_log (user_id, action, entity, meta, ip) VALUES (:user_id, :action, :entity, :meta, :ip)')
            ->execute([
                'user_id' => $_SESSION['user']['id'],
                'action' => 'change_password',
                'entity' => 'users',
                'meta' => json_encode([], JSON_UNESCAPED_UNICODE),
                'ip' => Helpers::getClientIp(),
            ]);

        Helpers::jsonResponse(['ok' => true, 'message' => 'Пароль изменен']);
    }

    public function updatePreferences(array $data): void
    {
        Helpers::startSession();
        if (empty($_SESSION['user'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        $theme = in_array($data['theme'] ?? 'light', ['light', 'dark'], true) ? $data['theme'] : 'light';
        $locale = in_array($data['locale'] ?? 'ru', ['ru', 'en'], true) ? $data['locale'] : 'ru';

        $this->db->prepare('UPDATE users SET theme = :theme, locale = :locale WHERE id = :id')
            ->execute([
                'theme' => $theme,
                'locale' => $locale,
                'id' => $_SESSION['user']['id'],
            ]);

        $_SESSION['user']['theme'] = $theme;
        $_SESSION['user']['locale'] = $locale;

        Helpers::jsonResponse(['ok' => true, 'data' => $_SESSION['user']]);
    }
}
