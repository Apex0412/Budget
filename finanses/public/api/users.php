<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\UserController;
use Finanses\Helpers;
use Finanses\Middleware\AuthMiddleware;

AuthMiddleware::requireRole(['admin']);
$controller = new UserController();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $controller->list($_GET);
        break;
    case 'POST':
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST ?: [];
        }
        $controller->store($payload);
        break;
    case 'PUT':
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            parse_str($raw, $payload);
            $payload = is_array($payload) ? $payload : [];
        }
        $controller->update((int)($_GET['id'] ?? 0), $payload);
        break;
    case 'DELETE':
        $controller->destroy((int)($_GET['id'] ?? 0));
        break;
    case 'PATCH':
        if (($_GET['action'] ?? '') === 'reset-password') {
            $controller->resetPassword((int)($_GET['id'] ?? 0));
        }
        break;
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported request'], 400);
