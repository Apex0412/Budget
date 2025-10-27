<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../pdf/template_request.php';

$pdo = get_pdo();
$action = $_GET['action'] ?? 'pdf';

function fetch_request_bundle(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT r.*, u.fio AS author_fio, u.position AS author_position, u.department AS author_department FROM requests r JOIN users u ON u.id = r.author_id WHERE r.id = :id');
    $stmt->execute([':id' => $id]);
    $request = $stmt->fetch();
    if (!$request) {
        fail('Заявка не найдена', 404);
    }
    $itemsStmt = $pdo->prepare('SELECT * FROM request_items WHERE request_id = :id');
    $itemsStmt->execute([':id' => $id]);
    $filesStmt = $pdo->prepare('SELECT id, original_name, stored_name, mime_type, size, created_at FROM request_files WHERE request_id = :id ORDER BY created_at');
    $filesStmt->execute([':id' => $id]);
    return [$request, $itemsStmt->fetchAll(), $filesStmt->fetchAll()];
}

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
        [$request, $items, $attachments] = fetch_request_bundle($pdo, $id);
        if ($user['role'] !== 'admin' && $request['author_id'] != $user['id']) {
            fail('Нет доступа', 403);
        }
        $cacheFile = __DIR__ . '/../pdf/' . $id . '.pdf';
        $shouldRegenerate = !file_exists($cacheFile);
        if (!$shouldRegenerate) {
            $updatedAt = $request['updated_at'] ?? $request['created_at'];
            if ($updatedAt && @filemtime($cacheFile) < strtotime($updatedAt)) {
                $shouldRegenerate = true;
            }
            if (!$shouldRegenerate) {
                $attachmentsHash = md5(json_encode(array_column($attachments, 'id')));
                $hashFile = $cacheFile . '.hash';
                $storedHash = is_file($hashFile) ? trim((string)file_get_contents($hashFile)) : '';
                if ($attachmentsHash !== $storedHash) {
                    $shouldRegenerate = true;
                }
            }
        }
        if ($shouldRegenerate) {
            if (!class_exists('TCPDF')) {
                fail('Библиотека TCPDF не установлена', 500);
            }
            $pdf = new TCPDF();
            render_request_pdf($pdf, [
                'request' => $request,
                'items' => $items,
                'attachments' => $attachments
            ]);
            $pdf->Output($cacheFile, 'F');
            file_put_contents($cacheFile . '.hash', md5(json_encode(array_column($attachments, 'id'))));
        }
        log_action($pdo, 'DOWNLOAD_PDF', 'requests', $id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="request_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($cacheFile));
        readfile($cacheFile);
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
