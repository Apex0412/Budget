<?php
declare(strict_types=1);


function build_pdo(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', 'localhost'),
        env('DB_NAME', 'finanses')
    );

    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"
    ]);

    return $pdo;
}

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = build_pdo();
    } catch (PDOException $e) {
        fail('Ошибка подключения к БД: ' . $e->getMessage(), 500);
    }

    return $pdo;
}

function try_get_pdo_connection(): array
{
    try {
        return [
            'ok' => true,
            'pdo' => build_pdo()
        ];
    } catch (PDOException $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }
}
