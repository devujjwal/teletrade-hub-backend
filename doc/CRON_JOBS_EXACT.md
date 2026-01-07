# Exact Cron Job Commands

## Server Information

Based on your cPanel server setup:
- **Base Path Pattern:** `/home2/vsmjr110/public_html/` or domain-specific path
- **PHP Binary:** `/usr/local/bin/php` (general) or `/usr/local/bin/ea-phpXX` (domain-specific)

## Step 1: Determine Your Exact Paths

### Option A: If deployed in public_html root
```
Project Path: /home2/vsmjr110/public_html/teletrade-hub-backend/
Stock Sync Script: /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php
Vendor Orders Script: /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php
```

### Option B: If deployed in domain-specific directory
```
Project Path: /home2/vsmjr110/your-domain.com/teletrade-hub-backend/
Stock Sync Script: /home2/vsmjr110/your-domain.com/teletrade-hub-backend/cli/sync-stock.php
Vendor Orders Script: /home2/vsmjr110/your-domain.com/teletrade-hub-backend/cli/create-vendor-orders.php
```

## Step 2: Check PHP Version

1. Go to **cPanel → MultiPHP Manager**
2. Find your domain
3. Note the PHP version (e.g., `ea-php81`, `ea-php82`, `ea-php99`)

## Step 3: Exact Cron Job Commands

### Using General PHP (if available)

```bash
# Daily Stock Sync at 3:00 AM
0 3 * * * /usr/local/bin/php /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-sync.log 2>&1

# Daily Vendor Sales Order Creation at 17:00 (5:00 PM)
0 17 * * * /usr/local/bin/php /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-vendor-orders.log 2>&1
```

### Using Domain-Specific PHP (Recommended)

**Replace `ea-php99` with your actual PHP version from MultiPHP Manager**

```bash
# Daily Stock Sync at 3:00 AM
0 3 * * * /usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-sync.log 2>&1

# Daily Vendor Sales Order Creation at 17:00 (5:00 PM)
0 17 * * * /usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-vendor-orders.log 2>&1
```

### If Using Domain-Specific Directory

**Replace `your-domain.com` with your actual domain path**

```bash
# Daily Stock Sync at 3:00 AM
0 3 * * * /usr/local/bin/ea-php99 /home2/vsmjr110/your-domain.com/teletrade-hub-backend/cli/sync-stock.php >> /home2/vsmjr110/your-domain.com/teletrade-hub-backend/storage/logs/cron-sync.log 2>&1

# Daily Vendor Sales Order Creation at 17:00 (5:00 PM)
0 17 * * * /usr/local/bin/ea-php99 /home2/vsmjr110/your-domain.com/teletrade-hub-backend/cli/create-vendor-orders.php >> /home2/vsmjr110/your-domain.com/teletrade-hub-backend/storage/logs/cron-vendor-orders.log 2>&1
```

## Step 4: How to Add Cron Jobs in cPanel

1. **Login to cPanel**
2. **Go to:** Advanced → Cron Jobs (or search "Cron Jobs")
3. **Select:** "Standard (cPanel v3 Style)" or "Advanced (Unix Style)"
4. **Add each cron job:**

### Cron Job 1: Daily Stock Sync
- **Minute:** `0`
- **Hour:** `3`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:** 
  ```
  /usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-sync.log 2>&1
  ```

### Cron Job 2: Daily Vendor Orders
- **Minute:** `0`
- **Hour:** `17`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
  ```
  /usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-vendor-orders.log 2>&1
  ```

5. **Click:** "Add New Cron Job"

## Step 5: Verify Paths Before Adding

### Test PHP Path
```bash
# SSH into your server and test:
/usr/local/bin/ea-php99 --version

# Or test with general PHP:
/usr/local/bin/php --version
```

### Test Script Paths
```bash
# SSH into your server and verify paths exist:
ls -la /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php
ls -la /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php

# Test scripts manually:
/usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php
/usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php
```

## Step 6: Check Logs

After cron jobs run, check logs:

```bash
# View sync log
tail -f /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-sync.log

# View vendor orders log
tail -f /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-vendor-orders.log

# View application logs
tail -f /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/sync-stock.log
tail -f /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/vendor-orders.log
```

## Important Notes

1. **Replace `ea-php99`** with your actual PHP version (check MultiPHP Manager)
2. **Replace path** `/home2/vsmjr110/public_html/` with your actual project path
3. **Ensure log directory exists:**
   ```bash
   mkdir -p /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs
   chmod 755 /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs
   ```
4. **Ensure scripts are executable:**
   ```bash
   chmod +x /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php
   chmod +x /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php
   ```

## Quick Reference (Copy-Paste Ready)

**After updating paths and PHP version, use these:**

```bash
# Stock Sync (3:00 AM)
0 3 * * * /usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/sync-stock.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-sync.log 2>&1

# Vendor Orders (17:00 / 5:00 PM)
0 17 * * * /usr/local/bin/ea-php99 /home2/vsmjr110/public_html/teletrade-hub-backend/cli/create-vendor-orders.php >> /home2/vsmjr110/public_html/teletrade-hub-backend/storage/logs/cron-vendor-orders.log 2>&1
```

**Remember to:**
- ✅ Replace `ea-php99` with your PHP version
- ✅ Replace `/home2/vsmjr110/public_html/` with your actual path
- ✅ Test scripts manually before adding to cron
- ✅ Monitor logs after first run

