<?php
// ============================================================
// infisical.php
// Reusable Infisical secret fetcher using Universal Auth.
// Lives in /config/manconfig/ so it's available to all apps.
//
// USAGE:
//   require_once '/config/manconfig/infisical.php';
//   $password = infisical_get('HLDEV_MYSQL_DB_PWD');
// ============================================================

// ------------------------------------------------------------
// CONFIGURATION — loaded from .env_infisical
// ------------------------------------------------------------ 
$_infisical_env_file = __DIR__ . '/.env_infisical';
if (!file_exists($_infisical_env_file)) {
    throw new RuntimeException('Infisical config missing: ' . $_infisical_env_file);
}
foreach (file($_infisical_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
    if ($_line[0] === '#' || strpos($_line, '=') === false) continue;
    [$_key, $_val] = explode('=', $_line, 2);
    putenv(trim($_key) . '=' . trim($_val));
}
unset($_infisical_env_file, $_line, $_key, $_val);

define('INFISICAL_HOST',          getenv('INFISICAL_HOST'));
define('INFISICAL_CLIENT_ID',     getenv('INFISICAL_CLIENT_ID'));
define('INFISICAL_CLIENT_SECRET', getenv('INFISICAL_CLIENT_SECRET'));
define('INFISICAL_PROJECT_ID',    getenv('INFISICAL_PROJECT_ID'));
define('INFISICAL_ENVIRONMENT',   getenv('INFISICAL_ENVIRONMENT'));
define('INFISICAL_SECRET_PATH',   getenv('INFISICAL_SECRET_PATH'));

// ------------------------------------------------------------
// Internal in-memory cache — within a single PHP request
// ------------------------------------------------------------
$_infisical_cache = [];
$_infisical_token = null;

// ------------------------------------------------------------
// infisical_get($secretName, $default = null)
// Fetches a single secret value by name.
// Returns $default if the secret is not found or on error.
// ------------------------------------------------------------
function infisical_get(string $secretName, ?string $default = null): ?string {
    global $_infisical_cache;

    // 1. In-memory cache (within this request)
    if (array_key_exists($secretName, $_infisical_cache)) {
        return $_infisical_cache[$secretName];
    }

    // 2. Session cache (across requests, server-side only)
    if (isset($_SESSION['_infisical'][$secretName])) {
        $_infisical_cache[$secretName] = $_SESSION['_infisical'][$secretName];
        return $_SESSION['_infisical'][$secretName];
    }

    // 3. Fetch from Infisical API and store in session
    try {
        _infisical_refresh_session();
        $value = $_SESSION['_infisical'][$secretName] ?? null;
        $_infisical_cache[$secretName] = $value;
        return $value ?? $default;
    } catch (Exception $e) {
        error_log('Infisical error fetching ' . $secretName . ': ' . $e->getMessage());
        return $default;
    }
}

// ------------------------------------------------------------
// infisical_get_many(array $secretNames)
// Fetches multiple secrets at once. Returns associative array.
// More efficient than calling infisical_get() in a loop.
// ------------------------------------------------------------
function infisical_get_many(array $secretNames): array {
    global $_infisical_cache;

    // Check if all requested secrets are already in session
    $allCached = true;
    foreach ($secretNames as $name) {
        if (!isset($_SESSION['_infisical'][$name])) { $allCached = false; break; }
    }

    // If any are missing, refresh from Infisical into session
    if (!$allCached) {
        try {
            _infisical_refresh_session();
        } catch (Exception $e) {
            error_log('Infisical refresh error: ' . $e->getMessage());
        }
    }

    $results = [];
    foreach ($secretNames as $name) {
        $value = $_SESSION['_infisical'][$name] ?? null;
        $_infisical_cache[$name] = $value;
        $results[$name] = $value;
    }
    return $results;
}

// ------------------------------------------------------------
// Internal: fetch all secrets from Infisical and store in session
// Called once per session (on first request that needs secrets)
// ------------------------------------------------------------
function _infisical_refresh_session(): void {
    global $_infisical_token;

    if (!$_infisical_token) {
        $_infisical_token = _infisical_authenticate();
    }

    // Fetch all secrets from the project/environment in one API call
    $url = INFISICAL_HOST . '/api/v3/secrets/raw'
         . '?workspaceId=' . urlencode(INFISICAL_PROJECT_ID)
         . '&environment=' . urlencode(INFISICAL_ENVIRONMENT)
         . '&secretPath='  . urlencode(INFISICAL_SECRET_PATH);

    $response = _infisical_request('GET', $url, null, $_infisical_token);

    $secrets = [];
    foreach ($response['secrets'] ?? [] as $secret) {
        $secrets[$secret['secretKey']] = $secret['secretValue'];
    }

    // Store in session — server-side only, never sent to browser
    $_SESSION['_infisical'] = $secrets;
}

// ------------------------------------------------------------
// Internal: authenticate and return access token
// ------------------------------------------------------------
function _infisical_authenticate(): string {
    $response = _infisical_request('POST', INFISICAL_HOST . '/api/v1/auth/universal-auth/login', [
        'clientId'     => INFISICAL_CLIENT_ID,
        'clientSecret' => INFISICAL_CLIENT_SECRET,
    ]);

    if (empty($response['accessToken'])) {
        throw new Exception('Infisical authentication failed — no access token returned.');
    }

    return $response['accessToken'];
}

// ------------------------------------------------------------
// Internal: make a curl request to the Infisical API
// ------------------------------------------------------------
function _infisical_request(string $method, string $url, ?array $body = null, ?string $token = null): array {
    $ch = curl_init($url);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Infisical curl error: ' . $error);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Infisical API returned HTTP {$httpCode}: " . $raw);
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Infisical returned invalid JSON: ' . $raw);
    }

    return $decoded;
}