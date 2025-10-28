<?php

namespace Finanses\Controllers;

use Finanses\Database;
use Finanses\Helpers;
use PDO;

class UserController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function list(array $query): void
    {
        $stmt = $this->db->query('SELECT id, fio, position, department, login, role, is_active, must_change_password, theme, locale, created_at FROM users ORDER BY fio');
        Helpers::jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
    }

    public function store(array $payload): void
    {
        $token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        $password = $payload['password'] ?? bin2hex(random_bytes(4));
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('INSERT INTO users (fio, position, department, login, password_hash, role, must_change_password) VALUES (:fio, :position, :department, :login, :password_hash, :role, :must_change_password)');
        $stmt->execute([
            'fio' => $payload['fio'],
            'position' => $payload['position'] ?? null,
            'department' => $payload['department'] ?? null,
            'login' => strtolower($payload['login']),
            'password_hash' => $hash,
            'role' => $payload['role'] ?? 'user',
            'must_change_password' => 1,
        ]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Пользователь создан', 'data' => ['password' => $password]]);
    }

    public function update(int $id, array $payload): void
    {
        $token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        $stmt = $this->db->prepare('UPDATE users SET fio = :fio, position = :position, department = :department, role = :role, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'fio' => $payload['fio'],
            'position' => $payload['position'] ?? null,
            'department' => $payload['department'] ?? null,
            'role' => $payload['role'] ?? 'user',
            'is_active' => (int)($payload['is_active'] ?? 1),
            'id' => $id,
        ]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Пользователь обновлен']);
    }

    public function resetPassword(int $id): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        $password = bin2hex(random_bytes(4));
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
        Helpers::jsonResponse(['ok' => true, 'data' => ['password' => $password]]);
    }

    public function destroy(int $id): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Пользователь удален']);
    }
}
