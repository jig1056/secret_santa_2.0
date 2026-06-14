<?php
// ============================================================
// config.php
// ============================================================

// ------------------------------------------------------------
// 1. LOCATE AND READ env file
// ------------------------------------------------------------
$envFilePath = '/config/manconfig/secret_santa_env.conf';

if (!file_exists($envFilePath)) {
    die('Configuration error: env file not found at ' . $envFilePath);
}

// Read manually -- avoids parse_ini_file comment-handling
// differences across PHP versions
$envRaw = file_get_contents($envFilePath);
if ($envRaw === false) {
    die('Configuration error: Could not read env file.');
}

$envSettings = [];
foreach (explode("\n", $envRaw) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') !== false) {
        [$key, $val] = explode('=', $line, 2);
        $envSettings[trim($key)] = trim($val);
    }
}

if (empty($envSettings['APP_ENV'])) {
    die('Configuration error: APP_ENV not set. Keys found: ' . implode(', ', array_keys($envSettings)));
}

$appEnv = strtolower($envSettings['APP_ENV']);


// ------------------------------------------------------------
// 2. DATABASE CREDENTIALS PER ENVIRONMENT
// ------------------------------------------------------------
$dbConfigs = [
    'dev' => [
        'host'   => 'prod2.home',
        'dbname' => 'HLDEV',
        'user'   => 'zoe',
        'pass'   => 'p9tX5vT3gZ6u2yH7QwXe',
    ],
    'prd' => [
        'host'   => 'localhost',
        'dbname' => 'HLPRD',
        'user'   => 'ss_prd_user',
        'pass'   => 'your_prd_password_here',
    ],
];

if (!array_key_exists($appEnv, $dbConfigs)) {
    die('Configuration error: Unknown APP_ENV value "' . htmlspecialchars($appEnv) . '".');
}

$db = $dbConfigs[$appEnv];


// ------------------------------------------------------------
// 3. DEFINE CONSTANTS
// ------------------------------------------------------------

// -- Environment --
define('APP_ENV',   $appEnv);
define('IS_DEV',    $appEnv === 'dev');
define('IS_PRD',    $appEnv === 'prd');

// -- Database --
define('DB_HOST',   $db['host']);
define('DB_NAME',   $db['dbname']);
define('DB_USER',   $db['user']);
define('DB_PASS',   $db['pass']);
define('DB_PORT',   '3307');
define('DB_CHARSET','utf8mb4');

// -- Application --
define('APP_NAME',  'Secret Santa');
define('APP_URL',   IS_DEV
    ? 'https://web-ace.nelsonone.com/secret_santa_2.0/dev'
    : 'https://web-ace.nelsonone.com/secret_santa_2.0/prd'
);

// -- Session --
define('SESSION_NAME',    'ss_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// -- Security --
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour in seconds

// -- Error display: on in dev, off in prod --
if (IS_DEV) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Clean up -- don't leave credentials floating in scope
unset($envRaw, $envSettings, $appEnv, $dbConfigs, $db, $envFilePath, $key, $val, $line);