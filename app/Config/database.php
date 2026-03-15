<?php

require_once __DIR__ . '/env.php';

/**
 * Database Configuration and Connection Manager
 */
class Database
{
    private static $connection = null;
    private static $driver = null;

    /**
     * Get PDO database connection (Singleton)
     */
    public static function getConnection()
    {
        if (self::$connection === null) {
            Env::load();

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];

            $dbConnection = strtolower((string)Env::get('DB_CONNECTION', 'mysql'));
            $databaseUrl = Env::get('SUPABASE_DATABASE_URL', Env::get('DATABASE_URL', ''));

            try {
                if ($dbConnection === 'pgsql' || !empty($databaseUrl)) {
                    self::$driver = 'pgsql';
                    // Supabase pooler (transaction mode) is not compatible with native prepared statements.
                    $options[PDO::ATTR_EMULATE_PREPARES] = true;

                    // Prefer full DATABASE_URL style connection string when provided.
                    if (!empty($databaseUrl)) {
                        $parts = self::parseDatabaseUrl($databaseUrl);
                        if ($parts === null) {
                            throw new Exception('Invalid SUPABASE_DATABASE_URL format');
                        }

                        $host = $parts['host'] ?? Env::get('DB_HOST', 'localhost');
                        $port = $parts['port'] ?? intval(Env::get('DB_PORT', 5432));
                        $dbname = $parts['dbname'] ?? Env::get('DB_NAME', Env::get('DB_DATABASE', 'postgres'));
                        $username = $parts['user'] ?? Env::get('DB_USER', Env::get('DB_USERNAME', 'postgres'));
                        $password = $parts['pass'] ?? Env::get('DB_PASSWORD', '');
                        $sslmode = $parts['sslmode'] ?? Env::get('DB_SSLMODE', '');
                    } else {
                        $host = Env::get('DB_HOST', 'localhost');
                        $port = intval(Env::get('DB_PORT', 5432));
                        $dbname = Env::get('DB_NAME', Env::get('DB_DATABASE', 'postgres'));
                        $username = Env::get('DB_USER', Env::get('DB_USERNAME', 'postgres'));
                        $password = Env::get('DB_PASSWORD', '');
                        $sslmode = Env::get('DB_SSLMODE', '');
                    }

                    $connectionAttempts = self::buildPostgresConnectionAttempts($host, $port, $dbname, $sslmode);
                    $lastException = null;

                    foreach ($connectionAttempts as $attempt) {
                        $dsn = self::buildPostgresDsn(
                            $attempt['host'],
                            $attempt['port'],
                            $attempt['dbname'],
                            $attempt['sslmode']
                        );

                        try {
                            self::$connection = new PDO($dsn, $username, $password, $options);
                            break;
                        } catch (PDOException $e) {
                            $lastException = $e;
                            error_log("Database Connection Error: " . $e->getMessage());
                        }
                    }

                    if (self::$connection === null) {
                        throw $lastException ?: new PDOException('Unable to connect to PostgreSQL');
                    }

                    self::$connection->exec("SET TIME ZONE 'UTC'");
                } else {
                    self::$driver = 'mysql';

                    $host = Env::get('DB_HOST', 'localhost');
                    $dbname = Env::get('DB_NAME', Env::get('DB_DATABASE', 'vsmjr110_api'));
                    $username = Env::get('DB_USER', Env::get('DB_USERNAME', 'vsmjr110_ujjwal'));
                    $password = Env::get('DB_PASSWORD', '');
                    $port = intval(Env::get('DB_PORT', 3306));

                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
                    }
                    self::$connection = new PDO($dsn, $username, $password, $options);
                }
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
        self::$driver = null;
    }

    /**
     * Get active PDO driver name
     */
    public static function getDriver()
    {
        if (self::$connection === null) {
            self::getConnection();
        }

        return self::$driver ?: (self::$connection ? self::$connection->getAttribute(PDO::ATTR_DRIVER_NAME) : null);
    }

    /**
     * Check if active DB is PostgreSQL
     */
    public static function isPostgres()
    {
        return self::getDriver() === 'pgsql';
    }

    /**
     * Parse DATABASE_URL/SUPABASE_DATABASE_URL safely, including raw '@' in password.
     */
    private static function parseDatabaseUrl($databaseUrl)
    {
        $url = trim((string)$databaseUrl);
        if ($url === '') {
            return null;
        }

        // Handle non-URL DSN strings like: host=... port=... dbname=... user=... password=...
        if (strpos($url, '://') === false) {
            $parts = [];
            foreach (preg_split('/\s+/', $url) as $chunk) {
                if (strpos($chunk, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $chunk, 2);
                $parts[$k] = $v;
            }
            if (!empty($parts)) {
                return [
                    'host' => $parts['host'] ?? null,
                    'port' => isset($parts['port']) ? intval($parts['port']) : null,
                    'dbname' => $parts['dbname'] ?? null,
                    'user' => $parts['user'] ?? null,
                    'pass' => $parts['password'] ?? null
                ];
            }
            return null;
        }

        // URL format parsing that tolerates unencoded '@' in password by splitting on last '@'
        $schemePos = strpos($url, '://');
        $remainder = substr($url, $schemePos + 3);
        $pathPos = strpos($remainder, '/');
        if ($pathPos === false) {
            return null;
        }

        $authority = substr($remainder, 0, $pathPos);
        $pathAndQuery = substr($remainder, $pathPos + 1);
        $queryPos = strpos($pathAndQuery, '?');
        $dbname = $queryPos === false ? $pathAndQuery : substr($pathAndQuery, 0, $queryPos);
        $queryString = $queryPos === false ? '' : substr($pathAndQuery, $queryPos + 1);
        $dbname = trim($dbname, '/');

        $user = null;
        $pass = null;
        $hostPort = $authority;

        $lastAt = strrpos($authority, '@');
        if ($lastAt !== false) {
            $userInfo = substr($authority, 0, $lastAt);
            $hostPort = substr($authority, $lastAt + 1);

            if (strpos($userInfo, ':') !== false) {
                [$user, $pass] = explode(':', $userInfo, 2);
            } else {
                $user = $userInfo;
            }
        }

        $host = $hostPort;
        $port = null;

        if (strpos($hostPort, ':') !== false) {
            [$host, $portPart] = explode(':', $hostPort, 2);
            if ($portPart !== '') {
                $port = intval($portPart);
            }
        }

        $queryParams = [];
        if ($queryString !== '') {
            parse_str($queryString, $queryParams);
        }

        return [
            'host' => $host !== '' ? $host : null,
            'port' => $port,
            'dbname' => $dbname !== '' ? $dbname : null,
            'user' => $user !== null ? urldecode($user) : null,
            'pass' => $pass !== null ? urldecode($pass) : null,
            'sslmode' => isset($queryParams['sslmode']) ? (string)$queryParams['sslmode'] : null
        ];
    }

    private static function buildPostgresConnectionAttempts($host, $port, $dbname, $sslmode)
    {
        $attempts = [[
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'sslmode' => self::normalizeSslmode($host, $sslmode)
        ]];

        // Shared hosting commonly cannot reach transaction pooler on 6543 but can reach session pooler on 5432.
        if (self::isSupabasePoolerHost($host) && (int)$port === 6543) {
            $attempts[] = [
                'host' => $host,
                'port' => 5432,
                'dbname' => $dbname,
                'sslmode' => self::normalizeSslmode($host, $sslmode)
            ];
        }

        return $attempts;
    }

    private static function buildPostgresDsn($host, $port, $dbname, $sslmode)
    {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        if (!empty($sslmode)) {
            $dsn .= ";sslmode={$sslmode}";
        }
        return $dsn;
    }

    private static function normalizeSslmode($host, $sslmode)
    {
        if (!empty($sslmode)) {
            return $sslmode;
        }

        if (strpos((string)$host, 'supabase.co') !== false) {
            return 'require';
        }

        return '';
    }

    private static function isSupabasePoolerHost($host)
    {
        return is_string($host) && strpos($host, '.pooler.supabase.com') !== false;
    }
}
