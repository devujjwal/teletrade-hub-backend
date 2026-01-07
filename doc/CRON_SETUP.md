# Cron Jobs Setup Guide

## Vendor Normal Operation Flow

Following TRIEL's recommended workflow:

### 1. Daily Stock Sync (GetCurrentStock)
**Time:** 3:00 AM daily  
**Script:** `cli/sync-stock.php`  
**What it does:**
- Calls `getStock()` API (equivalent to GetCurrentStock)
- Syncs all supported languages
- Updates local database with current stock
- Disables products no longer available

### 2. Reserve Articles (ReserveArticle)
**Time:** Real-time (on order payment success)  
**Trigger:** Automatic via `OrderService::processPaymentSuccess()`  
**What it does:**
- Reserves vendor products via `ReserveArticle` API
- Creates reservation records
- Updates order status to 'reserved'
- **Note:** Own products skip this step (direct stock deduction)

### 3. Create Sales Orders (CreateSalesOrder)
**Time:** 17:00 (5:00 PM) daily  
**Script:** `cli/create-vendor-orders.php`  
**What it does:**
- Gets all reserved orders from the day
- Creates consolidated sales order via `CreateSalesOrder` API
- Updates order status to 'vendor_ordered'
- Marks reservations as 'ordered'

## Cron Job Configuration

### Step 1: Make Scripts Executable
```bash
cd /path/to/teletrade-hub-backend
chmod +x cli/sync-stock.php
chmod +x cli/create-vendor-orders.php
```

### Step 2: Test Scripts Manually
```bash
# Test stock sync
php cli/sync-stock.php

# Test vendor order creation
php cli/create-vendor-orders.php
```

### Step 3: Add to Crontab
```bash
crontab -e

# Add these lines:
# Daily stock sync at 3:00 AM
0 3 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/sync-stock.php >> /var/log/teletrade-sync.log 2>&1

# Daily vendor sales order creation at 17:00 (5:00 PM)
0 17 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/create-vendor-orders.php >> /var/log/teletrade-vendor-orders.log 2>&1
```

### Step 4: Verify Cron Jobs
```bash
# List cron jobs
crontab -l

# Check cron service status
systemctl status cron    # Ubuntu/Debian
systemctl status crond   # CentOS/RHEL

# View cron logs
tail -f /var/log/cron    # System cron log
tail -f /var/log/teletrade-sync.log
tail -f /var/log/teletrade-vendor-orders.log
```

## Log Files

### Application Logs
- `storage/logs/sync-stock.log` - Stock sync details
- `storage/logs/vendor-orders.log` - Vendor order creation details

### Cron Output Logs
- `/var/log/teletrade-sync.log` - Full sync script output
- `/var/log/teletrade-vendor-orders.log` - Full vendor orders script output

## Monitoring

### Check Last Sync
```sql
SELECT * FROM vendor_sync_log 
ORDER BY started_at DESC 
LIMIT 1;
```

### Check Pending Reservations
```sql
SELECT COUNT(*) FROM reservations 
WHERE status = 'reserved' 
AND vendor_reservation_id IS NOT NULL;
```

### Check Orders Ready for Vendor Submission
```sql
SELECT COUNT(*) FROM orders 
WHERE status = 'reserved' 
AND payment_status = 'paid'
AND vendor_order_id IS NULL;
```

## Troubleshooting

### Cron Job Not Running
1. Check cron service: `systemctl status cron`
2. Check cron logs: `grep CRON /var/log/syslog`
3. Verify script path is absolute
4. Check file permissions: `ls -la cli/*.php`
5. Test script manually: `php cli/sync-stock.php`

### Script Errors
1. Check application logs: `tail -f storage/logs/sync-stock.log`
2. Check cron output: `tail -f /var/log/teletrade-sync.log`
3. Verify PHP path: `which php`
4. Check environment variables are loaded

### Permission Issues
```bash
# Ensure scripts are executable
chmod +x cli/*.php

# Ensure log directory is writable
chmod -R 755 storage/logs
```

## Alternative: Using API Endpoints

If cron jobs are not available, you can trigger via API:

### Stock Sync
```bash
curl -X POST https://your-domain.com/admin/sync/products \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### Create Vendor Orders
```bash
curl -X POST https://your-domain.com/admin/vendor/create-sales-order \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

**Note:** API endpoints require admin authentication.

## Summary

✅ **Daily Stock Sync** (3:00 AM) - GetCurrentStock → Update database  
✅ **Reserve Articles** (Real-time) - ReserveArticle on payment success  
✅ **Create Sales Orders** (17:00) - CreateSalesOrder batch at end of day  

This matches TRIEL's recommended workflow exactly!

