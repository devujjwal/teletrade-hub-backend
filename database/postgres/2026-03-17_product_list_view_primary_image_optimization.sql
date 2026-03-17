BEGIN;

CREATE INDEX IF NOT EXISTS idx_product_images_primary_lookup
  ON product_images(product_id, is_primary DESC, sort_order ASC, id ASC);

CREATE OR REPLACE VIEW product_list_view AS
SELECT
  p.id,
  p.product_source,
  p.vendor_article_id,
  p.sku,
  p.ean,
  p.name,
  p.name_en,
  p.name_de,
  p.name_sk,
  p.name_fr,
  p.name_es,
  p.name_ru,
  p.name_it,
  p.name_tr,
  p.name_ro,
  p.name_pl,
  p.description,
  p.description_en,
  p.description_de,
  p.description_sk,
  p.description_fr,
  p.description_es,
  p.description_ru,
  p.description_it,
  p.description_tr,
  p.description_ro,
  p.description_pl,
  p.slug,
  p.base_price,
  p.price,
  p.currency,
  p.stock_quantity,
  p.available_quantity,
  p.reserved_quantity,
  p.reorder_point,
  p.warehouse_location,
  p.is_available,
  p.is_featured,
  p.color,
  p.storage,
  p.ram,
  p.specifications,
  c.id AS category_id,
  c.name AS category_name,
  c.name_en AS category_name_en,
  c.name_de AS category_name_de,
  c.name_sk AS category_name_sk,
  c.name_fr AS category_name_fr,
  c.name_es AS category_name_es,
  c.name_ru AS category_name_ru,
  c.name_it AS category_name_it,
  c.name_tr AS category_name_tr,
  c.name_ro AS category_name_ro,
  c.name_pl AS category_name_pl,
  c.slug AS category_slug,
  b.id AS brand_id,
  b.name AS brand_name,
  b.slug AS brand_slug,
  w.id AS warranty_id,
  w.name AS warranty_name,
  w.duration_months AS warranty_months,
  pi.image_url AS primary_image,
  p.last_synced_at,
  p.created_at,
  p.updated_at
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN brands b ON p.brand_id = b.id
LEFT JOIN warranties w ON p.warranty_id = w.id
LEFT JOIN LATERAL (
  SELECT image_url
  FROM product_images i
  WHERE i.product_id = p.id
  ORDER BY i.is_primary DESC, i.sort_order ASC, i.id ASC
  LIMIT 1
) pi ON TRUE;

COMMIT;
