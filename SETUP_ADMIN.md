# Admin Setup Instructions

## Default Admin Credentials

After running the database migrations, you need to set up the admin password.

### Method 1: Using Setup Script (Recommended)

Run this command on your server:

```bash
cd /home2/vsmjr110/api
php public/setup-admin.php
```

This will set the admin password to: `Ujjwal@2026`

**Important:** Delete the `setup-admin.php` file after running it for security.

### Method 2: Using Admin Panel

1. Login with the placeholder password (check database)
2. Go to Admin → Settings
3. Scroll to "Security Settings"
4. Click "Reset to Default" button
5. This will set password to: `Ujjwal@2026`

### Method 3: Manual Database Update

If you prefer to update manually:

```bash
# Generate password hash
php public/generate-password-hash.php

# Copy the hash and run in MySQL:
UPDATE admin_users 
SET password_hash = 'YOUR_GENERATED_HASH' 
WHERE username = 'admin';
```

## Default Admin Account

- **Username:** `admin`
- **Email:** `tctradingek@gmail.com`
- **Password:** `Ujjwal@2026`
- **Role:** Super Admin

## Default Settings

The migration includes these default settings:

| Setting | Value |
|---------|-------|
| Site Name | Telecommunication Trading e.K. |
| Contact Email | tctradingek@gmail.com |
| Address | Marienstraße 20, Stuttgart, Deutschland, Germany - 70178 |
| Contact Number | +491737109267 |
| WhatsApp Number | +491737109267 |
| Currency | EUR |
| Tax Rate | 19.00% |
| Shipping Cost | €9.99 |
| Free Shipping Threshold | €100.00 |

You can change any of these values in: **Admin → Settings**

## Security Notes

⚠️ **After initial setup:**
1. Change the default admin password
2. Delete `setup-admin.php` file
3. Delete `generate-password-hash.php` file  
4. Update email to your actual admin email
5. Enable 2FA if available (future feature)

## Troubleshooting

### Can't login with Ujjwal@2026?

Run the setup script:
```bash
php public/setup-admin.php
```

### Forgot password?

1. SSH into your server
2. Run: `php public/setup-admin.php`
3. This resets password to: `Ujjwal@2026`

### Need different default password?

Edit `public/setup-admin.php` and change the `$defaultPassword` variable before running it.
