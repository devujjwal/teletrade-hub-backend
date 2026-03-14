#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA_SQL="$SCRIPT_DIR/schema.sql"
SEQUENCES_SQL="$SCRIPT_DIR/reset_sequences.sql"
CONVERTER="$SCRIPT_DIR/convert_mysql_dump_to_pg_inserts.sh"

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <mysql_dump.sql> [--fresh]"
  exit 1
fi

MYSQL_DUMP="$1"
FRESH_MODE="${2:-}"

if [[ ! -f "$MYSQL_DUMP" ]]; then
  echo "MySQL dump not found: $MYSQL_DUMP"
  exit 1
fi

if [[ -n "${DB_HOST:-}" && -n "${DB_USER:-}" && -n "${DB_NAME:-}" ]]; then
  DB_PORT="${DB_PORT:-5432}"
  DB_PASSWORD="${DB_PASSWORD:-}"
  if [[ -n "$DB_PASSWORD" ]]; then
    export PGPASSWORD="$DB_PASSWORD"
  fi
  PSQL_TARGET="host=${DB_HOST} port=${DB_PORT} dbname=${DB_NAME} user=${DB_USER} sslmode=require"
elif [[ -n "${SUPABASE_DATABASE_URL:-}" ]]; then
  PSQL_TARGET="$SUPABASE_DATABASE_URL"
else
  echo "Set SUPABASE_DATABASE_URL or DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD in environment"
  exit 1
fi

INSERTS_SQL="$(mktemp /tmp/teletrade_pg_inserts_XXXX.sql)"

cleanup() {
  rm -f "$INSERTS_SQL"
}
trap cleanup EXIT

"$CONVERTER" "$MYSQL_DUMP" "$INSERTS_SQL"

if [[ "$FRESH_MODE" == "--fresh" ]]; then
  psql "$PSQL_TARGET" -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"
fi

psql "$PSQL_TARGET" -v ON_ERROR_STOP=1 -f "$SCHEMA_SQL"
psql "$PSQL_TARGET" -v ON_ERROR_STOP=1 -f "$INSERTS_SQL"
psql "$PSQL_TARGET" -v ON_ERROR_STOP=1 -f "$SEQUENCES_SQL"

echo "Supabase migration completed successfully."
