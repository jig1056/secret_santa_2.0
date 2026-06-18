<?php
// ============================================================
// test_config.php
// Upload to your dev root, visit it in browser, then DELETE IT
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<pre>';

// Step 1: Check env file path
$envFilePath = __DIR__ . '/secret_santa_env.conf';
echo "1. Looking for env file at: " . $envFilePath . "\n";
echo "   Exists: " . (file_exists($envFilePath) ? 'YES' : 'NO') . "\n\n";

// Step 2: Try to read it
if (file_exists($envFilePath)) {
    $raw = file_get_contents($envFilePath);
    echo "2. Env file contents:\n" . htmlspecialchars($raw) . "\n\n";
} else {
    echo "2. CANNOT READ - file not found\n\n";
    // Show what IS in this directory
    echo "   Files in " . __DIR__ . ":\n";
    foreach (scandir(__DIR__) as $f) echo "   - $f\n";
}

// Step 3: Check infisical file
$infisicalPath = '/config/manconfig/infisical.php';
echo "3. Infisical helper at: " . $infisicalPath . "\n";
echo "   Exists: " . (file_exists($infisicalPath) ? 'YES' : 'NO') . "\n\n";

// Step 4: Try loading config
echo "4. Loading config.php...\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "   OK - APP_ENV: " . APP_ENV . "\n";
    echo "   DB_HOST: " . DB_HOST . "\n";
    echo "   DB_NAME: " . DB_NAME . "\n";
    echo "   DB_PORT: " . DB_PORT . "\n";
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   File:  " . $e->getFile() . "\n";
    echo "   Line:  " . $e->getLine() . "\n";
}

echo '</pre>';