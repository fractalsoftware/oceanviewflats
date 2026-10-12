<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Controller;

use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Reservation\InboundChannelSyncServiceInterface;
use OceanViewFlats\Domain\Reservation\MaintenanceBlock;
use OceanViewFlats\Domain\Reservation\MaintenanceBlockRepositoryInterface;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Administrative controller for maintenance blocks and calendar holds.
 * Integrates with ReservationLedger to prevent collisions with active reservations
 * and enforces immutability on concluded historical holds per ADR 0006.
 */
final class CalendarBlockController
{
    public function __construct(
        private readonly MaintenanceBlockRepositoryInterface $blockRepository,
        private readonly ReservationLedgerInterface $ledger,
        private readonly ViewRenderer $viewRenderer,
        private readonly AuditLogger $auditLogger,
        private readonly ?InboundChannelSyncServiceInterface $syncService = null,
        private readonly ?ReservationRepositoryInterface $reservationRepository = null
    ) {
    }

    /**
     * GET /calendar-blocks: Lists maintenance holds filtered by property and status.
     *
     * @param array<string, mixed> $session
     */
    public function index(Request $request, array &$session): Response
    {
        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', 'all'));
        $filter = $this->resolveFilter((string) $request->getQuery('filter', 'upcoming'));

        $viewContainerHtml = $this->renderViewContainerHtml($propertyId, $filter, oob: false, session: $session);

        if ($request->isHtmx() && $request->getHeader('HX-Target') !== 'body') {
            $headerActionsHtml = $this->renderHeaderActionsHtml($propertyId, $filter, oob: true);
            return Response::html($viewContainerHtml . "\n" . $headerActionsHtml, 200);
        }

        $headerActionsHtml = $this->renderHeaderActionsHtml($propertyId, $filter, oob: false);

        $csrfToken = (string) ($session['csrf_token'] ?? '');
        $panelData = ChannelSyncController::buildPanelViewData(
            syncService: $this->syncService,
            csrfToken: $csrfToken,
            notice: null,
            ledger: $this->ledger,
            reservationRepository: $this->reservationRepository
        );

        $fullHtml = $this->viewRenderer->render('calendar_blocks/index.php', [
            'propertyId' => $propertyId,
            'filter' => $filter,
            'headerActionsHtml' => $headerActionsHtml,
            'viewContainerHtml' => $viewContainerHtml,
            'csrfToken' => $csrfToken,
            'currentUser' => $this->buildCurrentUser($session),
            'currentRoute' => '/calendar-blocks',
            'channelSyncPanel' => $panelData,
        ]);

        return Response::html($fullHtml, 200);
    }

    /**
     * GET /calendar-blocks/new: Renders modal dialog for adding a new maintenance hold.
     *
     * @param array<string, mixed> $session
     */
    public function newHold(Request $request, array &$session): Response
    {
        $rawPropertyId = (string) $request->getQuery('property_id', '1606');
        $propertyId = $this->resolvePropertyId($rawPropertyId);
        $selectedPropertyId = $propertyId === 'all' ? '1606' : $propertyId;
        $filter = $this->resolveFilter((string) $request->getQuery('filter', 'upcoming'));

        $startDate = trim((string) $request->getQuery('start_date', ''));
        $endDate = trim((string) $request->getQuery('end_date', ''));

        $modalHtml = $this->viewRenderer->renderPartial('calendar_blocks/_modal_form.php', [
            'propertyId' => $selectedPropertyId,
            'currentPropertyId' => $propertyId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reason' => '',
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'errors' => [],
            'infoMessage' => null,
            'filter' => $filter,
        ]);

        return Response::html($modalHtml, 200);
    }

    /**
     * POST /calendar-blocks: Validates and creates a new maintenance hold.
     * Rejects collisions with confirmed/pending direct reservations (HTTP 422).
     *
     * @param array<string, mixed> $session
     */
    public function create(Request $request, array &$session): Response
    {
        $propertyId = trim((string) $request->getPost('property_id', ''));
        $startDate = trim((string) $request->getPost('start_date', ''));
        $endDate = trim((string) $request->getPost('end_date', ''));
        $reason = trim((string) $request->getPost('reason', ''));
        $filter = $this->resolveFilter((string) $request->getPost('current_filter', 'upcoming'));
        $csrfToken = (string) ($session['csrf_token'] ?? '');

        // 1. Validate CSRF token
        $submittedToken = (string) $request->getPost('csrf_token', '');
        if (!hash_equals($csrfToken, $submittedToken)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token.</div>', 403);
        }

        // 2. Format & Range Validations
        $errors = [];
        if (!in_array($propertyId, ['1606', '1707'], true)) {
            $errors[] = 'Please select a valid property unit (1606 or 1707).';
        }
        if ($startDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $errors[] = 'Start date must be a valid date in YYYY-MM-DD format.';
        }
        if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $errors[] = 'End date must be a valid date in YYYY-MM-DD format.';
        }
        if ($startDate !== '' && $endDate !== '' && $startDate >= $endDate) {
            $errors[] = sprintf('End date (%s) must be strictly after start date (%s).', $endDate, $startDate);
        }
        if ($reason === '') {
            $errors[] = 'Reason / operational justification cannot be empty.';
        }

        // 3. Collision Checks (if date format is valid)
        $infoMessage = null;
        if (empty($errors)) {
            // Check collisions with active direct reservations (Confirmed or active Pending)
            $resConflict = $this->ledger->findReservationConflict($propertyId, $startDate, $endDate);
            if ($resConflict !== null) {
                $errors[] = sprintf(
                    'Cannot create maintenance hold: Collides with active direct reservation %s for %s (%s to %s).',
                    $resConflict->reservationUid,
                    $resConflict->guestName,
                    $resConflict->checkIn,
                    $resConflict->checkOut
                );
            }

            // Check collision with existing maintenance blocks
            if ($this->blockRepository->hasOverlap($propertyId, $startDate, $endDate)) {
                $errors[] = 'Proposed dates overlap with an existing maintenance hold for this property.';
            }

            // Check if overlapping external OTA channel block (informational note, permitted per ADR 0006)
            $channelConflict = $this->ledger->findChannelConflict($propertyId, $startDate, $endDate);
            if ($channelConflict !== null) {
                $infoMessage = sprintf(
                    'Note: These dates overlap an external %s block. Local maintenance hold takes precedence.',
                    $channelConflict->source
                );
            }
        }

        // 4. Return Validation Errors (HTTP 422)
        if (!empty($errors)) {
            $modalHtml = $this->viewRenderer->renderPartial('calendar_blocks/_modal_form.php', [
                'propertyId' => $propertyId,
                'currentPropertyId' => (string) $request->getPost('current_property_id', $propertyId),
                'startDate' => $startDate,
                'endDate' => $endDate,
                'reason' => $reason,
                'csrfToken' => $csrfToken,
                'errors' => $errors,
                'infoMessage' => $infoMessage,
                'filter' => $filter,
            ]);

            return new Response(
                statusCode: 422,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Retarget' => '#modal-container',
                    'HX-Reswap' => 'innerHTML',
                ],
                body: $modalHtml
            );
        }

        // 5. Persist Maintenance Block
        $adminUserId = isset($session['admin_user_id']) ? (int) $session['admin_user_id'] : null;

        $block = new MaintenanceBlock(
            propertyId: $propertyId,
            startDate: $startDate,
            endDate: $endDate,
            reason: $reason,
            createdBy: $adminUserId
        );
        $savedBlock = $this->blockRepository->save($block);
        $blockId = $savedBlock->id;

        // 6. Audit Trail Logging
        $this->auditLogger->record(
            action: 'calendar_block_created',
            entityType: 'calendar_block',
            entityId: (string) $blockId,
            before: null,
            after: [
                'property_id' => $propertyId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
            ],
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // 7. Response: close modal and re-render blocks container & header actions
        $viewPropertyId = $this->resolvePropertyId((string) $request->getPost('current_property_id', $propertyId));

        $viewContainerHtml = $this->renderViewContainerHtml(
            $viewPropertyId,
            $filter,
            oob: true,
            session: $session
        );
        $headerActionsHtml = $this->renderHeaderActionsHtml(
            $viewPropertyId,
            $filter,
            oob: true
        );

        if ($request->isHtmx()) {
            $responseHtml = $viewContainerHtml . "\n" . $headerActionsHtml . "\n" . '<div id="modal-container" hx-swap-oob="innerHTML"></div>';

            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'blockSaved',
                ],
                body: $responseHtml
            );
        }

        return Response::redirect('/calendar-blocks?property_id=' . urlencode($viewPropertyId) . '&filter=' . urlencode($filter));
    }

    /**
     * DELETE /calendar-blocks/{id}: Deletes active/upcoming maintenance hold.
     * Rejects deletion of concluded historical holds (HTTP 422) per ADR 0006.
     *
     * @param array<string, mixed> $session
     */
    public function delete(Request $request, array &$session): Response
    {
        $id = (int) $request->getAttribute('id');
        $propertyId = $this->resolvePropertyId((string) $request->getQuery('property_id', (string) $request->getPost('property_id', 'all')));
        $filter = $this->resolveFilter((string) $request->getQuery('filter', (string) $request->getPost('filter', 'upcoming')));
        $csrfToken = (string) ($session['csrf_token'] ?? '');

        // 1. Verify CSRF for mutating requests
        $submittedToken = (string) ($request->getPost('csrf_token', $request->getHeader('X-CSRF-Token', '')));
        if ($csrfToken !== '' && !hash_equals($csrfToken, $submittedToken)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid CSRF token.</div>', 403);
        }

        // 2. Fetch existing block
        $block = $this->blockRepository->findById($id);
        if ($block === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Maintenance hold not found.</div>', 404);
        }

        // 3. Immutability Invariant: Concluded historical blocks cannot be deleted (ADR 0006)
        if ($block->isConcluded()) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Concluded historical maintenance blocks cannot be deleted.</div>', 422);
        }

        // 4. Delete block
        $this->blockRepository->delete($id);

        // 5. Audit Trail Logging
        $adminUserId = isset($session['admin_user_id']) ? (int) $session['admin_user_id'] : null;

        $this->auditLogger->record(
            action: 'calendar_block_deleted',
            entityType: 'calendar_block',
            entityId: (string) $id,
            before: $block->toArray(),
            after: null,
            adminUserId: $adminUserId,
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        // 6. Response: re-render view container and header actions
        $viewContainerHtml = $this->renderViewContainerHtml(
            $propertyId,
            $filter,
            oob: false,
            session: $session
        );
        $headerActionsHtml = $this->renderHeaderActionsHtml(
            $propertyId,
            $filter,
            oob: true
        );

        if ($request->isHtmx()) {
            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'blockReleased',
                ],
                body: $viewContainerHtml . "\n" . $headerActionsHtml
            );
        }

        return Response::redirect('/calendar-blocks?property_id=' . urlencode($propertyId) . '&filter=' . urlencode($filter));
    }

    /**
     * Renders dynamic blocks view container partial including filter pills and table.
     *
     * @param array<string, mixed> $session
     */
    private function renderViewContainerHtml(
        string $propertyId,
        string $filter,
        bool $oob = false,
        array $session = []
    ): string {
        $blocks = $this->blockRepository->listFiltered($propertyId, $filter);
        $csrfToken = (string) ($session['csrf_token'] ?? '');

        $tableHtml = $this->viewRenderer->renderPartial('calendar_blocks/_table.php', [
            'blocks' => $blocks,
            'propertyId' => $propertyId,
            'filter' => $filter,
            'csrfToken' => $csrfToken,
        ]);

        return $this->viewRenderer->renderPartial('calendar_blocks/_blocks_view.php', [
            'propertyId' => $propertyId,
            'filter' => $filter,
            'tableHtml' => $tableHtml,
            'oob' => $oob,
        ]);
    }

    /**
     * Renders header actions partial containing '+ Add Maintenance Hold' button.
     */
    private function renderHeaderActionsHtml(
        string $propertyId,
        string $filter,
        bool $oob = false
    ): string {
        return $this->viewRenderer->renderPartial('calendar_blocks/_header_actions.php', [
            'propertyId' => $propertyId,
            'filter' => $filter,
            'oob' => $oob,
        ]);
    }

    private function resolvePropertyId(string $raw): string
    {
        $cleaned = trim($raw);
        return in_array($cleaned, ['1606', '1707'], true) ? $cleaned : 'all';
    }

    private function resolveFilter(string $raw): string
    {
        $cleaned = trim($raw);
        return in_array($cleaned, ['upcoming', 'past', 'all'], true) ? $cleaned : 'upcoming';
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
            'email' => $session['admin_email'] ?? 'admin@oceanviewflats.com',
            'role' => $session['admin_role'] ?? 'admin',
        ];
    }
}
