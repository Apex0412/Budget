<?php
declare(strict_types=1);

require_once __DIR__ . '/../pdf/template_request.php';

function ensure_pdf_directory(): string
{
    $dir = realpath(__DIR__) ?: __DIR__;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Недоступна директория pdf');
    }
    return $dir;
}

function fetch_request_payload(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT r.*, u.fio AS author_fio, u.position AS author_position, u.department AS author_department
        FROM requests r
        JOIN users u ON u.id = r.author_id
        WHERE r.id = :id');
    $stmt->execute([':id' => $id]);
    $request = $stmt->fetch();
    if (!$request) {
        throw new RuntimeException('Заявка не найдена');
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM request_items WHERE request_id = :id ORDER BY id');
    $itemsStmt->execute([':id' => $id]);
    $items = array_map(static function (array $row): array {
        if (!empty($row['distribution'])) {
            $decoded = json_decode($row['distribution'], true);
            $row['distribution'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['distribution'] = [];
        }
        return $row;
    }, $itemsStmt->fetchAll());

    $filesStmt = $pdo->prepare('SELECT id, original_name, stored_name, mime_type, size, created_at FROM request_files WHERE request_id = :id ORDER BY created_at');
    $filesStmt->execute([':id' => $id]);

    return [
        'request' => $request,
        'items' => $items,
        'attachments' => $filesStmt->fetchAll()
    ];
}

function generate_request_pdf_file(PDO $pdo, int $requestId, bool $force = true): string
{
    if (!class_exists('TCPDF')) {
        throw new RuntimeException('Библиотека TCPDF не установлена');
    }

    $payload = fetch_request_payload($pdo, $requestId);
    $pdfDir = ensure_pdf_directory();
    $pdfPath = $pdfDir . '/' . $requestId . '.pdf';

    if ($force || !is_file($pdfPath)) {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->setFontSubsetting(false);
        $pdf->SetMargins(18, 20, 18);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 11);

        render_request_pdf($pdf, $payload);
        $pdf->Output($pdfPath, 'F');

        $update = $pdo->prepare('UPDATE requests SET pdf_generated = 1 WHERE id = :id');
        $update->execute([':id' => $requestId]);
    }

    return $pdfPath;
}
