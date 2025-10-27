<?php
require_once __DIR__ . '/bootstrap.php';

$connection = try_get_pdo_connection();
$pdo = $connection['ok'] ? $connection['pdo'] : null;
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action !== 'bootstrap-status' && !$connection['ok']) {
    fail('Нет подключения к базе данных: ' . ($connection['error'] ?? 'проверьте настройки .env'), 500);
}

if ($action !== 'bootstrap-status' && !($pdo instanceof PDO)) {
    $pdo = get_pdo();
}

function users_exist(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    return (int)$stmt->fetchColumn() > 0;
}

function collect_diagnostics(?PDO $pdo, array $connection): array
{
    $diagnostics = [
        'phpVersion' => PHP_VERSION,
        'phpVersionOk' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'extensions' => [],
        'directories' => [],
        'config' => [],
        'connectionOk' => $connection['ok'],
        'connectionError' => $connection['ok'] ? null : ($connection['error'] ?? 'Не удалось подключиться к базе данных'),
        'hasUsers' => false,
        'userCount' => 0,
        'usersTableExists' => false,
        'warnings' => [],
        'errors' => []
    ];

    if (!$diagnostics['phpVersionOk']) {
        $diagnostics['warnings'][] = 'Требуется PHP 8.1 или выше.';
    }

    $requiredExtensions = [
        'pdo_mysql' => 'Работа с MySQL (PDO)',
        'json' => 'Обработка JSON',
        'mbstring' => 'Многобайтовые строки',
        'openssl' => 'Генерация токенов и шифрование',
        'session' => 'Управление сессиями',
        'fileinfo' => 'Определение типов файлов',
        'dom' => 'Экспорт XLSX (PHPSpreadsheet)',
        'gd' => 'Графика и шрифты (TCPDF)',
        'intl' => 'Локализация и форматирование дат'
    ];

    foreach ($requiredExtensions as $extension => $description) {
        $loaded = extension_loaded($extension);
        $diagnostics['extensions'][] = [
            'name' => $extension,
            'description' => $description,
            'loaded' => $loaded
        ];
        if (!$loaded) {
            $diagnostics['errors'][] = "Расширение {$extension} ({$description}) не найдено.";
        }
    }

    $pdfDir = realpath(__DIR__ . '/../pdf') ?: __DIR__ . '/../pdf';
    $exists = is_dir($pdfDir);
    $writable = $exists && is_writable($pdfDir);
    $diagnostics['directories'][] = [
        'path' => $pdfDir,
        'exists' => $exists,
        'writable' => $writable,
        'description' => 'Каталог для кеширования PDF-файлов'
    ];
    if (!$exists) {
        $diagnostics['errors'][] = 'Каталог finanses/pdf не найден. Создайте его и назначьте права на запись.';
    } elseif (!$writable) {
        $diagnostics['warnings'][] = 'Каталог finanses/pdf недоступен для записи. PDF не смогут сохраняться.';
    }

    $configChecks = [
        'APP_BASE_URL' => [true, 'Базовый URL приложения'],
        'DB_HOST' => [true, 'Адрес сервера базы данных'],
        'DB_NAME' => [true, 'Имя базы данных'],
        'DB_USER' => [true, 'Пользователь базы данных'],
        'CSRF_SECRET' => [true, 'Секрет для CSRF-защиты'],
        'PDF_ORG_NAME' => [false, 'Название организации для PDF'],
        'PDF_CITY' => [false, 'Город в подвале PDF']
    ];

    foreach ($configChecks as $envKey => [$required, $description]) {
        $value = (string)env($envKey, '');
        $isPresent = $value !== '';
        if ($envKey === 'CSRF_SECRET' && $isPresent && strlen($value) < 32) {
            $diagnostics['warnings'][] = 'CSRF_SECRET должен содержать минимум 32 символа.';
        }
        if ($required && !$isPresent) {
            $diagnostics['errors'][] = "Переменная {$envKey} ({$description}) не задана в .env.";
        }
        $diagnostics['config'][] = [
            'key' => $envKey,
            'description' => $description,
            'configured' => $isPresent
        ];
    }

    if (!$diagnostics['connectionOk']) {
        $diagnostics['errors'][] = 'Нет подключения к базе данных. Проверьте настройки в .env и доступ MySQL.';
    }

    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            $diagnostics['usersTableExists'] = (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $diagnostics['errors'][] = 'Ошибка проверки таблицы users: ' . $e->getMessage();
        }

        if ($diagnostics['usersTableExists']) {
            try {
                $stmt = $pdo->query('SELECT COUNT(*) FROM users');
                $count = (int)$stmt->fetchColumn();
                $diagnostics['hasUsers'] = $count > 0;
                $diagnostics['userCount'] = $count;
            } catch (PDOException $e) {
                $diagnostics['errors'][] = 'Ошибка чтения таблицы users: ' . $e->getMessage();
            }
        } else {
            $diagnostics['warnings'][] = 'Таблица users отсутствует. Выполните скрипт schema.sql перед настройкой.';
        }
    }

    $diagnostics['canBootstrap'] = $diagnostics['connectionOk']
        && $diagnostics['usersTableExists']
        && empty(array_filter($diagnostics['errors'], fn($msg) => $msg !== null));

    return $diagnostics;
}

switch ($action) {
    case 'bootstrap-status':
        ensure_method('GET');
        $diagnostics = collect_diagnostics($pdo, $connection);
        ok([
            'needsBootstrap' => !$diagnostics['hasUsers'],
            'diagnostics' => $diagnostics
        ]);
        break;

    case 'bootstrap-admin':
        ensure_method('POST');
        csrf_check();
        if (!$connection['ok']) {
            fail('Нет подключения к базе данных: ' . ($connection['error'] ?? 'проверьте настройки .env'));
        }
        if (!($pdo instanceof PDO)) {
            fail('Подключение к базе данных недоступно');
        }
        $diagnostics = collect_diagnostics($pdo, $connection);
        if (!$diagnostics['canBootstrap']) {
            fail('Завершите проверку окружения перед созданием администратора.');
        }
        if ($diagnostics['hasUsers']) {
            fail('Установка уже выполнена', 403);
        }

        $payload = json_input();
        $fio = trim((string)($payload['fio'] ?? ''));
        $position = trim((string)($payload['position'] ?? ''));
        $department = trim((string)($payload['department'] ?? ''));
        $login = trim((string)($payload['login'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $deploymentType = trim((string)($payload['deployment'] ?? ''));

        if ($fio === '' || $login === '' || $password === '') {
            fail('Заполните ФИО, логин и пароль');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,}$/', $login)) {
            fail('Логин может содержать латиницу, цифры и символы _.- (мин. 3)');
        }
        if (strlen($password) < 8) {
            fail('Минимальная длина пароля — 8 символов');
        }
        if ($deploymentType === '') {
            fail('Укажите, где разворачивается система (локально или на хостинге)');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (fio, position, department, login, password_hash, role, must_change_password, is_active) VALUES (:fio, :position, :department, :login, :hash, "admin", 0, 1)');
        try {
            $stmt->execute([
                ':fio' => $fio,
                ':position' => $position !== '' ? $position : null,
                ':department' => $department !== '' ? $department : null,
                ':login' => $login,
                ':hash' => $hash
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                fail('Такой логин уже существует');
            }
            throw $e;
        }

        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user'] = [
            'id' => $userId,
            'fio' => $fio,
            'position' => $position,
            'department' => $department,
            'role' => 'admin',
            'must_change_password' => 0,
            'is_active' => 1
        ];

        log_action($pdo, 'BOOTSTRAP_ADMIN', 'users', $userId, [
            'login' => $login,
            'deployment' => $deploymentType
        ]);
        ok(['role' => 'admin', 'must_change_password' => 0, 'bootstrap_complete' => true]);
        break;

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
