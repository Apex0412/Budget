#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer не установлен. Установите composer перед запуском." >&2
  exit 1
fi

cd "$PROJECT_ROOT"
composer install
php database/cli.php migrate
php database/cli.php seed
