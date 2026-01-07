# Vendor API Implementation Review & Own Stock Support

## Current Implementation Analysis

### ✅ What We're Doing Right

1. **API Integration**: Correctly using TRIEL RESTful API endpoints
   - `GET /getStock/?lang_id={id}&price_drop=0` ✅
   - `POST /reserveArticle/` ✅
   - `POST /removeReservedArticle/` ✅
   - `POST /createSalesOrder/` ✅

2. **Multi-Language Support**: Properly handling language-specific data
   - Storing translations in language-specific columns ✅
   - Color translations in JSON specifications ✅
   - Category and brand translations ✅

3. **Stock Management**: Good separation of concerns
   - `stock_quantity` - Total stock
   - `available_quantity` - Available for sale
   - `reserved_quantity` - Reserved for orders ✅

4. **Pricing**: Flexible markup system
   - Global, category, brand, and product-level rules ✅
   - Percentage and fixed markup types ✅

5. **Order Flow**: Proper reservation workflow
   - Create order → Reserve → Create vendor order ✅
   - Error handling and rollback ✅

### ⚠️ Issues & Improvements Needed

1. **Product Source Distinction**: Currently assumes ALL products are from vendor
   - `vendor_article_id` is REQUIRED and UNIQUE
   - No way to distinguish vendor vs own products
   - Sync process affects all products

2. **Own Stock Management**: Missing support for own inventory
   - No direct stock management for own products
   - Reservation logic always calls vendor API
   - Order processing assumes vendor products

3. **Database Constraints**: Too restrictive for mixed inventory
   - `vendor_article_id` UNIQUE constraint prevents own products
   - No `product_source` field to distinguish types

## Recommended Database Structure

### Changes Required

1. **Add `product_source` field** to distinguish vendor vs own products
2. **Make `vendor_article_id` nullable** (own products don't have vendor IDs)
3. **Update unique constraints** (SKU unique, vendor_article_id unique only when not null)
4. **Add `warehouse_location`** for own products (optional)
5. **Add `reorder_point`** for own stock management

### Benefits

- ✅ Support both vendor and own products in same table
- ✅ Clear distinction between product sources
- ✅ Flexible stock management
- ✅ No breaking changes to existing vendor products
- ✅ Easy filtering and reporting

## Implementation Plan

### Phase 1: Database Changes
- Add `product_source` ENUM field
- Make `vendor_article_id` nullable
- Update unique constraints
- Add own stock management fields

### Phase 2: Service Layer Updates
- Update ProductSyncService to skip own products
- Update ReservationService to handle both types
- Update OrderService to process accordingly

### Phase 3: API Updates
- Add admin endpoints for own product management
- Update product creation/update logic
- Add stock management endpoints

## Best Practices

1. **Product Source Enum**: `vendor`, `own`, `mixed` (future)
2. **Stock Sync**: Only sync vendor products
3. **Reservations**: Only reserve vendor products via API
4. **Own Products**: Direct stock management, no vendor API calls
5. **Order Processing**: Route based on product source

