<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Config/env.php';
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Utils/Language.php';
require_once __DIR__ . '/../app/Services/VendorApiService.php';
require_once __DIR__ . '/../app/Services/ProductSyncService.php';

Env::load();

$encodedLanguageIds = $argv[1] ?? '';
$languageIds = null;

if ($encodedLanguageIds !== '') {
    $decoded = base64_decode($encodedLanguageIds, true);
    $parsed = $decoded !== false ? json_decode($decoded, true) : null;

    if (is_array($parsed) && !empty($parsed)) {
        $languageIds = array_values(array_filter(array_map('intval', $parsed), static function ($value) {
            return $value > 0;
        }));
    }
}

try {
    $service = new ProductSyncService();
    $service->syncProducts($languageIds ?: null);
} catch (Throwable $e) {
    error_log('Background product sync failed: ' . $e->getMessage());
    error_log($e->getTraceAsString());
    exit(1);
}

