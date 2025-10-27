<?php

require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Controllers\FileController;
use Finanses\PdfGenerator;
use Finanses\Helpers;
use Finanses\Middleware\AuthMiddleware;
use Finanses\Database;

AuthMiddleware::requireAuth();
$user = $_SESSION['user'];
$method = $_SERVER['REQUEST_METHOD'];
$controller = new FileController();

switch ($method) {
    case 'GET':
        if (isset($_GET['pdf'])) {
            $requestId = (int)$_GET['pdf'];
            $stmt = Database::connection()->prepare('SELECT author_id FROM requests WHERE id = :id');
            $stmt->execute(['id' => $requestId]);
            $owner = $stmt->fetchColumn();
            if (!$owner) {
                Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
            }
            if ($user['role'] !== 'admin' && (int)$owner !== (int)$user['id']) {
                Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа к PDF'], 403);
            }
            $generator = new PdfGenerator();
            $path = $generator->generateForRequest($requestId, $user['id']);
            if (!is_file($path)) {
                Helpers::jsonResponse(['ok' => false, 'error' => 'PDF не найден'], 404);
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="request_' . $requestId . '.pdf"');
            readfile($path);
            exit;
        }
        if (isset($_GET['request_id'])) {
            $controller->list((int)$_GET['request_id'], $user);
        }
        break;
    case 'POST':
        $controller->upload((int)($_POST['request_id'] ?? 0), $_FILES['files'], $user);
        break;
    case 'DELETE':
        $controller->delete((int)($_GET['id'] ?? 0), $user);
        break;
}

Helpers::jsonResponse(['ok' => false, 'error' => 'Unsupported request'], 400);
