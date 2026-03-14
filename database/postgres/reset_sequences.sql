DO $$
DECLARE
  r RECORD;
BEGIN
  FOR r IN
    SELECT c.table_name, c.column_name
    FROM information_schema.columns c
    JOIN information_schema.tables t
      ON t.table_schema = c.table_schema
     AND t.table_name = c.table_name
    WHERE c.table_schema = 'public'
      AND t.table_type = 'BASE TABLE'
      AND c.column_default LIKE 'nextval(%'
  LOOP
    EXECUTE format(
      'SELECT setval(pg_get_serial_sequence(''%I'', ''%I''), COALESCE(MAX(%I), 0) + 1, false) FROM %I',
      r.table_name,
      r.column_name,
      r.column_name,
      r.table_name
    );
  END LOOP;
END $$;
