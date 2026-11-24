<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Middleware;

use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;

/**
 * Middleware gating protected administrative views and HTMX partial endpoints,
 * redirecting unauthenticated traffic to the login screen per ADR 0005.
 */
final class AuthMiddleware
{
    public const DEFAULT_WHITELIST = [
        'GET /login',
        'POST /login',
        'POST /logout',
    ];

    /**
     * @param list<string> $whitelist
     */
    public function __construct(
        private readonly array $whitelist = self::DEFAULT_WHITELIST,
        private readonly string $loginUrl = '/login'
    ) {
    }

    /**
     * Inspects session authentication status, issuing HTTP 302 (standard) or HTTP 401 (HTMX).
     *
     * @param array<string, mixed> $session
     */
    public function process(Request $request, array &$session): ?Response
    {
        $methodAndUri = $request->getMethod() . ' ' . $request->getUri();
        $uri = $request->getUri();

        // 1. Check whitelisted public routes
        if (in_array($methodAndUri, $this->whitelist, true) || in_array($uri, $this->whitelist, true)) {
            return null;
        }

        // 2. Verified authenticated session
        if (!empty($session['admin_user_id'])) {
            return null;
        }

        // 3. Unauthenticated request branching
        if ($request->isHtmx()) {
            return Response::htmxUnauthorized($this->loginUrl);
        }

        return Response::redirect($this->loginUrl, 302);
    }
}
