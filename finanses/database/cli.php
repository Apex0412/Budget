<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Finanses\Config;
use Finanses\Database;

Config::load(dirname(__DIR__));
Database::connection();

$command = $argv[1] ?? null;

switch ($command) {
    case 'migrate':
        runMigrations();
        break;
    case 'seed':
        runSeeders();
        break;
    case 'rollback':
        rollbackMigrations();
        break;
    default:
        echo "Usage: php database/cli.php [migrate|seed|rollback]\n";
}

function runMigrations(): void
{
    foreach (glob(__DIR__ . '/migrations/*.php') as $file) {
        $migration = require $file;
        echo 'Running migration ' . basename($file) . PHP_EOL;
        $migration->up();
    }
}

function rollbackMigrations(): void
{
    foreach (array_reverse(glob(__DIR__ . '/migrations/*.php')) as $file) {
        $migration = require $file;
        echo 'Rolling back ' . basename($file) . PHP_EOL;
        $migration->down();
    }
}

function runSeeders(): void
{
    foreach (glob(__DIR__ . '/seeders/*.php') as $file) {
        $seeder = require $file;
        echo 'Seeding ' . basename($file) . PHP_EOL;
        $seeder->run();
    }
}
