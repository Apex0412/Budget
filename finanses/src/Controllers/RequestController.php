<?php

namespace Finanses\Controllers;

use Finanses\Config;
use Finanses\Database;
use Finanses\Helpers;
use Finanses\PdfGenerator;
use PDO;

class RequestController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(array $query, array $user): void
    {
        $page = max(1, (int)($query['page'] ?? 1));
        $perPage = min(100, max(5, (int)($query['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];
        if (!empty($query['status'])) {
            $conditions[] = 'r.status = :status';
            $params['status'] = $query['status'];
        }
        if (!empty($query['priority'])) {
            $conditions[] = 'r.priority = :priority';
            $params['priority'] = $query['priority'];
        }
        if (!empty($query['q'])) {
            $conditions[] = '(r.justification LIKE :q OR r.basis LIKE :q OR u.fio LIKE :q)';
            $params['q'] = '%' . $query['q'] . '%';
        }
        if (!empty($query['department'])) {
            $conditions[] = 'u.department = :department';
            $params['department'] = $query['department'];
        }
        if ($user['role'] === 'user') {
            $conditions[] = 'r.author_id = :author_id';
            $params['author_id'] = $user['id'];
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->db->prepare(
            "SELECT SQL_CALC_FOUND_ROWS r.*, u.fio, u.department, u.position,
                (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count
             FROM requests r
             JOIN users u ON u.id = r.author_id
             $where
             ORDER BY r.created_at DESC
             LIMIT :offset, :limit"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $total = $this->db->query('SELECT FOUND_ROWS()')->fetchColumn();

        Helpers::jsonResponse([
            'ok' => true,
            'data' => [
                'items' => $rows,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => (int)$total,
                ],
            ],
        ]);
    }

    public function show(int $id, array $user): void
    {
        $stmt = $this->db->prepare('SELECT r.*, u.fio, u.department, u.position FROM requests r JOIN users u ON u.id = r.author_id WHERE r.id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
        }
        if ($user['role'] === 'user' && (int)$request['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }

        $items = $this->db->prepare('SELECT * FROM request_items WHERE request_id = :id ORDER BY id');
        $items->execute(['id' => $id]);
        $files = $this->db->prepare('SELECT id, original_name, mime_type, size, created_at FROM request_files WHERE request_id = :id');
        $files->execute(['id' => $id]);

        Helpers::jsonResponse(['ok' => true, 'data' => [
            'request' => $request,
            'items' => $items->fetchAll(),
            'files' => $files->fetchAll(),
        ]]);
    }

    public function store(array $payload, array $user): void
    {
        $token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        $this->validatePayload($payload);

        $status = $payload['status'] ?? 'draft';
        if ($user['role'] !== 'admin' && !in_array($status, ['draft', 'submitted'], true)) {
            $status = 'draft';
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO requests (author_id, status, priority, justification, basis, deadline_date, service_objects) VALUES (:author_id, :status, :priority, :justification, :basis, :deadline_date, :service_objects)');
            $stmt->execute([
                'author_id' => $user['id'],
                'status' => $status,
                'priority' => $payload['priority'] ?? 'normal',
                'justification' => $payload['justification'],
                'basis' => $payload['basis'] ?? 'Муниципальное задание и Правила благоустройства',
                'deadline_date' => $payload['deadline_date'] ?? null,
                'service_objects' => json_encode($payload['service_objects'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
            $requestId = (int)$this->db->lastInsertId();

            $itemStmt = $this->db->prepare('INSERT INTO request_items (request_id, category_id, material_id, unit_id, qty, assignment, note, current_stock, required_qty, purchase_qty) VALUES (:request_id, :category_id, :material_id, :unit_id, :qty, :assignment, :note, :current_stock, :required_qty, :purchase_qty)');
            foreach ($payload['items'] as $item) {
                $itemStmt->execute([
                    'request_id' => $requestId,
                    'category_id' => $item['category_id'] ?? null,
                    'material_id' => $item['material_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'qty' => $item['qty'],
                    'assignment' => $item['assignment'] ?? '',
                    'note' => $item['note'] ?? '',
                    'current_stock' => $item['current_stock'] ?? 0,
                    'required_qty' => $item['required_qty'] ?? $item['qty'],
                    'purchase_qty' => $item['purchase_qty'] ?? $item['qty'],
                ]);
            }

            $this->logAction($user['id'], 'create_request', 'requests', $requestId, ['priority' => $payload['priority'] ?? 'normal', 'status' => $status]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Helpers::log('error', 'Failed to create request', ['error' => $e->getMessage()]);
            Helpers::jsonResponse(['ok' => false, 'error' => 'Не удалось сохранить заявку'], 500);
        }

        Helpers::jsonResponse(['ok' => true, 'message' => 'Заявка создана']);
    }

    public function update(int $id, array $payload, array $user): void
    {
        $token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        $this->validatePayload($payload);

        $stmt = $this->db->prepare('SELECT * FROM requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
        }
        if ($user['role'] === 'user' && (int)$request['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }

        $editableStatuses = ['draft', 'returned'];
        if ($user['role'] === 'user' && !in_array($request['status'], $editableStatuses, true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка недоступна для редактирования'], 422);
        }

        $status = $payload['status'] ?? $request['status'];
        if ($user['role'] !== 'admin' && !in_array($status, ['draft', 'submitted', 'returned'], true)) {
            $status = $request['status'];
        }

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare('UPDATE requests SET status = :status, priority = :priority, justification = :justification, basis = :basis, deadline_date = :deadline_date, service_objects = :service_objects, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'status' => $status,
                'priority' => $payload['priority'] ?? $request['priority'],
                'justification' => $payload['justification'],
                'basis' => $payload['basis'] ?? $request['basis'] ?? 'Муниципальное задание и Правила благоустройства',
                'deadline_date' => $payload['deadline_date'] ?? $request['deadline_date'],
                'service_objects' => json_encode($payload['service_objects'] ?? [], JSON_UNESCAPED_UNICODE),
                'id' => $id,
            ]);

            $this->db->prepare('DELETE FROM request_items WHERE request_id = :id')->execute(['id' => $id]);
            $itemStmt = $this->db->prepare('INSERT INTO request_items (request_id, category_id, material_id, unit_id, qty, assignment, note, current_stock, required_qty, purchase_qty) VALUES (:request_id, :category_id, :material_id, :unit_id, :qty, :assignment, :note, :current_stock, :required_qty, :purchase_qty)');
            foreach ($payload['items'] as $item) {
                $itemStmt->execute([
                    'request_id' => $id,
                    'category_id' => $item['category_id'] ?? null,
                    'material_id' => $item['material_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'qty' => $item['qty'],
                    'assignment' => $item['assignment'] ?? '',
                    'note' => $item['note'] ?? '',
                    'current_stock' => $item['current_stock'] ?? 0,
                    'required_qty' => $item['required_qty'] ?? $item['qty'],
                    'purchase_qty' => $item['purchase_qty'] ?? $item['qty'],
                ]);
            }

            $this->logAction($user['id'], 'update_request', 'requests', $id, ['status' => $status]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            Helpers::jsonResponse(['ok' => false, 'error' => 'Не удалось обновить заявку'], 500);
        }

        Helpers::jsonResponse(['ok' => true, 'message' => 'Заявка обновлена']);
    }

    public function destroy(int $id, array $user): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }
        $stmt = $this->db->prepare('SELECT author_id, status FROM requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
        }
        if ($user['role'] !== 'admin' && (int)$request['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }
        if ($user['role'] !== 'admin' && !in_array($request['status'], ['draft', 'returned'], true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Удаление запрещено'], 422);
        }

        $this->db->prepare('DELETE FROM requests WHERE id = :id')->execute(['id' => $id]);
        $this->logAction($user['id'], 'delete_request', 'requests', $id, []);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Заявка удалена']);
    }

    public function updateStatus(int $id, string $status, array $user): void
    {
        $allowed = ['draft', 'submitted', 'returned', 'approved', 'rejected', 'in_progress', 'purchased'];
        if (!in_array($status, $allowed, true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Неверный статус'], 422);
        }
        if ($user['role'] === 'user' && !in_array($status, ['draft', 'submitted'], true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет прав на изменение статуса'], 403);
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Helpers::verifyCsrf($token)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'CSRF token mismatch'], 419);
        }

        $request = $this->db->prepare('SELECT author_id FROM requests WHERE id = :id');
        $request->execute(['id' => $id]);
        $row = $request->fetch();
        if (!$row) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
        }
        if ($user['role'] === 'user' && (int)$row['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }

        $this->db->prepare('UPDATE requests SET status = :status, updated_at = NOW() WHERE id = :id')
            ->execute(['status' => $status, 'id' => $id]);

        if (in_array($status, ['approved', 'in_progress', 'purchased'], true)) {
            $this->generatePdf($id, $user);
        }

        $this->logAction($user['id'], 'update_status', 'requests', $id, ['status' => $status]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Статус обновлен']);
    }

    public function repeat(int $id, array $user): void
    {
        $stmt = $this->db->prepare('SELECT * FROM requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Заявка не найдена'], 404);
        }
        if ($user['role'] === 'user' && (int)$request['author_id'] !== (int)$user['id']) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Нет доступа'], 403);
        }
        $items = $this->db->prepare('SELECT * FROM request_items WHERE request_id = :id');
        $items->execute(['id' => $id]);
        $request['items'] = $items->fetchAll();

        Helpers::jsonResponse(['ok' => true, 'data' => $request]);
    }

    private function validatePayload(array $payload): void
    {
        if (empty($payload['justification'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Обоснование обязательно'], 422);
        }
        $allowedStatuses = ['draft','submitted','returned','approved','rejected','in_progress','purchased'];
        if (!empty($payload['status']) && !in_array($payload['status'], $allowedStatuses, true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Неверный статус'], 422);
        }
        $allowedPriority = ['normal','urgent','critical'];
        if (!empty($payload['priority']) && !in_array($payload['priority'], $allowedPriority, true)) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Неверный приоритет'], 422);
        }
        if (empty($payload['items']) || !is_array($payload['items'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Добавьте хотя бы одну позицию'], 422);
        }
        foreach ($payload['items'] as $item) {
            if (($item['qty'] ?? 0) <= 0) {
                Helpers::jsonResponse(['ok' => false, 'error' => 'Количество должно быть больше нуля'], 422);
            }
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

    private function generatePdf(int $requestId, array $user): void
    {
        $pdf = new PdfGenerator();
        $pdf->generateForRequest($requestId, $user['id']);
    }
}
