<?php
function translate_priority(string $priority): string
{
    return [
        'normal' => 'Обычный',
        'urgent' => 'Срочный',
        'critical' => 'Критический'
    ][$priority] ?? $priority;
}

function format_decimal($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $number = (float)$value;
    if (abs($number - round($number)) < 0.0001) {
        return number_format($number, 0, ',', ' ');
    }
    return rtrim(rtrim(number_format($number, 2, ',', ' '), '0'), ',');
}

function uppercase_text(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function render_request_pdf(TCPDF $pdf, array $context): void
{
    $request = $context['request'];
    $items = $context['items'] ?? [];

    $orgName = env('PDF_ORG_NAME', 'Муниципальное бюджетное учреждение «Комбинат благоустройства»');
    $directorLine = env('PDF_DIRECTOR_LINE', 'Директору Кравченко К.И.');
    $senderLine = env('PDF_SENDER_LINE', 'От заместителя директора');
    $signatureTitle = env('PDF_SIGNATURE_TITLE', 'Заместитель директора');
    $city = env('PDF_CITY', 'г. Серпухов');

    $authorLine = trim(($request['author_fio'] ?? '') . ($request['author_position'] ? ', ' . $request['author_position'] : ''));
    $basis = trim((string)($request['basis'] ?? ''));
    if ($basis === '') {
        $basis = env('PDF_BASIS_TEXT', 'Основание: муниципальное задание ' . date('Y') . ' и "Правила благоустройства территории муниципального образования «Городской округ Серпухов Московской области»", утверждённые решением Совета депутатов от 24.12.2024 № 25/288.');
    }
    $serviceObjectsText = $request['service_objects'] ?? '';
    $periodLabel = $request['period_label'] ?? '';
    if ($periodLabel === '' && !empty($request['deadline_date'])) {
        $periodLabel = date('Y', strtotime($request['deadline_date']));
    }
    if ($periodLabel === '') {
        $periodLabel = date('Y', strtotime($request['created_at'] ?? 'now'));
    }

    $categories = [];
    foreach ($items as $item) {
        if (!empty($item['category']) && !in_array($item['category'], $categories, true)) {
            $categories[] = $item['category'];
        }
    }
    $categoryLabel = $categories ? implode(', ', $categories) : 'товаров';

    $serviceObjects = array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', (string)$serviceObjectsText)));

    $pdf->SetFont('dejavusans', '', 11);

    $headerHtml = "<table width='100%' cellpadding='2' cellspacing='0'>" .
        "<tr><td width='55%'></td><td width='45%' style='text-align:right; line-height:1.4;'>" .
        nl2br(htmlspecialchars($orgName)) . '<br>' .
        htmlspecialchars($directorLine) . '<br>' .
        htmlspecialchars($senderLine) . '<br>' .
        htmlspecialchars($authorLine ?: ($request['author_fio'] ?? '')) .
        "</td></tr></table>";
    $pdf->writeHTML($headerHtml, true, false, false, false, '');

    $pdf->Ln(6);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->Cell(0, 10, 'ОБОСНОВАНИЕ К ЗАКУПКЕ ' . uppercase_text($categoryLabel), 0, 1, 'C');
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->Cell(0, 8, 'на ' . $periodLabel, 0, 1, 'C');

    $pdf->Ln(4);
    $pdf->SetFont('dejavusans', '', 10.5);
    $metaTable = "<table width='100%' cellpadding='4' cellspacing='0'>" .
        "<tr><td width='60%'><strong>Дата составления:</strong> " . date('d.m.Y', strtotime($request['created_at'])) . "</td>" .
        "<td width='40%' style='text-align:right;'><strong>Приоритет:</strong> " . translate_priority($request['priority'] ?? 'normal') . "</td></tr>" .
        "<tr><td colspan='2'><strong>Основание:</strong> " . nl2br(htmlspecialchars($basis)) . "</td></tr>" .
        "</table>";
    $pdf->writeHTML($metaTable, true, false, false, false, '');

    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 7, '1. Перечень обслуживаемых объектов', 0, 'L', false, 1);
    $pdf->SetFont('dejavusans', '', 10.5);
    if ($serviceObjects) {
        $listHtml = '<ul style="margin-left:12px;">';
        foreach ($serviceObjects as $object) {
            $listHtml .= '<li>' . htmlspecialchars($object) . '</li>';
        }
        $listHtml .= '</ul>';
        $pdf->writeHTML($listHtml, true, false, false, false, '');
    } else {
        $pdf->MultiCell(0, 6, 'Сведения не представлены.', 0, 'L', false, 1);
    }

    $pdf->Ln(4);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 7, 'Таблица 1. Назначение инвентаря и особенности', 0, 'L', false, 1);
    $pdf->SetFont('dejavusans', '', 10.5);
    $table1 = "<table border='1' cellpadding='5' cellspacing='0'>" .
        "<thead style='font-weight:bold; background-color:#f8fafc;'><tr>" .
        "<th width='30' align='center'>№</th>" .
        "<th width='150'>Наименование</th>" .
        "<th width='160'>Назначение</th>" .
        "<th width='140'>Особенности</th>" .
        "</tr></thead><tbody>";
    foreach ($items as $index => $item) {
        $purpose = $item['purpose'] ?? '';
        if ($purpose === '' && !empty($item['note'])) {
            $purpose = $item['note'];
        }
        $features = $item['features'] ?? '';
        if ($features === '' && $purpose !== ($item['note'] ?? '')) {
            $features = $item['note'] ?? '';
        }
        $table1 .= '<tr>' .
            "<td align='center'>" . ($index + 1) . '</td>' .
            "<td>" . htmlspecialchars($item['item_name'] ?? '') . '</td>' .
            "<td>" . ($purpose ? htmlspecialchars($purpose) : '—') . '</td>' .
            "<td>" . ($features ? htmlspecialchars($features) : '—') . '</td>' .
            '</tr>';
    }
    if (!$items) {
        $table1 .= "<tr><td colspan='4' align='center'>Позиции не указаны</td></tr>";
    }
    $table1 .= '</tbody></table>';
    $pdf->writeHTML($table1, true, false, false, false, '');

    $pdf->Ln(4);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 7, 'Таблица 2. Сведения об остатках и потребности', 0, 'L', false, 1);
    $pdf->SetFont('dejavusans', '', 10.5);
    $table2 = "<table border='1' cellpadding='5' cellspacing='0'>" .
        "<thead style='font-weight:bold; background-color:#f1f5f9;'><tr>" .
        "<th width='130'>Наименование</th>" .
        "<th width='60' align='right'>Остаток</th>" .
        "<th width='60' align='right'>Потребность</th>" .
        "<th width='60' align='right'>К закупке</th>" .
        "<th width='50'>Ед. изм.</th>" .
        "</tr></thead><tbody>";
    foreach ($items as $item) {
        $table2 .= '<tr>' .
            "<td>" . htmlspecialchars($item['item_name'] ?? '') . '</td>' .
            "<td align='right'>" . format_decimal($item['stock_qty'] ?? null) . '</td>' .
            "<td align='right'>" . format_decimal($item['need_qty'] ?? null) . '</td>' .
            "<td align='right'>" . format_decimal($item['purchase_qty'] ?? $item['qty'] ?? null) . '</td>' .
            "<td>" . htmlspecialchars($item['unit'] ?? '') . '</td>' .
            '</tr>';
    }
    if (!$items) {
        $table2 .= "<tr><td colspan='5' align='center'>Данные отсутствуют</td></tr>";
    }
    $table2 .= '</tbody></table>';
    $pdf->writeHTML($table2, true, false, false, false, '');

    $pdf->Ln(4);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 7, 'Таблица 3. Распределение по подразделениям', 0, 'L', false, 1);
    $pdf->SetFont('dejavusans', '', 10.5);
    $distributionRows = [];
    foreach ($items as $item) {
        if (!empty($item['distribution']) && is_array($item['distribution'])) {
            foreach ($item['distribution'] as $row) {
                $distributionRows[] = [
                    'department' => $row['department'] ?? '',
                    'item' => $item['item_name'] ?? '',
                    'qty' => $row['qty'] ?? null,
                    'unit' => $item['unit'] ?? ''
                ];
            }
        }
    }
    if ($distributionRows) {
        $table3 = "<table border='1' cellpadding='5' cellspacing='0'>" .
            "<thead style='font-weight:bold; background-color:#f8fafc;'><tr>" .
            "<th width='130'>Подразделение</th>" .
            "<th width='150'>Материал</th>" .
            "<th width='60' align='right'>Количество</th>" .
            "<th width='50'>Ед. изм.</th>" .
            "</tr></thead><tbody>";
        foreach ($distributionRows as $row) {
            $table3 .= '<tr>' .
                "<td>" . htmlspecialchars($row['department']) . '</td>' .
                "<td>" . htmlspecialchars($row['item']) . '</td>' .
                "<td align='right'>" . format_decimal($row['qty']) . '</td>' .
                "<td>" . htmlspecialchars($row['unit']) . '</td>' .
                '</tr>';
        }
        $table3 .= '</tbody></table>';
        $pdf->writeHTML($table3, true, false, false, false, '');
    } else {
        $pdf->MultiCell(0, 6, 'Распределение по подразделениям не предоставлено.', 0, 'L', false, 1);
    }

    $pdf->Ln(4);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->MultiCell(0, 7, '2. Вывод', 0, 'L', false, 1);
    $pdf->SetFont('dejavusans', '', 10.5);
    $justificationText = preg_replace("/(\r\n|\r)/", "\n", (string)$request['justification']);
    $pdf->MultiCell(0, 6.5, $justificationText, 0, 'L', false, 1);

    $pdf->Ln(10);
    $pdf->SetFont('dejavusans', '', 11);
    $signatureTable = "<table width='100%' cellpadding='4' cellspacing='0'>" .
        "<tr><td width='60%'>" . htmlspecialchars($signatureTitle) . "</td>" .
        "<td width='40%' align='right'>" . htmlspecialchars($city) . ', ' . date('Y', strtotime($request['created_at'])) . " г." . "</td></tr>" .
        "<tr><td width='60%' style='padding-top:12px;'>Подпись _______________________</td>" .
        "<td width='40%' style='padding-top:12px;' align='right'>" . htmlspecialchars($request['author_fio'] ?? '') . "</td></tr>" .
        "</table>";
    $pdf->writeHTML($signatureTable, true, false, false, false, '');
}
