<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Controller;

use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Repository\AdminAuditLogRepository;
use OceanViewFlats\Admin\Views\ViewRenderer;

/**
 * Controller handling audit log browsing, real-time debounced filtering,
 * pagination, and slide-over inspector drawer for payload state diffs.
 * Enforces role-based access control (RBAC) restricted to admin and superadmin tiers.
 */
class AuditLogController
{
    public const AUTHORIZED_ROLES = ['admin', 'superadmin'];

    public function __construct(
        private readonly AdminAuditLogRepository $repository,
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    /**
     * Lists audit logs with pagination and multi-field filtering.
     *
     * @param array<string, mixed> $session
     */
    public function index(Request $request, array &$session): Response
    {
        $currentUser = $this->buildCurrentUser($session);
        if (!$this->isAuthorized($currentUser)) {
            return $this->forbiddenResponse($request, $currentUser, $session);
        }

        $filters = $request->getAllQuery();
        $searchResult = $this->repository->search($filters);

        $tableHtml = $this->viewRenderer->renderPartial('audit_logs/_table.php', [
            'items' => $searchResult['items'],
            'total' => $searchResult['total'],
            'page' => $searchResult['page'],
            'per_page' => $searchResult['per_page'],
            'total_pages' => $searchResult['total_pages'],
            'filters' => $filters,
        ]);

        if ($request->isHtmx() && $request->getHeader('HX-Target') === 'audit-table-container') {
            return Response::html($tableHtml);
        }

        $adminUsers = $this->repository->getAdminUsers();
        $entityTypes = $this->repository->getDistinctEntityTypes();

        $fullHtml = $this->viewRenderer->render('audit_logs/index.php', [
            'tableHtml' => $tableHtml,
            'drawerHtml' => null,
            'filters' => $filters,
            'adminUsers' => $adminUsers,
            'entityTypes' => $entityTypes,
            'currentUser' => $currentUser,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'currentRoute' => '/audit-logs',
            'title' => 'Audit Logs - Ocean View Flats Admin',
        ]);

        return Response::html($fullHtml);
    }

    /**
     * Inspects a single audit log entry via slide-over drawer or full index view.
     *
     * @param array<string, mixed> $session
     */
    public function show(Request $request, array &$session): Response
    {
        $currentUser = $this->buildCurrentUser($session);
        if (!$this->isAuthorized($currentUser)) {
            return $this->forbiddenResponse($request, $currentUser, $session);
        }

        $id = (int) ($request->getAttribute('id') ?? 0);
        $log = $this->repository->findById($id);

        if ($log === null) {
            if ($request->isHtmx()) {
                $errorBanner = '
                    <div class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4">
                        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm text-center border border-gray-200">
                            <p class="text-sm font-semibold text-rose-600 mb-2">Audit log not found</p>
                            <p class="text-xs text-gray-500 mb-4">No audit entry exists with ID #' . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . '</p>
                            <button type="button" data-action="close-audit-drawer" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-xs rounded-md text-gray-700 font-medium cursor-pointer">Dismiss</button>
                        </div>
                    </div>';
                return Response::html($errorBanner, 404);
            }
            return Response::html('Audit log entry not found', 404);
        }

        $drawerHtml = $this->viewRenderer->renderPartial('audit_logs/_detail_drawer.php', [
            'log' => $log,
            'currentUser' => $currentUser,
        ]);

        if ($request->isHtmx() && $request->getHeader('HX-Target') === 'drawer-container') {
            return Response::html($drawerHtml);
        }

        // Full-page fallback with drawer open
        $filters = $request->getAllQuery();
        $searchResult = $this->repository->search($filters);
        $tableHtml = $this->viewRenderer->renderPartial('audit_logs/_table.php', [
            'items' => $searchResult['items'],
            'total' => $searchResult['total'],
            'page' => $searchResult['page'],
            'per_page' => $searchResult['per_page'],
            'total_pages' => $searchResult['total_pages'],
            'filters' => $filters,
        ]);

        $adminUsers = $this->repository->getAdminUsers();
        $entityTypes = $this->repository->getDistinctEntityTypes();

        $fullHtml = $this->viewRenderer->render('audit_logs/index.php', [
            'tableHtml' => $tableHtml,
            'drawerHtml' => $drawerHtml,
            'filters' => $filters,
            'adminUsers' => $adminUsers,
            'entityTypes' => $entityTypes,
            'currentUser' => $currentUser,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'currentRoute' => '/audit-logs',
            'title' => 'Audit Log #' . $id . ' - Ocean View Flats Admin',
        ]);

        return Response::html($fullHtml);
    }

    /**
     * Verifies whether the authenticated user possesses admin or superadmin privileges.
     *
     * @param array<string, mixed> $currentUser
     */
    private function isAuthorized(array $currentUser): bool
    {
        $role = (string) ($currentUser['role'] ?? '');
        return in_array($role, self::AUTHORIZED_ROLES, true);
    }

    /**
     * Issues an HTTP 403 Forbidden response.
     *
     * @param array<string, mixed> $currentUser
     * @param array<string, mixed> $session
     */
    private function forbiddenResponse(Request $request, array $currentUser, array $session): Response
    {
        if ($request->isHtmx()) {
            $html = '
                <div class="p-6 text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-rose-100 text-rose-600 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-rose-800">Access Restricted</p>
                    <p class="text-xs text-gray-500 mt-1">Audit logs require Administrator privileges.</p>
                </div>';
            return Response::html($html, 403);
        }

        $html = $this->viewRenderer->render('errors/403.php', [
            'currentUser' => $currentUser,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'currentRoute' => '/audit-logs',
            'title' => '403 Forbidden - Ocean View Flats Admin',
        ]);

        return Response::html($html, 403);
    }

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function buildCurrentUser(array $session): array
    {
        return [
            'id' => $session['admin_user_id'] ?? null,
            'name' => $session['admin_user_name'] ?? 'Admin',
            'email' => $session['admin_user_email'] ?? ($session['admin_email'] ?? 'admin@oceanviewflats.com'),
            'role' => \OceanViewFlats\Admin\Auth\AdminRole::fromSession($session)->value,
        ];
    }
}
