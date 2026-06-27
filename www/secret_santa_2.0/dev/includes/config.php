<?php
// ============================================================
// config.php
// ============================================================

// ------------------------------------------------------------
// 1. LOCATE AND READ env file
// ------------------------------------------------------------
$envFilePath = __DIR__ . '/../secret_santa_env.conf';

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
// 2. PULL SECRETS FROM INFISICAL
// ------------------------------------------------------------
require_once '/config/manconfig/infisical.php';

// Ensure session is started so infisical can use it as cache
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$secrets = infisical_get_many([
    'HLDEV_MYSQL_DB_USER',
    'HLDEV_MYSQL_DB_PWD',
    'HLPRD_MYSQL_DB_USER',
    'HLPRD_MYSQL_DB_PWD',
    'SS_GMAIL_MAILER_PASSWORD',
    'SS_TWILIO_ACCOUNT_SID',
    'SS_TWILIO_AUTH_TOKEN',
    'SS_TWILIO_FROM_NUMBER',
]);

// ------------------------------------------------------------
// 3. DATABASE CREDENTIALS PER ENVIRONMENT
// ------------------------------------------------------------
// DB credentials key prefix matches APP_ENV (dev -> HLDEV_, prd -> HLPRD_)
$secretPrefix = strtoupper($appEnv === 'prd' ? 'HLPRD' : 'HLDEV');

$dbConfigs = [
    'dev' => [
        'host'   => $envSettings['DB_HOST'] ?? '',
        'port'   => $envSettings['DB_PORT'] ?? '3307',
        'dbname' => $envSettings['DB_NAME'] ?? 'HLDEV',
        'user'   => $secrets[$secretPrefix . '_MYSQL_DB_USER'] ?? '',
        'pass'   => $secrets[$secretPrefix . '_MYSQL_DB_PWD']  ?? '',
    ],
    'prd' => [
        'host'   => $envSettings['DB_HOST'] ?? '',
        'port'   => $envSettings['DB_PORT'] ?? '3307',
        'dbname' => $envSettings['DB_NAME'] ?? 'HLPRD',
        'user'   => $secrets[$secretPrefix . '_MYSQL_DB_USER'] ?? '',
        'pass'   => $secrets[$secretPrefix . '_MYSQL_DB_PWD']  ?? '',
    ],
];

if (!array_key_exists($appEnv, $dbConfigs)) {
    die('Configuration error: Unknown APP_ENV value "' . htmlspecialchars($appEnv) . '".');
}

$db = $dbConfigs[$appEnv];


// ------------------------------------------------------------
// 4. DEFINE CONSTANTS
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
define('DB_PORT',   $db['port']);
define('DB_CHARSET','utf8mb4');

// -- Application --
define('APP_NAME',  'Secret Santa');
define('APP_URL',   $envSettings['APP_URL'] ?? '');

// -- Session --
define('SESSION_NAME',    'ss_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('REMEMBER_ME_DAYS', 60); // how long the "Remember me" cookie lasts

// -- Mail --
define('MAIL_PASSWORD_SECRET', $secrets['SS_GMAIL_MAILER_PASSWORD'] ?? '');

// -- Security --
// Note: actual expiry is read from SS_CONFIG key RESET_TOKEN_EXPIRY_MINS at runtime
// This constant is a fallback only used before the DB is available
define('PASSWORD_RESET_EXPIRY_FALLBACK', 60); // minutes

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
unset($envRaw, $envSettings, $appEnv, $dbConfigs, $db, $envFilePath, $key, $val, $line, $secrets);