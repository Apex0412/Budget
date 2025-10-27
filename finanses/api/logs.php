<?php
require_once __DIR__ . '/bootstrap.php';

ensure_method('GET');
require_admin();

$pdo = get_pdo();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(200, max(20, (int)($_GET['per_page'] ?? 100)));
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
$stmt = $pdo->prepare('SELECT al.*, u.fio AS user_fio FROM audit_log al LEFT JOIN users u ON u.id = al.user_id ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
foreach ($rows as &$row) {
    if (!empty($row['meta'])) {
        $row['meta'] = json_decode($row['meta'], true);
    }
}

ok([
    'items' => $rows,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total_items' => $total,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ]
]);
