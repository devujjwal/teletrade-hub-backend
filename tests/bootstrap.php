<?php

/**
 * PHPUnit Bootstrap File
 * Sets up test environment and autoloading
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load environment
putenv('APP_ENV=testing');

// Require Composer autoloader if available
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Manual autoloader for classes without Composer
spl_autoload_register(function ($class) {
    // Remove namespace prefix if present
    $class = str_replace(['App\\', 'Tests\\'], '', $class);
    
    // Convert class name to file path
    $paths = [
        BASE_PATH . '/app/Services/' . $class . '.php',
        BASE_PATH . '/app/Controllers/' . $class . '.php',
        BASE_PATH . '/app/Models/' . $class . '.php',
        BASE_PATH . '/app/Middlewares/' . $class . '.php',
        BASE_PATH . '/app/Utils/' . $class . '.php',
        BASE_PATH . '/app/Config/' . $class . '.php',
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load core files
require_once BASE_PATH . '/app/Config/env.php';
require_once BASE_PATH . '/app/Config/database.php';
require_once BASE_PATH . '/app/Utils/Response.php';
require_once BASE_PATH . '/app/Utils/Validator.php';
require_once BASE_PATH . '/app/Utils/Sanitizer.php';
require_once BASE_PATH . '/app/Utils/SecurityLogger.php';

/**
 * Test Database Helper
 */
class TestDatabase
{
    private static $connection = null;
    
    public static function getConnection()
    {
        if (self::$connection === null) {
            try {
                // Use SQLite for tests
                self::$connection = new PDO('sqlite::memory:');
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                self::createSchema();
            } catch (PDOException $e) {
                die("Test database connection failed: " . $e->getMessage());
            }
        }
        
        return self::$connection;
    }
    
    public static function createSchema()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            vendor_article_id VARCHAR(100) NOT NULL,
            sku VARCHAR(100),
            name VARCHAR(255) NOT NULL,
            category_id INTEGER,
            brand_id INTEGER,
            base_price DECIMAL(10,2) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            available_quantity INTEGER DEFAULT 0,
            reserved_quantity INTEGER DEFAULT 0,
            is_available BOOLEAN DEFAULT 1,
            is_featured BOOLEAN DEFAULT 0,
            color VARCHAR(50),
            storage VARCHAR(50),
            ram VARCHAR(50),
            warranty_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            parent_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS brands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            logo_url VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            user_id INTEGER,
            guest_email VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            payment_status VARCHAR(50) DEFAULT 'unpaid',
            payment_method VARCHAR(50),
            transaction_id VARCHAR(255),
            subtotal DECIMAL(10,2) NOT NULL,
            tax DECIMAL(10,2) NOT NULL,
            shipping_cost DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'EUR',
            billing_address_id INTEGER,
            shipping_address_id INTEGER,
            vendor_order_id VARCHAR(100),
            notes TEXT,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER,
            product_name VARCHAR(255) NOT NULL,
            product_sku VARCHAR(100),
            vendor_article_id VARCHAR(100),
            quantity INTEGER NOT NULL,
            base_price DECIMAL(10,2) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            vendor_article_id VARCHAR(100) NOT NULL,
            vendor_reservation_id VARCHAR(100),
            quantity INTEGER NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            company VARCHAR(100),
            address_line1 VARCHAR(255) NOT NULL,
            address_line2 VARCHAR(255),
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100),
            postal_code VARCHAR(20) NOT NULL,
            country VARCHAR(2) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            is_default BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS pricing_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            rule_type VARCHAR(20) NOT NULL,
            entity_id INTEGER,
            markup_type VARCHAR(20) DEFAULT 'percentage',
            markup_value DECIMAL(10,2) NOT NULL,
            priority INTEGER DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            is_admin BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS vendor_api_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            endpoint VARCHAR(255) NOT NULL,
            method VARCHAR(10) NOT NULL,
            request_payload TEXT,
            response_payload TEXT,
            status_code INTEGER,
            duration_ms INTEGER,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key VARCHAR(100) UNIQUE NOT NULL,
            value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";
        
        self::$connection->exec($sql);
        
        // Insert default pricing rule
        self::$connection->exec("
            INSERT INTO pricing_rules (rule_type, markup_type, markup_value, priority, is_active)
            VALUES ('global', 'percentage', 15.0, 0, 1)
        ");
        
        // Insert default settings
        self::$connection->exec("
            INSERT INTO settings (key, value) VALUES 
            ('tax_rate', '19.0'),
            ('shipping_cost', '9.99'),
            ('free_shipping_threshold', '100.0')
        ");
    }
    
    public static function reset()
    {
        if (self::$connection) {
            self::$connection->exec("DELETE FROM products");
            self::$connection->exec("DELETE FROM categories");
            self::$connection->exec("DELETE FROM brands");
            self::$connection->exec("DELETE FROM orders");
            self::$connection->exec("DELETE FROM order_items");
            self::$connection->exec("DELETE FROM reservations");
            self::$connection->exec("DELETE FROM addresses");
            self::$connection->exec("DELETE FROM users");
            self::$connection->exec("DELETE FROM vendor_api_logs");
            
            // Reset pricing rules to default
            self::$connection->exec("DELETE FROM pricing_rules");
            self::$connection->exec("
                INSERT INTO pricing_rules (rule_type, markup_type, markup_value, priority, is_active)
                VALUES ('global', 'percentage', 15.0, 0, 1)
            ");
        }
    }
    
    public static function close()
    {
        self::$connection = null;
    }
}

/**
 * Mock Vendor API Responses
 */
class MockVendorApi
{
    public static $responses = [];
    public static $callLog = [];
    
    public static function setResponse($endpoint, $response)
    {
        self::$responses[$endpoint] = $response;
    }
    
    public static function getResponse($endpoint)
    {
        self::$callLog[] = $endpoint;
        return self::$responses[$endpoint] ?? ['error' => 'No mock response configured'];
    }
    
    public static function reset()
    {
        self::$responses = [];
        self::$callLog = [];
    }
    
    public static function getCallLog()
    {
        return self::$callLog;
    }
}

// Override Database class for testing
class Database
{
    public static function getConnection()
    {
        return TestDatabase::getConnection();
    }
    
    public static function closeConnection()
    {
        TestDatabase::close();
    }
}

echo "✓ Test environment initialized\n";

