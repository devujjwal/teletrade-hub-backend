# Final Cron Job Commands - Ready to Use

## Your Server Configuration
- **PHP Version:** `ea-php83`
- **Project Path:** `/home2/vsmjr110/api/public`

## Exact Cron Job Commands (Copy-Paste Ready)

### Cron Job 1: Daily Stock Sync (3:00 AM)
```bash
0 3 * * * /usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/sync-stock.php >> /home2/vsmjr110/api/public/storage/logs/cron-sync.log 2>&1
```

### Cron Job 2: Daily Vendor Orders (17:00 / 5:00 PM)
```bash
0 17 * * * /usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/create-vendor-orders.php >> /home2/vsmjr110/api/public/storage/logs/cron-vendor-orders.log 2>&1
```

## How to Add in cPanel

1. **Login to cPanel**
2. **Go to:** Advanced → Cron Jobs (or search "Cron Jobs")
3. **Select:** "Standard (cPanel v3 Style)"

### Add Cron Job 1: Stock Sync
- **Minute:** `0`
- **Hour:** `3`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:** 
  ```
  0 3 * * * /usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/sync-stock.php >> /home2/vsmjr110/api/public/storage/logs/cron-sync.log 2>&1
  ```
- **Click:** "Add New Cron Job"

### Add Cron Job 2: Vendor Orders
- **Minute:** `0`
- **Hour:** `17`
- **Day:** `*`
- **Month:** `*`
- **Weekday:** `*`
- **Command:**
  ```
  0 17 * * * /usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/create-vendor-orders.php >> /home2/vsmjr110/api/public/storage/logs/cron-vendor-orders.log 2>&1
  ```
- **Click:** "Add New Cron Job"

## Before Adding: Test Scripts

SSH into your server and verify:

```bash
# Test PHP version
/usr/local/bin/ea-php83 --version

# Verify script paths exist
ls -la /home2/vsmjr110/api/public/cli/sync-stock.php
ls -la /home2/vsmjr110/api/public/cli/create-vendor-orders.php

# Test scripts manually (run these first!)
/usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/sync-stock.php
/usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/create-vendor-orders.php
```

## Verify Log Directory Exists

```bash
# Ensure log directory exists
mkdir -p /home2/vsmjr110/api/public/storage/logs
chmod 755 /home2/vsmjr110/api/public/storage/logs
```

## Check Logs After First Run

```bash
# View cron output logs
tail -f /home2/vsmjr110/api/public/storage/logs/cron-sync.log
tail -f /home2/vsmjr110/api/public/storage/logs/cron-vendor-orders.log

# View application logs
tail -f /home2/vsmjr110/api/public/storage/logs/sync-stock.log
tail -f /home2/vsmjr110/api/public/storage/logs/vendor-orders.log
```

## Summary

✅ **Stock Sync:** Runs daily at 3:00 AM  
✅ **Vendor Orders:** Runs daily at 17:00 (5:00 PM)  
✅ **PHP Version:** ea-php83  
✅ **Path:** /home2/vsmjr110/api/public  

**Ready to copy-paste into cPanel Cron Jobs!**

