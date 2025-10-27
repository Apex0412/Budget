<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\StatisticsController;
use Finanses\Helpers;
use Finanses\Middleware\AuthMiddleware;

AuthMiddleware::requireRole(['admin']);
$controller = new StatisticsController();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller->overview();
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported request'], 400);
