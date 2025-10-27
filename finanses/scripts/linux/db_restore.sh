#!/usr/bin/env bash
set -euo pipefail
DB_NAME=${1:-finanses}
FILE=${2:?Укажите путь к SQL дампу}
mysql -u root -p "$DB_NAME" < "$FILE"
echo "Восстановление завершено"
