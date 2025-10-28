<?php

namespace Finanses\Controllers;

use Finanses\Database;
use Finanses\Helpers;
use PDO;

class StatisticsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function overview(): void
    {
        $status = $this->db->query('SELECT status, COUNT(*) as total FROM requests GROUP BY status')->fetchAll();
        $category = $this->db->query('SELECT c.name AS category, COUNT(*) AS total FROM request_items ri JOIN categories c ON c.id = ri.category_id GROUP BY c.name')->fetchAll();
        $departments = $this->db->query('SELECT u.department, COUNT(*) AS total FROM requests r JOIN users u ON u.id = r.author_id GROUP BY u.department')->fetchAll();
        $monthly = $this->db->query('SELECT DATE_FORMAT(created_at, "%Y-%m") AS month, COUNT(*) AS total FROM requests GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY month')->fetchAll();
        $submittedUsers = $this->db->query('SELECT DISTINCT author_id FROM requests')->fetchAll(PDO::FETCH_COLUMN);
        $inactiveUsers = [];
        if ($submittedUsers) {
            $placeholders = implode(',', array_fill(0, count($submittedUsers), '?'));
            $stmt = $this->db->prepare("SELECT id, fio FROM users WHERE role != 'admin' AND id NOT IN ($placeholders)");
            $stmt->execute($submittedUsers);
            $inactiveUsers = $stmt->fetchAll();
        } else {
            $inactiveUsers = $this->db->query("SELECT id, fio FROM users WHERE role != 'admin'")->fetchAll();
        }

        Helpers::jsonResponse(['ok' => true, 'data' => [
            'status' => $status,
            'categories' => $category,
            'departments' => $departments,
            'monthly' => $monthly,
            'inactive_users' => $inactiveUsers,
        ]]);
    }
}
