#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NO_CACHE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-cache)
      NO_CACHE="--no-cache"
      shift
      ;;
    --project)
      PROJECT_ROOT="$2"
      shift 2
      ;;
    *)
      echo "Неизвестный параметр: $1" >&2
      exit 1
      ;;
  esac
done

cd "$PROJECT_ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker не найден. Установите Docker и повторите попытку." >&2
  exit 1
fi

compose_cmd="docker compose"
if ! docker compose version >/dev/null 2>&1; then
  if command -v docker-compose >/dev/null 2>&1; then
    compose_cmd="docker-compose"
  else
    echo "Команда docker compose не найдена." >&2
    exit 1
  fi
fi

$compose_cmd down --remove-orphans >/dev/null 2>&1 || true

echo "[FINANSES] Сборка Docker-образов..."
$compose_cmd build $NO_CACHE

echo "[FINANSES] Запуск контейнеров..."
$compose_cmd up -d
$compose_cmd ps

wait_health() {
  local container="$1"
  local attempts=60
  local delay=5
  for ((i=1; i<=attempts; i++)); do
    status=$(docker inspect --format '{{.State.Health.Status}}' "$container" 2>/dev/null || true)
    if [[ "$status" == "healthy" ]]; then
      echo "[FINANSES] $container: healthy"
      return 0
    fi
    sleep "$delay"
  done
  return 1
}

wait_health "finanses-db" || echo "[FINANSES] Предупреждение: контейнер БД не стал healthy вовремя." >&2

echo "[FINANSES] Проверяем healthcheck приложения..."
health_url="http://localhost:8080/api/health.php?type=app"
app_ready=1
for i in {1..40}; do
  if curl -fsS "$health_url" >/dev/null 2>&1; then
    app_ready=0
    echo "[FINANSES] Healthcheck приложения успешен."
    break
  fi
  sleep 3
done

if [[ $app_ready -ne 0 ]]; then
  echo "[FINANSES] Предупреждение: приложение не ответило на healthcheck." >&2
fi

echo "[FINANSES] Устанавливаем зависимости и запускаем миграции..."
$compose_cmd exec -T app bash -lc 'composer install --no-interaction --prefer-dist --no-progress && php database/cli.php migrate && php database/cli.php seed'

echo "[FINANSES] Готово! Откройте http://localhost:8080 (логин admin / пароль admin123)."
