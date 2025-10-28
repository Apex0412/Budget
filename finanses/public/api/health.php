<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Database;
use Finanses\Helpers;

$type = $_GET['type'] ?? 'app';

$dbStatus = [
    'ok' => false,
    'error' => null,
];

try {
    Database::connection()->query('SELECT 1');
    $dbStatus['ok'] = true;
} catch (\Throwable $e) {
    $dbStatus['error'] = $e->getMessage();
}

if ($type === 'db') {
    if ($dbStatus['ok']) {
        Helpers::jsonResponse(['ok' => true, 'status' => 'db_ok']);
    }

    Helpers::jsonResponse([
        'ok' => false,
        'status' => 'db_error',
        'error' => $dbStatus['error'] ?? 'unknown',
    ], 500);
}

if ($dbStatus['ok']) {
    Helpers::jsonResponse(['ok' => true, 'status' => 'app_ok']);
}

Helpers::jsonResponse([
    'ok' => false,
    'status' => 'app_error',
    'error' => $dbStatus['error'] ?? 'unknown',
], 500);
