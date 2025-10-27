#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/var/www/html"
cd "$PROJECT_ROOT"

export COMPOSER_ALLOW_SUPERUSER=1

# Ensure storage directories exist before anything else
mkdir -p storage/uploads storage/pdf storage/logs
chmod -R 775 storage || true
chown -R www-data:www-data storage || true

# Provide default environment for containerised usage
if [ ! -f .env ]; then
  echo "[entrypoint] .env not found, bootstrapping from .env.docker"
  cp .env.docker .env
fi

# Install dependencies if vendor directory is missing or composer.json newer than vendor/autoload.php
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ] || [ composer.json -nt vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies"
  composer install --no-interaction --prefer-dist --no-progress
else
  echo "[entrypoint] Composer dependencies already installed"
fi

# Wait for the database to be reachable before running migrations/seeders
php docker/wait-for-db.php

# Run migrations and seeders once (if tables are absent)
if php -r "require 'vendor/autoload.php'; Finanses\\Config::load(__DIR__); try { \$pdo = Finanses\\Database::connection(); \$stmt = \$pdo->query(\"SHOW TABLES LIKE 'users'\"); exit((\$stmt && \$stmt->rowCount() > 0) ? 0 : 1); } catch (Throwable \$e) { fwrite(STDERR, \$e->getMessage()); exit(1); }"; then
  echo "[entrypoint] Database already initialised"
else
  echo "[entrypoint] Running database migrations and seeders"
  php database/cli.php migrate
  php database/cli.php seed
fi

# Ensure log file exists and owned by web server user
if [ ! -f storage/logs/app.log ]; then
  touch storage/logs/app.log
fi
chown www-data:www-data storage/logs/app.log || true

exec apache2-foreground
