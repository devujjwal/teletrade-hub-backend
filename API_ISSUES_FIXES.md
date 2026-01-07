# API Issues & Fixes

## Issues Identified

1. ✅ **Orders endpoint - Foreign key constraint violation** - FIXED
2. 🔧 **Products/Categories empty** - Requires data sync
3. 🔧 **Product search returns empty** - Requires data sync  
4. 🔧 **Category endpoints return empty** - Requires data sync

---

## Issue #1: Orders Foreign Key Constraint (FIXED ✅)

### Problem
```json
{
    "success": false,
    "message": "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`vsmjr110_api`.`addresses`, CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE)"
}
```

### Root Cause
The order creation endpoint was accepting a `user_id` parameter that didn't exist in the `users` table, causing a foreign key constraint violation.

### Solution Applied
Updated `OrderController.php` to validate that the `user_id` exists before using it. For guest orders, `user_id` is now properly set to `NULL`.

**Changes made:**
- Added User model validation before setting user_id
- Only uses user_id if the user exists in the database
- Properly handles guest orders with NULL user_id

### Testing
You can now create orders as a guest user without errors:

```bash
curl -X POST https://api.vs-mjrinfotech.com/orders \
  -H "Content-Type: application/json" \
  -d '{
    "cart_items": [
      {
        "product_id": 1,
        "quantity": 1
      }
    ],
    "billing_address": {
      "first_name": "John",
      "last_name": "Doe",
      "address_line1": "123 Main St",
      "city": "Berlin",
      "postal_code": "10115",
      "country": "DE",
      "phone": "+49123456789"
    },
    "guest_email": "john.doe@example.com",
    "payment_method": "credit_card"
  }'
```

---

## Issue #2, #3, #4: Empty Products/Categories (REQUIRES ACTION 🔧)

### Problem
All product-related endpoints return empty results:
- `/products/search?q=iPhone` - "Product not found"
- `/categories` - Empty categories array
- `/categories/1/products` - "Category not found"

### Root Cause
The database doesn't have any products or categories yet. The system needs to sync products from the TRIEL vendor API.

---

## Solution: Sync Products from Vendor API

### Step 1: Set Up Environment Variables

You need to create a `.env` file in the backend root with your TRIEL API credentials:

1. Navigate to the backend directory (via cPanel File Manager or SSH):
   ```bash
   cd /home/vsmjr110/public_html/api
   ```

2. Create a `.env` file:
   ```bash
   nano .env
   ```

3. Add the following configuration:
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_NAME=vsmjr110_api
   DB_USERNAME=vsmjr110_ujjwal
   DB_PASSWORD=your_actual_database_password

   # JWT Configuration
   JWT_SECRET=your_jwt_secret_key_here_make_it_long_and_random
   JWT_EXPIRY=3600

   # Vendor API Configuration (TRIEL)
   VENDOR_API_BASE_URL=https://b2b.triel.sk/api
   VENDOR_API_KEY=your_actual_triel_api_key_here

   # CORS Configuration
   CORS_ALLOWED_ORIGINS=https://teletrade-hub.com,http://localhost:3000

   # Environment
   APP_ENV=production
   APP_DEBUG=false
   ```

4. **IMPORTANT**: Replace the following:
   - `your_actual_database_password` - Your actual MySQL password
   - `your_jwt_secret_key_here_make_it_long_and_random` - Generate a random 64-character string
   - `your_actual_triel_api_key_here` - Your actual TRIEL API key

5. Secure the file:
   ```bash
   chmod 600 .env
   ```

### Step 2: Run Product Sync

#### Option A: Using the Sync Script (Recommended)

Access the sync script via browser:
```
https://api.vs-mjrinfotech.com/sync-products.php?key=SECURE_KEY_12345
```

This will:
- Fetch all products from TRIEL API
- Create brands automatically
- Calculate prices with markup
- Add products to the database
- Return sync statistics

**Expected Output:**
```json
{
    "success": true,
    "message": "Product sync completed successfully",
    "data": {
        "synced": 150,
        "added": 150,
        "updated": 0,
        "disabled": 0
    }
}
```

#### Option B: Using Admin API (If Admin Panel is Set Up)

```bash
curl -X POST https://api.vs-mjrinfotech.com/admin/sync/products \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json"
```

### Step 3: Verify Data

After syncing, verify the data was imported:

1. **Check Products Count:**
   ```bash
   curl https://api.vs-mjrinfotech.com/products?page=1&limit=10&lang=en
   ```

2. **Check Categories:**
   ```bash
   curl https://api.vs-mjrinfotech.com/categories?lang=en
   ```

3. **Check Brands:**
   ```bash
   curl https://api.vs-mjrinfotech.com/brands
   ```

4. **Search Products:**
   ```bash
   curl "https://api.vs-mjrinfotech.com/products/search?q=iPhone&page=1&limit=20&lang=en"
   ```

### Step 4: Security Cleanup

**IMPORTANT**: After the first successful sync, delete the sync script for security:

```bash
rm /home/vsmjr110/public_html/api/public/sync-products.php
```

This file was meant as a temporary workaround. Future syncs should use the admin API endpoint.

---

## Common Issues & Troubleshooting

### Issue: Sync script returns "Unauthorized access"
**Solution**: Make sure you're using the correct key parameter: `?key=SECURE_KEY_12345`

### Issue: Sync script returns "Invalid stock data received from vendor"
**Solution**: 
- Verify your VENDOR_API_KEY is correct in the .env file
- Check that the TRIEL API is accessible from your server
- Test the TRIEL API directly:
  ```bash
  curl -H "Authorization: YOUR_API_KEY" "https://b2b.triel.sk/api/getStock/?lang_id=0&price_drop=0"
  ```

### Issue: Database connection errors
**Solution**: Verify your database credentials in the .env file match your actual MySQL credentials

### Issue: Products still showing empty after sync
**Solution**: 
- Check the `vendor_sync_log` table for sync status:
  ```sql
  SELECT * FROM vendor_sync_log ORDER BY started_at DESC LIMIT 1;
  ```
- Check for errors in `storage/logs/app.log`

---

## Summary of Changes Made

### Files Modified:
1. **`app/Controllers/OrderController.php`**
   - Added User model validation for guest orders
   - Prevents foreign key constraint violations
   - Properly handles NULL user_id for guest checkouts

### Files Created:
1. **`API_ISSUES_FIXES.md`** (this file)
   - Complete troubleshooting guide
   - Step-by-step sync instructions

---

## Next Steps

1. ✅ Order creation is now fixed - no action needed
2. 🔧 Create the `.env` file with your TRIEL API credentials
3. 🔧 Run the product sync script
4. 🔧 Test all API endpoints
5. 🔧 Delete the sync script for security
6. ✅ All APIs should now return data correctly

---

## API Endpoints Testing Checklist

After completing the sync, test these endpoints:

- [ ] `GET /products` - Should return product list
- [ ] `GET /products/search?q=iPhone` - Should return search results
- [ ] `GET /products/{id}` - Should return product details
- [ ] `GET /categories` - Should return categories
- [ ] `GET /categories/{id}/products` - Should return category products
- [ ] `GET /brands` - Should return brands
- [ ] `POST /orders` - Should create order (already fixed)

---

## Support

If you encounter any issues:
1. Check `storage/logs/app.log` for error details
2. Check `vendor_api_logs` table for API call logs
3. Verify environment variables are set correctly

---

**Last Updated:** January 7, 2026

