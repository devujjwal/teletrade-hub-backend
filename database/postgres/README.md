# Supabase/PostgreSQL Migration

This folder contains scripts to migrate the current MySQL dump into Supabase PostgreSQL.

## Files

- `schema.sql`: PostgreSQL schema compatible with the current backend models/controllers.
- `convert_mysql_dump_to_pg_inserts.sh`: Converts MySQL dump `INSERT` statements to PostgreSQL-safe inserts.
- `reset_sequences.sql`: Resets identity/serial sequences after import.
- `migrate_to_supabase.sh`: End-to-end migration runner.

## Prerequisites

- `psql` installed locally.
- Supabase Postgres credentials.

## Environment options

Use one of these:

1. `SUPABASE_DATABASE_URL` (recommended)
2. `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

## Run migration

```bash
cd teletrade-hub-backend
./database/postgres/migrate_to_supabase.sh /absolute/path/vsmjr110_api.sql --fresh
```

`--fresh` drops and recreates the `public` schema before import.

## Notes

- The converter imports only `INSERT` data from the MySQL dump.
- `product_list_view` is created as a PostgreSQL view from `schema.sql`.
- For passwords containing special characters in URL format, prefer the `DB_HOST`/`DB_USER`/`DB_PASSWORD` env approach.
