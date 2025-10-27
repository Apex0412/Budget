PROJECT_ROOT := $(shell pwd)
PHP := php
COMPOSER := composer
COMPOSE := docker compose

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
	$(COMPOSE) up --build -d
	$(COMPOSE) ps

down:
	$(COMPOSE) down --remove-orphans

logs:
	$(COMPOSE) logs -f

db-dump:
	mkdir -p backups
	$(COMPOSE) exec -T db mysqldump -u root -proot finanses > backups/finanses_`date +%Y%m%d_%H%M%S`.sql

db-restore:
	@if [ -z "$(FILE)" ]; then echo "Use FILE=/path/to/dump.sql make db-restore"; exit 1; fi
	$(COMPOSE) exec -T db mysql -u root -proot finanses < $(FILE)

test:
	$(PHP) -l public/api/auth.php

lint:
	$(COMPOSER) validate --no-check-all
