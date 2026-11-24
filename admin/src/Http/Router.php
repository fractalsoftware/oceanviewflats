<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Http;

use OceanViewFlats\Admin\Middleware\AuthMiddleware;
use OceanViewFlats\Admin\Middleware\CsrfMiddleware;
use OceanViewFlats\Admin\Middleware\RbacMiddleware;
use OceanViewFlats\Admin\Middleware\SessionMiddleware;

/**
 * Front-controller router and request dispatcher for the Admin interface.
 */
final class Router
{
    /**
     * Fast constant-time lookup for static routes: [METHOD => [normalizedPath => handler]]
     *
     * @var array<string, array<string, callable(Request, array<string, mixed>&): Response>>
     */
    private array $staticRoutes = [];

    /**
     * Declarative RBAC access policies for static routes: [METHOD => [normalizedPath => RouteAccess]]
     *
     * @var array<string, array<string, RouteAccess>>
     */
    private array $staticRouteAccess = [];

    /**
     * Dynamic regex-compiled routes: [METHOD => list of route definitions]
     *
     * @var array<string, list<array{pattern: string, handler: callable(Request, array<string, mixed>&): Response, access: ?RouteAccess}>>
     */
    private array $dynamicRoutes = [];

    public function __construct(
        private readonly ?SessionMiddleware $sessionMiddleware = null,
        private readonly ?CsrfMiddleware $csrfMiddleware = null,
        private readonly ?AuthMiddleware $authMiddleware = null,
        private readonly ?RbacMiddleware $rbacMiddleware = null
    ) {
    }

    /**
     * Registers a custom route handler for future expansion (e.g. #33, #34).
     * Backward-compatible alias for addRoute.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function registerRoute(string $method, string $path, callable $handler, ?RouteAccess $access = null): void
    {
        $this->addRoute($method, $path, $handler, $access);
    }

    /**
     * Adds a route for a specific HTTP method and path pattern.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function addRoute(string $method, string $path, callable $handler, ?RouteAccess $access = null): self
    {
        $method = strtoupper(trim($method));
        $normalizedPath = $this->normalizePath($path);

        if ($this->isDynamic($normalizedPath)) {
            $this->dynamicRoutes[$method][] = [
                'pattern' => $this->compileRoutePattern($normalizedPath),
                'handler' => $handler,
                'access' => $access,
            ];
        } else {
            $this->staticRoutes[$method][$normalizedPath] = $handler;
            if ($access !== null) {
                $this->staticRouteAccess[$method][$normalizedPath] = $access;
            }
        }

        return $this;
    }

    /**
     * Fluent helper for registering GET routes.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function get(string $path, callable $handler, ?RouteAccess $access = null): self
    {
        return $this->addRoute('GET', $path, $handler, $access);
    }

    /**
     * Fluent helper for registering POST routes.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function post(string $path, callable $handler, ?RouteAccess $access = null): self
    {
        return $this->addRoute('POST', $path, $handler, $access);
    }

    /**
     * Fluent helper for registering DELETE routes.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function delete(string $path, callable $handler, ?RouteAccess $access = null): self
    {
        return $this->addRoute('DELETE', $path, $handler, $access);
    }

    /**
     * Fluent helper for registering PUT routes.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function put(string $path, callable $handler, ?RouteAccess $access = null): self
    {
        return $this->addRoute('PUT', $path, $handler, $access);
    }

    /**
     * Fluent helper for registering PATCH routes.
     *
     * @param callable(Request, array<string, mixed>&): Response $handler
     */
    public function patch(string $path, callable $handler, ?RouteAccess $access = null): self
    {
        return $this->addRoute('PATCH', $path, $handler, $access);
    }

    /**
     * Dispatches an incoming request through the security middleware pipeline to its route handler.
     *
     * @param array<string, mixed> $session
     */
    public function dispatch(Request $request, array &$session): Response
    {
        // 1. Session Lifecycle Middleware
        if ($this->sessionMiddleware !== null) {
            $sessionResponse = $this->sessionMiddleware->process($request, $session);
            if ($sessionResponse !== null) {
                return $sessionResponse;
            }
        }

        // 2. CSRF Protection Middleware
        if ($this->csrfMiddleware !== null) {
            $csrfResponse = $this->csrfMiddleware->process($request, $session);
            if ($csrfResponse !== null) {
                return $csrfResponse;
            }
        }

        // 3. Authentication & Authorization Middleware
        if ($this->authMiddleware !== null) {
            $authResponse = $this->authMiddleware->process($request, $session);
            if ($authResponse !== null) {
                return $authResponse;
            }
        }

        $method = strtoupper($request->getMethod());
        $normalizedUri = $this->normalizePath($request->getUri());

        // 4. Fast constant-time lookup for static routes
        if (isset($this->staticRoutes[$method][$normalizedUri])) {
            if ($this->rbacMiddleware !== null) {
                $rbacResponse = $this->rbacMiddleware->process(
                    $request,
                    $session,
                    $this->staticRouteAccess[$method][$normalizedUri] ?? null
                );
                if ($rbacResponse !== null) {
                    return $rbacResponse;
                }
            }
            return ($this->staticRoutes[$method][$normalizedUri])($request, $session);
        }

        // 5. Dynamic regex-compiled routes
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $route) {
                if (preg_match($route['pattern'], $normalizedUri, $matches) === 1) {
                    if ($this->rbacMiddleware !== null) {
                        $rbacResponse = $this->rbacMiddleware->process($request, $session, $route['access']);
                        if ($rbacResponse !== null) {
                            return $rbacResponse;
                        }
                    }
                    $params = [];
                    foreach ($matches as $key => $val) {
                        if (is_string($key)) {
                            $params[$key] = rawurldecode($val);
                        }
                    }
                    $matchedRequest = $request->withAttributes($params);
                    return ($route['handler'])($matchedRequest, $session);
                }
            }
        }

        // 6. Check if URI matches other HTTP methods (HTTP 405 Method Not Allowed)
        $allowedMethods = $this->getAllowedMethodsForUri($normalizedUri, $method);
        if (!empty($allowedMethods)) {
            return Response::html(
                '<div class="p-8 text-center"><h1 class="text-2xl font-bold text-gray-800">405 Method Not Allowed</h1><p class="text-gray-500 mt-2">The requested method is not allowed for this URL.</p></div>',
                405,
                ['Allow' => implode(', ', $allowedMethods)]
            );
        }

        // 7. No match across any HTTP method (HTTP 404 Not Found)
        return Response::html(
            '<div class="p-8 text-center"><h1 class="text-2xl font-bold text-gray-800">404 Not Found</h1><p class="text-gray-500 mt-2">The requested administrative page could not be located.</p></div>',
            404
        );
    }


    /**
     * Normalizes a URI or route pattern by stripping query/hash and trailing slashes,
     * ensuring leading slash, while preserving root '/'.
     */
    public static function normalizePath(string $path): string
    {
        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];
        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        if (!str_starts_with($trimmed, '/')) {
            $trimmed = '/' . $trimmed;
        }

        $normalized = rtrim($trimmed, '/');
        return $normalized !== '' ? $normalized : '/';
    }

    /**
     * Checks if a path pattern contains dynamic {param} placeholders.
     */
    private function isDynamic(string $path): bool
    {
        return str_contains($path, '{') && preg_match('/\{[a-zA-Z0-9_]+\}/', $path) === 1;
    }

    /**
     * Compiles a path with {param} placeholders into a regular expression with named capture groups.
     */
    private function compileRoutePattern(string $path): string
    {
        $parts = preg_split('/(\{[a-zA-Z0-9_]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return '#^' . preg_quote($path, '#') . '$#';
        }

        $regexParts = [];
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $part, $tokenMatch) === 1) {
                $paramName = $tokenMatch[1];
                $regexParts[] = '(?P<' . $paramName . '>[^/]+)';
            } else {
                $regexParts[] = preg_quote($part, '#');
            }
        }

        return '#^' . implode('', $regexParts) . '$#';
    }

    /**
     * Resolves all HTTP methods that can handle the given normalized URI, excluding any specified method.
     *
     * @return list<string>
     */
    private function getAllowedMethodsForUri(string $normalizedUri, string $excludeMethod = ''): array
    {
        $allowed = [];

        foreach ($this->staticRoutes as $method => $paths) {
            if ($method !== $excludeMethod && isset($paths[$normalizedUri])) {
                $allowed[] = $method;
            }
        }

        foreach ($this->dynamicRoutes as $method => $routes) {
            if ($method !== $excludeMethod && !in_array($method, $allowed, true)) {
                foreach ($routes as $route) {
                    if (preg_match($route['pattern'], $normalizedUri) === 1) {
                        $allowed[] = $method;
                        break;
                    }
                }
            }
        }

        sort($allowed);

        return $allowed;
    }
}

