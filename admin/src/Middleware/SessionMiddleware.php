<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Middleware;

use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;

/**
 * Manages administrative session lifecycle, subdomain cookie isolation,
 * idle inactivity timeouts (30m), and absolute session lifetimes (8h).
 */
final class SessionMiddleware
{
    public const SESSION_NAME = 'OVF_ADMIN_SESSID';
    public const IDLE_TIMEOUT_SECONDS = 1800;    // 30 minutes
    public const MAX_LIFETIME_SECONDS = 28800;   // 8 hours

    public function __construct(
        private readonly int $idleTimeout = self::IDLE_TIMEOUT_SECONDS,
        private readonly int $maxLifetime = self::MAX_LIFETIME_SECONDS
    ) {
    }

    /**
     * Inspects and updates session state, returning a redirect response upon expiration.
     *
     * @param array<string, mixed> $session
     */
    public function process(Request $request, array &$session, ?int $currentTime = null): ?Response
    {
        $now = $currentTime ?? time();

        // 1. Check absolute max lifetime timeout
        if (isset($session['created_at']) && ($now - (int) $session['created_at'] > $this->maxLifetime)) {
            self::destroySession();
            $session = [];
            $target = '/login?reason=session_expired';
            return $request->isHtmx() ? Response::htmxUnauthorized($target) : Response::redirect($target, 302);
        }

        // 2. Check idle inactivity timeout
        if (isset($session['last_activity']) && ($now - (int) $session['last_activity'] > $this->idleTimeout)) {
            self::destroySession();
            $session = [];
            $target = '/login?reason=idle_timeout';
            return $request->isHtmx() ? Response::htmxUnauthorized($target) : Response::redirect($target, 302);
        }

        // 3. Initialize creation timestamp if absent
        if (empty($session['created_at'])) {
            $session['created_at'] = $now;
        }

        // 4. Touch last activity timestamp
        $session['last_activity'] = $now;

        // 5. Ensure 256-bit CSRF token is present
        if (empty($session['csrf_token'])) {
            $session['csrf_token'] = CsrfMiddleware::generateToken();
        }

        return null;
    }

    /**
     * Resolves session cookie parameters enforcing subdomain isolation and strict security flags.
     *
     * Per RFC 6265 §4.1.2.3, omitting or setting an empty domain attribute creates a Host-Only cookie,
     * which is required for localhost, IP addresses, and strict origin isolation.
     *
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Strict'}
     */
    public static function resolveCookieParams(?string $serverName = null, ?bool $isHttps = null): array
    {
        $rawHost = $serverName ?? (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');

        // Handle bracketed IPv6 (e.g. [::1]:8080) vs standard host:port
        if (str_starts_with($rawHost, '[') && str_contains($rawHost, ']')) {
            $host = substr($rawHost, 1, (int) strpos($rawHost, ']') - 1);
        } else {
            $host = trim(explode(':', $rawHost)[0]);
        }

        $isSecure = $isHttps ?? (
            (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && (int) $_SERVER['HTTP_X_FORWARDED_PORT'] === 443)
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        );

        // Host-Only cookie: leave domain empty for localhost, IPs, single-label hosts, or origin-scoped environments.
        // Browsers strictly reject cookies with Domain=localhost or Domain=<ip-address> (RFC 6265).
        $isLocalOrIp = $host === ''
            || $host === 'localhost'
            || !str_contains($host, '.')
            || filter_var($host, FILTER_VALIDATE_IP) !== false;

        // When $serverName is explicitly provided (and not local/IP), retain it.
        // Otherwise, leave domain empty ('') for RFC 6265 Host-Only cookies across origin environments.
        $domain = ($isLocalOrIp || $serverName === null) ? '' : $host;

        return [
            'lifetime' => 0,                            // Expire when browser closes
            'path' => '/',                              // Valid across all admin routes
            'domain' => $domain,                        // Host-Only cookie or scoped subdomain
            'secure' => $isSecure,                      // Secure cookie in production/HTTPS
            'httponly' => true,                         // Inaccessible to JavaScript
            'samesite' => 'Strict',                     // Block third-party transmission
        ];
    }

    /**
     * Checks whether a session has exceeded either its absolute lifetime or idle inactivity timeout.
     *
     * @param array<string, mixed> $session
     */
    public static function isSessionExpired(
        array $session,
        ?int $currentTime = null,
        int $idleTimeout = self::IDLE_TIMEOUT_SECONDS,
        int $maxLifetime = self::MAX_LIFETIME_SECONDS
    ): bool {
        $now = $currentTime ?? time();
        if (isset($session['created_at']) && ($now - (int) $session['created_at'] > $maxLifetime)) {
            return true;
        }
        if (isset($session['last_activity']) && ($now - (int) $session['last_activity'] > $idleTimeout)) {
            return true;
        }
        return false;
    }

    /**
     * Configures cookie parameters and starts native PHP session if not already active.
     */
    public static function startNativeSession(?string $serverName = null, ?bool $isHttps = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            session_name(self::SESSION_NAME);
            session_set_cookie_params(self::resolveCookieParams($serverName, $isHttps));
            session_start();
        }
    }

    /**
     * Rotates session ID to prevent session fixation attacks upon authentication.
     */
    public static function regenerateId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Completely wipes session state and destroys native session.
     */
    public static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                $sessionName = (string) session_name();
                setcookie($sessionName, '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Strict',
                ]);
            }
            session_destroy();
        }
    }
}
