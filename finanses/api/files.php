<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../pdf/generator.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? 'pdf';

function ensure_upload_path(): string
{
    $base = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
    if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
        throw new RuntimeException('Каталог uploads недоступен');
    }
    return $base;
}

function normalize_priority_value(?string $priority): string
{
    $priority = $priority ? strtolower(trim($priority)) : 'normal';
    return in_array($priority, ['normal','urgent','critical'], true) ? $priority : 'normal';
}

switch ($action) {
    case 'pdf':
        ensure_method('GET');
        $user = require_auth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный идентификатор');
        }
        $stmt = $pdo->prepare('SELECT author_id FROM requests WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $ownerId = $stmt->fetchColumn();
        if (!$ownerId) {
            fail('Заявка не найдена', 404);
        }
        if ($user['role'] !== 'admin' && (int)$ownerId !== (int)$user['id']) {
            fail('Нет доступа', 403);
        }
        try {
            $pdfPath = generate_request_pdf_file($pdo, $id, true);
        } catch (Throwable $e) {
            fail('Ошибка генерации PDF: ' . $e->getMessage(), 500);
        }
        log_action($pdo, 'download_pdf', 'requests', $id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="request_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        exit;

    case 'attachment':
        ensure_method('GET');
        $user = require_auth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный файл');
        }
        $stmt = $pdo->prepare('SELECT rf.*, r.author_id FROM request_files rf JOIN requests r ON r.id = rf.request_id WHERE rf.id = :id');
        $stmt->execute([':id' => $id]);
        $file = $stmt->fetch();
        if (!$file) {
            fail('Файл не найден', 404);
        }
        if ($user['role'] !== 'admin' && $file['author_id'] != $user['id']) {
            fail('Нет доступа', 403);
        }
        $base = ensure_upload_path();
        $path = $base . '/' . $file['request_id'] . '/' . $file['stored_name'];
        if (!is_file($path)) {
            fail('Файл отсутствует на диске', 404);
        }
        log_action($pdo, 'DOWNLOAD_ATTACHMENT', 'request_files', $id, ['request_id' => (int)$file['request_id']]);
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;

    case 'export_csv':
        ensure_method('GET');
        require_admin();
        $filters = [];
        $params = [];
        if (!empty($_GET['status'])) {
            $filters[] = 'r.status = :status';
            $params[':status'] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $filters[] = 'r.priority = :priority';
            $params[':priority'] = normalize_priority_value($_GET['priority']);
        }
        if (!empty($_GET['from'])) {
            $filters[] = 'DATE(r.created_at) >= :from';
            $params[':from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $filters[] = 'DATE(r.created_at) <= :to';
            $params[':to'] = $_GET['to'];
        }
        if (!empty($_GET['fio'])) {
            $filters[] = 'u.fio LIKE :fio';
            $params[':fio'] = '%' . $_GET['fio'] . '%';
        }
        if (!empty($_GET['department'])) {
            $filters[] = 'u.department LIKE :department';
            $params[':department'] = '%' . $_GET['department'] . '%';
        }
        $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
        $stmt = $pdo->prepare("SELECT r.id, r.created_at, r.priority, r.deadline_date, u.fio, u.department, r.status, r.justification,
            (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count,
            (SELECT COUNT(*) FROM request_files rf WHERE rf.request_id = r.id) AS attachments_count
            FROM requests r JOIN users u ON u.id = r.author_id {$where} ORDER BY r.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        log_action($pdo, 'EXPORT', 'requests', null, ['format' => 'csv']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="requests.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['№', 'Дата', 'Приоритет', 'Дедлайн', 'ФИО', 'Подразделение', 'Статус', 'Позиций', 'Файлов', 'Обоснование']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                translate_priority($row['priority']),
                $row['deadline_date'],
                $row['fio'],
                $row['department'],
                $row['status'],
                $row['items_count'],
                $row['attachments_count'],
                $row['justification']
            ]);
        }
        fclose($output);
        exit;

    case 'export_xlsx':
        ensure_method('GET');
        require_admin();
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            fail('Библиотека PHPSpreadsheet не установлена', 500);
        }
        $filters = [];
        $params = [];
        if (!empty($_GET['status'])) {
            $filters[] = 'r.status = :status';
            $params[':status'] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $filters[] = 'r.priority = :priority';
            $params[':priority'] = normalize_priority_value($_GET['priority']);
        }
        if (!empty($_GET['from'])) {
            $filters[] = 'DATE(r.created_at) >= :from';
            $params[':from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $filters[] = 'DATE(r.created_at) <= :to';
            $params[':to'] = $_GET['to'];
        }
        if (!empty($_GET['fio'])) {
            $filters[] = 'u.fio LIKE :fio';
            $params[':fio'] = '%' . $_GET['fio'] . '%';
        }
        if (!empty($_GET['department'])) {
            $filters[] = 'u.department LIKE :department';
            $params[':department'] = '%' . $_GET['department'] . '%';
        }
        $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
        $stmt = $pdo->prepare("SELECT r.id, r.created_at, r.priority, r.deadline_date, u.fio, u.department, r.status, r.justification,
            (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count,
            (SELECT COUNT(*) FROM request_files rf WHERE rf.request_id = r.id) AS attachments_count
            FROM requests r JOIN users u ON u.id = r.author_id {$where} ORDER BY r.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['№', 'Дата', 'Приоритет', 'Дедлайн', 'ФИО', 'Подразделение', 'Статус', 'Позиций', 'Файлов', 'Обоснование'], null, 'A1');
        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['id'],
                $row['created_at'],
                translate_priority($row['priority']),
                $row['deadline_date'],
                $row['fio'],
                $row['department'],
                $row['status'],
                $row['items_count'],
                $row['attachments_count'],
                $row['justification']
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        log_action($pdo, 'EXPORT', 'requests', null, ['format' => 'xlsx']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="requests.xlsx"');
        $writer->save('php://output');
        exit;

    default:
        fail('Неизвестное действие', 404);
}
