<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../pdf/generator.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

const REQUEST_STATUSES = ['draft','submitted','returned','approved','rejected','in_progress','purchased'];
const REQUEST_PRIORITIES = ['normal','urgent','critical'];

function default_basis(): string
{
    return env('PDF_BASIS_TEXT', 'Основание: муниципальное задание ' . date('Y') . ' и "Правила благоустройства территории муниципального образования «Городской округ Серпухов Московской области»", утверждённые решением Совета депутатов от 24.12.2024 № 25/288.');
}

function normalize_priority(?string $priority): string
{
    $priority = $priority ? strtolower(trim($priority)) : 'normal';
    return in_array($priority, REQUEST_PRIORITIES, true) ? $priority : 'normal';
}

function normalize_deadline($value): ?string
{
    if (!$value) {
        return null;
    }
    $date = date_create_from_format('Y-m-d', (string)$value);
    if (!$date) {
        throw new InvalidArgumentException('Некорректная дата дедлайна');
    }
    return $date->format('Y-m-d');
}

function parse_distribution($value): array
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $row) {
            $department = isset($row['department']) ? trim((string)$row['department']) : '';
            $qty = isset($row['qty']) && $row['qty'] !== '' ? (float)$row['qty'] : null;
            if ($department === '' && $qty === null) {
                continue;
            }
            $result[] = [
                'department' => $department,
                'qty' => $qty
            ];
        }
        return $result;
    }

    if (is_string($value)) {
        $result = [];
        $lines = preg_split('/\r\n|\n|\r/', $value);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, ':')) {
                [$dept, $qty] = array_map('trim', explode(':', $line, 2));
                $result[] = [
                    'department' => $dept,
                    'qty' => $qty !== '' ? (float)str_replace(',', '.', $qty) : null
                ];
            } else {
                $result[] = [
                    'department' => $line,
                    'qty' => null
                ];
            }
        }
        return $result;
    }

    return [];
}

function encode_distribution(array $rows): ?string
{
    if (!$rows) {
        return null;
    }
    $prepared = [];
    foreach ($rows as $row) {
        $prepared[] = [
            'department' => trim((string)($row['department'] ?? '')),
            'qty' => isset($row['qty']) && $row['qty'] !== '' ? (float)$row['qty'] : null
        ];
    }
    return json_encode($prepared, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function map_item_row(array $row): array
{
    if (!empty($row['distribution'])) {
        $decoded = json_decode($row['distribution'], true);
        $row['distribution'] = is_array($decoded) ? $decoded : [];
    } else {
        $row['distribution'] = [];
    }
    foreach (['stock_qty', 'need_qty', 'purchase_qty', 'qty'] as $numeric) {
        if ($row[$numeric] !== null) {
            $row[$numeric] = (float)$row[$numeric];
        }
    }
    return $row;
}

function fetch_request_items(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM request_items WHERE request_id = :id ORDER BY id');
    $stmt->execute([':id' => $id]);
    return array_map('map_item_row', $stmt->fetchAll());
}

function sanitize_items($items): array
{
    if (!is_array($items) || !$items) {
        throw new InvalidArgumentException('Добавьте позиции');
    }
    $prepared = [];
    foreach ($items as $item) {
        $category = trim((string)($item['category'] ?? ''));
        $itemName = trim((string)($item['item_name'] ?? ''));
        $unit = trim((string)($item['unit'] ?? ''));
        $qty = (float)($item['qty'] ?? 0);
        $note = trim((string)($item['note'] ?? '')) ?: null;
        if ($category === '' || $itemName === '' || $unit === '' || $qty <= 0) {
            throw new InvalidArgumentException('Некорректные данные позиции');
        }
        $purpose = trim((string)($item['purpose'] ?? '')) ?: null;
        $features = trim((string)($item['features'] ?? '')) ?: null;
        $stockQty = isset($item['stock_qty']) && $item['stock_qty'] !== '' ? max(0, (float)$item['stock_qty']) : null;
        $needQty = isset($item['need_qty']) && $item['need_qty'] !== '' ? max(0, (float)$item['need_qty']) : null;
        $purchaseQty = isset($item['purchase_qty']) && $item['purchase_qty'] !== '' ? max(0, (float)$item['purchase_qty']) : $qty;
        $distribution = encode_distribution(parse_distribution($item['distribution'] ?? []));

        $prepared[] = [
            'category' => $category,
            'item_name' => $itemName,
            'unit' => $unit,
            'qty' => $qty,
            'purpose' => $purpose,
            'features' => $features,
            'stock_qty' => $stockQty,
            'need_qty' => $needQty,
            'purchase_qty' => $purchaseQty,
            'distribution' => $distribution,
            'note' => $note
        ];
    }
    return $prepared;
}

function fetch_request(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM requests WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function fetch_attachments(PDO $pdo, int $requestId): array
{
    $stmt = $pdo->prepare('SELECT id, original_name, stored_name, mime_type, size, created_at FROM request_files WHERE request_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => $requestId]);
    return $stmt->fetchAll();
}

function ensure_upload_dir(int $requestId): string
{
    $dir = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось подготовить каталог uploads');
    }
    $path = $dir . '/' . $requestId;
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Не удалось создать директорию загрузки заявки');
    }
    return $path;
}

function delete_request_files(int $requestId): void
{
    $base = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
    $path = $base . '/' . $requestId;
    if (!is_dir($path)) {
        return;
    }
    $files = glob($path . '/*');
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    @rmdir($path);
}

switch ($action) {
    case 'list':
        ensure_method('GET');
        $user = require_auth();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(5, (int)($_GET['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $conditions[] = 'r.status = :status';
            $params[':status'] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $conditions[] = 'r.priority = :priority';
            $params[':priority'] = normalize_priority($_GET['priority']);
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
                (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count,
                (SELECT COUNT(*) FROM request_files rf WHERE rf.request_id = r.id) AS attachments_count
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
            $item['can_edit'] = $user['role'] === 'admin' || ($item['author_id'] == $user['id'] && in_array($item['status'], ['draft', 'submitted', 'returned'], true));
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
        $request = fetch_request($pdo, $id);
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && $request['author_id'] != $user['id']) {
            fail('Нет доступа', 403);
        }
        ok([
            'request' => $request,
            'items' => fetch_request_items($pdo, $id),
            'attachments' => fetch_attachments($pdo, $id)
        ]);
        break;

    case 'latest':
        ensure_method('GET');
        $user = require_auth();
        $stmt = $pdo->prepare('SELECT id FROM requests WHERE author_id = :author ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([':author' => $user['id']]);
        $id = (int)$stmt->fetchColumn();
        if (!$id) {
            ok(null);
            break;
        }
        $request = fetch_request($pdo, $id);
        ok([
            'request' => $request,
            'items' => fetch_request_items($pdo, $id),
            'attachments' => fetch_attachments($pdo, $id)
        ]);
        break;

    case 'create':
        ensure_method('POST');
        csrf_check();
        $user = require_auth();
        $payload = json_input();
        $justification = trim((string)($payload['justification'] ?? ''));
        if ($justification === '') {
            fail('Обоснование обязательно');
        }
        $basis = trim((string)($payload['basis'] ?? '')) ?: default_basis();
        $serviceObjects = trim((string)($payload['service_objects'] ?? '')) ?: null;
        $periodLabel = trim((string)($payload['period_label'] ?? '')) ?: null;
        try {
            $items = sanitize_items($payload['items'] ?? []);
            $priority = normalize_priority($payload['priority'] ?? null);
            $deadline = normalize_deadline($payload['deadline_date'] ?? null);
        } catch (Throwable $e) {
            fail($e->getMessage());
        }
        $statusCandidate = $payload['status'] ?? 'submitted';
        $status = in_array($statusCandidate, ['draft', 'submitted'], true) ? $statusCandidate : 'submitted';

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO requests (author_id, justification, basis, service_objects, period_label, status, priority, deadline_date, pdf_generated) VALUES (:author_id, :justification, :basis, :service_objects, :period_label, :status, :priority, :deadline, 0)');
            $stmt->execute([
                ':author_id' => $user['id'],
                ':justification' => $justification,
                ':basis' => $basis,
                ':service_objects' => $serviceObjects,
                ':period_label' => $periodLabel,
                ':status' => $status,
                ':priority' => $priority,
                ':deadline' => $deadline
            ]);
            $requestId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO request_items (request_id, category, item_name, unit, qty, purpose, features, stock_qty, need_qty, purchase_qty, distribution, note) VALUES (:request_id, :category, :item_name, :unit, :qty, :purpose, :features, :stock_qty, :need_qty, :purchase_qty, :distribution, :note)');
            foreach ($items as $item) {
                $itemStmt->execute([
                    ':request_id' => $requestId,
                    ':category' => $item['category'],
                    ':item_name' => $item['item_name'],
                    ':unit' => $item['unit'],
                    ':qty' => $item['qty'],
                    ':purpose' => $item['purpose'],
                    ':features' => $item['features'],
                    ':stock_qty' => $item['stock_qty'],
                    ':need_qty' => $item['need_qty'],
                    ':purchase_qty' => $item['purchase_qty'],
                    ':distribution' => $item['distribution'],
                    ':note' => $item['note']
                ]);
            }

            log_action($pdo, 'create_request', 'requests', $requestId, [
                'status' => $status,
                'priority' => $priority
            ]);
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
        $request = fetch_request($pdo, $id);
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && ($request['author_id'] != $user['id'] || !in_array($request['status'], ['draft', 'submitted', 'returned'], true))) {
            fail('Редактирование запрещено', 403);
        }
        $justification = trim((string)($payload['justification'] ?? ''));
        if ($justification === '') {
            fail('Обоснование обязательно');
        }
        $basis = trim((string)($payload['basis'] ?? ($request['basis'] ?? '')));
        $basis = $basis !== '' ? $basis : default_basis();
        $serviceObjects = trim((string)($payload['service_objects'] ?? ($request['service_objects'] ?? '')));
        $serviceObjects = $serviceObjects !== '' ? $serviceObjects : null;
        $periodLabel = trim((string)($payload['period_label'] ?? ($request['period_label'] ?? '')));
        $periodLabel = $periodLabel !== '' ? $periodLabel : null;
        try {
            $items = sanitize_items($payload['items'] ?? []);
            $priority = normalize_priority($payload['priority'] ?? $request['priority']);
            $deadline = normalize_deadline($payload['deadline_date'] ?? $request['deadline_date']);
        } catch (Throwable $e) {
            fail($e->getMessage());
        }
        $statusCandidate = $payload['status'] ?? $request['status'];
        $status = in_array($statusCandidate, ['draft', 'submitted'], true) ? $statusCandidate : $request['status'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE requests SET justification = :justification, basis = :basis, service_objects = :service_objects, period_label = :period_label, status = :status, priority = :priority, deadline_date = :deadline, pdf_generated = 0, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                ':justification' => $justification,
                ':basis' => $basis,
                ':service_objects' => $serviceObjects,
                ':period_label' => $periodLabel,
                ':status' => $status,
                ':priority' => $priority,
                ':deadline' => $deadline,
                ':id' => $id
            ]);

            $pdo->prepare('DELETE FROM request_items WHERE request_id = :id')->execute([':id' => $id]);
            $itemStmt = $pdo->prepare('INSERT INTO request_items (request_id, category, item_name, unit, qty, purpose, features, stock_qty, need_qty, purchase_qty, distribution, note) VALUES (:request_id, :category, :item_name, :unit, :qty, :purpose, :features, :stock_qty, :need_qty, :purchase_qty, :distribution, :note)');
            foreach ($items as $item) {
                $itemStmt->execute([
                    ':request_id' => $id,
                    ':category' => $item['category'],
                    ':item_name' => $item['item_name'],
                    ':unit' => $item['unit'],
                    ':qty' => $item['qty'],
                    ':purpose' => $item['purpose'],
                    ':features' => $item['features'],
                    ':stock_qty' => $item['stock_qty'],
                    ':need_qty' => $item['need_qty'],
                    ':purchase_qty' => $item['purchase_qty'],
                    ':distribution' => $item['distribution'],
                    ':note' => $item['note']
                ]);
            }
            log_action($pdo, 'update_request', 'requests', $id, [
                'status' => $status,
                'priority' => $priority
            ]);
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
        if ($id <= 0 || !in_array($status, REQUEST_STATUSES, true)) {
            fail('Некорректные данные');
        }
        $stmt = $pdo->prepare('UPDATE requests SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        log_action($pdo, 'update_status', 'requests', $id, ['status' => $status]);
        if ($status === 'approved') {
            try {
                generate_request_pdf_file($pdo, $id, true);
                log_action($pdo, 'approve_request', 'requests', $id);
            } catch (Throwable $e) {
                fail('Статус обновлён, но PDF не сформирован: ' . $e->getMessage(), 500);
            }
        }
        ok(true);
        break;

    case 'upload_attachment':
        ensure_method('POST');
        csrf_check();
        $user = require_auth();
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            fail('Некорректная заявка');
        }
        $request = fetch_request($pdo, $requestId);
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && $request['author_id'] != $user['id']) {
            fail('Недостаточно прав', 403);
        }
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            fail('Файл не получен');
        }
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            fail('Ошибка загрузки файла');
        }
        $maxSize = 20 * 1024 * 1024;
        if ($file['size'] <= 0 || $file['size'] > $maxSize) {
            fail('Размер файла превышает 20 МБ');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: $file['type'];
        $allowed = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-word.document.macroEnabled.12',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel.sheet.macroEnabled.12',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain'
        ];
        if ($mime && !in_array($mime, $allowed, true)) {
            fail('Недопустимый тип файла');
        }
        $uploadDir = ensure_upload_dir($requestId);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(16)) . ($extension ? '.' . $extension : '');
        $target = $uploadDir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            fail('Не удалось сохранить файл');
        }
        $stmt = $pdo->prepare('INSERT INTO request_files (request_id, original_name, stored_name, mime_type, size) VALUES (:request_id, :original_name, :stored_name, :mime, :size)');
        $stmt->execute([
            ':request_id' => $requestId,
            ':original_name' => $file['name'],
            ':stored_name' => $stored,
            ':mime' => $mime,
            ':size' => $file['size']
        ]);
        log_action($pdo, 'UPLOAD_ATTACHMENT', 'request_files', (int)$pdo->lastInsertId(), ['request_id' => $requestId]);
        ok(fetch_attachments($pdo, $requestId));
        break;

    case 'delete_attachment':
        ensure_method('POST');
        csrf_check();
        $user = require_auth();
        $payload = json_input();
        $attachmentId = (int)($payload['id'] ?? 0);
        if ($attachmentId <= 0) {
            fail('Некорректный файл');
        }
        $stmt = $pdo->prepare('SELECT * FROM request_files WHERE id = :id');
        $stmt->execute([':id' => $attachmentId]);
        $file = $stmt->fetch();
        if (!$file) {
            fail('Файл не найден', 404);
        }
        $request = fetch_request($pdo, (int)$file['request_id']);
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && $request['author_id'] != $user['id']) {
            fail('Нет доступа', 403);
        }
        $baseDir = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
        $path = $baseDir . '/' . $file['request_id'] . '/' . $file['stored_name'];
        if (is_file($path)) {
            @unlink($path);
        }
        $pdo->prepare('DELETE FROM request_files WHERE id = :id')->execute([':id' => $attachmentId]);
        log_action($pdo, 'DELETE_ATTACHMENT', 'request_files', $attachmentId, ['request_id' => (int)$file['request_id']]);
        ok(fetch_attachments($pdo, (int)$file['request_id']));
        break;

    case 'remove':
        ensure_method('DELETE');
        csrf_check();
        $user = require_auth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный идентификатор');
        }
        $request = fetch_request($pdo, $id);
        if (!$request) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin') {
            if ($request['author_id'] != $user['id'] || $request['status'] !== 'draft') {
                fail('Удаление запрещено', 403);
            }
        }
        $pdo->prepare('DELETE FROM requests WHERE id = :id')->execute([':id' => $id]);
        delete_request_files($id);
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
        $statusTitles = [
            'draft' => 'Черновик',
            'submitted' => 'Отправлена',
            'returned' => 'На доработке',
            'approved' => 'Согласована',
            'rejected' => 'Отклонена',
            'in_progress' => 'В работе',
            'purchased' => 'Закуплено',
        ];
        foreach ($byStatus as $row) {
            $stats[] = [
                'metric' => 'Статус: ' . ($statusTitles[$row['status']] ?? $row['status']),
                'value' => (int)$row['cnt'],
                'description' => 'Количество заявок со статусом'
            ];
        }
        $byPriority = $pdo->query('SELECT priority, COUNT(*) as cnt FROM requests GROUP BY priority')->fetchAll();
        $priorityTitles = [
            'normal' => 'Обычный',
            'urgent' => 'Срочный',
            'critical' => 'Критический'
        ];
        foreach ($byPriority as $row) {
            $stats[] = [
                'metric' => 'Приоритет: ' . ($priorityTitles[$row['priority']] ?? $row['priority']),
                'value' => (int)$row['cnt'],
                'description' => 'Количество заявок по приоритету'
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
