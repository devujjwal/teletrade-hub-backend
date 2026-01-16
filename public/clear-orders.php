<?php
/**
 * Clear all orders and related data
 * Use this to reset orders for testing
 */

require_once __DIR__ . '/../app/Config/database.php';

try {
    $db = Database::getConnection();
    
    echo "Clearing orders and related data...\n\n";
    
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate order-related tables in reverse dependency order
    $tables = [
        'order_items',
        'reservations',
        'orders',
        'addresses' // Clear addresses too if needed
    ];
    
    foreach ($tables as $table) {
        try {
            $db->exec("TRUNCATE TABLE `{$table}`");
            echo "✅ Cleared: {$table}\n";
        } catch (Exception $e) {
            echo "⚠️  {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n✅ All order data cleared successfully!\n";
    echo "You can now test order creation from scratch.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
