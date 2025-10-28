<?php

namespace Finanses\Controllers;

use Finanses\Config;
use Finanses\Database;
use Finanses\Helpers;
use PDO;

class FileController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function list(int $requestId, array $user): void
    {
        $this->assertAccess($requestId, $user);
        $stmt = $this->db->prepare('SELECT id, original_name, mime_type, size, created_at FROM request_files WHERE request_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id' => $requestId]);
        Helpers::jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
    }

    public function upload(int $requestId, array $files, array $user): void
    {
        $this->assertAccess($requestId, $user);
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        $allowed = Config::getNested('upload.allowed_mime', []);
        $maxSize = Config::getNested('upload.max_size', 5 * 1024 * 1024);
        $storage = Config::getNested('paths.files', 'storage/uploads');
        $dir = Helpers::projectPath(trim($storage, "\\/") . '/' . $requestId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $insert = $this->db->prepare('INSERT INTO request_files (request_id, path, original_name, mime_type, size) VALUES (:request_id, :path, :original_name, :mime_type, :size)');
        $uploaded = [];

        if (!is_array($files['name'] ?? null)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Не выбраны файлы'], 422);
        }

        foreach ($files['name'] as $idx => $name) {
            if ($files['error'][$idx] !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmpName = $files['tmp_name'][$idx];
            $size = (int)$files['size'][$idx];
            $mime = mime_content_type($tmpName) ?: $files['type'][$idx];
            if ($size > $maxSize || !in_array($mime, $allowed, true)) {
                continue;
            }
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = uniqid('req_' . $requestId . '_', true) . '.' . $ext;
            $destination = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($tmpName, $destination)) {
                continue;
            }
            chmod($destination, 0664);
            $insert->execute([
                'request_id' => $requestId,
                'path' => Helpers::relativeProjectPath($destination),
                'original_name' => $name,
                'mime_type' => $mime,
                'size' => $size,
            ]);
            $uploaded[] = [
                'id' => (int)$this->db->lastInsertId(),
                'original_name' => $name,
                'mime_type' => $mime,
                'size' => $size,
            ];
        }

        $this->logAction($user['id'], 'upload_file', 'request_files', $requestId, ['count' => count($uploaded)]);
        Helpers::jsonResponse(['ok' => true, 'data' => $uploaded]);
    }

    public function delete(int $fileId, array $user): void
    {
        $stmt = $this->db->prepare('SELECT rf.*, r.author_id FROM request_files rf JOIN requests r ON r.id = rf.request_id WHERE rf.id = :id');
        $stmt->execute(['id' => $fileId]);
        $file = $stmt->fetch();
        if (!$file) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Файл не найден'], 404);
        }
        if ($user['role'] !== 'admin' && (int)$file['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        $fullPath = Helpers::projectPath($file['path']);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
        $this->db->prepare('DELETE FROM request_files WHERE id = :id')->execute(['id' => $fileId]);
        $this->logAction($user['id'], 'delete_file', 'request_files', $file['request_id'], ['file_id' => $fileId]);
        Helpers::jsonResponse(['ok' => true]);
    }

    private function assertAccess(int $requestId, array $user): void
    {
        $stmt = $this->db->prepare('SELECT author_id FROM requests WHERE id = :id');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
        }
        if ($user['role'] !== 'admin' && (int)$request['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }
    }

    private function logAction(int $userId, string $action, string $entity, int $entityId, array $meta): void
    {
        $stmt = $this->db->prepare('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (:user_id, :action, :entity, :entity_id, :meta, :ip)');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip' => Helpers::getClientIp(),
        ]);
    }
}
