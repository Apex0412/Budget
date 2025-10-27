<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\LogController;
use Finanses\Helpers;
use Finanses\Middleware\AuthMiddleware;

AuthMiddleware::requireRole(['admin']);
$controller = new LogController();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller->list($_GET);
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported request'], 400);
