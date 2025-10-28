#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUTPUT=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --output)
      OUTPUT="$2"
      shift 2
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
  echo "Docker не найден." >&2
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

backup_dir="$PROJECT_ROOT/backups"
mkdir -p "$backup_dir"

if [[ -z "$OUTPUT" ]]; then
  OUTPUT="$backup_dir/finanses_$(date +%Y%m%d_%H%M%S).sql"
fi

echo "[FINANSES] Создаём дамп: $OUTPUT"
$compose_cmd exec -T db mysqldump -u root -proot finanses > "$OUTPUT"

echo "[FINANSES] Дамп готов."
