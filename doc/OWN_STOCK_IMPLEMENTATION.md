# Own Stock Implementation Guide

## Overview

The database schema has been updated to support both **vendor products** (from TRIEL) and **own products** (your inventory) in the same system.

## Database Changes

### Products Table

**New Fields:**
- `product_source` ENUM('vendor', 'own') - Distinguishes product source
- `reorder_point` INT - Minimum stock level for reordering (own products)
- `warehouse_location` VARCHAR(100) - Physical location (own products)

**Modified Fields:**
- `vendor_article_id` - Now **NULLABLE** (required only for vendor products)
- `base_price` - Now represents cost price (vendor price OR purchase cost)
- `last_synced_at` - NULL for own products (no vendor sync)

**Constraints:**
- `sku` - Still UNIQUE (all products)
- `vendor_article_id` - UNIQUE when NOT NULL (vendor products only)
- Multiple NULL values allowed (own products)

### Order Items Table

**New Fields:**
- `product_source` - Captures source at time of order (for historical accuracy)
- `vendor_article_id` - Now NULLABLE

### Reservations Table

- Only used for **vendor products**
- Own products don't require vendor API reservations
- Direct stock management for own products

## Product Source Types

### 1. Vendor Products (`product_source = 'vendor'`)

**Characteristics:**
- ✅ Has `vendor_article_id` (required)
- ✅ Synced from TRIEL API
- ✅ Requires vendor reservation API calls
- ✅ Stock managed via vendor sync
- ✅ `last_synced_at` updated on sync

**Example:**
```sql
INSERT INTO products (
  product_source, vendor_article_id, sku, name, 
  base_price, price, stock_quantity, available_quantity
) VALUES (
  'vendor', '1028-131500-10142_BR001', 'SKU-001', 
  'iPhone 16 Pro', 607.00, 698.05, 10, 10
);
```

### 2. Own Products (`product_source = 'own'`)

**Characteristics:**
- ✅ `vendor_article_id` is NULL
- ✅ NOT synced from vendor
- ✅ Direct stock management
- ✅ No vendor API calls
- ✅ `last_synced_at` is NULL
- ✅ Can set `reorder_point` and `warehouse_location`

**Example:**
```sql
INSERT INTO products (
  product_source, sku, name, 
  base_price, price, stock_quantity, available_quantity,
  reorder_point, warehouse_location
) VALUES (
  'own', 'OWN-001', 'Custom Product', 
  50.00, 75.00, 100, 100,
  20, 'Warehouse A - Shelf 3'
);
```

## Implementation Best Practices

### 1. Product Sync Service

**Update `ProductSyncService`:**
```php
// Only sync vendor products
private function syncProductsForLanguage($languageId) {
    // ... existing sync logic ...
    
    // Mark all synced products as vendor source
    $normalized[':product_source'] = 'vendor';
    
    // Skip products that are own inventory
    if ($existingProduct && $existingProduct['product_source'] === 'own') {
        continue; // Don't sync own products
    }
}
```

### 2. Reservation Service

**Update `ReservationService`:**
```php
public function reserveProduct($orderId, $productId, $vendorArticleId, $quantity) {
    $product = $this->productModel->getById($productId);
    
    // Only reserve vendor products via API
    if ($product['product_source'] === 'vendor') {
        // Call vendor API
        $vendorResponse = $this->vendorApi->reserveArticle(...);
        // ... existing reservation logic ...
    } else {
        // Own product - direct stock reservation
        $this->productModel->reserveStock($productId, $quantity);
        // No vendor API call needed
    }
}
```

### 3. Order Service

**Update `OrderService`:**
```php
public function createOrder(...) {
    // ... existing order creation ...
    
    foreach ($cartItems as $item) {
        $product = $this->productModel->getById($item['product_id']);
        
        $this->orderModel->addItem($orderId, [
            ':product_id' => $product['id'],
            ':product_source' => $product['product_source'], // Capture source
            ':vendor_article_id' => $product['vendor_article_id'], // Can be NULL
            // ... other fields ...
        ]);
    }
}
```

### 4. Stock Management

**Vendor Products:**
- Stock updated via sync from vendor API
- Use `available_quantity` from vendor response
- Don't manually adjust stock

**Own Products:**
- Manual stock management
- Update `stock_quantity` directly
- Calculate `available_quantity = stock_quantity - reserved_quantity`
- Monitor `reorder_point` for low stock alerts

## API Endpoints Needed

### Admin Endpoints for Own Products

```php
// Create own product
POST /admin/products/own
{
  "sku": "OWN-001",
  "name": "Custom Product",
  "base_price": 50.00,
  "price": 75.00,
  "stock_quantity": 100,
  "reorder_point": 20,
  "warehouse_location": "Warehouse A"
}

// Update own product stock
PATCH /admin/products/{id}/stock
{
  "stock_quantity": 150,
  "operation": "add" // or "set", "subtract"
}

// Get low stock alerts
GET /admin/products/low-stock?source=own
```

## Migration Steps

1. **Backup Database** (CRITICAL!)
2. **Run Migration:**
   ```sql
   -- Add new columns
   ALTER TABLE products 
   ADD COLUMN product_source ENUM('vendor', 'own') DEFAULT 'vendor' AFTER id,
   ADD COLUMN reorder_point INT UNSIGNED DEFAULT 0 AFTER reserved_quantity,
   ADD COLUMN warehouse_location VARCHAR(100) NULL AFTER reorder_point;
   
   -- Make vendor_article_id nullable
   ALTER TABLE products 
   MODIFY vendor_article_id VARCHAR(100) NULL;
   
   -- Update existing products (all are vendor)
   UPDATE products SET product_source = 'vendor';
   
   -- Update order_items
   ALTER TABLE order_items 
   ADD COLUMN product_source ENUM('vendor', 'own') DEFAULT 'vendor' AFTER product_sku,
   MODIFY vendor_article_id VARCHAR(100) NULL;
   
   -- Update existing order_items
   UPDATE order_items oi
   JOIN products p ON oi.product_id = p.id
   SET oi.product_source = p.product_source;
   ```

3. **Update Application Code:**
   - ProductSyncService
   - ReservationService
   - OrderService
   - Product Model

4. **Test:**
   - Create own product
   - Create order with own product
   - Verify no vendor API calls for own products
   - Verify stock management works

## Query Examples

### Get All Vendor Products
```sql
SELECT * FROM products WHERE product_source = 'vendor';
```

### Get All Own Products
```sql
SELECT * FROM products WHERE product_source = 'own';
```

### Get Low Stock Own Products
```sql
SELECT * FROM products 
WHERE product_source = 'own' 
  AND available_quantity <= reorder_point
  AND is_available = 1;
```

### Get Products Needing Reorder
```sql
SELECT id, sku, name, available_quantity, reorder_point, warehouse_location
FROM products
WHERE product_source = 'own'
  AND available_quantity <= reorder_point
  AND is_available = 1
ORDER BY available_quantity ASC;
```

## Benefits

✅ **Unified Product Management** - Both types in same table
✅ **Clear Distinction** - Easy to filter and report
✅ **Flexible Stock Management** - Different rules per source
✅ **No Breaking Changes** - Existing vendor products work as before
✅ **Scalable** - Easy to add more sources in future (e.g., 'mixed')

## Future Enhancements

1. **Mixed Products** - Products from multiple sources
2. **Supplier Management** - Track multiple suppliers for own products
3. **Purchase Orders** - Automate reordering for own products
4. **Stock Transfers** - Move stock between warehouses
5. **Cost Tracking** - Track purchase costs vs selling prices

