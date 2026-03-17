<?php

/**
 * Minimal JSON-lines diagnostics logger for cache/revalidation observability.
 */
class DiagnosticsLoggerService
{
    private const LOG_FILE = __DIR__ . '/../../storage/logs/cache-diagnostics.log';

    public static function log($event, array $payload = [])
    {
        try {
            $dir = dirname(self::LOG_FILE);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $entry = [
                'timestamp' => gmdate('c'),
                'event' => (string) $event,
                'payload' => $payload,
            ];

            @file_put_contents(self::LOG_FILE, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            error_log('Diagnostics logger write failed: ' . $e->getMessage());
        }
    }

    public static function readRecent($limit = 100)
    {
        $limit = max(1, min(500, intval($limit)));

        if (!file_exists(self::LOG_FILE)) {
            return [];
        }

        $lines = @file(self::LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || empty($lines)) {
            return [];
        }

        $slice = array_slice($lines, -$limit);
        $events = [];
        foreach ($slice as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }
}
