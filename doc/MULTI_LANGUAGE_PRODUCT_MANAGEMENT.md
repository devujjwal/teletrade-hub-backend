# Multi-Language Product Management Guide

## Overview

This document explains how multi-language product management works in TeleTrade Hub, including vendor sync, manual product entry, and color translation handling.

## Color Translation Handling

### Problem
The vendor API returns language-specific color values:
- English (`lang_id=0`): `"color": "White"`
- German (`lang_id=3`): `"color": "Weiß"`

### Solution
Color translations are stored in the `specifications` JSON field under `color_translations`:

```json
{
  "ean": "195949822025",
  "prod_network": "5G",
  "prod_storage": "128 GB",
  "prod_memory": "8GB RAM",
  "color_translations": {
    "en": "White",
    "de": "Weiß",
    "fr": "Blanc",
    "es": "Blanco"
  }
}
```

### How It Works

1. **Vendor Sync**: 
   - Base language (English) color is stored in the main `color` field
   - All language-specific colors are stored in `specifications.color_translations`
   - When syncing translations, color values are merged into existing translations

2. **API Response**:
   - The Product model's `applyLanguage()` method automatically selects the correct color based on the requested language
   - Falls back to English if translation is not available
   - Returns the translated color in the `color` field of the API response

3. **Example**:
   ```php
   // Request: GET /products/iphone-16-pro?lang=de
   // Response includes: "color": "Weiß"
   
   // Request: GET /products/iphone-16-pro?lang=en
   // Response includes: "color": "White"
   ```

## Vendor Product Sync

### Sync Process

1. **Base Language Sync** (English - `lang_id=1`):
   - Creates/updates products with base data
   - Generates slugs (SEO-friendly URLs)
   - Stores English color in main `color` field
   - Stores all vendor properties in `specifications` JSON

2. **Translation Sync** (Other languages):
   - Updates `name_{lang}` columns
   - Updates `description_{lang}` columns (if available)
   - Merges color translations into `specifications.color_translations`
   - Updates category translations

### Sync All Languages

The sync service automatically syncs all supported languages:

```php
// Sync all languages
$syncService = new ProductSyncService();
$stats = $syncService->syncProducts();

// Sync specific languages
$stats = $syncService->syncProducts([1, 3, 4]); // English, German, French
```

### Supported Languages

- `1` - English (en) - Base language
- `3` - German (de)
- `4` - French (fr)
- `5` - Spanish (es)
- `6` - Russian (ru)
- `7` - Italian (it)
- `8` - Turkish (tr)
- `9` - Romanian (ro)
- `10` - Slovakian (sk)
- `11` - Polish (pl)

## Manual Product Entry

### Recommended Approach: Default Language + Optional Translations

For admin-created products, we recommend a **single-language entry with optional translations** approach:

### 1. Required Fields (Default Language - English)

When creating a product manually, admins only need to enter:

**Required:**
- `name` (English) - Product name
- `sku` - Stock Keeping Unit
- `category_id` - Category
- `brand_id` - Brand
- `base_price` - Cost price
- `price` - Selling price
- `stock_quantity` - Available stock
- `color` - Color (English)

**Optional:**
- `description` - Product description (English)
- `ean` - European Article Number
- `storage` - Storage capacity
- `ram` - RAM specification
- `weight` - Product weight
- `dimensions` - Product dimensions

### 2. Translation Management

**Option A: Add Translations Later**
- Admin creates product in English only
- Translations can be added later via update API
- Frontend falls back to English if translation missing

**Option B: Bulk Translation Import**
- Admin can import translations from CSV/Excel
- Format: `sku, name_de, name_fr, description_de, description_fr`
- System updates language-specific columns

**Option C: Translation UI**
- Admin panel shows product with translation tabs
- Admin can add/edit translations per language
- Real-time preview of translated content

### 3. API Endpoints for Manual Products

#### Create Product (English Only)

```http
POST /admin/products
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "name": "Custom Product Name",
  "sku": "CUSTOM-001",
  "category_id": 1,
  "brand_id": 1,
  "base_price": 100.00,
  "price": 115.00,
  "stock_quantity": 10,
  "color": "Black",
  "description": "Product description in English"
}
```

#### Add Translation

```http
PATCH /admin/products/{id}/translations
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "language": "de",
  "name": "Benutzerdefinierter Produktname",
  "description": "Produktbeschreibung auf Deutsch",
  "color": "Schwarz"
}
```

#### Update Product with Translations

```http
PUT /admin/products/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "name": "Updated Product Name",
  "name_de": "Aktualisierter Produktname",
  "name_fr": "Nom de produit mis à jour",
  "description": "Updated description",
  "description_de": "Aktualisierte Beschreibung",
  "price": 120.00,
  "color": "White",
  "specifications": {
    "color_translations": {
      "en": "White",
      "de": "Weiß",
      "fr": "Blanc"
    }
  }
}
```

## Database Schema

### Products Table

```sql
CREATE TABLE products (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(500) NOT NULL,                    -- Base name (English)
  name_en VARCHAR(500),                           -- English name
  name_de VARCHAR(500),                           -- German name
  name_fr VARCHAR(500),                           -- French name
  -- ... other language columns
  color VARCHAR(100),                             -- Base color (English)
  specifications JSON,                            -- Contains color_translations
  slug VARCHAR(500) UNIQUE NOT NULL,              -- SEO-friendly URL
  -- ... other fields
);
```

### Color Translations Structure

```json
{
  "color_translations": {
    "en": "White",
    "de": "Weiß",
    "fr": "Blanc",
    "es": "Blanco",
    "ru": "Белый",
    "it": "Bianco",
    "tr": "Beyaz",
    "ro": "Alb",
    "sk": "Biely",
    "pl": "Biały"
  }
}
```

## Frontend Integration

### Server-Side Rendering (SSR)

For SSR, the frontend should:

1. **Request products with language parameter**:
   ```http
   GET /api/products?lang=de
   ```

2. **Use language-specific slugs** (if implemented):
   ```http
   GET /api/products/iphone-16-pro-de
   ```

3. **Handle fallbacks**:
   - If German translation missing, fallback to English
   - Display language indicator if using fallback

### Client-Side Rendering

1. **Detect user language**:
   ```javascript
   const lang = navigator.language.split('-')[0]; // 'de', 'fr', etc.
   ```

2. **Fetch products with language**:
   ```javascript
   fetch(`/api/products?lang=${lang}`)
   ```

3. **Display translated content**:
   ```javascript
   // API returns already translated product
   product.color // "Weiß" for German, "White" for English
   product.name // Translated name
   ```

## Best Practices

### 1. Product Creation

- ✅ Always create products in English first
- ✅ Generate slug from English name
- ✅ Add translations incrementally
- ✅ Use color_translations for multi-language colors

### 2. Translation Management

- ✅ Keep English as fallback
- ✅ Validate translations before saving
- ✅ Use translation memory/glossary for consistency
- ✅ Review translations for accuracy

### 3. Performance

- ✅ Index language columns for fast queries
- ✅ Cache translated products per language
- ✅ Use CDN for product images
- ✅ Optimize JSON specifications field

### 4. SEO

- ✅ Use language-specific slugs (if needed)
- ✅ Generate meta tags per language
- ✅ Implement hreflang tags
- ✅ Use language-specific sitemaps

## Migration Guide

### Existing Products

If you have existing products without translations:

1. **Run sync for all languages**:
   ```bash
   php public/sync-products.php
   ```

2. **Update color translations**:
   ```sql
   UPDATE products 
   SET specifications = JSON_SET(
     COALESCE(specifications, '{}'),
     '$.color_translations.en',
     color
   )
   WHERE color IS NOT NULL;
   ```

3. **Verify translations**:
   ```sql
   SELECT id, name, color, 
          JSON_EXTRACT(specifications, '$.color_translations') as translations
   FROM products 
   LIMIT 10;
   ```

## Troubleshooting

### Color Not Translating

**Problem**: Color shows in English even when requesting German

**Solution**: 
1. Check if `specifications.color_translations.de` exists
2. Verify sync ran for German language
3. Check Product model's `applyLanguage()` method

### Missing Translations

**Problem**: Product name shows in English for non-English requests

**Solution**:
1. Verify `name_{lang}` column has value
2. Check fallback chain in Language utility
3. Ensure sync completed successfully

### Sync Issues

**Problem**: Translations not syncing from vendor

**Solution**:
1. Check vendor API response for language-specific data
2. Verify language ID mapping in Language utility
3. Review sync logs in `vendor_sync_log` table

## API Examples

### Get Product in German

```http
GET /api/products/iphone-16-pro?lang=de

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "name": "iPhone 16 Dual eSIM 128GB 8GB RAM (Weiß)",
    "color": "Weiß",
    "category_name": "Handy",
    "language": "de"
  }
}
```

### Get Product in English

```http
GET /api/products/iphone-16-pro?lang=en

Response:
{
  "success": true,
  "data": {
    "id": 1,
    "name": "iPhone 16 Dual eSIM 128GB 8GB RAM (White)",
    "color": "White",
    "category_name": "Phone",
    "language": "en"
  }
}
```

## Summary

- ✅ Color translations stored in `specifications.color_translations`
- ✅ Base color (English) stored in main `color` field
- ✅ Product model automatically applies language-specific colors
- ✅ Manual products: Enter in English, add translations optionally
- ✅ Vendor sync: Syncs all languages automatically
- ✅ Frontend: Request with `?lang=` parameter for translated content
- ✅ Fallback: Always falls back to English if translation missing

