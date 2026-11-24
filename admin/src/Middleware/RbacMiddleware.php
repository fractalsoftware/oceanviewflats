<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Middleware;

use OceanViewFlats\Admin\Auth\AdminRole;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Http\RouteAccess;
use OceanViewFlats\Admin\Http\Router;
use OceanViewFlats\Admin\Views\ViewRenderer;

/**
 * Centralized fail-closed Role-Based Access Control (RBAC) middleware for the Admin Console.
 *
 * Evaluates the declarative RouteAccess policy declared on each route in AdminApp:
 * - PUBLIC: always permitted (login/logout).
 * - AUTHENTICATED: permitted for any authenticated AdminUser (including read-only viewer).
 * - MANAGER: permitted for manager, admin, and superadmin roles.
 * - PRIVILEGED: permitted strictly for admin and superadmin roles.
 * - Unannotated routes fail closed: AUTHENTICATED for safe reads, PRIVILEGED for mutations.
 */
final class RbacMiddleware
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    public function __construct(
        private readonly ?ViewRenderer $viewRenderer = null
    ) {
    }

    /**
     * Evaluates role authorization for the current request, returning HTTP 403 on denial.
     *
     * @param array<string, mixed> $session
     */
    public function process(Request $request, array &$session, ?RouteAccess $requiredAccess = null): ?Response
    {
        $method = strtoupper($request->getMethod());
        $uri = Router::normalizePath($request->getUri());

        $effectiveAccess = $requiredAccess ?? (
            in_array($method, self::MUTATING_METHODS, true)
                ? RouteAccess::PRIVILEGED
                : RouteAccess::AUTHENTICATED
        );

        if ($effectiveAccess === RouteAccess::PUBLIC) {
            return null;
        }

        // Unauthenticated requests are handled by AuthMiddleware
        if (empty($session['admin_user_id'])) {
            return null;
        }

        $role = AdminRole::fromSession($session);

        if ($effectiveAccess === RouteAccess::AUTHENTICATED) {
            return null;
        }

        if ($effectiveAccess === RouteAccess::MANAGER) {
            if (!$role->canManage()) {
                return $this->deny($request, $session, $uri, 'Read-only viewer accounts cannot modify reservation or calendar records.');
            }
            return null;
        }

        if (!$role->isPrivileged()) {
            return $this->deny($request, $session, $uri, 'Administrator privileges are required to access this resource.');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function deny(Request $request, array $session, string $uri, string $message): Response
    {
        $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        if ($request->isHtmx()) {
            return Response::html(
                '<div class="p-6 text-center"><p class="text-sm font-semibold text-rose-800">Access Restricted</p><p class="text-xs text-gray-500 mt-1">' . $safeMsg . '</p></div>',
                403
            );
        }

        if ($this->viewRenderer !== null) {
            $html = $this->viewRenderer->render('errors/403.php', [
                'currentUser' => [
                    'id' => isset($session['admin_user_id']) ? (int) $session['admin_user_id'] : null,
                    'name' => (string) ($session['admin_user_name'] ?? 'Admin'),
                    'email' => (string) ($session['admin_user_email'] ?? ($session['admin_email'] ?? 'admin@oceanviewflats.com')),
                    'role' => AdminRole::fromSession($session)->value,
                ],
                'csrfToken' => (string) ($session['csrf_token'] ?? ''),
                'currentRoute' => $uri,
                'title' => '403 Forbidden - Ocean View Flats Admin',
            ]);
            return Response::html($html, 403);
        }

        return Response::html(
            '<div class="p-8 text-center"><h1 class="text-2xl font-bold text-gray-800">403 - Access Restricted</h1><p class="text-gray-500 mt-2">' . $safeMsg . '</p></div>',
            403
        );
    }
}
