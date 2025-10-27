<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\AuthController;
use Finanses\Helpers;

$controller = new AuthController();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($method) {
    case 'POST':
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST ?: [];
        }
        if ($action === 'login') {
            $controller->login($payload);
        }
        if ($action === 'logout') {
            $controller->logout();
        }
        if ($action === 'change-password') {
            $controller->changePassword($payload);
        }
        if ($action === 'preferences') {
            $controller->updatePreferences($payload);
        }
        break;
    case 'GET':
        if ($action === 'me') {
            $controller->me();
        }
        break;
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported action'], 400);
