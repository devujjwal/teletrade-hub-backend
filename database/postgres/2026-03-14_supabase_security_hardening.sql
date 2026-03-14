-- Supabase Security Hardening (2026-03-14)
-- 1) Fix Advisor finding for SECURITY DEFINER behavior on product_list_view.
-- 2) Remove broad grants from anon/authenticated on public schema relations.
-- 3) Remove schema usage grants from anon/authenticated.
-- 4) Apply least-privilege controls on storage schema/buckets.
-- 5) Apply minimal storage.objects RLS policy for public product images.

BEGIN;

-- Fix Advisor warning: make view use invoker permissions.
ALTER VIEW public.product_list_view SET (security_invoker = true);

-- Revoke all object privileges from public client roles.
DO $$
DECLARE
    obj RECORD;
BEGIN
    FOR obj IN
        SELECT n.nspname AS schema_name, c.relname AS rel_name
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND c.relkind IN ('r', 'v', 'm', 'f', 'p')
    LOOP
        EXECUTE format('REVOKE ALL ON %I.%I FROM anon, authenticated;', obj.schema_name, obj.rel_name);
    END LOOP;
END
$$;

-- Revoke schema-level access for anon/authenticated.
REVOKE ALL ON SCHEMA public FROM anon, authenticated;

-- Storage hardening:
-- Keep direct table access restricted to service_role only.
DO $$
DECLARE
    s_obj RECORD;
BEGIN
    FOR s_obj IN
        SELECT c.relname AS rel_name
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'storage'
          AND c.relkind IN ('r', 'v', 'm', 'f', 'p')
    LOOP
        EXECUTE format('REVOKE ALL ON storage.%I FROM anon, authenticated;', s_obj.rel_name);
    END LOOP;
END
$$;

-- Ensure registration documents are private.
UPDATE storage.buckets
SET public = false
WHERE id = 'registration-documents';

-- Product images can stay public for storefront rendering.
UPDATE storage.buckets
SET public = true
WHERE id = 'product-images';

-- Apply bucket-level file validation controls.
UPDATE storage.buckets
SET file_size_limit = 10485760, -- 10 MB
    allowed_mime_types = ARRAY['application/pdf', 'image/jpeg', 'image/png']
WHERE id = 'registration-documents';

UPDATE storage.buckets
SET file_size_limit = 5242880, -- 5 MB
    allowed_mime_types = ARRAY['image/jpeg', 'image/png', 'image/webp']
WHERE id = 'product-images';

-- Keep RLS on storage.objects and allow read-only access only for product images.
-- Note: On managed Supabase projects, table ownership may prevent policy DDL here.
DO $$
BEGIN
    ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;
    DROP POLICY IF EXISTS "Public read product images" ON storage.objects;
    CREATE POLICY "Public read product images"
    ON storage.objects
    FOR SELECT
    TO anon, authenticated
    USING (bucket_id = 'product-images');
EXCEPTION
    WHEN insufficient_privilege THEN
        RAISE WARNING 'Skipping storage.objects policy DDL (insufficient privilege on managed storage schema).';
END
$$;

COMMIT;

-- Verification queries (run separately if needed):
-- SELECT n.nspname AS schema, c.relname AS view_name, c.reloptions
-- FROM pg_class c
-- JOIN pg_namespace n ON n.oid = c.relnamespace
-- WHERE c.relkind='v' AND n.nspname='public' AND c.relname='product_list_view';
--
-- SELECT table_schema, table_name, privilege_type, grantee
-- FROM information_schema.role_table_grants
-- WHERE table_schema='public' AND grantee IN ('anon','authenticated')
-- ORDER BY table_name, grantee, privilege_type;
--
-- SELECT table_name, grantee, privilege_type
-- FROM information_schema.role_table_grants
-- WHERE table_schema='storage' AND grantee IN ('anon','authenticated','service_role')
-- ORDER BY table_name, grantee, privilege_type;
--
-- SELECT id, name, public, file_size_limit, allowed_mime_types
-- FROM storage.buckets
-- WHERE id IN ('registration-documents', 'product-images')
-- ORDER BY id;
