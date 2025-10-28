#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --file)
      FILE="$2"
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
if [[ -z "$FILE" ]]; then
  if [[ ! -d "$backup_dir" ]]; then
    echo "Каталог backups не найден." >&2
    exit 1
  fi
  mapfile -t files < <(ls -1t "$backup_dir"/*.sql 2>/dev/null || true)
  if [[ ${#files[@]} -eq 0 ]]; then
    echo "Нет файлов для восстановления." >&2
    exit 1
  fi
  echo "Выберите файл для восстановления:"
  select choice in "${files[@]}"; do
    if [[ -n "$choice" ]]; then
      FILE="$choice"
      break
    else
      echo "Неверный выбор."
    fi
  done
fi

if [[ ! -f "$FILE" ]]; then
  echo "Файл $FILE не найден." >&2
  exit 1
fi

echo "[FINANSES] Восстановление из $FILE"
$compose_cmd exec -T db mysql -u root -proot finanses < "$FILE"

echo "[FINANSES] Восстановление завершено."
