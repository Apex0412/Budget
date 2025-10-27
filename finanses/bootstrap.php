<?php

use Finanses\Config;
use Finanses\Helpers;

require_once __DIR__ . '/vendor/autoload.php';

Config::load(__DIR__);
Helpers::startSession();

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}
