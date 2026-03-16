-- Manual invoice workflow, order pricing fields, and account approval status

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS approval_status VARCHAR(20) NOT NULL DEFAULT 'pending';

UPDATE users
SET approval_status = CASE
  WHEN is_active = 1 THEN 'approved'
  ELSE COALESCE(NULLIF(approval_status, ''), 'pending')
END
WHERE approval_status IS NULL OR approval_status = '' OR approval_status = 'pending';

ALTER TABLE users
  DROP CONSTRAINT IF EXISTS chk_users_approval_status;

ALTER TABLE users
  ADD CONSTRAINT chk_users_approval_status
  CHECK (approval_status IN ('pending', 'approved', 'rejected'));

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS final_order_price NUMERIC(10, 2);

CREATE TABLE IF NOT EXISTS order_invoices (
  id BIGSERIAL PRIMARY KEY,
  order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  invoice_url TEXT NOT NULL,
  uploaded_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_by_admin BIGINT REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_order_invoices_order_id ON order_invoices(order_id);

INSERT INTO storage.buckets (id, name, public)
VALUES ('order-invoices', 'order-invoices', false)
ON CONFLICT (id) DO NOTHING;
