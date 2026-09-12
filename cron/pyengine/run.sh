#!/bin/bash
# PyEngine runner for Linux/ECS cron servers
# Usage: ./run.sh [job_key] [--email] [--fix]
#   ./run.sh written_premium --email
#   ./run.sh ageing
#   ./run.sh anomaly --fix

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_DIR="$(dirname "$SCRIPT_DIR")"

cd "$CRON_DIR"

PYTHON="${PYTHON_BIN:-python3}"

if [ -z "$1" ]; then
    $PYTHON -m pyengine.engine
else
    $PYTHON -m pyengine.engine --job "$@"
fi
