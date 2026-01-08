# Database Setup & Migration Guide

## Quick Fix for Missing Tables

### Step 1: Create user_sessions Table
Visit in your browser:
```
https://api.vs-mjrinfotech.com/setup/create-user-sessions-table
```

Expected response:
```json
{
  "success": true,
  "message": "user_sessions table created successfully"
}
```

### Step 2: Update addresses Table
Visit in your browser:
```
https://api.vs-mjrinfotech.com/setup/update-addresses-table
```

Expected response:
```json
{
  "success": true,
  "data": {
    "label": "added",
    "street": "added",
    "street2": "added",
    "street_not_null": "updated",
    "first_name_nullable": "updated",
    "last_name_nullable": "updated",
    "phone_nullable": "updated",
    "country_varchar": "updated"
  },
  "message": "addresses table updated successfully"
}
```

### Step 3: Debug Authentication (Optional)
To check if your token is valid, visit with Authorization header:
```
https://api.vs-mjrinfotech.com/setup/debug-auth
```

**Using curl:**
```bash
curl -X GET https://api.vs-mjrinfotech.com/setup/debug-auth \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Using browser console:**
```javascript
fetch('https://api.vs-mjrinfotech.com/setup/debug-auth', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
  }
})
  .then(r => r.json())
  .then(console.log);
```

Expected response (if valid):
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe"
    },
    "session": {
      "created_at": "2026-01-08 10:00:00",
      "expires_at": "2026-01-09 10:00:00",
      "seconds_until_expiry": 86400,
      "hours_until_expiry": 24
    }
  },
  "message": "Authentication is valid"
}
```

## After Setup

### 1. Log Out and Log Back In
After creating the tables, you need to:
1. Log out from the frontend
2. Clear localStorage (optional)
3. Log back in

This creates a fresh session in the `user_sessions` table.

### 2. Test All Pages
- ✅ `/account` - Profile updates
- ✅ `/account/addresses` - Address management
- ✅ `/account/settings` - Password change
- ✅ `/account/orders` - Order list
- ✅ `/checkout` - Checkout process

### 3. Remove Setup Endpoints (After Verification)
Once everything works, remove these lines from `routes/api.php`:
```php
// Database setup/migration endpoints (remove after setup)
$router->get('setup/create-user-sessions-table', 'HealthController', 'createUserSessionsTable');
$router->get('setup/update-addresses-table', 'HealthController', 'updateAddressesTable');
$router->get('setup/debug-auth', 'HealthController', 'debugAuth');
```

## Troubleshooting

### If you get "Endpoint not found"
Make sure the updated code is deployed to the server:
- Upload the updated `routes/api.php`
- Upload the updated `app/Controllers/HealthController.php`
- Upload the updated `app/Controllers/AuthController.php`
- Upload the updated `app/Controllers/OrderController.php`

### If authentication still fails
1. Check the debug endpoint output
2. Verify the token in browser localStorage
3. Check if session expired (tokens last 24 hours)
4. Look at PHP error logs: `storage/logs/php_errors.log`

## What These Migrations Fix

### user_sessions Table
**Purpose:** Store customer authentication tokens

**Before:** Tokens weren't stored in database → couldn't validate → 401 errors  
**After:** Tokens validated from database → authentication works ✅

### addresses Table Updates
**Purpose:** Support user address book (Home, Office, etc.)

**Before:** Only supported order addresses (first_name, last_name, phone required)  
**After:** Supports both user addresses AND order addresses ✅

**Changes:**
- Added `label` column (Home, Office, etc.)
- Added `street` column (primary street field)
- Added `street2` column (address line 2)
- Made `first_name`, `last_name`, `phone` optional
- Expanded `country` to VARCHAR(100) for full names

## Files Changed

### Backend
- `app/Controllers/HealthController.php` - Added migration methods
- `app/Controllers/AuthController.php` - Fixed address handling
- `app/Controllers/OrderController.php` - Added `list()` method
- `routes/api.php` - Added setup endpoints
- `database/migrations.sql` - Updated schema

### Frontend
- `lib/api/auth.ts` - Added `changePassword()` method
- `app/(shop)/account/settings/page.tsx` - Connected password change
- Multiple pages - Fixed hydration and routing issues

## New API Endpoints

| Method | Endpoint | Purpose | Auth Required |
|--------|----------|---------|---------------|
| GET | `/orders` | List customer orders | ✅ Yes |
| PUT | `/auth/password` | Change password | ✅ Yes |
| POST | `/auth/logout` | Logout user | Optional |
| GET | `/setup/create-user-sessions-table` | Create table | ❌ No (one-time) |
| GET | `/setup/update-addresses-table` | Update table | ❌ No (one-time) |
| GET | `/setup/debug-auth` | Debug auth | ✅ Yes |

