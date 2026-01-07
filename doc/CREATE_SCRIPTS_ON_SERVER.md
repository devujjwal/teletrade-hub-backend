# Create Cron Scripts on Server

## Your Project Path
**Path:** `/home2/vsmjr110/api/public/`

## Step 1: Create CLI Directory

Run on your server:
```bash
mkdir -p /home2/vsmjr110/api/public/cli
chmod 755 /home2/vsmjr110/api/public/cli
```

## Step 2: Create sync-stock.php

Run on your server:
```bash
cat > /home2/vsmjr110/api/public/cli/sync-stock.php << 'ENDOFFILE'
#!/usr/bin/env php
<?php
/**
 * Daily Stock Sync Cron Job
 * Syncs vendor stock using GetCurrentStock (getStock) API
 * 
 * Usage: php cli/sync-stock.php
 * 
 * Recommended cron schedule: Daily at 3:00 AM
 */

// Set working directory
chdir(__DIR__ . '/..');

// Load dependencies
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Language.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Models/Category.php';
require_once __DIR__ . '/../app/Models/Brand.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';
require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/ProductSyncService.php';

// Initialize environment
Env::load();

// Increase execution time and memory for multi-language sync
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

// Logging function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    echo $logMessage;
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log($logMessage, 3, $logDir . '/sync-stock.log');
}

try {
    logMessage("Starting daily stock sync...");
    
    $syncService = new ProductSyncService();
    
    // Sync all supported languages
    // Language IDs: 1=English, 3=German, 4=French, 5=Spanish, 6=Russian, 
    // 7=Italian, 8=Turkish, 9=Romanian, 10=Slovakian, 11=Polish
    $languageIds = [1, 3, 4, 5, 6, 7, 8, 9, 10, 11];
    
    logMessage("Syncing products for " . count($languageIds) . " languages...");
    
    $startTime = microtime(true);
    $result = $syncService->syncProducts($languageIds);
    $duration = round(microtime(true) - $startTime, 2);
    
    logMessage("Sync completed in {$duration} seconds");
    logMessage("Products synced: {$result['synced']}");
    logMessage("Products added: {$result['added']}");
    logMessage("Products updated: {$result['updated']}");
    logMessage("Products disabled: {$result['disabled']}");
    
    if (!empty($result['languages'])) {
        foreach ($result['languages'] as $langCode => $langStats) {
            if (isset($langStats['error'])) {
                logMessage("Language {$langCode} error: {$langStats['error']}", 'ERROR');
            } else {
                $translated = $langStats['translated'] ?? $langStats['synced'] ?? 0;
                logMessage("Language {$langCode}: {$translated} items processed");
            }
        }
    }
    
    logMessage("Daily stock sync completed successfully");
    exit(0);
    
} catch (Exception $e) {
    logMessage("Sync failed: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}
ENDOFFILE
```

## Step 3: Create create-vendor-orders.php

Run on your server:
```bash
cat > /home2/vsmjr110/api/public/cli/create-vendor-orders.php << 'ENDOFFILE'
#!/usr/bin/env php
<?php
/**
 * Daily Vendor Sales Order Creation Cron Job
 * Creates sales orders at vendor using CreateSalesOrder API based on reservations
 * 
 * Usage: php cli/create-vendor-orders.php
 * 
 * Recommended cron schedule: Daily at 17:00 (5:00 PM)
 */

// Set working directory
chdir(__DIR__ . '/..');

// Load dependencies
require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Models/Order.php';
require_once __DIR__ . '/../app/Models/OrderItem.php';
require_once __DIR__ . '/../app/Models/Product.php';
require_once __DIR__ . '/../app/Services/OrderService.php';
require_once __DIR__ . '/../app/Services/ReservationService.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';

// Initialize environment
Env::load();

// Increase execution time
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Logging function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    echo $logMessage;
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log($logMessage, 3, $logDir . '/vendor-orders.log');
}

try {
    logMessage("Starting daily vendor sales order creation...");
    
    $orderService = new OrderService();
    
    $startTime = microtime(true);
    $result = $orderService->createVendorSalesOrder();
    $duration = round(microtime(true) - $startTime, 2);
    
    logMessage("Vendor order creation completed in {$duration} seconds");
    logMessage("Orders processed: {$result['orders_processed']}");
    
    if (!empty($result['processed_orders'])) {
        logMessage("Successfully processed orders: " . implode(', ', $result['processed_orders']));
    }
    
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $error) {
            logMessage("Order {$error['order_number']} error: {$error['error']}", 'ERROR');
        }
    }
    
    if ($result['success']) {
        logMessage("Daily vendor sales order creation completed successfully");
        exit(0);
    } else {
        logMessage("Vendor order creation completed with errors", 'WARNING');
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("Vendor order creation failed: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    exit(1);
}
ENDOFFILE
```

## Step 4: Make Scripts Executable

```bash
chmod +x /home2/vsmjr110/api/public/cli/sync-stock.php
chmod +x /home2/vsmjr110/api/public/cli/create-vendor-orders.php
```

## Step 5: Test Scripts

```bash
# Test stock sync
/usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/sync-stock.php

# Test vendor orders
/usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/create-vendor-orders.php
```

## Step 6: Verify Scripts Exist

```bash
ls -la /home2/vsmjr110/api/public/cli/
```

You should see:
- `sync-stock.php`
- `create-vendor-orders.php`

## Step 7: Add Cron Jobs

Once scripts are created and tested, add these cron jobs in cPanel:

### Cron Job 1: Stock Sync (3:00 AM)
```
0 3 * * * /usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/sync-stock.php >> /home2/vsmjr110/api/public/storage/logs/cron-sync.log 2>&1
```

### Cron Job 2: Vendor Orders (17:00)
```
0 17 * * * /usr/local/bin/ea-php83 /home2/vsmjr110/api/public/cli/create-vendor-orders.php >> /home2/vsmjr110/api/public/storage/logs/cron-vendor-orders.log 2>&1
```

## Troubleshooting

If scripts fail, check:
1. **Logs:** `tail -f /home2/vsmjr110/api/public/storage/logs/sync-stock.log`
2. **Permissions:** `ls -la /home2/vsmjr110/api/public/cli/`
3. **PHP errors:** Check error_log in `/home2/vsmjr110/api/public/`

