<?php

if (!class_exists('Env')) {
    require_once __DIR__ . '/../Config/env.php';
}
require_once __DIR__ . '/DiagnosticsLoggerService.php';

/**
 * Lightweight file-based API cache with tag versioning.
 * Version bumps make invalidation O(1) without scanning all cache entries.
 */
class ApiCacheService
{
    private const DEFAULT_DIR = __DIR__ . '/../../storage/cache/api';
    private const TAG_VERSION_FILE = 'tag_versions.json';

    private $enabled;
    private $cacheDir;
    private $tagVersionFile;

    public function __construct()
    {
        Env::load();

        $this->enabled = strtolower((string) Env::get('API_CACHE_ENABLED', 'true')) === 'true';
        $configuredDir = trim((string) Env::get('API_CACHE_DIR', ''));
        if ($configuredDir !== '') {
            if (strpos($configuredDir, '/') === 0) {
                $this->cacheDir = $configuredDir;
            } else {
                $this->cacheDir = realpath(__DIR__ . '/../../') . '/' . ltrim($configuredDir, '/');
            }
        } else {
            $this->cacheDir = self::DEFAULT_DIR;
        }
        $this->tagVersionFile = rtrim($this->cacheDir, '/') . '/' . self::TAG_VERSION_FILE;

        if ($this->enabled) {
            $this->ensureDirectory();
            $this->ensureTagVersionFile();
        }
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getTtlProducts()
    {
        return max(1, intval(Env::get('API_CACHE_TTL_PRODUCTS', 120)));
    }

    public function getTtlTaxonomy()
    {
        return max(1, intval(Env::get('API_CACHE_TTL_TAXONOMY', 1800)));
    }

    public function getTtlTracking()
    {
        return max(1, intval(Env::get('API_CACHE_TTL_TRACKING', 60)));
    }

    public function buildKey($namespace, array $params = [], array $context = [], array $tags = [])
    {
        if (!$this->enabled) {
            return '';
        }

        $normalizedParams = $this->normalizeArray($params);
        $normalizedContext = $this->normalizeArray($context);
        $normalizedTags = array_values(array_unique(array_filter($tags, function ($tag) {
            return is_string($tag) && $tag !== '';
        })));
        sort($normalizedTags);

        $tagVersions = $this->getTagVersions($normalizedTags);

        $payload = [
            'namespace' => $namespace,
            'params' => $normalizedParams,
            'context' => $normalizedContext,
            'tag_versions' => $tagVersions,
        ];

        return hash('sha256', json_encode($payload));
    }

    public function get($key)
    {
        if (!$this->enabled || $key === '') {
            return null;
        }

        $path = $this->cachePath($key);
        if (!file_exists($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $entry = json_decode($raw, true);
        if (!is_array($entry)) {
            @unlink($path);
            return null;
        }

        $expiresAt = intval($entry['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
            @unlink($path);
            return null;
        }

        return $entry;
    }

    public function put($key, $body, $statusCode, $contentType, $ttl)
    {
        if (!$this->enabled || $key === '') {
            return;
        }

        $ttl = max(1, intval($ttl));
        $entry = [
            'expires_at' => time() + $ttl,
            'status_code' => intval($statusCode),
            'content_type' => $contentType ?: 'application/json; charset=utf-8',
            'body' => $body,
        ];

        $path = $this->cachePath($key);
        $tmp = $path . '.tmp';
        @file_put_contents($tmp, json_encode($entry), LOCK_EX);
        @rename($tmp, $path);
    }

    public function invalidateTags(array $tags)
    {
        if (!$this->enabled) {
            return;
        }

        $tags = array_values(array_unique(array_filter($tags, function ($tag) {
            return is_string($tag) && $tag !== '';
        })));

        if (empty($tags)) {
            return;
        }

        $versions = $this->readTagVersionMap();
        foreach ($tags as $tag) {
            $versions[$tag] = intval($versions[$tag] ?? 1) + 1;
        }

        $this->writeTagVersionMap($versions);
        DiagnosticsLoggerService::log('api_cache.tags_invalidated', [
            'tags' => $tags,
        ]);
    }

    private function cachePath($key)
    {
        return rtrim($this->cacheDir, '/') . '/' . $key . '.json';
    }

    private function ensureDirectory()
    {
        if (!is_dir($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0775, true) && !is_dir($this->cacheDir)) {
                DiagnosticsLoggerService::log('api_cache.mkdir_failed', [
                    'cache_dir' => $this->cacheDir,
                ]);
            }
        }
    }

    private function ensureTagVersionFile()
    {
        if (!file_exists($this->tagVersionFile)) {
            @file_put_contents($this->tagVersionFile, json_encode([]), LOCK_EX);
        }
    }

    private function normalizeArray(array $value)
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeArray($item);
            } elseif (is_bool($item)) {
                $value[$key] = $item ? 1 : 0;
            } elseif ($item === null) {
                $value[$key] = null;
            } else {
                $value[$key] = (string) $item;
            }
        }

        return $value;
    }

    private function getTagVersions(array $tags)
    {
        $map = $this->readTagVersionMap();
        $versions = [];
        foreach ($tags as $tag) {
            $versions[$tag] = intval($map[$tag] ?? 1);
        }

        ksort($versions);
        return $versions;
    }

    private function readTagVersionMap()
    {
        $this->ensureTagVersionFile();
        $raw = @file_get_contents($this->tagVersionFile);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function writeTagVersionMap(array $map)
    {
        $tmp = $this->tagVersionFile . '.tmp';
        @file_put_contents($tmp, json_encode($map), LOCK_EX);
        @rename($tmp, $this->tagVersionFile);
    }
}
