# Addresses Table Fix

## Problem
The `addresses` table schema didn't match the frontend API calls, causing address creation to fail with a 500 error.

## Root Cause

### Original Table Schema (Order Addresses)
```sql
CREATE TABLE addresses (
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  phone VARCHAR(50) NOT NULL,
  ...
)
```

### Frontend Sending (User Address Book)
```json
{
  "label": "Home",
  "street": "123 Main St",
  "street2": "Apt 4",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "United States",
  "is_default": true
}
```

**Mismatch:**
- ❌ `label` column didn't exist
- ❌ `street` should be `address_line1`
- ❌ `first_name`, `last_name`, `phone` were required but not sent

## Solution

### Updated Table Schema (Hybrid - Both Use Cases)
```sql
CREATE TABLE addresses (
  label VARCHAR(100) NULL,              -- NEW: Address nickname
  first_name VARCHAR(100) NULL,         -- CHANGED: Now optional
  last_name VARCHAR(100) NULL,          -- CHANGED: Now optional
  street VARCHAR(255) NOT NULL,         -- NEW: Primary street field
  street2 VARCHAR(255) NULL,            -- NEW: Address line 2
  address_line1 VARCHAR(255) NULL,      -- KEPT: For order addresses (backward compat)
  address_line2 VARCHAR(255) NULL,      -- KEPT: For order addresses (backward compat)
  phone VARCHAR(50) NULL,               -- CHANGED: Now optional
  country VARCHAR(100) NOT NULL,        -- CHANGED: From VARCHAR(2) to support full names
  ...
)
```

### Backend Changes

**AuthController.php:**
1. **createAddress()** - Now accepts both formats:
   - User address book: `street`, `street2`, `label`
   - Order addresses: `address_line1`, `address_line2`, `first_name`, `last_name`, `phone`

2. **updateAddress()** - Keeps both fields in sync:
   ```php
   street = :street, address_line1 = :street  // Sync both columns
   ```

3. **Validation** - Made flexible:
   ```php
   'street' => 'sometimes|required|string',
   'address_line1' => 'sometimes|required|string',
   'state' => 'sometimes|string',  // Optional now
   ```

## Migration Steps

### Step 1: Update the addresses table
Visit: `https://api.vs-mjrinfotech.com/update-addresses-table.php`

This will:
- ✅ Add `label` column
- ✅ Add `street` column  
- ✅ Add `street2` column
- ✅ Make `first_name`, `last_name`, `phone` optional (NULL)
- ✅ Change `country` from VARCHAR(2) to VARCHAR(100)

### Step 2: Test address creation
```bash
curl -X POST https://api.vs-mjrinfotech.com/auth/addresses \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "Home",
    "street": "123 Main St",
    "street2": "Apt 4",
    "city": "New York",
    "state": "NY",
    "postal_code": "10001",
    "country": "United States",
    "is_default": true
  }'
```

Expected response:
```json
{
  "success": true,
  "data": {
    "address": {
      "id": 1,
      "label": "Home",
      "street": "123 Main St",
      ...
    }
  },
  "message": "Address created successfully"
}
```

## Backward Compatibility

### Order Creation (Still Works)
Order addresses can still use the old format:
```php
$billingAddress = [
  ':first_name' => 'John',
  ':last_name' => 'Doe',
  ':address_line1' => '123 Main St',
  ':address_line2' => 'Apt 4',
  ':phone' => '+1234567890',
  ...
];
```

The backend now syncs both `street` and `address_line1` to maintain compatibility.

## Files Modified

### Backend
- `app/Controllers/AuthController.php`
  - `createAddress()` - Supports both formats
  - `updateAddress()` - Syncs both street formats
  - Validation rules updated

- `database/migrations.sql`
  - Updated addresses table schema

- `public/update-addresses-table.php`
  - Migration script to update existing table

### No Frontend Changes Needed
The frontend was already sending the correct format (`street`, `street2`, `label`). The backend just needed to support it.

## Testing Checklist

- [ ] Create user_sessions table
- [ ] Update addresses table
- [ ] Log out and log back in
- [ ] Test `/account/addresses` page
  - [ ] List addresses
  - [ ] Create new address
  - [ ] Edit existing address
  - [ ] Delete address
  - [ ] Set default address
- [ ] Test order creation (ensure backward compatibility)
- [ ] Test `/checkout` page

## Debug Tools

1. **Authentication debug:**  
   `https://api.vs-mjrinfotech.com/debug-auth.php`

2. **Create user_sessions table:**  
   `https://api.vs-mjrinfotech.com/create-user-sessions-table.php`

3. **Update addresses table:**  
   `https://api.vs-mjrinfotech.com/update-addresses-table.php`

