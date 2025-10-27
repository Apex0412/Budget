<?php

declare(strict_types=1);

use Finanses\Database;

return new class {
    public function run(): void
    {
        $pdo = Database::connection();
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (fio, position, department, login, password_hash, role, must_change_password) VALUES (:fio,:position,:department,:login,:password_hash,:role,:must_change_password)')
            ->execute([
                'fio' => 'Администратор Системы',
                'position' => 'Системный администратор',
                'department' => 'ИТ-отдел',
                'login' => 'admin',
                'password_hash' => $password,
                'role' => 'admin',
                'must_change_password' => 1,
            ]);

        $categories = ['Спецодежда','Инструмент','Материалы','ДИП'];
        foreach ($categories as $category) {
            $pdo->prepare('INSERT INTO categories (name) VALUES (:name) ON DUPLICATE KEY UPDATE name = VALUES(name)')->execute(['name' => $category]);
        }

        $units = ['шт','компл','м','л','кг'];
        foreach ($units as $unit) {
            $pdo->prepare('INSERT INTO units (name) VALUES (:name) ON DUPLICATE KEY UPDATE name = VALUES(name)')->execute(['name' => $unit]);
        }

        $materials = [
            ['code' => 'M-001', 'name' => 'Костюм зимний утепленный', 'category' => 'Спецодежда', 'unit' => 'компл', 'description' => 'Зимняя спецодежда для рабочих'],
            ['code' => 'M-002', 'name' => 'Лопата снеговая', 'category' => 'Инструмент', 'unit' => 'шт', 'description' => 'Инструмент для уборки снега'],
            ['code' => 'M-003', 'name' => 'Соль техническая', 'category' => 'Материалы', 'unit' => 'кг', 'description' => 'Противогололедный реагент'],
            ['code' => 'M-004', 'name' => 'Песок речной', 'category' => 'Материалы', 'unit' => 'кг', 'description' => 'Песок для посыпки тротуаров'],
            ['code' => 'M-005', 'name' => 'Щетка дорожная', 'category' => 'Инструмент', 'unit' => 'шт', 'description' => 'Комплект щеток для уборочной техники'],
        ];

        foreach ($materials as $item) {
            $categoryId = $this->resolveId($pdo, 'categories', $item['category']);
            $unitId = $this->resolveId($pdo, 'units', $item['unit']);
            $stmt = $pdo->prepare('INSERT INTO materials (code, name, category_id, unit_id, description) VALUES (:code,:name,:category_id,:unit_id,:description)');
            $stmt->execute([
                'code' => $item['code'],
                'name' => $item['name'],
                'category_id' => $categoryId,
                'unit_id' => $unitId,
                'description' => $item['description'],
            ]);
        }
    }

    private function resolveId(\PDO $pdo, string $table, string $name): int
    {
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE name = :name");
        $stmt->execute(['name' => $name]);
        return (int) $stmt->fetchColumn();
    }
};
