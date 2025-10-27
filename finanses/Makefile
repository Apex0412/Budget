PROJECT_ROOT := $(shell pwd)
PHP := php
COMPOSER := composer

.PHONY: install migrate seed rollback up down logs db-dump db-restore test lint

install:
$(COMPOSER) install

migrate:
$(PHP) database/cli.php migrate

seed:
$(PHP) database/cli.php seed

rollback:
$(PHP) database/cli.php rollback

up:
docker compose up -d
docker compose ps
docker exec -it $$(docker compose ps -q app) bash -lc "composer install && php database/cli.php migrate && php database/cli.php seed"
echo "Сервис доступен: http://localhost:8080"

down:
docker compose down

logs:
docker compose logs -f

db-dump:
docker exec -it $$(docker compose ps -q db) mysqldump -u root -proot finanses > dump.sql
echo "dump.sql сохранен"

db-restore:
docker exec -i $$(docker compose ps -q db) mysql -u root -proot finanses < dump.sql
echo "База восстановлена из dump.sql"

test:
$(PHP) -l public/api/auth.php

lint:
$(COMPOSER) validate --no-check-all
