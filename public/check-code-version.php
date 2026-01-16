<?php
/**
 * Check if the code is updated with the fix
 */

header('Content-Type: text/plain');

echo "=== CODE VERSION CHECK ===\n\n";

// Check Product.php
$productFile = __DIR__ . '/../app/Models/Product.php';
$productCode = file_get_contents($productFile);

echo "1. Checking Product.php:\n";
if (strpos($productCode, ':meta_title') !== false) {
    echo "   ✅ FIXED - Contains :meta_title\n";
} else {
    echo "   ❌ NOT FIXED - Missing :meta_title\n";
}

if (strpos($productCode, ':meta_description') !== false) {
    echo "   ✅ FIXED - Contains :meta_description\n";
} else {
    echo "   ❌ NOT FIXED - Missing :meta_description\n";
}

// Check ProductSyncService.php
$syncFile = __DIR__ . '/../app/Services/ProductSyncService.php';
$syncCode = file_get_contents($syncFile);

echo "\n2. Checking ProductSyncService.php:\n";
if (strpos($syncCode, "':meta_title'") !== false) {
    echo "   ✅ FIXED - Contains ':meta_title'\n";
} else {
    echo "   ❌ NOT FIXED - Missing ':meta_title'\n";
}

if (strpos($syncCode, "':meta_description'") !== false) {
    echo "   ✅ FIXED - Contains ':meta_description'\n";
} else {
    echo "   ❌ NOT FIXED - Missing ':meta_description'\n";
}

// Check git status
echo "\n3. Git Status:\n";
if (file_exists(__DIR__ . '/../.git')) {
    chdir(__DIR__ . '/..');
    $gitLog = shell_exec('git log --oneline -1 2>&1');
    echo "   Current commit: " . trim($gitLog) . "\n";
    
    $gitStatus = shell_exec('git status --short 2>&1');
    if (empty(trim($gitStatus))) {
        echo "   ✅ No uncommitted changes\n";
    } else {
        echo "   ⚠️  Uncommitted changes:\n";
        echo "   " . str_replace("\n", "\n   ", trim($gitStatus)) . "\n";
    }
} else {
    echo "   ⚠️  Not a git repository\n";
}

// Check PHP opcache
echo "\n4. PHP Opcache Status:\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status && $status['opcache_enabled']) {
        echo "   ⚠️  OPCACHE IS ENABLED\n";
        echo "   Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "   \n";
        echo "   TO FIX: Run this command on your server:\n";
        echo "   php -r 'opcache_reset(); echo \"Cache cleared\\n\";'\n";
        echo "   OR: touch /home2/vsmjr110/api/public/index.php\n";
    } else {
        echo "   ✅ Opcache disabled or not active\n";
    }
} else {
    echo "   ℹ️  Opcache not available\n";
}

// Count parameters in Product::create
echo "\n5. Parameter Count in Product::create():\n";
preg_match('/VALUES\s*\((.*?)\)/s', $productCode, $matches);
if (!empty($matches[1])) {
    $params = explode(',', $matches[1]);
    $paramCount = count($params);
    echo "   Found " . $paramCount . " parameters in VALUES clause\n";
    
    // Should be 47 parameters (45 original + 2 meta fields)
    if ($paramCount >= 47) {
        echo "   ✅ Correct parameter count (includes meta fields)\n";
    } else {
        echo "   ❌ MISSING PARAMETERS - Expected 47+, got " . $paramCount . "\n";
    }
}

echo "\n6. File Modification Times:\n";
echo "   Product.php: " . date('Y-m-d H:i:s', filemtime($productFile)) . "\n";
echo "   ProductSyncService.php: " . date('Y-m-d H:i:s', filemtime($syncFile)) . "\n";

echo "\n=== INSTRUCTIONS ===\n";
echo "If any checks show ❌ or ⚠️, run these commands on your server:\n\n";
echo "cd /home2/vsmjr110/api\n";
echo "git fetch origin\n";
echo "git reset --hard origin/main\n";
echo "php -r 'if(function_exists(\"opcache_reset\")) opcache_reset();'\n";
echo "touch public/index.php\n\n";
echo "Then check again: https://api.vs-mjrinfotech.com/check-code-version.php\n";
