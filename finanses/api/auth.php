<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'login':
        ensure_method('POST');
        csrf_check();
        $payload = json_input();
        $login = trim((string)($payload['login'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        if ($login === '' || $password === '') {
            fail('Укажите логин и пароль');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE login = :login LIMIT 1');
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_action($pdo, 'LOGIN_FAIL', 'users', null, ['login' => $login]);
            fail('Неверные учетные данные', 401);
        }
        if (!(int)$user['is_active']) {
            fail('Учетная запись заблокирована', 403);
        }

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'fio' => $user['fio'],
            'position' => $user['position'],
            'department' => $user['department'],
            'role' => $user['role'],
            'must_change_password' => (int)$user['must_change_password'],
            'is_active' => (int)$user['is_active']
        ];
        log_action($pdo, 'LOGIN', 'users', (int)$user['id']);
        ok(['role' => $user['role'], 'must_change_password' => (int)$user['must_change_password']]);
        break;

    case 'me':
        ensure_method('GET');
        $user = require_auth();
        ok($user);
        break;

    case 'logout':
        ensure_method('POST');
        csrf_check();
        $user = current_user();
        if ($user) {
            log_action($pdo, 'LOGOUT', 'users', (int)$user['id']);
        }
        $_SESSION = [];
        session_destroy();
        ok(true);
        break;

    case 'password/change':
        ensure_method('POST');
        csrf_check();
        $user = require_auth();
        $payload = json_input();
        $newPassword = (string)($payload['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            fail('Минимальная длина пароля — 8 символов');
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $user['id']]);
        $_SESSION['user']['must_change_password'] = 0;
        log_action($pdo, 'PASSWORD_CHANGE', 'users', (int)$user['id']);
        ok(true);
        break;

    default:
        fail('Неизвестное действие', 404);
}
