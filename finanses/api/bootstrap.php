<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// Автозагрузка Composer (для TCPDF, PHPSpreadsheet)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

load_env(__DIR__ . '/../.env');

$sessionName = env('SESSION_NAME', 'finanses_session');
$cookieParams = session_get_cookie_params();
$cookieParams['httponly'] = true;
$cookieParams['samesite'] = 'Lax';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $cookieParams['secure'] = true;
}
session_set_cookie_params($cookieParams);
session_name($sessionName);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/middleware.php';
