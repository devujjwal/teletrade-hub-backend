<?php

require_once __DIR__ . '/env.php';

/**
 * Database Configuration and Connection Manager
 */
class Database
{
    private static $connection = null;

    /**
     * Get PDO database connection (Singleton)
     */
    public static function getConnection()
    {
        if (self::$connection === null) {
            Env::load();

            $host = Env::get('DB_HOST', 'localhost');
            $dbname = Env::get('DB_NAME', 'vsmjr110_api');
            $username = Env::get('DB_USER', 'vsmjr110_ujjwal');
            $password = Env::get('DB_PASSWORD', '');

            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$connection = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check your configuration.");
            }
        }

        return self::$connection;
    }

    /**
     * Close database connection
     */
    public static function closeConnection()
    {
        self::$connection = null;
    }
}

