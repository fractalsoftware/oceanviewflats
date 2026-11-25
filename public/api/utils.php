<?php
/**
 * OceanViewFlats - Shared API Utility Library
 * PHP 8 Compatible
 * 
 * Unifies core reusable subroutines: Database PDO initialization, inputs sanitization,
 * response dispatching, rate limiting, and mathematical captcha generation & verification.
 */

declare(strict_types=1);

if (!defined('PRIVACY_POLICY_VERSION')) {
    define('PRIVACY_POLICY_VERSION', '2026-10');
}

/**
 * Centrally manages the database connection using PDO.
 * 
 * @param array{host: string, dbname: string, user: string, pass: string} $dbConfig Database credentials mapping.
 * @return PDO Established database connection handle.
 */
function get_db_connection(array $dbConfig): PDO {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    return new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Cleans and sanitizes string inputs to prevent Cross-Site Scripting (XSS).
 * 
 * @param string $data Raw input string.
 * @return string Cleaned and safe sanitization string.
 */
function clean_input(string $data): string {
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Strips carriage returns and newlines to prevent email header injection attacks.
 * 
 * @param string $str Raw header input.
 * @return string Stripped line safe for headers.
 */
function strip_newlines(string $str): string {
    return str_replace(["\r", "\n", "%0a", "%0d"], '', $str);
}

/**
 * Dispatches a standard JSON response to the client and terminates execution.
 * 
 * @param bool $success Operation status.
 * @param string $message Narrative text response.
 * @param array<string, mixed> $extra Optional key-value pairs to merge into response.
 * @return never
 */
function send_json_response(bool $success, string $message, array $extra = []): never {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $res = array_merge(['success' => $success], $extra);
    if ($success) {
        $res['message'] = $message;
    } else {
        $res['error'] = $message;
    }
    echo json_encode($res);
    exit;
}

/**
 * Detects if the current request is an AJAX/XMLHttpRequest request.
 * 
 * @return bool True if AJAX, false otherwise.
 */
function is_ajax_request(): bool {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
        || (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/**
 * Ensures the Composer autoloader is loaded when CaptchaChallengeService is referenced directly.
 */
function ovf_ensure_captcha_service_loaded(): void {
    if (!class_exists(\OceanViewFlats\Domain\Security\CaptchaChallengeService::class)) {
        $autoloader = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require_once $autoloader;
        }
    }
}

/**
 * Resolves the optional test override for the CAPTCHA nonce store.
 */
function ovf_resolve_captcha_nonce_store(
    ?\OceanViewFlats\Domain\Security\CaptchaNonceStoreInterface $nonceStore = null
): ?\OceanViewFlats\Domain\Security\CaptchaNonceStoreInterface {
    if ($nonceStore !== null) {
        return $nonceStore;
    }

    if (
        isset($GLOBALS['TEST_CAPTCHA_NONCE_STORE'])
        && $GLOBALS['TEST_CAPTCHA_NONCE_STORE'] instanceof \OceanViewFlats\Domain\Security\CaptchaNonceStoreInterface
    ) {
        return $GLOBALS['TEST_CAPTCHA_NONCE_STORE'];
    }

    return null;
}

/**
 * Resolves localized CAPTCHA error messages from the shared booking translation slice.
 *
 * @param array<string, mixed> $bookingTranslations
 * @return array{sign: string, invalid: string, wrong: string, expired: string, replayed: string, unavailable: string}
 */
function ovf_captcha_error_messages(array $bookingTranslations): array {
    return [
        'sign' => is_string($bookingTranslations['err_captcha_sign'] ?? null)
            ? $bookingTranslations['err_captcha_sign']
            : 'Security check failed. Please refresh the page and try again.',
        'invalid' => is_string($bookingTranslations['err_captcha_invalid'] ?? null)
            ? $bookingTranslations['err_captcha_invalid']
            : 'Invalid verification challenge.',
        'wrong' => is_string($bookingTranslations['err_captcha_wrong'] ?? null)
            ? $bookingTranslations['err_captcha_wrong']
            : 'Incorrect answer to the security verification question.',
        'expired' => is_string($bookingTranslations['err_captcha_expired'] ?? null)
            ? $bookingTranslations['err_captcha_expired']
            : 'Security verification expired. Please refresh and solve the new challenge.',
        'replayed' => is_string($bookingTranslations['err_captcha_replayed'] ?? null)
            ? $bookingTranslations['err_captcha_replayed']
            : 'This security verification has already been used. Please solve the new challenge.',
        'unavailable' => is_string($bookingTranslations['err_captcha_unavailable'] ?? null)
            ? $bookingTranslations['err_captcha_unavailable']
            : 'Security verification is temporarily unavailable.',
    ];
}

/**
 * Resolves CAPTCHA_SECRET from environment and fails closed with HTTP 500 JSON when missing in production.
 *
 * @param string|null $errorMessage Optional localized error message returned on HTTP 500 failure.
 * @return string Validated non-empty CAPTCHA signing secret.
 */
function require_captcha_secret(?string $errorMessage = null): string {
    ovf_ensure_captcha_service_loaded();

    $envSecret = getenv('CAPTCHA_SECRET');
    $hasConfiguredSecret = (isset($_ENV['CAPTCHA_SECRET']) && is_string($_ENV['CAPTCHA_SECRET']) && trim($_ENV['CAPTCHA_SECRET']) !== '')
        || (isset($_SERVER['CAPTCHA_SECRET']) && is_string($_SERVER['CAPTCHA_SECRET']) && trim($_SERVER['CAPTCHA_SECRET']) !== '')
        || (is_string($envSecret) && trim($envSecret) !== '');

    if (!$hasConfiguredSecret && class_exists(\OceanViewFlats\Domain\Support\EnvLoader::class)) {
        \OceanViewFlats\Domain\Support\EnvLoader::load();
    }

    try {
        return \OceanViewFlats\Domain\Security\CaptchaChallengeService::resolveSecret();
    } catch (\RuntimeException $e) {
        error_log('[CAPTCHA Configuration Error] ' . $e->getMessage());
        $resolvedMessage = $errorMessage;
        if ($resolvedMessage === null || trim($resolvedMessage) === '') {
            $translationsPath = __DIR__ . '/translations.php';
            if (is_file($translationsPath)) {
                $allTranslations = require $translationsPath;
                $lang = get_validated_lang();
                $bookingT = is_array($allTranslations[$lang]['booking'] ?? null)
                    ? $allTranslations[$lang]['booking']
                    : (is_array($allTranslations['en']['booking'] ?? null) ? $allTranslations['en']['booking'] : []);
                $resolvedMessage = ovf_captcha_error_messages($bookingT)['unavailable'];
            } else {
                $resolvedMessage = 'Security verification is temporarily unavailable.';
            }
        }
        http_response_code(500);
        send_json_response(false, $resolvedMessage);
    }
}

/**
 * Generates an expiring, single-use HMAC-SHA256 mathematical captcha challenge.
 * 
 * @param string $secret Cryptographic signing secret.
 * @param int|null $now Optional Unix timestamp override for deterministic tests.
 * @param int $ttlSeconds Expiration TTL in seconds (default 600 = 10 minutes).
 * @return array{challenge: string, signature: string, expires_at: int} Challenge formula, signed v2 token, and expiry timestamp.
 */
function generate_captcha_challenge(
    string $secret,
    ?int $now = null,
    int $ttlSeconds = \OceanViewFlats\Domain\Security\CaptchaChallengeService::DEFAULT_TTL_SECONDS
): array {
    ovf_ensure_captcha_service_loaded();

    return \OceanViewFlats\Domain\Security\CaptchaChallengeService::generate($secret, $now, $ttlSeconds);
}

/**
 * Verifies the mathematical captcha solution, TTL expiration, and single-use nonce consumption.
 * 
 * @param string $challenge Challenged arithmetic equation.
 * @param string $signature Signed v2 CAPTCHA token.
 * @param string $response Provided user response value.
 * @param string $secret HMAC encryption salt.
 * @param string $errorSign Custom error signature failure text.
 * @param string $errorInvalid Custom invalid formula format text.
 * @param string $errorWrong Custom incorrect math sum text.
 * @param string|null $errorExpired Optional custom expired challenge text.
 * @param string|null $errorReplayed Optional custom replayed challenge text.
 * @param \OceanViewFlats\Domain\Security\CaptchaNonceStoreInterface|null $nonceStore Optional nonce store override.
 * @param int|null $now Optional Unix timestamp override.
 * @return true|string True if valid and atomically consumed, or the matched error message string on failure.
 */
function verify_captcha_challenge(
    string $challenge,
    string $signature,
    string $response,
    string $secret,
    string $errorSign = 'Security check failed. Please refresh the page and try again.',
    string $errorInvalid = 'Invalid verification challenge.',
    string $errorWrong = 'Incorrect answer.',
    ?string $errorExpired = null,
    ?string $errorReplayed = null,
    ?\OceanViewFlats\Domain\Security\CaptchaNonceStoreInterface $nonceStore = null,
    ?int $now = null
): bool|string {
    ovf_ensure_captcha_service_loaded();

    try {
        return \OceanViewFlats\Domain\Security\CaptchaChallengeService::verify(
            challenge: $challenge,
            signature: $signature,
            response: $response,
            secret: $secret,
            nonceStore: ovf_resolve_captcha_nonce_store($nonceStore),
            now: $now,
            errorSign: $errorSign,
            errorInvalid: $errorInvalid,
            errorWrong: $errorWrong,
            errorExpired: $errorExpired,
            errorReplayed: $errorReplayed
        );
    } catch (\RuntimeException $e) {
        error_log('[CAPTCHA Nonce Store Error] ' . $e->getMessage());
        return $errorSign;
    }
}

/**
 * Issues a single-use, expiring, stay-bound payment continuation token after book-request.php
 * verifies the guest's math CAPTCHA.
 */
function issue_payment_captcha_token(
    string $reservationUid,
    string $propertyId,
    string $checkIn,
    string $checkOut,
    string $secret,
    ?int $now = null,
    int $ttlSeconds = \OceanViewFlats\Domain\Security\CaptchaChallengeService::DEFAULT_TTL_SECONDS
): string {
    ovf_ensure_captcha_service_loaded();

    return \OceanViewFlats\Domain\Security\CaptchaChallengeService::issuePaymentToken(
        $reservationUid,
        $propertyId,
        $checkIn,
        $checkOut,
        $secret,
        $now,
        $ttlSeconds
    );
}

/**
 * Verifies and atomically consumes a single-use, stay-bound payment continuation token.
 *
 * @return true|string True when valid and consumed, or error string otherwise.
 */
function verify_payment_captcha_token(
    string $token,
    string $reservationUid,
    string $propertyId,
    string $checkIn,
    string $checkOut,
    string $secret,
    string $errorSign = 'Security check failed. Please refresh the page and try again.',
    ?string $errorExpired = null,
    ?string $errorReplayed = null,
    ?\OceanViewFlats\Domain\Security\CaptchaNonceStoreInterface $nonceStore = null,
    ?int $now = null
): bool|string {
    ovf_ensure_captcha_service_loaded();

    try {
        return \OceanViewFlats\Domain\Security\CaptchaChallengeService::verifyPaymentToken(
            token: $token,
            reservationUid: $reservationUid,
            propertyId: $propertyId,
            checkIn: $checkIn,
            checkOut: $checkOut,
            secret: $secret,
            nonceStore: ovf_resolve_captcha_nonce_store($nonceStore),
            now: $now,
            errorSign: $errorSign,
            errorExpired: $errorExpired,
            errorReplayed: $errorReplayed
        );
    } catch (\RuntimeException $e) {
        error_log('[CAPTCHA Nonce Store Error] ' . $e->getMessage());
        return $errorSign;
    }
}

/**
 * Enforces IP-based rate-limiting with a file-backed storage inside sys_get_temp_dir().
 * 
 * @param string $filename Local storage JSON filename.
 * @param int $maxSubmissions Max requests permitted in the window.
 * @param int $windowSeconds Expiration window timer in seconds.
 * @param string $errorMessage Localized rate limiting message.
 */
function enforce_rate_limit(string $filename, int $maxSubmissions, int $windowSeconds, string $errorMessage = 'Too many requests. Please wait a few minutes and try again.'): void {
    $temp_dir = sys_get_temp_dir();
    $rate_limit_path = $temp_dir . '/' . $filename;
    $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $now = time();

    $limits = [];
    if (file_exists($rate_limit_path)) {
        $file_content = @file_get_contents($rate_limit_path);
        if ($file_content !== false) {
            $limits = json_decode($file_content, true) ?: [];
        }
    }

    // Clean up old rate limit entries
    foreach ($limits as $hash => $timestamps) {
        $filtered = array_filter($timestamps, function($ts) use ($now, $windowSeconds) {
            return ($now - $ts) < $windowSeconds;
        });
        if (empty($filtered)) {
            unset($limits[$hash]);
        } else {
            $limits[$hash] = array_values($filtered);
        }
    }

    // Check rate limit
    if (isset($limits[$ip_hash]) && count($limits[$ip_hash]) >= $maxSubmissions) {
        http_response_code(429);
        send_json_response(false, $errorMessage);
    }

    // Append current timestamp and save back
    $limits[$ip_hash][] = $now;
    @file_put_contents($rate_limit_path, json_encode($limits), LOCK_EX);
}

/**
 * Determines whether the current API request runs in an explicit non-production environment.
 */
function ovf_is_non_production_env(): bool {
    if (class_exists(\OceanViewFlats\Domain\Support\AppEnvironment::class)) {
        return \OceanViewFlats\Domain\Support\AppEnvironment::isNonProduction(allowPhpUnit: false);
    }
    $env = strtolower(trim((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? (getenv('APP_ENV') ?: 'production'))));
    return in_array($env, ['development', 'local', 'testing', 'test'], true);
}

/**
 * Enforces security headers and CORS (Cross-Origin Resource Sharing) restrictions.
 * 
 * @param list<string> $allowedMethods Allowed HTTP methods for this endpoint.
 */
function enforce_security_headers_and_cors(array $allowedMethods = ['GET', 'POST', 'OPTIONS']): void {
    // 1. Send Security Headers (emit Vary: Origin unconditionally to prevent shared-cache CORS poisoning)
    if (!headers_sent()) {
        header_remove('X-Powered-By');
        header("Vary: Origin");
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self)");
    }

    $isNonProduction = ovf_is_non_production_env();

    // 2. Validate Origin if set
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null) {
        $originClean = trim($origin);
        $allowed = false;

        if ($originClean === 'https://www.oceanviewflats.com' || $originClean === 'https://oceanviewflats.com') {
            $allowed = true;
        } elseif ($isNonProduction && preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i', $originClean)) {
            $allowed = true;
        }

        if ($allowed) {
            header("Access-Control-Allow-Origin: " . $originClean);
            header("Access-Control-Allow-Methods: " . implode(', ', $allowedMethods));
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            header("Access-Control-Max-Age: 86400"); // 24 Hours
        } else {
            http_response_code(403);
            send_json_response(false, 'CORS Policy: Access Denied.');
        }
    }

    // 3. Handle OPTIONS preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // 4. Validate Method
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods, true)) {
        http_response_code(405);
        send_json_response(false, 'Method Not Allowed');
    }
}

/**
 * Restricts access to forms to prevent direct browser address bar accesses (missing Referer)
 * or accesses from unauthorized foreign domains.
 * 
 * @param list<string>|null $allowedHosts Optional explicit allowed referrer domain names.
 */
function enforce_referer_check(?array $allowedHosts = null): void {
    if ($allowedHosts === null) {
        $allowedHosts = ovf_is_non_production_env()
            ? ['oceanviewflats.com', 'localhost', '127.0.0.1']
            : ['oceanviewflats.com'];
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($referer)) {
        // Enforce restriction for browser clients only (to avoid blocking legitimate curl tests or server webhooks)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!empty($userAgent) && stripos($userAgent, 'Mozilla') !== false) {
            http_response_code(403);
            send_json_response(false, 'Direct access to this processing script is not permitted.');
        }
    } else {
        $host = parse_url($referer, PHP_URL_HOST);
        $host = strtolower($host ?: '');
        $allowed = false;
        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || (strlen($host) > strlen($allowedHost) && substr($host, -(strlen($allowedHost) + 1)) === '.' . $allowedHost)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            send_json_response(false, 'Unauthorized access origin.');
        }
    }
}

/**
 * Resolves, sanitizes, and validates the lang parameter.
 * Supports: 'en', 'es', 'fr', 'it', 'de', 'ja'.
 * Falls back to 'en' if missing, empty, or invalid.
 * 
 * @param string|null $explicitLang Optional explicit language code to validate.
 * @return string The validated lang code.
 */
function get_validated_lang(?string $explicitLang = null): string {
    $lang = ($explicitLang !== null && $explicitLang !== '') ? $explicitLang : ($_POST['lang'] ?? $_GET['lang'] ?? 'en');
    if (is_array($lang)) {
        return 'en';
    }
    $langStr = htmlspecialchars(trim(stripslashes((string)$lang)), ENT_QUOTES, 'UTF-8');
    if (!in_array($langStr, ['en', 'es', 'fr', 'it', 'de', 'ja'], true)) {
        return 'en';
    }
    return $langStr;
}
