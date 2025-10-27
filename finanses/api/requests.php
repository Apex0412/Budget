<?php
require_once __DIR__ . '/bootstrap.php';


$pdo = get_pdo();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

switch ($action) {
    case 'list':
        ensure_method('GET');
        $user = require_auth();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $conditions[] = 'r.status = :status';
            $params[':status'] = $_GET['status'];
        }
        if (!empty($_GET['from'])) {
            $conditions[] = 'DATE(r.created_at) >= :from';
            $params[':from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $conditions[] = 'DATE(r.created_at) <= :to';
            $params[':to'] = $_GET['to'];
        }

        if ($user['role'] === 'admin') {
            if (!empty($_GET['fio'])) {
                $conditions[] = 'u.fio LIKE :fio';
                $params[':fio'] = '%' . $_GET['fio'] . '%';
            }
            if (!empty($_GET['department'])) {
                $conditions[] = 'u.department LIKE :department';
                $params[':department'] = '%' . $_GET['department'] . '%';
            }
            if (!empty($_GET['category'])) {
                $conditions[] = 'EXISTS (SELECT 1 FROM request_items ri2 WHERE ri2.request_id = r.id AND ri2.category LIKE :category)';
                $params[':category'] = '%' . $_GET['category'] . '%';
            }
        } else {
            $conditions[] = 'r.author_id = :author_id';
            $params[':author_id'] = $user['id'];
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM requests r JOIN users u ON u.id = r.author_id {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT r.*, u.fio AS author_fio, u.position AS author_position, u.department AS author_department,
                (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count
                FROM requests r JOIN users u ON u.id = r.author_id {$where}
                ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['can_edit'] = $user['role'] === 'admin' || ($item['author_id'] == $user['id'] && in_array($item['status'], ['draft', 'submitted'], true));
        }

        ok([
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $total,
                'total_pages' => max(1, (int)ceil($total / $perPage))
            ]
        ]);
        break;

    case 'one':
        ensure_method('GET');
        $user = require_auth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный идентификатор');
        }
        $stmt = $pdo->prepare('SELECT * FROM requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && $request['author_id'] != $user['id']) {
            fail('Нет доступа', 403);
        }
        $itemsStmt = $pdo->prepare('SELECT * FROM request_items WHERE request_id = :id');
        $itemsStmt->execute([':id' => $id]);
        ok(['request' => $request, 'items' => $itemsStmt->fetchAll()]);
        break;

    case 'create':
        ensure_method('POST');
        csrf_check();
        $user = require_auth();
        $payload = json_input();
        $justification = trim((string)($payload['justification'] ?? ''));
        $items = $payload['items'] ?? [];
        $statusCandidate = $payload['status'] ?? 'submitted';
        $status = in_array($statusCandidate, ['draft', 'submitted'], true) ? $statusCandidate : 'submitted';

        if ($justification === '') {
            fail('Обоснование обязательно');
        }
        if (!is_array($items) || !$items) {
            fail('Добавьте позиции');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO requests (author_id, justification, status) VALUES (:author_id, :justification, :status)');
            $stmt->execute([
                ':author_id' => $user['id'],
                ':justification' => $justification,
                ':status' => $status
            ]);
            $requestId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO request_items (request_id, category, item_name, unit, qty, note) VALUES (:request_id, :category, :item_name, :unit, :qty, :note)');
            foreach ($items as $item) {
                $category = trim((string)($item['category'] ?? ''));
                $itemName = trim((string)($item['item_name'] ?? ''));
                $unit = trim((string)($item['unit'] ?? ''));
                $qty = (float)($item['qty'] ?? 0);
                $note = trim((string)($item['note'] ?? '')) ?: null;
                if ($category === '' || $itemName === '' || $unit === '' || $qty <= 0) {
                    throw new InvalidArgumentException('Некорректные данные позиции');
                }
                $itemStmt->execute([
                    ':request_id' => $requestId,
                    ':category' => $category,
                    ':item_name' => $itemName,
                    ':unit' => $unit,
                    ':qty' => $qty,
                    ':note' => $note
                ]);
            }

            log_action($pdo, 'CREATE_REQUEST', 'requests', $requestId, ['status' => $status]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            fail('Ошибка сохранения заявки: ' . $e->getMessage(), 500);
        }
        ok(['id' => $requestId]);
        break;

    case 'update':
        ensure_method('POST');
        csrf_check();
        $user = require_auth();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректная заявка');
        }
        $stmt = $pdo->prepare('SELECT * FROM requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && ($request['author_id'] != $user['id'] || !in_array($request['status'], ['draft', 'submitted'], true))) {
            fail('Редактирование запрещено', 403);
        }

        $justification = trim((string)($payload['justification'] ?? ''));
        $items = $payload['items'] ?? [];
        $statusCandidate = $payload['status'] ?? $request['status'];
        $status = in_array($statusCandidate, ['draft', 'submitted'], true) ? $statusCandidate : $request['status'];

        if ($justification === '') {
            fail('Обоснование обязательно');
        }
        if (!is_array($items) || !$items) {
            fail('Добавьте позиции');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE requests SET justification = :justification, status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                ':justification' => $justification,
                ':status' => $status,
                ':id' => $id
            ]);

            $pdo->prepare('DELETE FROM request_items WHERE request_id = :id')->execute([':id' => $id]);
            $itemStmt = $pdo->prepare('INSERT INTO request_items (request_id, category, item_name, unit, qty, note) VALUES (:request_id, :category, :item_name, :unit, :qty, :note)');
            foreach ($items as $item) {
                $category = trim((string)($item['category'] ?? ''));
                $itemName = trim((string)($item['item_name'] ?? ''));
                $unit = trim((string)($item['unit'] ?? ''));
                $qty = (float)($item['qty'] ?? 0);
                $note = trim((string)($item['note'] ?? '')) ?: null;
                if ($category === '' || $itemName === '' || $unit === '' || $qty <= 0) {
                    throw new InvalidArgumentException('Некорректные данные позиции');
                }
                $itemStmt->execute([
                    ':request_id' => $id,
                    ':category' => $category,
                    ':item_name' => $itemName,
                    ':unit' => $unit,
                    ':qty' => $qty,
                    ':note' => $note
                ]);
            }
            log_action($pdo, 'UPDATE_REQUEST', 'requests', $id, ['status' => $status]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            fail('Ошибка обновления заявки: ' . $e->getMessage(), 500);
        }
        ok(['id' => $id]);
        break;

    case 'status':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        $status = (string)($payload['status'] ?? '');
        $allowed = ['draft','submitted','approved','rejected','in_progress','purchased'];
        if ($id <= 0 || !in_array($status, $allowed, true)) {
            fail('Некорректные данные');
        }
        $stmt = $pdo->prepare('UPDATE requests SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        log_action($pdo, 'UPDATE_STATUS', 'requests', $id, ['status' => $status]);
        ok(true);
        break;

    case 'remove':
        ensure_method('DELETE');
        csrf_check();
        $user = require_auth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный идентификатор');
        }
        $stmt = $pdo->prepare('SELECT * FROM requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin') {
            if ($request['author_id'] != $user['id'] || $request['status'] !== 'draft') {
                fail('Удаление запрещено', 403);
            }
        }
        $pdo->prepare('DELETE FROM requests WHERE id = :id')->execute([':id' => $id]);
        log_action($pdo, 'DELETE_REQUEST', 'requests', $id);
        ok(true);
        break;

    case 'summary':
        ensure_method('GET');
        require_admin();
        $stats = [];
        $total = (int)$pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();
        $stats[] = [
            'metric' => 'Всего заявок',
            'value' => $total,
            'description' => 'Количество заявок в системе'
        ];
        $currentMonth = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")->fetchColumn();
        $stats[] = [
            'metric' => 'В этом месяце',
            'value' => $currentMonth,
            'description' => 'Создано в текущем месяце'
        ];
        $byStatus = $pdo->query('SELECT status, COUNT(*) as cnt FROM requests GROUP BY status')->fetchAll();
        foreach ($byStatus as $row) {
            $stats[] = [
                'metric' => 'Статус: ' . $row['status'],
                'value' => (int)$row['cnt'],
                'description' => 'Количество заявок со статусом'
            ];
        }
        ok($stats);
        break;

    case 'audit':
        ensure_method('GET');
        require_admin();
        $stmt = $pdo->query('SELECT al.*, u.fio AS user_fio FROM audit_log al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT 200');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            if (!empty($row['meta'])) {
                $row['meta'] = json_encode(json_decode($row['meta'], true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        ok($rows);
        break;

    default:
        fail('Неизвестное действие', 404);
}
