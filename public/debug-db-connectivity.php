<?php

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';

Env::load();

header('Content-Type: application/json; charset=utf-8');

$providedKey = $_GET['key'] ?? '';
$expectedKey = (string) Env::get('DEBUG_KEY', '');

if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

function parse_supabase_connection_target($databaseUrl)
{
    $url = trim((string) $databaseUrl);
    if ($url === '') {
        return null;
    }

    if (strpos($url, '://') === false) {
        $parts = [];
        foreach (preg_split('/\s+/', $url) as $chunk) {
            if (strpos($chunk, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $chunk, 2);
            $parts[$k] = $v;
        }

        return [
            'host' => $parts['host'] ?? null,
            'port' => isset($parts['port']) ? (int) $parts['port'] : null,
            'dbname' => $parts['dbname'] ?? null,
            'user' => $parts['user'] ?? null
        ];
    }

    $parsed = parse_url($url);
    if (!is_array($parsed)) {
        return null;
    }

    return [
        'host' => $parsed['host'] ?? null,
        'port' => isset($parsed['port']) ? (int) $parsed['port'] : null,
        'dbname' => isset($parsed['path']) ? ltrim($parsed['path'], '/') : null,
        'user' => $parsed['user'] ?? null
    ];
}

function test_tcp_port($host, $port)
{
    $errno = 0;
    $errstr = '';
    $start = microtime(true);
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    $elapsedMs = (int) round((microtime(true) - $start) * 1000);

    if ($socket !== false) {
        fclose($socket);
        return [
            'reachable' => true,
            'elapsed_ms' => $elapsedMs
        ];
    }

    return [
        'reachable' => false,
        'elapsed_ms' => $elapsedMs,
        'errno' => $errno,
        'error' => $errstr
    ];
}

$databaseUrl = (string) Env::get('SUPABASE_DATABASE_URL', Env::get('DATABASE_URL', ''));
$target = parse_supabase_connection_target($databaseUrl);
$host = $target['host'] ?? Env::get('DB_HOST', '');
$configuredPort = (int) ($target['port'] ?? Env::get('DB_PORT', 0));

$report = [
    'success' => true,
    'app_env' => Env::get('APP_ENV', 'unknown'),
    'php_version' => PHP_VERSION,
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'openssl' => extension_loaded('openssl')
    ],
    'env' => [
        'db_connection' => Env::get('DB_CONNECTION', null),
        'has_supabase_database_url' => $databaseUrl !== '',
        'target_host' => $host,
        'configured_port' => $configuredPort,
        'target_db' => $target['dbname'] ?? Env::get('DB_NAME', Env::get('DB_DATABASE', null)),
        'target_user' => $target['user'] ?? Env::get('DB_USER', Env::get('DB_USERNAME', null))
    ],
    'dns' => [
        'resolved_ips' => $host !== '' ? array_values(array_unique(gethostbynamel($host) ?: [])) : []
    ],
    'tcp_checks' => [],
    'pdo_check' => null
];

if ($host !== '') {
    foreach ([5432, 6543] as $port) {
        $report['tcp_checks']["port_{$port}"] = test_tcp_port($host, $port);
    }
}

try {
    Database::closeConnection();
    $db = Database::getConnection();
    $stmt = $db->query('SELECT 1');
    $report['pdo_check'] = [
        'connected' => true,
        'result' => $stmt->fetchColumn()
    ];
} catch (Throwable $e) {
    $report['pdo_check'] = [
        'connected' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
