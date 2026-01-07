# Vendor Normal Operation Flow

## Overview

This document describes the standard vendor (TRIEL) operation flow as recommended by the vendor API documentation.

## Three-Step Process

### Step 1: Daily Stock Sync (GetCurrentStock)
**When:** Daily (recommended: 3:00 AM)  
**What:** Retrieve current stock from vendor and update local database

**Implementation:**
- Uses `getStock()` API endpoint (equivalent to GetCurrentStock)
- Syncs all supported languages
- Updates product stock quantities
- Disables products no longer available

**Cron Job:**
```bash
# Daily at 3:00 AM
0 3 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/sync-stock.php >> /var/log/teletrade-sync.log 2>&1
```

**Manual Execution:**
```bash
php cli/sync-stock.php
```

**Or via API:**
```bash
POST /admin/sync/products
```

### Step 2: Reserve Articles on Order (ReserveArticle)
**When:** When customer places order and payment succeeds  
**What:** Reserve purchased items with vendor

**Implementation:**
- Automatically triggered on `processPaymentSuccess()`
- Only reserves **vendor products** (own products skip this step)
- Creates reservation record in database
- Updates order status to 'reserved'

**Flow:**
```
Customer Order → Payment Success → ReserveArticle (vendor items only)
```

**Code Location:**
- `OrderService::processPaymentSuccess()`
- `ReservationService::reserveOrderProducts()`

### Step 3: Create Sales Order (CreateSalesOrder)
**When:** End of day (recommended: 17:00 / 5:00 PM)  
**What:** Create consolidated sales order at vendor based on all reservations

**Implementation:**
- Batch processes all reserved orders
- Only includes vendor items (own items excluded)
- Creates vendor sales order via API
- Updates order status to 'vendor_ordered'
- Marks reservations as 'ordered'

**Cron Job:**
```bash
# Daily at 17:00 (5:00 PM)
0 17 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/create-vendor-orders.php >> /var/log/teletrade-vendor-orders.log 2>&1
```

**Manual Execution:**
```bash
php cli/create-vendor-orders.php
```

**Or via API:**
```bash
POST /admin/vendor/create-sales-order
```

## Complete Workflow

### Daily Operations

**Morning (3:00 AM):**
```
1. Sync Stock
   └─ GetCurrentStock (getStock)
   └─ Update local database
   └─ Disable unavailable products
```

**Throughout Day:**
```
2. Customer Orders
   └─ Order created
   └─ Payment succeeds
   └─ ReserveArticle (vendor items)
   └─ Stock deducted (own items)
```

**Evening (17:00 / 5:00 PM):**
```
3. Create Sales Orders
   └─ Get all reserved orders
   └─ CreateSalesOrder (batch)
   └─ Update order status
   └─ Mark reservations as ordered
```

## Order Lifecycle

### Vendor Products Only
```
Order Created (status: 'pending')
  ↓
Payment Success
  ↓
ReserveArticle (status: 'reserved')
  ↓
[Wait until end of day]
  ↓
CreateSalesOrder (status: 'vendor_ordered')
  ↓
Vendor Ships (status: 'shipped')
```

### Own Products Only
```
Order Created (status: 'pending')
  ↓
Payment Success
  ↓
Stock Deducted (status: 'processing')
  ↓
Ready to Ship (status: 'processing')
  ↓
Shipped (status: 'shipped')
```

### Mixed Order (Vendor + Own)
```
Order Created (status: 'pending')
  ↓
Payment Success
  ├─ Vendor Items: ReserveArticle (status: 'reserved')
  └─ Own Items: Stock Deducted (status: 'stock_deducted')
  ↓
(fulfillment_status: 'partially_fulfilled')
  ↓
CreateSalesOrder (vendor items only)
  ↓
(fulfillment_status: 'fulfilled' when both complete)
```

## Cron Job Setup

### 1. Make Scripts Executable
```bash
chmod +x cli/sync-stock.php
chmod +x cli/create-vendor-orders.php
```

### 2. Test Scripts Manually
```bash
# Test stock sync
php cli/sync-stock.php

# Test vendor order creation
php cli/create-vendor-orders.php
```

### 3. Add to Crontab
```bash
crontab -e

# Add these lines:
# Daily stock sync at 3:00 AM
0 3 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/sync-stock.php >> /var/log/teletrade-sync.log 2>&1

# Daily vendor sales order creation at 17:00 (5:00 PM)
0 17 * * * /usr/bin/php /path/to/teletrade-hub-backend/cli/create-vendor-orders.php >> /var/log/teletrade-vendor-orders.log 2>&1
```

### 4. Verify Cron Jobs
```bash
# List cron jobs
crontab -l

# Check cron service
systemctl status cron  # Ubuntu/Debian
systemctl status crond  # CentOS/RHEL
```

## Log Files

### Stock Sync Logs
- Location: `storage/logs/sync-stock.log`
- Contains: Sync statistics, errors, language details

### Vendor Orders Logs
- Location: `storage/logs/vendor-orders.log`
- Contains: Orders processed, errors, vendor responses

### Cron Output Logs
- Location: `/var/log/teletrade-sync.log`
- Location: `/var/log/teletrade-vendor-orders.log`
- Contains: Full script output

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

## Error Handling

### Stock Sync Errors
- Logged to `storage/logs/sync-stock.log`
- Failed syncs don't affect existing products
- Can be retried manually

### Reservation Errors
- Order status set to 'payment_pending'
- Customer notified
- Admin can retry reservation

### Vendor Order Creation Errors
- Logged to `storage/logs/vendor-orders.log`
- Failed orders remain in 'reserved' status
- Can be retried next day or manually

## Best Practices

✅ **Stock Sync:**
- Run daily before business hours (3:00 AM)
- Sync all languages for complete data
- Monitor sync logs for errors

✅ **Reservations:**
- Reserve immediately after payment
- Handle failures gracefully
- Rollback on critical errors

✅ **Sales Orders:**
- Create at end of business day (17:00)
- Batch process all reservations
- Verify vendor order creation
- Monitor for failed orders

## Summary

1. **Daily Stock Sync** (3:00 AM) - GetCurrentStock → Update database
2. **Order Reservations** (Real-time) - ReserveArticle on payment success
3. **Daily Sales Orders** (17:00) - CreateSalesOrder batch at end of day

This workflow ensures:
- ✅ Stock is always up-to-date
- ✅ Items are reserved immediately
- ✅ Vendor orders are created efficiently in batches
- ✅ No manual intervention needed

