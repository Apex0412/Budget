<?php

namespace Finanses;

use Mpdf\Mpdf;
use PDO;
use Finanses\Helpers;

class PdfGenerator
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function generateForRequest(int $requestId, int $userId): string
    {
        $stmt = $this->db->prepare('SELECT r.*, u.fio, u.position, u.department FROM requests r JOIN users u ON u.id = r.author_id WHERE r.id = :id');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            throw new \RuntimeException('Request not found');
        }

        $itemsStmt = $this->db->prepare('SELECT ri.*, c.name AS category_name, m.name AS material_name, u.name AS unit_name
            FROM request_items ri
            LEFT JOIN categories c ON c.id = ri.category_id
            LEFT JOIN materials m ON m.id = ri.material_id
            LEFT JOIN units u ON u.id = ri.unit_id
            WHERE ri.request_id = :id');
        $itemsStmt->execute(['id' => $requestId]);
        $items = $itemsStmt->fetchAll();

        $serviceObjects = json_decode($request['service_objects'] ?? '[]', true) ?: [];

        $pdfDir = Config::getNested('paths.pdf', 'storage/pdf');
        $dir = Helpers::projectPath(trim($pdfDir, "\\/"));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filePath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'request_' . $requestId . '.pdf';

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->SetTitle('Обоснование к закупке №' . $requestId);
        $mpdf->SetAuthor($request['fio']);
        $mpdf->SetMargins(15, 15, 20, 20);
        $mpdf->WriteHTML($this->renderTemplate($request, $items, $serviceObjects));
        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

        $this->db->prepare('UPDATE requests SET pdf_generated = 1 WHERE id = :id')->execute(['id' => $requestId]);
        $this->db->prepare('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (:user_id, :action, :entity, :entity_id, :meta, :ip)')
            ->execute([
                'user_id' => $userId,
                'action' => 'generate_pdf',
                'entity' => 'requests',
                'entity_id' => $requestId,
                'meta' => json_encode(['file' => Helpers::relativeProjectPath($filePath)], JSON_UNESCAPED_UNICODE),
                'ip' => Helpers::getClientIp(),
            ]);

        return $filePath;
    }

    private function renderTemplate(array $request, array $items, array $serviceObjects): string
    {
        $config = Config::get('pdf');
        $priorityMap = [
            'normal' => 'Обычный',
            'urgent' => 'Срочный',
            'critical' => 'Критический'
        ];
        $priorityLabel = $priorityMap[$request['priority']] ?? $request['priority'];
        $deadline = $request['deadline_date'] ? date('d.m.Y', strtotime($request['deadline_date'])) : '—';
        $created = $request['created_at'] ? date('d.m.Y', strtotime($request['created_at'])) : date('d.m.Y');

        ob_start();
        ?>
        <style>
            body { font-family: "DejaVu Sans", sans-serif; font-size: 12pt; }
            .header { text-align: right; line-height: 1.4; }
            .title { text-align: center; font-weight: bold; font-size: 14pt; margin: 20px 0; text-transform: uppercase; }
            .section-title { font-weight: bold; margin-top: 16px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #555; padding: 6px; }
            th { background-color: #f0f0f0; }
            .signature { margin-top: 40px; }
            .signature-line { display: inline-block; min-width: 200px; border-bottom: 1px solid #333; }
            .meta { margin-top: 10px; }
        </style>
        <div class="header">
            <div><?= htmlspecialchars($config['org_name'] ?? '') ?></div>
            <div><?= htmlspecialchars($config['director_position'] ?? '') ?> <?= htmlspecialchars($config['director_name'] ?? '') ?></div>
            <div>От <?= htmlspecialchars($request['fio']) ?>, <?= htmlspecialchars($request['position'] ?? '') ?></div>
            <div>Подразделение: <?= htmlspecialchars($request['department'] ?? '—') ?></div>
            <div>Дата: <?= $created ?></div>
        </div>

        <div class="title">ОБОСНОВАНИЕ К ЗАКУПКЕ <?= strtoupper(htmlspecialchars($this->resolveCategoryTitle($items))) ?><br>на <?= htmlspecialchars($deadline) ?></div>

        <div class="meta"><strong>Основание:</strong> <?= htmlspecialchars($request['basis'] ?? '') ?></div>
        <div class="meta"><strong>Приоритет:</strong> <?= htmlspecialchars($priorityLabel) ?></div>

        <?php if ($serviceObjects): ?>
            <div class="section-title">Перечень обслуживаемых объектов</div>
            <ol>
                <?php foreach ($serviceObjects as $object): ?>
                    <li><?= htmlspecialchars($object) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="section-title">Таблица 1. Назначение инвентаря</div>
        <table>
            <thead>
                <tr>
                    <th>№</th>
                    <th>Категория</th>
                    <th>Наименование</th>
                    <th>Назначение</th>
                    <th>Особенности</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($item['material_name'] ?? $item['assignment'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['assignment'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-title">Таблица 2. Остатки и потребности</div>
        <table>
            <thead>
                <tr>
                    <th>№</th>
                    <th>Наименование</th>
                    <th>Остаток</th>
                    <th>Потребность</th>
                    <th>К закупке</th>
                    <th>Ед. изм.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['material_name'] ?? $item['assignment'] ?? '') ?></td>
                        <td><?= htmlspecialchars((string)($item['current_stock'] ?? 0)) ?></td>
                        <td><?= htmlspecialchars((string)($item['required_qty'] ?? 0)) ?></td>
                        <td><?= htmlspecialchars((string)($item['purchase_qty'] ?? $item['qty'])) ?></td>
                        <td><?= htmlspecialchars($item['unit_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-title">Вывод</div>
        <p><?= nl2br(htmlspecialchars($request['justification'])) ?></p>

        <div class="signature">
            <div><?= htmlspecialchars($config['signature_title'] ?? 'Ответственный') ?></div>
            <div><span class="signature-line"></span> <?= htmlspecialchars($request['fio']) ?></div>
            <div>«___» ____________ <?= date('Y') ?> г.</div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function resolveCategoryTitle(array $items): string
    {
        $categories = array_unique(array_filter(array_map(fn($item) => $item['category_name'] ?? '', $items)));
        if (!$categories) {
            return 'ЗАКУПКИ';
        }
        if (count($categories) === 1) {
            return $categories[0];
        }
        return 'РАЗНЫЕ КАТЕГОРИИ';
    }
}
