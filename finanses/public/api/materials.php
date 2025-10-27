<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\MaterialController;
use Finanses\Helpers;
use Finanses\Middleware\AuthMiddleware;

AuthMiddleware::requireAuth();
$controller = new MaterialController();
$method = $_SERVER['REQUEST_METHOD'];
$role = $_SESSION['user']['role'] ?? 'user';

switch ($method) {
    case 'GET':
        $controller->list($_GET);
        break;
    case 'POST':
        if (!in_array($role, ['admin'], true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST ?: [];
        }
        $controller->store($payload);
        break;
    case 'PUT':
        if (!in_array($role, ['admin'], true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            parse_str($raw, $payload);
            $payload = is_array($payload) ? $payload : [];
        }
        $controller->update((int)($_GET['id'] ?? 0), $payload);
        break;
    case 'DELETE':
        if (!in_array($role, ['admin'], true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $controller->destroy((int)($_GET['id'] ?? 0));
        break;
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported request'], 400);
