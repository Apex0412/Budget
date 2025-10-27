<?php
require_once __DIR__ . '/bootstrap.php';

ensure_method('GET');
require_admin();

$pdo = get_pdo();

$byStatus = $pdo->query('SELECT status, COUNT(*) AS cnt FROM requests GROUP BY status ORDER BY cnt DESC')->fetchAll();
$byPriority = $pdo->query('SELECT priority, COUNT(*) AS cnt FROM requests GROUP BY priority ORDER BY cnt DESC')->fetchAll();
$byCategory = $pdo->query("SELECT ri.category, COUNT(*) AS cnt FROM request_items ri JOIN requests r ON r.id = ri.request_id GROUP BY ri.category ORDER BY cnt DESC LIMIT 10")->fetchAll();
$byDepartment = $pdo->query("SELECT COALESCE(u.department, 'Не указано') AS department, COUNT(*) AS cnt FROM requests r JOIN users u ON u.id = r.author_id GROUP BY department ORDER BY cnt DESC")->fetchAll();
$monthly = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM requests GROUP BY ym ORDER BY ym DESC LIMIT 12")->fetchAll();

ok([
    'status' => $byStatus,
    'priority' => $byPriority,
    'category' => $byCategory,
    'department' => $byDepartment,
    'monthly' => array_reverse($monthly)
]);
