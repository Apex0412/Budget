<?php

namespace Finanses\Controllers;

use Finanses\Database;
use Finanses\Helpers;
use PDO;

class MaterialController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function list(array $query): void
    {
        $limit = min(100, max(5, (int)($query['limit'] ?? 20)));
        $q = trim($query['q'] ?? '');
        $category = $query['category_id'] ?? null;

        $sql = 'SELECT m.*, c.name AS category_name, u.name AS unit_name FROM materials m
                LEFT JOIN categories c ON c.id = m.category_id
                LEFT JOIN units u ON u.id = m.unit_id
                WHERE m.is_active = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (m.name LIKE :q OR m.code LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($category) {
            $sql .= ' AND m.category_id = :category_id';
            $params['category_id'] = $category;
        }
        $sql .= ' ORDER BY m.name LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $materials = $stmt->fetchAll();

        if (!empty($query['meta'])) {
            $categories = $this->db->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
            $units = $this->db->query('SELECT id, name FROM units WHERE is_active = 1 ORDER BY name')->fetchAll();
            Helpers::jsonResponse(['ok' => true, 'data' => $materials, 'meta' => compact('categories', 'units')]);
        }

        Helpers::jsonResponse(['ok' => true, 'data' => $materials]);
    }

    public function store(array $payload): void
    {
        $this->validate($payload);
        $stmt = $this->db->prepare('INSERT INTO materials (code, name, category_id, unit_id, description) VALUES (:code, :name, :category_id, :unit_id, :description)');
        $stmt->execute([
            'code' => $payload['code'] ?? null,
            'name' => $payload['name'],
            'category_id' => $payload['category_id'],
            'unit_id' => $payload['unit_id'],
            'description' => $payload['description'] ?? null,
        ]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Материал создан']);
    }

    public function update(int $id, array $payload): void
    {
        $this->validate($payload);
        $stmt = $this->db->prepare('UPDATE materials SET code = :code, name = :name, category_id = :category_id, unit_id = :unit_id, description = :description, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'code' => $payload['code'] ?? null,
            'name' => $payload['name'],
            'category_id' => $payload['category_id'],
            'unit_id' => $payload['unit_id'],
            'description' => $payload['description'] ?? null,
            'is_active' => (int)($payload['is_active'] ?? 1),
            'id' => $id,
        ]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Материал обновлен']);
    }

    public function destroy(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM materials WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Helpers::jsonResponse(['ok' => true, 'message' => 'Материал удален']);
    }

    private function validate(array $payload): void
    {
        if (empty($payload['name'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Название обязательно'], 422);
        }
        if (empty($payload['category_id']) || empty($payload['unit_id'])) {
            Helpers::jsonResponse(['ok' => false, 'error' => 'Выберите категорию и единицу измерения'], 422);
        }
    }
}
