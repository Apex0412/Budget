<?php

use Finanses\Config;
use Finanses\Helpers;

require_once __DIR__ . '/vendor/autoload.php';

Config::load(__DIR__);

$debug = Config::get('app_debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('log_errors', '1');

$phpLog = Helpers::projectPath('storage/logs/php-error.log');
$phpLogDir = dirname($phpLog);
if (!is_dir($phpLogDir)) {
    mkdir($phpLogDir, 0775, true);
}
if (!is_file($phpLog)) {
    if (@touch($phpLog)) {
        @chmod($phpLog, 0664);
    }
}
ini_set('error_log', $phpLog);

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Moscow');

Helpers::startSession();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}
