<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Controller;

use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardHubViewData;
use OceanViewFlats\Domain\Reservation\Dashboard\DashboardQueryServiceInterface;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;

/**
 * Controller handling the administrative dashboard overview and home interface.
 */
final class DashboardController
{
    public function __construct(
        private readonly ViewRenderer $viewRenderer,
        private readonly ?InboundChannelSyncServiceInterface $syncService = null,
        private readonly ?DashboardQueryServiceInterface $dashboardQueryService = null
    ) {
    }

    /**
     * Renders the administrative dashboard overview screen.
     *
     * @param array<string, mixed> $session
     */
    public function index(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', 'all'));

        // If request is HTMX targeting #dashboard-hub-content or #dashboard-hub-container, render partial directly
        if ($request->isHtmx() && $this->isHubTarget($request)) {
            return $this->renderHubPartial($propertyId, $session);
        }

        return $this->renderFullDashboard($propertyId, $session);
    }

    /**
     * GET /dashboard/hub: HTMX endpoint for reactive property filter switching.
     *
     * @param array<string, mixed> $session
     */
    public function hub(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', 'all'));

        // If not HTMX or explicitly targeting whole body, render full page
        if (!$request->isHtmx() || $request->getHeader('HX-Target') === 'body') {
            return $this->renderFullDashboard($propertyId, $session);
        }

        return $this->renderHubPartial($propertyId, $session);
    }

    /**
     * GET /dashboard/day: Slide-over multi-stay and operational day drawer.
     *
     * @param array<string, mixed> $session
     */
    public function dayDrawer(Request $request, array &$session): Response
    {
        $date = (string) $request->getQuery('date', '');
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::html('<div class="p-6 text-rose-600 font-semibold">Invalid or missing date parameter. Expected format: YYYY-MM-DD.</div>', 400);
        }

        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', 'all'));

        if ($this->dashboardQueryService === null) {
            return Response::html('<div class="p-6 text-rose-600 font-semibold">Dashboard query service is unavailable.</div>', 500);
        }

        try {
            $summary = $this->dashboardQueryService->getDaySummary($date, $propertyId);
        } catch (\InvalidArgumentException $e) {
            return Response::html('<div class="p-6 text-rose-600 font-semibold">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>', 400);
        }

        $csrfToken = (string) ($session['csrf_token'] ?? '');

        $html = $this->viewRenderer->renderPartial('dashboard/_day_drawer.php', [
            'summary' => $summary,
            'propertyFilter' => $propertyId,
            'csrfToken' => $csrfToken,
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function renderHubPartial(string $propertyId, array &$session): Response
    {
        $csrfToken = (string) ($session['csrf_token'] ?? '');
        $viewData = $this->fetchHubViewData($propertyId);
        $channelCardData = ChannelSyncController::buildCardViewData(
            syncService: $this->syncService,
            csrfToken: $csrfToken
        );

        $html = $this->viewRenderer->renderPartial('dashboard/_hub_content.php', [
            'propertyId' => $propertyId,
            'viewData' => $viewData,
            'channelCardData' => $channelCardData,
            'csrfToken' => $csrfToken,
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string, mixed> $session
     */
    private function renderFullDashboard(string $propertyId, array &$session): Response
    {
        $currentUser = [
            'id' => $session['admin_user_id'] ?? null,
            'name' => $session['admin_user_name'] ?? 'Admin',
            'email' => $session['admin_user_email'] ?? '',
            'role' => $session['admin_user_role'] ?? 'admin',
        ];

        $csrfToken = (string) ($session['csrf_token'] ?? '');
        $viewData = $this->fetchHubViewData($propertyId);
        $channelCardData = ChannelSyncController::buildCardViewData(
            syncService: $this->syncService,
            csrfToken: $csrfToken
        );

        $hubContentHtml = $this->viewRenderer->renderPartial('dashboard/_hub_content.php', [
            'propertyId' => $propertyId,
            'viewData' => $viewData,
            'channelCardData' => $channelCardData,
            'csrfToken' => $csrfToken,
        ]);

        $html = $this->viewRenderer->render('dashboard/index.php', [
            'title' => 'Dashboard - Ocean View Flats Admin',
            'currentRoute' => '/',
            'currentUser' => $currentUser,
            'csrfToken' => $csrfToken,
            'propertyId' => $propertyId,
            'viewData' => $viewData,
            'channelCardData' => $channelCardData,
            'hubContentHtml' => $hubContentHtml,
        ]);

        return Response::html($html);
    }

    private function fetchHubViewData(string $propertyId): DashboardHubViewData
    {
        if ($this->dashboardQueryService !== null) {
            return $this->dashboardQueryService->getDashboardHubData($propertyId);
        }

        return new DashboardHubViewData(
            selectedPropertyFilter: $propertyId,
            alerts: [],
            scheduleByDate: [],
            rateStatus: [],
            upcomingMaintenanceBlocks: [],
            channelSyncData: [],
            todayArrivalsCount: 0,
            todayDeparturesCount: 0,
            activeStaysCount: 0
        );
    }

    private function resolvePropertyId(string $propertyId): string
    {
        return in_array($propertyId, ['1606', '1707'], true) ? $propertyId : 'all';
    }

    private function isHubTarget(Request $request): bool
    {
        $target = ltrim((string) $request->getHeader('HX-Target'), '#');
        return $target === 'dashboard-hub-content' || $target === 'dashboard-hub-container';
    }
}
