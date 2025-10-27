<?php
function render_request_pdf(TCPDF $pdf, array $context): void
{
    $request = $context['request'];
    $items = $context['items'];
    $attachments = $context['attachments'] ?? [];

    $pdf->SetCreator('Finanses');
    $pdf->SetAuthor($request['author_fio']);
    $pdf->SetTitle('Служебная записка #' . $request['id']);
    $pdf->SetMargins(18, 20, 18);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 11);

    $orgName = env('PDF_ORG_NAME', 'Муниципальное бюджетное учреждение «Комбинат благоустройства»');
    $city = env('PDF_CITY', 'г. Серпухов');
    $signTitle = env('PDF_SIGNATORY_TITLE', $request['author_position'] ?: 'Ответственный');
    $signName = env('PDF_SIGNATORY_NAME', $request['author_fio']);
    $recipientTitle = env('PDF_RECIPIENT_TITLE', 'Начальнику отдела закупок');
    $recipientName = env('PDF_RECIPIENT_NAME', '');

    $header = "<table width='100%' cellpadding='0' cellspacing='0'>
        <tr>
            <td width='55%' style='font-size:10pt; line-height:1.5;'>" . nl2br(htmlspecialchars($orgName)) . "</td>
            <td width='45%' style='text-align:right; font-size:10pt; line-height:1.5;'>" . htmlspecialchars($recipientTitle);
    if ($recipientName) {
        $header .= '<br>' . htmlspecialchars($recipientName);
    }
    if (!empty($request['author_position'])) {
        $header .= '<br>' . htmlspecialchars($request['author_position']);
    }
    $header .= '<br>' . htmlspecialchars($request['author_fio']);
    $header .= "</td>
        </tr>
    </table>";
    $pdf->writeHTML($header, true, false, false, false, '');

    $pdf->Ln(6);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 12, 'СЛУЖЕБНАЯ ЗАПИСКА', 0, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', '', 11);
    $metaTable = "<table width='100%' cellpadding='4'>";
    $metaTable .= "<tr>";
    $metaTable .= "<td width='60%'><strong>От:</strong> " . htmlspecialchars($request['author_fio']) . '</td>'; 
    $metaTable .= "<td width='40%' align='right'><strong>Дата:</strong> " . date('d.m.Y', strtotime($request['created_at'])) . '</td>';
    $metaTable .= "</tr>";
    $metaTable .= "<tr><td><strong>Подразделение:</strong> " . htmlspecialchars($request['author_department'] ?? '—') . '</td>';
    $metaTable .= "<td align='right'><strong>Приоритет:</strong> " . translate_priority($request['priority']) . '</td></tr>';
    if (!empty($request['deadline_date'])) {
        $metaTable .= "<tr><td colspan='2'><strong>Желаемый срок закупки:</strong> " . date('d.m.Y', strtotime($request['deadline_date'])) . '</td></tr>';
    }
    $metaTable .= '</table>';
    $pdf->writeHTML($metaTable, true, false, false, false, '');

    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->MultiCell(0, 7, 'Обоснование закупки:', 0, 'L', false, 1);
    $justificationText = preg_replace("/(\r\n|\r)/", "\n", (string)$request['justification']);
    $pdf->SetFont('dejavusans', '', 10.5);
    $pdf->MultiCell(0, 6.5, $justificationText, 0, 'L', false, 1);

    $pdf->Ln(4);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 7, 'Позиции для закупки', 0, 'L', false, 1);

    $table = "<table border='1' cellpadding='6'>
        <thead style='font-weight:bold; background-color:#f8fafc;'>
            <tr>
                <th width='30' align='center'>№</th>
                <th width='110'>Категория</th>
                <th width='190'>Наименование</th>
                <th width='70'>Ед. изм.</th>
                <th width='65' align='right'>Кол-во</th>
                <th width='105'>Комментарий</th>
            </tr>
        </thead><tbody>";
    foreach ($items as $index => $item) {
        $table .= '<tr>';
        $table .= "<td align='center'>" . ($index + 1) . '</td>';
        $table .= "<td>" . htmlspecialchars($item['category']) . '</td>';
        $table .= "<td>" . htmlspecialchars($item['item_name']) . '</td>';
        $table .= "<td>" . htmlspecialchars($item['unit']) . '</td>';
        $table .= "<td align='right'>" . rtrim(rtrim(number_format((float)$item['qty'], 2, ',', ' '), '0'), ',') . '</td>';
        $table .= "<td>" . htmlspecialchars($item['note'] ?? '') . '</td>';
        $table .= '</tr>';
    }
    $table .= '</tbody></table>';
    $pdf->writeHTML($table, true, false, false, false, '');

    if ($attachments) {
        $pdf->Ln(4);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->MultiCell(0, 7, 'Прикреплённые документы', 0, 'L', false, 1);
        $pdf->SetFont('dejavusans', '', 10.5);
        foreach ($attachments as $file) {
            $pdf->MultiCell(0, 6, '• ' . htmlspecialchars($file['original_name']) . ' (' . format_size($file['size']) . ')', 0, 'L', false, 1);
        }
    }

    $pdf->Ln(12);
    $pdf->SetFont('dejavusans', '', 11);
    $signature = "<table width='100%' cellpadding='2'>
        <tr>
            <td width='55%'>" . htmlspecialchars($signTitle) . "</td>
            <td width='45%' align='right'>" . htmlspecialchars($city) . ', ' . date('d.m.Y', strtotime($request['created_at'])) . "</td>
        </tr>
        <tr>
            <td width='55%' style='padding-top:12px;'>Подпись _______________________</td>
            <td width='45%' style='padding-top:12px;' align='right'>" . htmlspecialchars($signName) . "</td>
        </tr>
    </table>";
    $pdf->writeHTML($signature, true, false, false, false, '');
}

function translate_priority(string $priority): string
{
    return [
        'normal' => 'Обычный',
        'urgent' => 'Срочный',
        'critical' => 'Критический'
    ][$priority] ?? $priority;
}

function format_size(int $bytes): string
{
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $index = 0;
    $value = (float)$bytes;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return number_format($value, $index === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$index];
}
