<?php

require_once __DIR__ . '/ApiCacheService.php';
require_once __DIR__ . '/FrontendRevalidateService.php';
require_once __DIR__ . '/DiagnosticsLoggerService.php';

/**
 * Coordinates API cache invalidation and frontend ISR revalidation.
 */
class ContentInvalidationService
{
    private $cache;
    private $frontendRevalidator;

    public function __construct()
    {
        $this->cache = new ApiCacheService();
        $this->frontendRevalidator = new FrontendRevalidateService();
    }

    public function invalidate(array $tags = [], array $revalidatePayload = [])
    {
        DiagnosticsLoggerService::log('cache.invalidate.start', [
            'tags' => array_values($tags),
            'revalidate_payload_keys' => array_keys($revalidatePayload),
        ]);

        if (!empty($tags)) {
            $this->cache->invalidateTags($tags);
        }

        if (!empty($revalidatePayload)) {
            try {
                $revalidateSuccess = $this->frontendRevalidator->trigger($revalidatePayload);
                DiagnosticsLoggerService::log('cache.invalidate.revalidate_trigger', [
                    'success' => $revalidateSuccess ? 1 : 0,
                ]);
            } catch (Throwable $e) {
                error_log('Frontend revalidation trigger failed: ' . $e->getMessage());
                DiagnosticsLoggerService::log('cache.invalidate.revalidate_error', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        DiagnosticsLoggerService::log('cache.invalidate.complete', [
            'tags' => array_values($tags),
        ]);
    }
}
