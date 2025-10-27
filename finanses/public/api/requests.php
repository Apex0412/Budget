<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\RequestController;
use Finanses\Helpers;
use Finanses\Middleware\AuthMiddleware;

AuthMiddleware::requireAuth();
$controller = new RequestController();
$method = $_SERVER['REQUEST_METHOD'];
$user = $_SESSION['user'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $controller->show((int)$_GET['id'], $user);
        }
        if (isset($_GET['repeat_id'])) {
            $controller->repeat((int)$_GET['repeat_id'], $user);
        }
        $controller->index($_GET, $user);
        break;
    case 'POST':
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST ?: [];
        }
        $controller->store($payload, $user);
        break;
    case 'PUT':
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            parse_str($raw, $payload);
            $payload = is_array($payload) ? $payload : [];
        }
        $id = (int)($_GET['id'] ?? $payload['id'] ?? 0);
        if (!$id) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'ID обязателен'], 422);
        }
        $controller->update($id, $payload, $user);
        break;
    case 'PATCH':
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            parse_str($raw, $payload);
            $payload = is_array($payload) ? $payload : [];
        }
        if (isset($_GET['status'])) {
            $controller->updateStatus((int)($_GET['id'] ?? 0), $_GET['status'], $user);
        }
        break;
    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'ID обязателен'], 422);
        }
        $controller->destroy($id, $user);
        break;
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported request'], 400);
