<?php
/**
 * Setup script to initialize admin user with correct password
 * Run this after database migration to set the default password
 * 
 * Usage: php public/setup-admin.php
 * Or visit: https://your-domain.com/setup-admin.php (remove after setup)
 */

require_once __DIR__ . '/../app/Config/database.php';

try {
    $db = Database::getConnection();
    
    // Default admin password
    $defaultPassword = 'Ujjwal@2026';
    $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
    
    // Update admin user password
    $sql = "UPDATE admin_users SET password_hash = :password_hash WHERE username = 'admin'";
    $stmt = $db->prepare($sql);
    $stmt->execute([':password_hash' => $passwordHash]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Admin password set successfully!\n";
        echo "Username: admin\n";
        echo "Password: {$defaultPassword}\n";
        echo "\n⚠️  IMPORTANT: Delete this script after setup for security!\n";
    } else {
        echo "❌ No admin user found. Please run migrations first.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
