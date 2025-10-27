#!/usr/bin/env bash
set -euo pipefail
DB_NAME=${1:-finanses}
OUTPUT=${2:-finanses_$(date +%Y%m%d_%H%M).sql}
mysqldump -u root -p "$DB_NAME" > "$OUTPUT"
echo "Дамп сохранен в $OUTPUT"
