<?php
/**
 * Update settings with company information
 * Run this once to populate settings from migration defaults
 */

require_once __DIR__ . '/../app/Config/database.php';

try {
    $db = Database::getConnection();
    
    $settings = [
        'site_name' => 'Telecommunication Trading e.K.',
        'site_email' => 'tctradingek@gmail.com',
        'address' => 'Marienstraße 20, Stuttgart, Deutschland, Germany - 70178',
        'contact_number' => '+491737109267',
        'whatsapp_number' => '+491737109267',
    ];
    
    foreach ($settings as $key => $value) {
        $sql = "INSERT INTO settings (`key`, `value`, `type`, `description`) 
                VALUES (:key, :value, 'string', :description)
                ON DUPLICATE KEY UPDATE `value` = :update_value";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
            ':description' => ucfirst(str_replace('_', ' ', $key)),
            ':update_value' => $value
        ]);
    }
    
    echo "✅ Settings updated successfully!\n\n";
    echo "Updated values:\n";
    foreach ($settings as $key => $value) {
        echo "  - {$key}: {$value}\n";
    }
    
    echo "\n⚠️  Remember to hard refresh your browser (Cmd+Shift+R or Ctrl+Shift+R)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
