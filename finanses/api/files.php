<?php
require_once __DIR__ . '/bootstrap.php';


$pdo = get_pdo();
$action = $_GET['action'] ?? 'pdf';

function fetch_request_with_items(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT r.*, u.fio AS author_fio, u.position AS author_position, u.department AS author_department FROM requests r JOIN users u ON u.id = r.author_id WHERE r.id = :id');
    $stmt->execute([':id' => $id]);
    $request = $stmt->fetch();
    if (!$request) {
        fail('Заявка не найдена', 404);
    }
    $items = $pdo->prepare('SELECT * FROM request_items WHERE request_id = :id');
    $items->execute([':id' => $id]);
    return [$request, $items->fetchAll()];
}

switch ($action) {
    case 'pdf':
        ensure_method('GET');
        $user = require_auth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            fail('Некорректный идентификатор');
        }
        [$request, $items] = fetch_request_with_items($pdo, $id);
        if ($user['role'] !== 'admin' && $request['author_id'] != $user['id']) {
            fail('Нет доступа', 403);
        }
        $cacheFile = __DIR__ . '/../pdf/' . $id . '.pdf';
        if (!file_exists($cacheFile)) {
            if (!class_exists('TCPDF')) {
                fail('Библиотека TCPDF не установлена', 500);
            }
            $pdf = new TCPDF();
            $pdf->SetCreator('Finanses');
            $pdf->SetAuthor($request['author_fio']);
            $pdf->SetTitle('Служебная записка #' . $id);
            $pdf->SetMargins(15, 20, 15);
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 11);

            $header = sprintf("<div style='text-align:right;'>%s<br>%s<br>%s<br>%s</div>",
                htmlspecialchars($request['author_fio']),
                htmlspecialchars($request['author_position'] ?? ''),
                htmlspecialchars($request['author_department'] ?? ''),
                date('d.m.Y', strtotime($request['created_at']))
            );
            $pdf->writeHTML($header, true, false, false, false, '');

            $pdf->SetFont('dejavusans', 'B', 14);
            $pdf->Cell(0, 10, 'СЛУЖЕБНАЯ ЗАПИСКА', 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont('dejavusans', '', 11);
            $pdf->MultiCell(0, 6, 'Прошу включить в заявку на закупку следующие позиции:', 0, 'L', false, 1);

            $table = "<table border='1' cellpadding='4'>
                <thead><tr>
                    <th width='30' align='center'>№</th>
                    <th width='120'>Категория</th>
                    <th width='160'>Наименование</th>
                    <th width='60'>Ед. изм.</th>
                    <th width='60' align='right'>Кол-во</th>
                    <th width='100'>Комментарий</th>
                </tr></thead><tbody>";
            foreach ($items as $index => $item) {
                $table .= '<tr>';
                $table .= "<td align='center'>" . ($index + 1) . '</td>';
                $table .= "<td>" . htmlspecialchars($item['category']) . '</td>';
                $table .= "<td>" . htmlspecialchars($item['item_name']) . '</td>';
                $table .= "<td>" . htmlspecialchars($item['unit']) . '</td>';
                $table .= "<td align='right'>" . number_format((float)$item['qty'], 2, ',', ' ') . '</td>';
                $table .= "<td>" . htmlspecialchars($item['note'] ?? '') . '</td>';
                $table .= '</tr>';
            }
            $table .= '</tbody></table>';
            $pdf->writeHTML($table, true, false, false, false, '');

            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->MultiCell(0, 6, 'Обоснование:', 0, 'L', false, 1);
            $pdf->SetFont('dejavusans', '', 11);
            $pdf->MultiCell(0, 6, $request['justification'], 0, 'L', false, 1);

            $pdf->Ln(10);
            $footer = sprintf("<table width='100%%'><tr><td>Подпись ____________</td><td align='right'>%s</td></tr><tr><td colspan='2'>Расшифровка подписи: %s</td></tr></table>",
                htmlspecialchars(env('PDF_CITY', 'г. Серпухов')),
                htmlspecialchars($request['author_fio'])
            );
            $pdf->writeHTML($footer, true, false, false, false, '');

            $pdf->Output($cacheFile, 'F');
        }

        log_action($pdo, 'DOWNLOAD_PDF', 'requests', $id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="request_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($cacheFile));
        readfile($cacheFile);
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
        $stmt = $pdo->prepare("SELECT r.id, r.created_at, u.fio, u.department, r.status, r.justification,
            (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count FROM requests r JOIN users u ON u.id = r.author_id {$where} ORDER BY r.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        log_action($pdo, 'EXPORT', 'requests', null, ['format' => 'csv']);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="requests.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['№', 'Дата', 'ФИО', 'Подразделение', 'Статус', 'Позиций', 'Обоснование']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['fio'],
                $row['department'],
                $row['status'],
                $row['items_count'],
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
        $stmt = $pdo->prepare("SELECT r.id, r.created_at, u.fio, u.department, r.status, r.justification,
            (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS items_count FROM requests r JOIN users u ON u.id = r.author_id {$where} ORDER BY r.created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['№', 'Дата', 'ФИО', 'Подразделение', 'Статус', 'Позиций', 'Обоснование'], NULL, 'A1');
        foreach ($rows as $index => $row) {
            $sheet->setCellValueExplicit('A' . ($index + 2), $row['id']);
            $sheet->setCellValue('B' . ($index + 2), $row['created_at']);
            $sheet->setCellValue('C' . ($index + 2), $row['fio']);
            $sheet->setCellValue('D' . ($index + 2), $row['department']);
            $sheet->setCellValue('E' . ($index + 2), $row['status']);
            $sheet->setCellValue('F' . ($index + 2), $row['items_count']);
            $sheet->setCellValue('G' . ($index + 2), $row['justification']);
        }
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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
