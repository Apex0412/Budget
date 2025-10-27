<?php

namespace Finanses\Controllers;

use Finanses\Database;
use Finanses\Helpers;
use PDO;

class LogController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function list(array $query): void
    {
        $limit = min(200, max(10, (int)($query['limit'] ?? 50)));
        $stmt = $this->db->prepare('SELECT a.*, u.fio FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        Helpers::jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
    }
}
