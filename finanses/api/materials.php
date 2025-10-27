<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list_active');

function fetch_catalog_map(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SELECT id, name FROM {$table}");
    $map = [];
    foreach ($stmt as $row) {
        $map[mb_strtolower($row['name'])] = (int)$row['id'];
    }
    return $map;
}

switch ($action) {
    case 'list':
        ensure_method('GET');
        require_admin();
        $stmt = $pdo->query('SELECT m.*, c.name AS category_name, u.name AS unit_name
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            ORDER BY m.is_active DESC, m.name');
        ok($stmt->fetchAll());
        break;

    case 'list_active':
        ensure_method('GET');
        require_auth();
        $stmt = $pdo->query('SELECT m.id, m.name, m.description, c.name AS category_name, u.name AS unit_name
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            WHERE m.is_active = 1
            ORDER BY m.name');
        ok($stmt->fetchAll());
        break;

    case 'create':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $name = trim((string)($payload['name'] ?? ''));
        $categoryId = isset($payload['category_id']) ? (int)$payload['category_id'] : null;
        $unitId = isset($payload['unit_id']) ? (int)$payload['unit_id'] : null;
        $description = trim((string)($payload['description'] ?? '')) ?: null;
        if ($name === '') {
            fail('Укажите название материала');
        }
        $stmt = $pdo->prepare('INSERT INTO materials (name, category_id, unit_id, description) VALUES (:name, :category_id, :unit_id, :description)');
        try {
            $stmt->execute([
                ':name' => $name,
                ':category_id' => $categoryId ?: null,
                ':unit_id' => $unitId ?: null,
                ':description' => $description,
            ]);
        } catch (PDOException $e) {
            fail('Не удалось добавить материал: ' . $e->getMessage(), 500);
        }
        log_action($pdo, 'CREATE_MATERIAL', 'materials', (int)$pdo->lastInsertId());
        ok(true);
        break;

    case 'update':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный материал');
        }
        $fields = [];
        $params = [':id' => $id];
        if (isset($payload['name'])) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                fail('Название не может быть пустым');
            }
            $fields[] = 'name = :name';
            $params[':name'] = $name;
        }
        if (array_key_exists('category_id', $payload)) {
            $fields[] = 'category_id = :category_id';
            $params[':category_id'] = $payload['category_id'] ? (int)$payload['category_id'] : null;
        }
        if (array_key_exists('unit_id', $payload)) {
            $fields[] = 'unit_id = :unit_id';
            $params[':unit_id'] = $payload['unit_id'] ? (int)$payload['unit_id'] : null;
        }
        if (array_key_exists('description', $payload)) {
            $fields[] = 'description = :description';
            $params[':description'] = trim((string)$payload['description']) ?: null;
        }
        if (!$fields) {
            fail('Нет данных для обновления');
        }
        $fields[] = 'updated_at = NOW()';
        $stmt = $pdo->prepare('UPDATE materials SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
        log_action($pdo, 'UPDATE_MATERIAL', 'materials', $id);
        ok(true);
        break;

    case 'toggle':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный материал');
        }
        $stmt = $pdo->prepare('UPDATE materials SET is_active = 1 - is_active, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
        log_action($pdo, 'TOGGLE_MATERIAL', 'materials', $id);
        ok(true);
        break;

    case 'delete':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный материал');
        }
        $stmt = $pdo->prepare('DELETE FROM materials WHERE id = :id');
        $stmt->execute([':id' => $id]);
        log_action($pdo, 'DELETE_MATERIAL', 'materials', $id);
        ok(true);
        break;

    case 'import':
        ensure_method('POST');
        csrf_check();
        require_admin();
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            fail('Не удалось загрузить файл');
        }
        $path = $_FILES['file']['tmp_name'];
        $handle = fopen($path, 'r');
        if (!$handle) {
            fail('Не удалось прочитать файл', 500);
        }
        $delimiter = ";";
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || count($header) === 1) {
            rewind($handle);
            $delimiter = ",";
            $header = fgetcsv($handle, 0, $delimiter);
        }
        if ($header === false) {
            fclose($handle);
            fail('Пустой файл');
        }
        $normalized = array_map(static fn($h) => mb_strtolower(trim($h)), $header);
        $required = ['name'];
        foreach ($required as $column) {
            if (!in_array($column, $normalized, true)) {
                fclose($handle);
                fail('Отсутствует обязательный столбец: ' . $column);
            }
        }
        $categoryMap = fetch_catalog_map($pdo, 'categories');
        $unitMap = fetch_catalog_map($pdo, 'units');
        $inserted = 0;
        $updated = 0;
        $rowNumber = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if (count(array_filter($row, static fn($value) => trim($value) !== '')) === 0) {
                continue;
            }
            $data = array_combine($normalized, array_map('trim', $row));
            if ($data === false) {
                continue;
            }
            $name = $data['name'] ?? '';
            if ($name === '') {
                continue;
            }
            $categoryId = null;
            if (!empty($data['category'])) {
                $key = mb_strtolower($data['category']);
                if (!isset($categoryMap[$key])) {
                    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
                    $stmt->execute([':name' => $data['category']]);
                    $categoryId = (int)$pdo->lastInsertId();
                    $categoryMap[$key] = $categoryId;
                } else {
                    $categoryId = $categoryMap[$key];
                }
            }
            $unitId = null;
            if (!empty($data['unit'])) {
                $key = mb_strtolower($data['unit']);
                if (!isset($unitMap[$key])) {
                    $stmt = $pdo->prepare('INSERT INTO units (name) VALUES (:name)');
                    $stmt->execute([':name' => $data['unit']]);
                    $unitId = (int)$pdo->lastInsertId();
                    $unitMap[$key] = $unitId;
                } else {
                    $unitId = $unitMap[$key];
                }
            }
            $description = $data['description'] ?? null;
            $isActive = isset($data['is_active']) ? (int)($data['is_active'] === '' ? 1 : (int)$data['is_active']) : 1;
            $existingStmt = $pdo->prepare('SELECT id FROM materials WHERE name = :name AND ((:category_id IS NULL AND category_id IS NULL) OR category_id = :category_id) AND ((:unit_id IS NULL AND unit_id IS NULL) OR unit_id = :unit_id)');
            $existingStmt->execute([
                ':name' => $name,
                ':category_id' => $categoryId,
                ':unit_id' => $unitId,
            ]);
            $existing = $existingStmt->fetchColumn();
            if ($existing) {
                $stmt = $pdo->prepare('UPDATE materials SET description = :description, is_active = :is_active, updated_at = NOW() WHERE id = :id');
                $stmt->execute([
                    ':description' => $description ?: null,
                    ':is_active' => $isActive ? 1 : 0,
                    ':id' => $existing,
                ]);
                $updated++;
            } else {
                $stmt = $pdo->prepare('INSERT INTO materials (name, category_id, unit_id, description, is_active) VALUES (:name, :category_id, :unit_id, :description, :is_active)');
                $stmt->execute([
                    ':name' => $name,
                    ':category_id' => $categoryId,
                    ':unit_id' => $unitId,
                    ':description' => $description ?: null,
                    ':is_active' => $isActive ? 1 : 0,
                ]);
                $inserted++;
            }
        }
        fclose($handle);
        log_action($pdo, 'IMPORT_MATERIALS', 'materials', null, ['inserted' => $inserted, 'updated' => $updated]);
        ok([
            'inserted' => $inserted,
            'updated' => $updated,
        ]);
        break;

    case 'export':
        ensure_method('GET');
        require_admin();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="materials-export-' . date('Ymd-His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['name', 'category', 'unit', 'description', 'is_active'], ';');
        $stmt = $pdo->query('SELECT m.name, c.name AS category, u.name AS unit, m.description, m.is_active
            FROM materials m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN units u ON u.id = m.unit_id
            ORDER BY m.name');
        foreach ($stmt as $row) {
            fputcsv($output, [
                $row['name'],
                $row['category'],
                $row['unit'],
                $row['description'],
                $row['is_active'],
            ], ';');
        }
        fclose($output);
        exit;

    default:
        fail('Неизвестное действие', 404);
}
