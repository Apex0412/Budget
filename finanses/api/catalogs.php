<?php
require_once __DIR__ . '/bootstrap.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

switch ($action) {
    case 'list':
        ensure_method('GET');
        require_admin();
        $categories = $pdo->query('SELECT id, name, is_active FROM categories ORDER BY name')->fetchAll();
        $units = $pdo->query('SELECT id, name, is_active FROM units ORDER BY name')->fetchAll();
        ok(['categories' => $categories, 'units' => $units]);
        break;

    case 'create_category':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            fail('Укажите название категории');
        }
        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name) ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->execute([':name' => $name]);
        log_action($pdo, 'CATALOG_CREATE', 'categories', (int)$pdo->lastInsertId(), ['name' => $name]);
        ok(true);
        break;

    case 'toggle_category':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Категория не найдена');
        }
        $stmt = $pdo->prepare('SELECT is_active FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $category = $stmt->fetch();
        if (!$category) {
            fail('Категория не найдена', 404);
        }
        $new = $category['is_active'] ? 0 : 1;
        $pdo->prepare('UPDATE categories SET is_active = :state WHERE id = :id')->execute([':state' => $new, ':id' => $id]);
        log_action($pdo, 'CATALOG_TOGGLE', 'categories', $id, ['is_active' => $new]);
        ok(['is_active' => $new]);
        break;

    case 'create_unit':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            fail('Укажите название единицы');
        }
        $stmt = $pdo->prepare('INSERT INTO units (name) VALUES (:name) ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->execute([':name' => $name]);
        log_action($pdo, 'CATALOG_CREATE', 'units', (int)$pdo->lastInsertId(), ['name' => $name]);
        ok(true);
        break;

    case 'toggle_unit':
        ensure_method('POST');
        csrf_check();
        require_admin();
        $payload = json_input();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            fail('Единица не найдена');
        }
        $stmt = $pdo->prepare('SELECT is_active FROM units WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $unit = $stmt->fetch();
        if (!$unit) {
            fail('Единица не найдена', 404);
        }
        $new = $unit['is_active'] ? 0 : 1;
        $pdo->prepare('UPDATE units SET is_active = :state WHERE id = :id')->execute([':state' => $new, ':id' => $id]);
        log_action($pdo, 'CATALOG_TOGGLE', 'units', $id, ['is_active' => $new]);
        ok(['is_active' => $new]);
        break;

    default:
        fail('Неизвестное действие', 404);
}
