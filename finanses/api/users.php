<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

switch ($action) {
    case 'list':
        ensure_method('GET');
        require_admin();
        $stmt = $pdo->query('SELECT id, fio, position, department, login, role, must_change_password, is_active FROM users ORDER BY fio');
        ok($stmt->fetchAll());
        break;

    case 'create':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $fio = trim((string)($payload['fio'] ?? ''));
        $login = trim((string)($payload['login'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $role = $payload['role'] === 'admin' ? 'admin' : 'user';
        if ($fio === '' || $login === '' || strlen($password) < 8) {
            fail('Заполните ФИО, логин и пароль (мин. 8 символов)');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare('INSERT INTO users (fio, login, password_hash, role, must_change_password) VALUES (:fio, :login, :password_hash, :role, 1)');
            $stmt->execute([
                ':fio' => $fio,
                ':login' => $login,
                ':password_hash' => $hash,
                ':role' => $role
            ]);
            log_action($pdo, 'CREATE_USER', 'users', (int)$pdo->lastInsertId(), ['role' => $role]);
        } catch (PDOException $e) {
            fail('Не удалось создать пользователя: ' . $e->getMessage(), 500);
        }
        ok(true);
        break;

    case 'update':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный пользователь');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (['fio', 'position', 'department'] as $field) {
            if (isset($payload[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = trim((string)$payload[$field]);
            }
        }
        if (isset($payload['role']) && in_array($payload['role'], ['user', 'admin'], true)) {
            $fields[] = 'role = :role';
            $params[':role'] = $payload['role'];
        }
        if (!$fields) {
            fail('Нет данных для обновления');
        }
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
        log_action($pdo, 'UPDATE_USER', 'users', $id);
        ok(true);
        break;

    case 'deactivate':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный пользователь');
        }
        $stmt = $pdo->prepare('SELECT is_active FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            fail('Пользователь не найден', 404);
        }
        $newStatus = $user['is_active'] ? 0 : 1;
        $pdo->prepare('UPDATE users SET is_active = :status WHERE id = :id')->execute([':status' => $newStatus, ':id' => $id]);
        log_action($pdo, 'TOGGLE_USER', 'users', $id, ['is_active' => $newStatus]);
        ok(['is_active' => $newStatus]);
        break;

    case 'reset_password':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный пользователь');
        }
        $tempPassword = substr(bin2hex(random_bytes(6)), 0, 10);
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $id]);
        log_action($pdo, 'RESET_PASSWORD', 'users', $id);
        ok(['temp_password' => $tempPassword]);
        break;

    default:
        fail('Неизвестное действие', 404);
}
