<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Database;
use Finanses\Helpers;

$type = $_GET['type'] ?? 'app';

if ($type === 'db') {
    try {
        Database::connection()->query('SELECT 1');
        Helpers::jsonResponse(['ok' => true, 'status' => 'db_ok']);
    } catch (\Throwable $e) {
        Helpers::jsonResponse(['ok' => false, 'status' => 'db_error', 'error' => $e->getMessage()], 500);
    }
}

Helpers::jsonResponse(['ok' => true, 'status' => 'app_ok']);
