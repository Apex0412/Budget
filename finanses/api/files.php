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
        $shouldRegenerate = !file_exists($cacheFile);
        if (!$shouldRegenerate) {
            $updatedAt = $request['updated_at'] ?? $request['created_at'];
            if ($updatedAt && @filemtime($cacheFile) < strtotime($updatedAt)) {
                $shouldRegenerate = true;
            }
        }
        if ($shouldRegenerate) {
            if (!class_exists('TCPDF')) {
                fail('Библиотека TCPDF не установлена', 500);
            }
            $pdf = new TCPDF();
            $pdf->SetCreator('Finanses');
            $pdf->SetAuthor($request['author_fio']);
            $pdf->SetTitle('Служебная записка #' . $id);
            $pdf->SetMargins(20, 25, 20);
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 11);

            $orgName = env('PDF_ORG_NAME', 'Муниципальное бюджетное учреждение «Комбинат благоустройства»');
            $recipientTitle = env('PDF_RECIPIENT_TITLE', 'Начальнику отдела закупок');
            $recipientName = env('PDF_RECIPIENT_NAME', '');
            $authorHeader = env('PDF_AUTHOR_TITLE', $request['author_position'] ?: 'Отправитель');
            $authorSignatureTitle = env('PDF_SIGNATORY_TITLE', $request['author_position'] ?: 'Руководитель');
            $authorSignatureName = env('PDF_SIGNATORY_NAME', $request['author_fio']);
            $city = env('PDF_CITY', 'г. Серпухов');

            $headerTable = "<table width='100%' cellpadding='0' cellspacing='0'>
                <tr>
                    <td width='55%' style='text-align:left; font-size:10pt; line-height:1.4;'>" .
                        nl2br(htmlspecialchars($orgName)) . "</td>
                    <td width='45%' style='text-align:right; font-size:10pt; line-height:1.6;'>" .
                        htmlspecialchars($recipientTitle) . (strlen($recipientName) ? '<br>' . htmlspecialchars($recipientName) : '') .
                        '<br>' . htmlspecialchars($authorHeader) . '<br>' . htmlspecialchars($authorSignatureName) .
                    "</td>
                </tr>
            </table>";
            $pdf->writeHTML($headerTable, true, false, false, false, '');

            $pdf->Ln(8);
            $pdf->SetFont('dejavusans', 'B', 15);
            $pdf->Cell(0, 12, 'ЗАЯВКА', 0, 1, 'C');

            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', '', 11);
            $leadText = env('PDF_BODY_INTRO', 'В рамках исполнения планов закупок прошу включить в заявку следующие позиции:');
            $pdf->MultiCell(0, 7, $leadText, 0, 'L', false, 1);

            $table = "<table border='1' cellpadding='6'>
                <thead style='font-weight:bold; background-color:#f8fafc;'>
                    <tr>
                        <th width='35' align='center'>№</th>
                        <th width='120'>Категория</th>
                        <th width='190'>Наименование</th>
                        <th width='70'>Ед. изм.</th>
                        <th width='70' align='right'>Кол-во</th>
                        <th width='110'>Комментарий</th>
                    </tr>
                </thead><tbody>";
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

            $pdf->Ln(4);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->MultiCell(0, 7, 'Обоснование:', 0, 'L', false, 1);
            $pdf->SetFont('dejavusans', '', 11);
            $justificationText = preg_replace("/(\r\n|\r)/", "\n", (string)$request['justification']);
            $pdf->MultiCell(0, 7, $justificationText, 0, 'L', false, 1);

            $pdf->Ln(12);
            $signatureTable = "<table width='100%' cellpadding='2'>
                <tr>
                    <td width='55%'>" . htmlspecialchars($authorSignatureTitle) . "</td>
                    <td width='45%' align='right'>" . htmlspecialchars($city) . ', ' . date('d.m.Y', strtotime($request['created_at'])) . "</td>
                </tr>
                <tr>
                    <td width='55%' style='padding-top:12px;'>Подпись ____________</td>
                    <td width='45%' style='padding-top:12px;' align='right'>" . htmlspecialchars($authorSignatureName) . "</td>
                </tr>
            </table>";
            $pdf->writeHTML($signatureTable, true, false, false, false, '');

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
