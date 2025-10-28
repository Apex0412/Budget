<?php

declare(strict_types=1);

use Finanses\Database;

return new class {
    public function up(): void
    {
        $pdo = Database::connection();
        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        $queries = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($queries as $query) {
            if ($query === '' || str_starts_with($query, 'CREATE DATABASE')) {
                continue;
            }
            $pdo->exec($query);
        }
    }

    public function down(): void
    {
        $pdo = Database::connection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['audit_log','request_files','request_items','materials','units','categories','requests','users'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
};
