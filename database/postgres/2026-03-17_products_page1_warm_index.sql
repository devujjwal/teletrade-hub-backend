BEGIN;

CREATE INDEX IF NOT EXISTS idx_products_available_created_at
  ON products(is_available, created_at DESC, id DESC);

COMMIT;
