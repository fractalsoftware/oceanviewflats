<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Controller;

use DateTimeImmutable;
use InvalidArgumentException;
use OceanViewFlats\Admin\Audit\AuditLogger;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Http\Response;
use OceanViewFlats\Admin\Service\AdminReservationUpdateService;
use OceanViewFlats\Admin\Service\MercadoPagoRefundClientInterface;
use OceanViewFlats\Admin\Service\PublicUrlBuilder;
use OceanViewFlats\Admin\Views\ViewRenderer;
use OceanViewFlats\Domain\Fulfillment\AdminContext;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillment;
use OceanViewFlats\Domain\Fulfillment\BookingFulfillmentInterface;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRenderer;
use OceanViewFlats\Domain\Fulfillment\CancellationEmailRendererInterface;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearance;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceRepositoryInterface;
use OceanViewFlats\Domain\Fulfillment\CondominiumClearanceSyncInterface;
use OceanViewFlats\Domain\Fulfillment\EmailSenderInterface;
use OceanViewFlats\Domain\Fulfillment\GuestLifecycleFulfillmentServiceInterface;
use OceanViewFlats\Domain\Fulfillment\GuestRegistryRequiredException;
use OceanViewFlats\Domain\Fulfillment\HuespedManagerClearanceSync;
use OceanViewFlats\Domain\Fulfillment\PdoCondominiumClearanceRepository;
use OceanViewFlats\Domain\Quote\QuoteEngineInterface;
use OceanViewFlats\Domain\Reservation\ActorContext;
use OceanViewFlats\Domain\Reservation\CancellationPreview;
use OceanViewFlats\Domain\Reservation\CancellationRequest;
use OceanViewFlats\Domain\Reservation\ExcessiveRefundException;
use OceanViewFlats\Domain\Reservation\GatewayRefundException;
use OceanViewFlats\Domain\Reservation\InvalidReservationStateException;
use OceanViewFlats\Domain\Reservation\Port\AuditPort;
use OceanViewFlats\Domain\Reservation\PrimaryGuest;
use OceanViewFlats\Domain\Reservation\RefundInstruction;
use OceanViewFlats\Domain\Reservation\Reservation;
use OceanViewFlats\Domain\Reservation\ReservationConflictException;
use OceanViewFlats\Domain\Reservation\ReservationDraft;
use OceanViewFlats\Domain\Reservation\ReservationLedgerInterface;
use OceanViewFlats\Domain\Reservation\ReservationLifecycleEngineInterface;
use OceanViewFlats\Domain\Reservation\ReservationNotFoundException;
use OceanViewFlats\Domain\Reservation\ReservationRepositoryInterface;
use OceanViewFlats\Domain\Reservation\ReservationStatus;
use OceanViewFlats\Domain\Reservation\ReservationValidationException;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchCriteria;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchInterface;
use OceanViewFlats\Domain\Reservation\Search\ReservationSearchResult;
use OceanViewFlats\Infrastructure\Reservation\ReservationLifecycleEngineFactory;
use PDO;
use Throwable;

/**
 * Controller handling reservation management, real-time debounced filtering,
 * slide-over detail inspector drawer, manual booking creation, PIN overrides,
 * cancellation with automated refunds, and guest registry administration.
 */
final class ReservationController
{
    private readonly CancellationEmailRendererInterface $cancellationEmailRenderer;
    private readonly PublicUrlBuilder $urlBuilder;
    private readonly ?CondominiumClearanceRepositoryInterface $clearanceRepo;
    private readonly ?CondominiumClearanceSyncInterface $clearanceSync;
    private readonly ReservationLifecycleEngineInterface $lifecycleEngine;
    private readonly AdminReservationUpdateService $updateService;

    public function __construct(
        private readonly ReservationRepositoryInterface $repository,
        private readonly ReservationSearchInterface $search,
        private readonly ViewRenderer $viewRenderer,
        private readonly AuditLogger $auditLogger,
        private readonly ReservationLedgerInterface $ledger,
        private readonly QuoteEngineInterface $quoteEngine,
        private readonly EmailSenderInterface $emailSender,
        private readonly GuestLifecycleFulfillmentServiceInterface $lifecycleService,
        private readonly string $publicSiteUrl = 'https://oceanviewflats.com',
        private readonly ?MercadoPagoRefundClientInterface $refundClient = null,
        ?CancellationEmailRendererInterface $cancellationEmailRenderer = null,
        private readonly ?PDO $pdo = null,
        ?PublicUrlBuilder $urlBuilder = null,
        ?CondominiumClearanceRepositoryInterface $clearanceRepo = null,
        ?CondominiumClearanceSyncInterface $clearanceSync = null,
        ?ReservationLifecycleEngineInterface $lifecycleEngine = null,
        private readonly ?BookingFulfillmentInterface $bookingFulfillment = null,
        ?AdminReservationUpdateService $updateService = null
    ) {
        $this->cancellationEmailRenderer = $cancellationEmailRenderer ?? new CancellationEmailRenderer($this->publicSiteUrl);
        $this->urlBuilder = $urlBuilder ?? new PublicUrlBuilder($this->publicSiteUrl);
        $this->clearanceRepo = $clearanceRepo ?? ($this->pdo !== null ? new PdoCondominiumClearanceRepository($this->pdo) : null);
        $this->clearanceSync = $clearanceSync ?? ($this->clearanceRepo !== null ? new HuespedManagerClearanceSync(
            repository: $this->clearanceRepo,
            reservationRepository: $this->repository,
            searchAdapter: $this->search,
            auditLogger: $this->auditLogger
        ) : null);
        $this->lifecycleEngine = $lifecycleEngine ?? $this->buildFallbackLifecycleEngine();
        $this->updateService = $updateService ?? new AdminReservationUpdateService(
            repository: $this->repository,
            search: $this->search,
            ledger: $this->ledger,
            auditLogger: $this->auditLogger,
            emailSender: $this->emailSender,
            urlBuilder: $this->urlBuilder,
            publicSiteUrl: $this->publicSiteUrl,
            pdo: $this->pdo,
            clearanceSync: $this->clearanceSync
        );
    }

    /**
     * Lists reservations with pagination, live debounced filtering, and sorting.
     *
     * @param array<string, mixed> $session
     */
    public function list(Request $request, array &$session): Response
    {
        $filters = $request->getAllQuery();
        $criteria = ReservationSearchCriteria::fromArray($filters);
        $searchResult = $this->search->search($criteria);

        if ($request->isHtmx()) {
            $tableHtml = $this->viewRenderer->renderPartial('reservations/_table.php', [
                'items' => $searchResult->items,
                'total' => $searchResult->totalCount,
                'page' => $searchResult->page,
                'per_page' => $searchResult->limit,
                'total_pages' => $searchResult->totalPages,
                'filters' => $filters,
            ]);
            return Response::html($tableHtml);
        }

        return Response::html($this->renderFullDashboard($session, $filters, $searchResult));
    }

    /**
     * Inspects a single reservation via slide-over drawer or full index view.
     *
     * @param array<string, mixed> $session
     */
    public function show(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $dossier = $this->search->findWithAuditTrail($uid);

        if ($dossier === null) {
            if ($request->isHtmx()) {
                $errorBanner = '
                    <div class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4">
                        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm text-center border border-gray-200">
                            <p class="text-sm font-semibold text-rose-600 mb-2">Reservation not found</p>
                            <p class="text-xs text-gray-500 mb-4">No reservation exists with UID: ' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . '</p>
                            <button type="button" onclick="closeReservationDrawer();" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-xs rounded-md text-gray-700 font-medium cursor-pointer">Dismiss</button>
                        </div>
                    </div>
                ';
                return Response::html($errorBanner, 404);
            }

            return Response::redirect('/reservations');
        }

        $clearance = $this->clearanceRepo?->findByReservationUid($uid);

        $drawerHtml = $this->viewRenderer->renderPartial('reservations/_detail_drawer.php', [
            'reservation' => $dossier->reservation,
            'auditLogs' => $dossier->auditLogs,
            'refunds' => $dossier->refunds,
            'condominiumClearance' => $clearance,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'publicSiteUrl' => $this->publicSiteUrl,
            'urlBuilder' => $this->urlBuilder,
        ]);

        if ($request->isHtmx() && $request->getHeader('HX-Target') === 'drawer-container') {
            return Response::html($drawerHtml);
        }

        // Direct browser request or non-drawer HTMX request: render full dashboard with pre-opened drawer
        return Response::html($this->renderFullDashboard(
            session: $session,
            drawerHtml: $drawerHtml,
            title: 'Reservation ' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . ' - Ocean View Flats Admin'
        ));
    }

    /**
     * Inspects guest registry dossier or pending status modal.
     *
     * @param array<string, mixed> $session
     */
    public function showRegistry(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $reservation = $this->repository->findByUid($uid);

        if ($reservation === null) {
            $errorModal = '
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs p-4">
                    <div class="bg-white p-6 rounded-xl shadow-xl max-w-sm text-center border border-gray-200">
                        <p class="text-sm font-semibold text-rose-600 mb-2">Reservation not found</p>
                        <p class="text-xs text-gray-500 mb-4">No reservation exists with UID: ' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . '</p>
                        <button type="button" onclick="document.getElementById(\'modal-container\').innerHTML = \'\';" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-xs rounded-md text-gray-700 font-medium cursor-pointer">Close</button>
                    </div>
                </div>
            ';
            return Response::html($errorModal, 404);
        }

        $registry = $this->search->findGuestRegistry($uid);

        $modalHtml = $this->viewRenderer->renderPartial('reservations/_registry_modal.php', [
            'reservation' => $reservation->toArray(),
            'registry' => $registry,
            'publicSiteUrl' => $this->publicSiteUrl,
            'urlBuilder' => $this->urlBuilder,
        ]);

        return Response::html($modalHtml);
    }

    /**
     * Renders the modal dialog for creating a manual reservation (HTMX or direct deep-link).
     *
     * @param array<string, mixed> $session
     */
    public function newReservation(Request $request, array &$session): Response
    {
        $propertyId = (string) $request->getQuery('property_id', '');
        $checkIn = (string) $request->getQuery('check_in', '');
        $checkOut = (string) $request->getQuery('check_out', '');
        $source = (string) $request->getQuery('source', 'manual_override');
        $channelBlockUid = (string) $request->getQuery('channel_block_uid', '');
        $externalConfirmationCode = (string) $request->getQuery('external_confirmation_code', '');
        $maxGuests = (string) $request->getQuery('max_guests', (string) Reservation::DEFAULT_MAX_GUESTS);

        $modalHtml = $this->viewRenderer->renderPartial('reservations/_create_modal.php', [
            'errorMessage' => null,
            'propertyId' => $propertyId,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'source' => $source,
            'channelBlockUid' => $channelBlockUid,
            'externalConfirmationCode' => $externalConfirmationCode,
            'maxGuests' => $maxGuests,
        ]);

        if ($request->isHtmx()) {
            return Response::html($modalHtml);
        }

        return Response::html($this->renderFullDashboard(
            session: $session,
            filters: $request->getAllQuery(),
            modalHtml: $modalHtml,
            title: 'New Reservation - Ocean View Flats Admin'
        ));
    }

    /**
     * Real-time quote and availability checker for manual booking creation and reservation editor forms.
     */
    public function quotePreview(Request $request): Response
    {
        $body = $request->getAllPost();
        $propertyId = (string) ($body['property_id'] ?? '');
        $checkIn = (string) ($body['check_in'] ?? '');
        $checkOut = (string) ($body['check_out'] ?? '');
        $source = (string) ($body['source'] ?? 'manual_override');
        $excludeUidRaw = trim((string) ($body['exclude_reservation_uid'] ?? ''));
        $excludeReservationUid = $excludeUidRaw !== '' ? $excludeUidRaw : null;
        $isEditMode = $excludeReservationUid !== null || !empty($body['edit_mode']);

        if ($propertyId === '' || $checkIn === '' || $checkOut === '' || $checkIn >= $checkOut) {
            $html = $this->viewRenderer->renderPartial('reservations/_quote_preview.php', [
                'isAvailable' => false,
                'conflictReasons' => ['Please select check-in and check-out dates (check-out must be after check-in).'],
                'quote' => null,
                'source' => $source,
                'defaultPrice' => 0.0,
                'isEditMode' => $isEditMode,
            ]);
            return Response::html($html);
        }

        $absorbingSource = (strtolower($source) === 'airbnb') ? 'airbnb' : null;
        $isAvailable = $this->ledger->isAvailable($propertyId, $checkIn, $checkOut, null, $absorbingSource, $excludeReservationUid);
        $conflictReasons = $isAvailable ? [] : $this->ledger->getConflictReasons($propertyId, $checkIn, $checkOut, null, $absorbingSource, $excludeReservationUid);
        $quote = null;
        $defaultPrice = 0.0;

        if ($isAvailable) {
            try {
                $quote = $this->quoteEngine->quote($propertyId, $checkIn, $checkOut);
                $defaultPrice = ($source === 'owner_stay' || strtolower($source) === 'airbnb') ? 0.0 : $quote->totalCop;
            } catch (Throwable) {
                $isAvailable = false;
                $conflictReasons[] = 'Failed to calculate rate quote for specified dates.';
            }
        }

        $html = $this->viewRenderer->renderPartial('reservations/_quote_preview.php', [
            'isAvailable' => $isAvailable,
            'conflictReasons' => $conflictReasons,
            'quote' => $quote,
            'source' => $source,
            'defaultPrice' => $defaultPrice,
            'isEditMode' => $isEditMode,
        ]);

        return Response::html($html);
    }

    /**
     * Creates a manual reservation with gateway bypass and optional instant guest registry pre-completion.
     *
     * @param array<string, mixed> $session
     */
    public function createManual(Request $request, array &$session): Response
    {
        $body = $request->getAllPost();
        $propertyId = trim((string) ($body['property_id'] ?? ''));
        $checkIn = trim((string) ($body['check_in'] ?? ''));
        $checkOut = trim((string) ($body['check_out'] ?? ''));
        $source = trim((string) ($body['source'] ?? 'manual_override'));
        $totalPriceRaw = $body['total_price'] ?? null;
        $guestName = trim((string) ($body['guest_name'] ?? ''));
        $guestEmail = trim((string) ($body['guest_email'] ?? ''));
        $guestPhone = trim((string) ($body['guest_phone'] ?? ''));
        $notes = trim((string) ($body['notes'] ?? ''));
        $preMarkRegistry = $this->parseCheckbox($body['pre_mark_registry'] ?? null);
        $sendConfirmationEmail = $this->parseCheckbox($body['send_confirmation_email'] ?? null);
        $externalConfirmationCode = trim((string) ($body['external_confirmation_code'] ?? ''));
        $channelBlockUid = trim((string) ($body['channel_block_uid'] ?? '')) ?: null;
        $maxGuestsRaw = $body['max_guests'] ?? null;

        // Validation: Property
        if (!in_array($propertyId, ['1606', '1707'], true)) {
            return $this->renderCreateError($request, 'Please select a valid property (1606 or 1707).');
        }

        // Validation: Dates format (strict Y-m-d)
        $checkInDate = DateTimeImmutable::createFromFormat('Y-m-d', $checkIn);
        $checkOutDate = DateTimeImmutable::createFromFormat('Y-m-d', $checkOut);
        $isValidCheckIn = ($checkInDate !== false) && ($checkInDate->format('Y-m-d') === $checkIn);
        $isValidCheckOut = ($checkOutDate !== false) && ($checkOutDate->format('Y-m-d') === $checkOut);

        if (!$isValidCheckIn || !$isValidCheckOut) {
            return $this->renderCreateError($request, 'Check-in and check-out dates must be valid dates in Y-m-d format.');
        }

        if ($checkIn >= $checkOut) {
            return $this->renderCreateError($request, 'Check-out date must be strictly after check-in date.');
        }

        // Validation: Source whitelist
        $allowedSources = ['airbnb', 'bank_transfer', 'cash', 'owner_stay', 'manual_override'];
        if (!in_array($source, $allowedSources, true)) {
            return $this->renderCreateError($request, 'Invalid reservation source.');
        }

        // Validation: Maximum occupancy override (max_guests 1..12, default 6)
        if ($maxGuestsRaw === null || trim((string) $maxGuestsRaw) === '') {
            $maxGuests = Reservation::DEFAULT_MAX_GUESTS;
        } else {
            $maxGuestsValidated = filter_var($maxGuestsRaw, FILTER_VALIDATE_INT);
            if ($maxGuestsValidated === false) {
                return $this->renderCreateError($request, 'Maximum occupancy (max_guests) must be between 1 and 12.');
            }
            $maxGuests = (int) $maxGuestsValidated;
            if ($maxGuests < Reservation::MIN_MAX_GUESTS || $maxGuests > Reservation::MAX_MAX_GUESTS) {
                return $this->renderCreateError($request, 'Maximum occupancy (max_guests) must be between 1 and 12.');
            }
        }

        $isAirbnb = ($source === 'airbnb');

        if ($isAirbnb) {
            $airbnbResult = $this->normalizeAirbnbInputs($externalConfirmationCode, $totalPriceRaw, $guestEmail, $guestPhone, $sendConfirmationEmail);
            if (is_string($airbnbResult)) {
                return $this->renderCreateError($request, $airbnbResult);
            }
            [$guestEmail, $guestPhone, $totalPrice] = $airbnbResult;
        } else {
            $totalPrice = is_numeric($totalPriceRaw) ? (float) $totalPriceRaw : -1.0;
        }

        // Validation: Guest details
        if ($guestName === '') {
            return $this->renderCreateError($request, 'Guest name is required.');
        }

        if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->renderCreateError($request, 'A valid guest email address is required.');
        }

        if (!$isAirbnb && (strlen($guestPhone) < 7 || strlen($guestPhone) > 25)) {
            return $this->renderCreateError($request, 'Guest phone number must be between 7 and 25 characters.');
        }

        if ($totalPrice < 0.0) {
            return $this->renderCreateError($request, 'Total price cannot be negative.');
        }

        $currentUser = $this->buildCurrentUser($session);
        $primaryGuest = new PrimaryGuest(
            name: $guestName,
            email: $guestEmail,
            phone: $guestPhone,
            lang: 'es'
        );
        $adminUserId = $currentUser['id'];
        $actor = $adminUserId !== null
            ? ActorContext::admin($adminUserId, $request->getClientIp(), (string) $request->getHeader('User-Agent', ''))
            : new ActorContext(adminUserId: null, ipAddress: $request->getClientIp(), userAgent: (string) $request->getHeader('User-Agent', ''), source: 'admin');

        if ($isAirbnb) {
            $draft = ReservationDraft::external(
                propertyId: $propertyId,
                checkIn: $checkIn,
                checkOut: $checkOut,
                primaryGuest: $primaryGuest,
                externalConfirmationCode: $externalConfirmationCode,
                channelBlockUid: $channelBlockUid,
                hostPayoutCop: $totalPrice,
                notes: $notes !== '' ? $notes : null,
                preMarkRegistry: $preMarkRegistry,
                source: 'airbnb',
                actor: $actor,
                sendConfirmationEmail: $sendConfirmationEmail,
                maxGuests: $maxGuests
            );
        } else {
            $draft = ReservationDraft::manual(
                propertyId: $propertyId,
                checkIn: $checkIn,
                checkOut: $checkOut,
                primaryGuest: $primaryGuest,
                totalPrice: $totalPrice,
                notes: $notes !== '' ? $notes : null,
                preMarkRegistry: $preMarkRegistry,
                source: $source,
                actor: $actor,
                sendConfirmationEmail: $sendConfirmationEmail,
                maxGuests: $maxGuests
            );
        }

        try {
            $result = $this->lifecycleEngine->confirmOrRecord($draft);
        } catch (ReservationConflictException $e) {
            $errorMessage = $e->conflictReasons !== ''
                ? (str_starts_with($e->conflictReasons, 'with ')
                    ? 'Selected dates conflict ' . $e->conflictReasons
                    : 'Selected dates conflict: ' . $e->conflictReasons)
                : 'Selected dates conflict with an existing reservation or channel block.';
            return $this->renderCreateError($request, $errorMessage);
        } catch (ReservationValidationException $e) {
            return $this->renderCreateError($request, $e->getMessage());
        }

        $savedReservation = $result->reservation;
        $uid = $savedReservation->reservationUid;

        $dossier = $this->search->findWithAuditTrail($uid);
        $clearance = $this->clearanceRepo?->findByReservationUid($uid);
        $drawerHtml = $this->viewRenderer->renderPartial('reservations/_detail_drawer.php', [
            'reservation' => $dossier !== null ? $dossier->reservation : $savedReservation->toArray(),
            'auditLogs' => $dossier !== null ? $dossier->auditLogs : [],
            'refunds' => $dossier !== null ? $dossier->refunds : [],
            'condominiumClearance' => $clearance,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'publicSiteUrl' => $this->publicSiteUrl,
            'urlBuilder' => $this->urlBuilder,
        ]);

        if ($request->isHtmx()) {
            $responseBody = '<script>document.getElementById("modal-container").innerHTML = "";</script>';
            $responseBody .= '<div id="drawer-container" hx-swap-oob="innerHTML">' . $drawerHtml . '</div>';

            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'reservationUpdated',
                    'HX-Push-Url' => '/reservations/' . urlencode($uid),
                ],
                body: $responseBody
            );
        }

        return Response::redirect('/reservations/' . urlencode($uid));
    }

    /**
     * Renders the Reservation Editor modal for an active Confirmed Reservation.
     *
     * @param array<string, mixed> $session
     */
    public function editReservation(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $reservation = $this->repository->findByUid($uid);

        if ($reservation === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        if ($reservation->status === ReservationStatus::CANCELLED) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Cannot edit a cancelled reservation.</div>', 422);
        }

        if ($reservation->status !== ReservationStatus::CONFIRMED) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Only confirmed reservations can be edited.</div>', 422);
        }

        if ($reservation->isConcluded()) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Cannot edit a concluded reservation.</div>', 422);
        }

        $registry = $this->search->findGuestRegistry($uid);
        $registeredGuestCount = $this->updateService->resolveRegisteredGuestCount($registry);
        $occupants = $this->updateService->extractRegistryOccupants($registry);
        $carPlates = (string) ($registry['car_plates'] ?? '');
        $carModel = (string) ($registry['car_model'] ?? '');
        $clearance = $this->clearanceRepo?->findByReservationUid($uid);
        $clearanceModifiedAfterSync = $this->updateService->isRegistryModifiedSinceLastSync($reservation, $registry, $clearance);

        $quote = null;
        try {
            $quote = $this->quoteEngine->quote($reservation->propertyId, $reservation->checkIn, $reservation->checkOut);
        } catch (Throwable) {
            $quote = null;
        }

        $quotePreviewHtml = null;
        if ($quote !== null) {
            $quotePreviewHtml = $this->viewRenderer->renderPartial('reservations/_quote_preview.php', [
                'isAvailable' => true,
                'conflictReasons' => [],
                'quote' => $quote,
                'source' => $reservation->source,
                'defaultPrice' => $quote->totalCop,
                'isEditMode' => true,
            ]);
        }

        $modalHtml = $this->viewRenderer->renderPartial('reservations/_edit_modal.php', [
            'reservation' => $reservation->toArray(),
            'registry' => $registry,
            'occupants' => $occupants,
            'carPlates' => $carPlates,
            'carModel' => $carModel,
            'condominiumClearance' => $clearance,
            'clearanceModifiedAfterSync' => $clearanceModifiedAfterSync,
            'syncCondominiumClearance' => false,
            'sendAccessDispatch' => false,
            'resetGuestRegistry' => false,
            'errorMessage' => null,
            'registeredGuestCount' => $registeredGuestCount,
            'quotePreviewHtml' => $quotePreviewHtml,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
        ]);

        if ($request->isHtmx()) {
            return Response::html($modalHtml);
        }

        return Response::html($this->renderFullDashboard(
            session: $session,
            filters: $request->getAllQuery(),
            modalHtml: $modalHtml,
            title: 'Edit Reservation ' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . ' - Ocean View Flats Admin'
        ));
    }

    /**
     * Updates contractual details, stay dates, Occupancy Override, vehicle registration,
     * and Guest Registry occupant roster for an active Confirmed Reservation,
     * recording an immutable Audit Log and executing opt-in downstream fulfillment.
     *
     * @param array<string, mixed> $session
     */
    public function updateReservation(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $reservation = $this->repository->findByUid($uid);

        if ($reservation === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        if ($reservation->status === ReservationStatus::CANCELLED) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Cannot edit a cancelled reservation.</div>', 422);
        }

        if ($reservation->status !== ReservationStatus::CONFIRMED) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Only confirmed reservations can be edited.</div>', 422);
        }

        if ($reservation->isConcluded()) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Cannot edit a concluded reservation.</div>', 422);
        }

        $registry = $this->search->findGuestRegistry($uid);
        $currentUser = $this->buildCurrentUser($session);

        $outcome = $this->updateService->updateReservation(
            reservation: $reservation,
            registry: $registry,
            body: $request->getAllPost(),
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        if (!$outcome['success']) {
            return $this->renderEditError(
                $request,
                $session,
                $reservation,
                $outcome['registeredGuestCount'],
                $outcome['errorMessage'] ?? 'Failed to update reservation.',
                $outcome['conflictReasons'] ?? null
            );
        }

        $savedReservation = $outcome['reservation'] ?? $reservation;
        $dossier = $this->search->findWithAuditTrail($uid);
        $clearance = $this->clearanceRepo?->findByReservationUid($uid);
        $drawerHtml = $this->viewRenderer->renderPartial('reservations/_detail_drawer.php', [
            'reservation' => $dossier !== null ? $dossier->reservation : $savedReservation->toArray(),
            'auditLogs' => $dossier !== null ? $dossier->auditLogs : [],
            'refunds' => $dossier !== null ? $dossier->refunds : [],
            'condominiumClearance' => $clearance,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'publicSiteUrl' => $this->publicSiteUrl,
            'urlBuilder' => $this->urlBuilder,
        ]);

        if ($request->isHtmx()) {
            $responseBody = '<script>document.getElementById("modal-container").innerHTML = "";</script>';
            $responseBody .= '<div id="drawer-container" hx-swap-oob="innerHTML">' . $drawerHtml . '</div>';

            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'reservationUpdated',
                ],
                body: $responseBody
            );
        }

        return Response::redirect('/reservations/' . urlencode($uid));
    }

    /**
     * Manually marks guest registry as verified and complete for out-of-band guests.
     *
     * @param array<string, mixed> $session
     */
    public function completeRegistry(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $reservation = $this->repository->findByUid($uid);

        if ($reservation === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        $currentUser = $this->buildCurrentUser($session);
        $adminContext = new AdminContext(
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        $result = $this->lifecycleService->completeRegistryManually($uid, $adminContext);
        if (!$result->success) {
            $errorMessage = !empty($result->errors) ? implode(', ', $result->errors) : 'Failed to complete registry.';
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>', 400);
        }

        return $this->renderDetailDrawerResponse($uid, $session, $reservation->toArray());
    }

    /**
     * Retries or initiates condominium clearance synchronization via the Condominium Administration Portal.
     *
     * @param array<string, mixed> $session
     */
    public function retryClearance(Request $request, array &$session): Response
    {
        // 1. Session authorization check
        $currentUser = $this->buildCurrentUser($session);
        if ($currentUser['id'] === null) {
            if ($request->isHtmx()) {
                return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Unauthorized: Administrative session required.</div>', 401);
            }
            return Response::json(['success' => false, 'error' => 'Unauthorized: Administrative session required.'], 401);
        }

        // 2. CSRF validation for mutating requests
        if ($request->isMutating()) {
            $sessionCsrf = (string) ($session['csrf_token'] ?? '');
            $clientCsrf = (string) (
                $request->getHeader('HX-CSRF-TOKEN')
                ?? $request->getHeader('X-CSRF-TOKEN')
                ?? $request->getPost('csrf_token')
                ?? ''
            );
            if ($sessionCsrf === '' || $clientCsrf === '' || !hash_equals($sessionCsrf, $clientCsrf)) {
                if ($request->isHtmx()) {
                    return Response::forbidden('<div class="p-4 text-xs text-rose-600 font-semibold">Forbidden: Invalid CSRF token.</div>', isHtmx: true);
                }
                return Response::json(['success' => false, 'error' => 'Forbidden: Invalid CSRF token.'], 403);
            }
        }

        if ($this->clearanceSync === null) {
            if ($request->isHtmx()) {
                return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Condominium clearance synchronization service is unavailable.</div>', 500);
            }
            return Response::json(['success' => false, 'error' => 'Condominium clearance synchronization service is unavailable.'], 500);
        }

        $uid = (string) ($request->getAttribute('uid') ?? '');
        $adminContext = new AdminContext(
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        try {
            $clearance = $this->clearanceSync->syncForReservation($uid, $adminContext);
        } catch (ReservationNotFoundException) {
            if ($request->isHtmx()) {
                return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found.</div>', 404);
            }
            return Response::json(['success' => false, 'error' => 'Reservation not found.'], 404);
        } catch (GuestRegistryRequiredException) {
            if ($request->isHtmx()) {
                return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Guest registry must be submitted before condominium clearance can be synced.</div>', 400);
            }
            return Response::json(['success' => false, 'error' => 'Guest registry must be submitted before condominium clearance can be synced.'], 400);
        }

        if ($request->isHtmx()) {
            $reservation = $this->repository->findByUid($uid);
            return $this->renderDetailDrawerResponse($uid, $session, $reservation?->toArray() ?? [], $clearance);
        }

        return Response::json([
            'success' => $clearance->isSynced(),
            'status' => $clearance->status,
            'clearance_number' => $clearance->clearanceNumber,
            'error' => $clearance->errorMessage,
            'attempts' => $clearance->attempts,
            'last_attempt_at' => $clearance->lastAttemptAt,
            'synced_at' => $clearance->syncedAt,
        ]);
    }

    /**
     * Manually overrides the door code (PIN) for a confirmed reservation.
     *
     * @param array<string, mixed> $session
     */
    public function overrideDoorCode(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $body = $request->getAllPost();
        $rawCode = (string) ($body['door_code'] ?? '');

        $currentUser = $this->buildCurrentUser($session);
        $adminContext = new AdminContext(
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        $result = $this->lifecycleService->overrideDoorCode($uid, $rawCode, $adminContext);
        if (!$result->success) {
            $error = $result->error ?? 'An error occurred while updating the PIN.';
            $statusCode = ($error === 'Reservation not found') ? 404 : 422;
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>', $statusCode);
        }

        return $this->renderDetailDrawerResponse($uid, $session);
    }

    /**
     * Regenerates a fresh random door code (PIN) for a confirmed reservation.
     *
     * @param array<string, mixed> $session
     */
    public function regenerateDoorCode(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $currentUser = $this->buildCurrentUser($session);
        $adminContext = new AdminContext(
            adminUserId: $currentUser['id'],
            ipAddress: $request->getClientIp(),
            userAgent: (string) $request->getHeader('User-Agent', '')
        );

        $result = $this->lifecycleService->regenerateDoorCode($uid, $adminContext);
        if (!$result->success) {
            $error = $result->error ?? 'An error occurred while regenerating the PIN.';
            $statusCode = ($error === 'Reservation not found') ? 404 : 422;
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>', $statusCode);
        }

        return $this->renderDetailDrawerResponse($uid, $session);
    }

    /**
     * Helper to render detail drawer HTMX response.
     *
     * @param array<string, mixed> $session
     * @param array<string, mixed> $fallbackReservation
     */
    private function renderDetailDrawerResponse(
        string $uid,
        array &$session,
        array $fallbackReservation = [],
        ?CondominiumClearance $clearance = null
    ): Response {
        $dossier = $this->search->findWithAuditTrail($uid);
        $clearance ??= $this->clearanceRepo?->findByReservationUid($uid);
        $drawerHtml = $this->viewRenderer->renderPartial('reservations/_detail_drawer.php', [
            'reservation' => $dossier !== null ? $dossier->reservation : $fallbackReservation,
            'auditLogs' => $dossier !== null ? $dossier->auditLogs : [],
            'refunds' => $dossier !== null ? $dossier->refunds : [],
            'condominiumClearance' => $clearance,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'publicSiteUrl' => $this->publicSiteUrl,
            'urlBuilder' => $this->urlBuilder,
        ]);

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
                'HX-Trigger' => 'reservationUpdated',
            ],
            body: $drawerHtml
        );
    }


    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed> $filters
     */
    private function renderFullDashboard(
        array $session,
        array $filters = [],
        ?ReservationSearchResult $searchResult = null,
        ?string $drawerHtml = null,
        ?string $modalHtml = null,
        string $title = 'Reservations - Ocean View Flats Admin'
    ): string {
        $result = $searchResult ?? $this->search->search(ReservationSearchCriteria::fromArray($filters));
        $tableHtml = $this->viewRenderer->renderPartial('reservations/_table.php', [
            'items' => $result->items,
            'total' => $result->totalCount,
            'page' => $result->page,
            'per_page' => $result->limit,
            'total_pages' => $result->totalPages,
            'filters' => $filters,
        ]);

        return $this->viewRenderer->render(
            template: 'reservations/index.php',
            data: [
                'title' => $title,
                'currentRoute' => '/reservations',
                'currentUser' => $this->buildCurrentUser($session),
                'csrfToken' => (string) ($session['csrf_token'] ?? ''),
                'tableHtml' => $tableHtml,
                'drawerHtml' => $drawerHtml,
                'modalHtml' => $modalHtml,
                'filters' => $filters,
            ]
        );
    }

    /**
     * Renders the cancellation confirmation and refund modal.
     *
     * @param array<string, mixed> $session
     */
    public function cancelModal(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');
        $reservation = $this->repository->findByUid($uid);

        if ($reservation === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        try {
            $preview = $this->lifecycleEngine->previewCancellation($uid);
        } catch (InvalidReservationStateException $e) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>', 422);
        } catch (InvalidArgumentException) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        $modalHtml = $this->viewRenderer->renderPartial('reservations/_cancel_modal.php', [
            'reservation' => $reservation->toArray(),
            'preview' => $preview,
            'refundableBalance' => $preview->refundableBalanceCop,
            'isOnlinePayment' => $preview->isOnlinePayment,
            'errorMessage' => null,
            'oldInput' => [],
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
        ]);

        return Response::html($modalHtml);
    }

    /**
     * Executes atomic cancellation and Mercado Pago refund dispatch.
     *
     * @param array<string, mixed> $session
     */
    public function cancel(Request $request, array &$session): Response
    {
        $uid = (string) ($request->getAttribute('uid') ?? '');

        // 1. Verify CSRF Token
        $csrfToken = (string) $request->getPost('csrf_token', '');
        if (!hash_equals((string) ($session['csrf_token'] ?? ''), $csrfToken)) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Invalid or expired CSRF token. Please refresh.</div>', 403);
        }

        // 2. Acquire reservation & preview
        $reservation = $this->repository->findByUid($uid);
        if ($reservation === null) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        try {
            $preview = $this->lifecycleEngine->previewCancellation($uid);
        } catch (InvalidReservationStateException $e) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>', 422);
        } catch (InvalidArgumentException) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Reservation not found</div>', 404);
        }

        $reason = trim((string) $request->getPost('reason', ''));
        $refundType = trim((string) $request->getPost('refund_type', 'none'));
        $refundAmountInput = (float) $request->getPost('refund_amount', 0.0);
        $sendCancellationEmailRaw = $request->getPost('send_cancellation_email');
        $sendCancellationEmail = !empty($sendCancellationEmailRaw);

        $failWithCancelError = fn(string $errorMessage): Response => $this->renderCancelError(
            reservation: $reservation->toArray(),
            refundableBalance: $preview->refundableBalanceCop,
            isOnlinePayment: $preview->isOnlinePayment,
            errorMessage: $errorMessage,
            oldInput: [
                'reason' => $reason,
                'refund_type' => $refundType,
                'refund_amount' => $refundAmountInput,
                'send_cancellation_email' => $sendCancellationEmailRaw,
            ],
            csrfToken: (string) ($session['csrf_token'] ?? ''),
            preview: $preview
        );

        if ($reason === '') {
            return $failWithCancelError('Cancellation reason is required.');
        }

        if ($refundType === 'full') {
            $refundInstruction = RefundInstruction::full();
        } elseif ($refundType === 'partial') {
            if ($refundAmountInput <= 0 || $refundAmountInput > $preview->refundableBalanceCop) {
                return $failWithCancelError('Partial refund amount must be greater than 0 and cannot exceed the refundable balance ($' . number_format($preview->refundableBalanceCop, 0, '.', ',') . ' COP).');
            }
            $refundInstruction = RefundInstruction::partial($refundAmountInput);
        } else {
            $refundType = 'none';
            $refundInstruction = RefundInstruction::none();
        }

        $currentUser = $this->buildCurrentUser($session);
        $adminUserId = $currentUser['id'];
        $actor = $adminUserId !== null
            ? ActorContext::admin($adminUserId, $request->getClientIp(), (string) $request->getHeader('User-Agent', ''))
            : new ActorContext(adminUserId: null, ipAddress: $request->getClientIp(), userAgent: (string) $request->getHeader('User-Agent', ''), source: 'admin');

        $cancellationRequest = new CancellationRequest(
            reason: $reason,
            refundInstruction: $refundInstruction,
            actor: $actor,
            sendCancellationEmail: $sendCancellationEmail
        );

        try {
            $result = $this->lifecycleEngine->cancel($uid, $cancellationRequest);
        } catch (GatewayRefundException $e) {
            return $failWithCancelError($e->getUserFriendlyMessage());
        } catch (ExcessiveRefundException $e) {
            return $failWithCancelError($e->getMessage());
        } catch (InvalidReservationStateException $e) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>', 422);
        } catch (Throwable $e) {
            return Response::html('<div class="p-4 text-xs text-rose-600 font-semibold">Cancellation failed unexpectedly: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>', 500);
        }

        $savedReservation = $result->reservation;
        $updatedDossier = $this->search->findWithAuditTrail($uid);
        $clearance = $this->clearanceRepo?->findByReservationUid($uid);
        $drawerHtml = $this->viewRenderer->renderPartial('reservations/_detail_drawer.php', [
            'reservation' => $updatedDossier !== null ? $updatedDossier->reservation : $savedReservation->toArray(),
            'auditLogs' => $updatedDossier !== null ? $updatedDossier->auditLogs : [],
            'refunds' => $updatedDossier !== null ? $updatedDossier->refunds : [],
            'condominiumClearance' => $clearance,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
            'publicSiteUrl' => $this->publicSiteUrl,
            'urlBuilder' => $this->urlBuilder,
        ]);

        if ($request->isHtmx()) {
            $responseBody = '<script>document.getElementById("modal-container").innerHTML = "";</script>';
            $responseBody .= '<div id="drawer-container" hx-swap-oob="innerHTML">' . $drawerHtml . '</div>';

            return new Response(
                statusCode: 200,
                headers: [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'HX-Trigger' => 'reservationUpdated',
                ],
                body: $responseBody
            );
        }

        return Response::redirect('/reservations/' . urlencode($uid));
    }

    /**
     * @param array<string, mixed> $reservation
     * @param array<string, mixed> $oldInput
     */
    private function renderCancelError(
        array $reservation,
        float $refundableBalance,
        bool $isOnlinePayment,
        string $errorMessage,
        array $oldInput,
        string $csrfToken,
        ?CancellationPreview $preview = null
    ): Response {
        $modalHtml = $this->viewRenderer->renderPartial('reservations/_cancel_modal.php', [
            'reservation' => $reservation,
            'refundableBalance' => $refundableBalance,
            'isOnlinePayment' => $isOnlinePayment,
            'errorMessage' => $errorMessage,
            'oldInput' => $oldInput,
            'csrfToken' => $csrfToken,
            'preview' => $preview,
        ]);

        return Response::html($modalHtml, 422);
    }

    /**
     * @return array{0: string, 1: string, 2: float}|string Normalized [guestEmail, guestPhone, totalPrice] or error string
     */
    private function normalizeAirbnbInputs(
        string $externalConfirmationCode,
        mixed $totalPriceRaw,
        string $guestEmail,
        string $guestPhone,
        bool $sendConfirmationEmail
    ): array|string {
        if ($externalConfirmationCode === '') {
            return 'Airbnb confirmation code is required.';
        }

        if ($totalPriceRaw === null || trim((string) $totalPriceRaw) === '') {
            $totalPrice = 0.0;
        } else {
            $totalPrice = is_numeric($totalPriceRaw) ? (float) $totalPriceRaw : -1.0;
        }

        if ($guestEmail === '' && !$sendConfirmationEmail) {
            $cleanCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $externalConfirmationCode) ?? 'guest');
            $guestEmail = 'airbnb-' . $cleanCode . '@guest.oceanviewflats.com';
        }

        if ($guestPhone === '') {
            $guestPhone = 'N/A';
        }

        return [$guestEmail, $guestPhone, $totalPrice];
    }

    private function renderCreateError(Request $request, string $errorMessage): Response
    {
        $body = $request->getAllPost();
        $modalHtml = $this->viewRenderer->renderPartial('reservations/_create_modal.php', [
            'errorMessage' => $errorMessage,
            'propertyId' => (string) ($body['property_id'] ?? ''),
            'source' => (string) ($body['source'] ?? 'manual_override'),
            'checkIn' => (string) ($body['check_in'] ?? ''),
            'checkOut' => (string) ($body['check_out'] ?? ''),
            'totalPrice' => isset($body['total_price']) ? (string) $body['total_price'] : '',
            'guestName' => (string) ($body['guest_name'] ?? ''),
            'guestEmail' => (string) ($body['guest_email'] ?? ''),
            'guestPhone' => (string) ($body['guest_phone'] ?? ''),
            'notes' => (string) ($body['notes'] ?? ''),
            'preMarkRegistry' => $this->parseCheckbox($body['pre_mark_registry'] ?? null),
            'sendConfirmationEmail' => $this->parseCheckbox($body['send_confirmation_email'] ?? null),
            'externalConfirmationCode' => (string) ($body['external_confirmation_code'] ?? ''),
            'channelBlockUid' => (string) ($body['channel_block_uid'] ?? ''),
            'maxGuests' => isset($body['max_guests']) && trim((string) $body['max_guests']) !== ''
                ? (string) $body['max_guests']
                : (string) Reservation::DEFAULT_MAX_GUESTS,
        ]);

        return Response::html($modalHtml, 422);
    }

    /**
     * @param array<string, mixed> $session
     * @param list<string>|null $conflictReasons
     */
    private function renderEditError(
        Request $request,
        array &$session,
        Reservation $reservation,
        int $registeredGuestCount,
        string $errorMessage,
        ?array $conflictReasons = null
    ): Response {
        $body = $request->getAllPost();
        $uid = $reservation->reservationUid;
        $registry = $this->search->findGuestRegistry($uid);
        $clearance = $this->clearanceRepo?->findByReservationUid($uid);
        $clearanceModifiedAfterSync = $this->updateService->isRegistryModifiedSinceLastSync($reservation, $registry, $clearance);

        $rosterExtraction = $this->updateService->extractSubmittedRoster($body);
        if ($rosterExtraction['rosterSubmitted']) {
            $occupantsToRender = $rosterExtraction['rawOccupants'];
            $registeredGuestCount = count($occupantsToRender);
        } else {
            $occupantsToRender = $this->updateService->extractRegistryOccupants($registry);
        }

        $propertyId = array_key_exists('property_id', $body)
            ? (string) $body['property_id']
            : $reservation->propertyId;
        $checkIn = array_key_exists('check_in', $body)
            ? (string) $body['check_in']
            : $reservation->checkIn;
        $checkOut = array_key_exists('check_out', $body)
            ? (string) $body['check_out']
            : $reservation->checkOut;

        $quote = null;
        $quotePreviewHtml = null;
        if ($conflictReasons !== null) {
            $quotePreviewHtml = $this->viewRenderer->renderPartial('reservations/_quote_preview.php', [
                'isAvailable' => false,
                'conflictReasons' => $conflictReasons,
                'quote' => null,
                'source' => $reservation->source,
                'defaultPrice' => 0.0,
                'isEditMode' => true,
            ]);
        } elseif (in_array($propertyId, ['1606', '1707'], true) && $checkIn !== '' && $checkOut !== '' && $checkIn < $checkOut) {
            try {
                $quote = $this->quoteEngine->quote($propertyId, $checkIn, $checkOut);
                $quotePreviewHtml = $this->viewRenderer->renderPartial('reservations/_quote_preview.php', [
                    'isAvailable' => true,
                    'conflictReasons' => [],
                    'quote' => $quote,
                    'source' => $reservation->source,
                    'defaultPrice' => $quote->totalCop,
                    'isEditMode' => true,
                ]);
            } catch (Throwable) {
                $quote = null;
            }
        }

        $modalHtml = $this->viewRenderer->renderPartial('reservations/_edit_modal.php', [
            'reservation' => $reservation->toArray(),
            'registry' => $registry,
            'occupants' => $occupantsToRender,
            'carPlates' => array_key_exists('car_plates', $body)
                ? (string) $body['car_plates']
                : (string) ($registry['car_plates'] ?? ''),
            'carModel' => array_key_exists('car_model', $body)
                ? (string) $body['car_model']
                : (string) ($registry['car_model'] ?? ''),
            'condominiumClearance' => $clearance,
            'clearanceModifiedAfterSync' => $clearanceModifiedAfterSync,
            'syncCondominiumClearance' => $this->parseCheckbox($body['sync_condominium_clearance'] ?? null),
            'sendAccessDispatch' => $this->parseCheckbox($body['send_access_dispatch'] ?? null),
            'resetGuestRegistry' => $rosterExtraction['resetRequested'],
            'errorMessage' => $errorMessage,
            'propertyId' => $propertyId,
            'source' => $reservation->source,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'totalPrice' => array_key_exists('total_price', $body)
                ? (string) $body['total_price']
                : (string) $reservation->totalPrice,
            'maxGuests' => array_key_exists('max_guests', $body)
                ? (string) $body['max_guests']
                : (string) $reservation->maxGuests,
            'guestName' => array_key_exists('guest_name', $body)
                ? (string) $body['guest_name']
                : $reservation->guestName,
            'guestEmail' => array_key_exists('guest_email', $body)
                ? (string) $body['guest_email']
                : $reservation->guestEmail,
            'guestPhone' => array_key_exists('guest_phone', $body)
                ? (string) $body['guest_phone']
                : $reservation->guestPhone,
            'lang' => array_key_exists('lang', $body)
                ? (string) $body['lang']
                : $reservation->lang,
            'externalConfirmationCode' => array_key_exists('external_confirmation_code', $body)
                ? (string) $body['external_confirmation_code']
                : (string) ($reservation->externalConfirmationCode ?? ''),
            'channelBlockUid' => array_key_exists('channel_block_uid', $body)
                ? (string) $body['channel_block_uid']
                : (string) ($reservation->channelBlockUid ?? ''),
            'notes' => array_key_exists('notes', $body)
                ? (string) $body['notes']
                : (string) ($reservation->notes ?? ''),
            'registeredGuestCount' => $registeredGuestCount,
            'quotePreviewHtml' => $quotePreviewHtml,
            'csrfToken' => (string) ($session['csrf_token'] ?? ''),
        ]);

        return Response::html($modalHtml, 422);
    }

    private function parseCheckbox(mixed $val): bool
    {
        return $val !== null && in_array((string) $val, ['1', 'true', 'on'], true);
    }

    /**
     * @param array<string, mixed> $session
     * @return array{id: int|null, name: string, email: string, role: string}
     */
    private function buildCurrentUser(array $session): array
    {
        return [
            'id' => isset($session['admin_user_id']) ? (int) $session['admin_user_id'] : null,
            'name' => (string) ($session['admin_user_name'] ?? 'Admin'),
            'email' => (string) ($session['admin_user_email'] ?? ''),
            'role' => (string) ($session['admin_user_role'] ?? 'admin'),
        ];
    }

    private function buildFallbackLifecycleEngine(): ReservationLifecycleEngineInterface
    {
        $auditPort = new class($this->auditLogger) implements AuditPort {
            public function __construct(private readonly AuditLogger $logger) {}
            /**
             * @param array<string, mixed>|null $payloadBefore
             * @param array<string, mixed>|null $payloadAfter
             */
            public function record(string $action, string $entityType, string $entityId, ?array $payloadBefore = null, ?array $payloadAfter = null, ?ActorContext $actor = null): int {
                return $this->logger->record(
                    action: $action,
                    entityType: $entityId !== '' ? $entityType : 'reservation',
                    entityId: $entityId,
                    before: $payloadBefore,
                    after: $payloadAfter,
                    adminUserId: $actor?->adminUserId,
                    ipAddress: $actor !== null ? $actor->ipAddress : '',
                    userAgent: $actor?->userAgent
                );
            }
        };

        return ReservationLifecycleEngineFactory::create($this->pdo, [
            'repository' => $this->repository,
            'mpRefundClient' => $this->refundClient,
            'auditPort' => $auditPort,
            'bookingFulfillment' => $this->bookingFulfillment ?? BookingFulfillment::createDefault(emailSender: $this->emailSender),
            'cancellationRenderer' => $this->cancellationEmailRenderer,
            'emailSender' => $this->emailSender,
            'ledger' => $this->ledger,
            'quoteEngine' => $this->quoteEngine,
        ]);
    }
}
